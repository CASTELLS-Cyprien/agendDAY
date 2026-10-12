// ===== AGENDDAY — CALENDRIER =====
// Grille mensuelle accessible (motif ARIA « grid » avec focus itinérant),
// panneau du jour, prochains événements, vue liste, recherche, filtres,
// création / modification / duplication / suppression d'événements.
(function (A) {
    'use strict';

    var D = A.dates;
    var el = A.el;

    var MAX_CHIPS = 3;
    var UPCOMING_DAYS = 14;
    var UPCOMING_MAX = 8;
    var STORAGE_FILTERS = 'agendday.hiddenCategories';
    var STORAGE_VIEW = 'agendday.view';

    function storageGet(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }

    function storageSet(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* préférence non mémorisée */ }
    }

    function keyOf(occ) {
        return occ.id + '|' + occ.date;
    }

    function isTyping(target) {
        return target instanceof Element && target.closest('input, textarea, select, [contenteditable="true"]') !== null;
    }

    function timeToMinutes(hhmm) {
        var parts = String(hhmm).split(':').map(Number);
        return parts[0] * 60 + parts[1];
    }

    function minutesToTime(total) {
        return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
    }

    function nowTime() {
        var now = new Date();
        return minutesToTime(now.getHours() * 60 + now.getMinutes());
    }

    // ==================================================================
    // Application
    // ==================================================================

    function CalendarApp(config) {
        this.config = config;
        this.categoryKeys = Object.keys(config.categories);
        this.today = D.today();

        var params = new URLSearchParams(window.location.search);
        var dateParam = params.get('date');
        this.selected = D.isValid(dateParam) ? dateParam : this.today;
        this.month = D.startOfMonth(D.parse(this.selected));
        this.view = storageGet(STORAGE_VIEW) === 'list' ? 'list' : 'month';
        this.hidden = this.readHiddenCategories();

        this.occurrences = [];
        this.byDate = new Map();
        this.upcoming = [];
        this.cache = new Map();
        this.cells = new Map();
        this.loadSeq = 0;
        this.pendingLoad = Promise.resolve();

        this.dom = {};
        ['calendarTitle', 'todayBtn', 'prevBtn', 'nextBtn', 'monthPicker', 'monthGrid', 'monthView', 'listView',
            'newEventBtn', 'fabNewEvent', 'addOnDayBtn', 'dayEyebrow', 'dayTitle', 'dayEvents', 'dayEmpty',
            'upcomingList', 'upcomingEmpty', 'calError', 'searchInput', 'searchResults', 'searchStatus', 'searchList'
        ].forEach(function (id) {
            this.dom[id] = document.getElementById(id);
        }, this);

        this.form = new EventForm(this);
        this.details = new EventDetails(this);

        this.bind();
        this.setView(this.view, false);
        this.renderMonth();

        var eventParam = Number(params.get('event'));
        var self = this;
        this.loadUpcoming();
        if (eventParam > 0) {
            this.pendingLoad.then(function () { self.openEvent(eventParam, self.selected); });
        }
    }

    CalendarApp.prototype.readHiddenCategories = function () {
        var keys = this.categoryKeys;
        try {
            var saved = JSON.parse(storageGet(STORAGE_FILTERS) || '[]');
            return new Set(Array.isArray(saved) ? saved.filter(function (k) { return keys.indexOf(k) !== -1; }) : []);
        } catch (e) {
            return new Set();
        }
    };

    CalendarApp.prototype.isVisible = function (occ) {
        return !this.hidden.has(occ.category);
    };

    CalendarApp.prototype.categoryLabel = function (key) {
        return this.config.categories[key] || key;
    };

    // ------------------------------------------------------------------
    // Événements DOM
    // ------------------------------------------------------------------

    CalendarApp.prototype.bind = function () {
        var self = this;
        var dom = this.dom;

        dom.todayBtn.addEventListener('click', function () { self.selectDate(self.today); });
        dom.prevBtn.addEventListener('click', function () { self.shiftMonth(-1); });
        dom.nextBtn.addEventListener('click', function () { self.shiftMonth(1); });

        dom.monthPicker.addEventListener('change', function () {
            if (/^\d{4}-\d{2}$/.test(dom.monthPicker.value)) {
                self.selectDate(dom.monthPicker.value + '-01');
            }
        });

        document.querySelectorAll('.segmented__btn[data-view]').forEach(function (button) {
            button.addEventListener('click', function () { self.setView(button.dataset.view, true); });
        });

        document.querySelectorAll('.filter-chip[data-category]').forEach(function (chip) {
            chip.addEventListener('click', function () { self.toggleCategory(chip.dataset.category); });
        });
        this.syncFilterChips();

        [dom.newEventBtn, dom.fabNewEvent, dom.addOnDayBtn].forEach(function (button) {
            button.addEventListener('click', function () { self.form.openCreate({ date: self.selected }); });
        });

        dom.monthGrid.addEventListener('click', function (event) { self.onGridClick(event); });
        // Double-clic : création à la date choisie par le premier clic (si ce jour
        // appartenait à un autre mois, la grille a été reconstruite entre-temps).
        dom.monthGrid.addEventListener('dblclick', function (event) {
            if (event.target.closest('[role="gridcell"]') && !event.target.closest('.chip')) {
                self.form.openCreate({ date: self.selected });
            }
        });
        dom.monthGrid.addEventListener('keydown', function (event) { self.onGridKeydown(event); });

        // Ouverture des détails depuis toutes les listes (délégation)
        [dom.dayEvents, dom.upcomingList, dom.listView].forEach(function (container) {
            container.addEventListener('click', function (event) {
                var row = event.target.closest('[data-key]');
                if (row) {
                    var occ = self.findByKey(row.dataset.key);
                    if (occ) { self.details.open(occ); }
                }
            });
        });
        dom.listView.addEventListener('click', function (event) {
            if (event.target.closest('[data-action="create"]')) {
                self.form.openCreate({ date: self.selected });
            }
        });

        // Raccourcis : « n » nouvel événement, « / » recherche
        document.addEventListener('keydown', function (event) {
            if (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target) || document.querySelector('dialog[open]')) {
                return;
            }
            if (event.key === 'n') {
                event.preventDefault();
                self.form.openCreate({ date: self.selected });
            } else if (event.key === '/') {
                event.preventDefault();
                dom.searchInput.focus();
            }
        });

        this.search = new EventSearch(this);
    };

    CalendarApp.prototype.onGridClick = function (event) {
        var cell = event.target.closest('[role="gridcell"]');
        if (!cell) {
            return;
        }
        // Sur petit écran, les événements sont des pastilles trop petites pour être
        // visées : le toucher sélectionne le jour, le détail est dans le panneau.
        var compact = window.matchMedia('(max-width: 639px)').matches;
        var chip = compact ? null : event.target.closest('.chip[data-key]');
        var occ = chip ? this.findByKey(chip.dataset.key) : null;
        this.selectDate(cell.dataset.date);
        if (occ) {
            this.details.open(occ);
        }
    };

    CalendarApp.prototype.onGridKeydown = function (event) {
        var cell = event.target.closest('[role="gridcell"]');
        if (!cell) {
            return;
        }
        var current = D.parse(cell.dataset.date);
        var target;

        switch (event.key) {
            case 'ArrowLeft': target = D.addDays(current, -1); break;
            case 'ArrowRight': target = D.addDays(current, 1); break;
            case 'ArrowUp': target = D.addDays(current, -7); break;
            case 'ArrowDown': target = D.addDays(current, 7); break;
            case 'Home': target = D.startOfWeek(current); break;
            case 'End': target = D.addDays(D.startOfWeek(current), 6); break;
            case 'PageUp': target = D.addMonths(current, event.shiftKey ? -12 : -1); break;
            case 'PageDown': target = D.addMonths(current, event.shiftKey ? 12 : 1); break;
            case 'Enter':
            case ' ':
                event.preventDefault();
                this.selectDate(cell.dataset.date);
                this.focusDayPanel();
                return;
            default:
                return;
        }
        event.preventDefault();
        this.selectDate(D.ymd(target), { focus: true });
    };

    // ------------------------------------------------------------------
    // Navigation et sélection
    // ------------------------------------------------------------------

    CalendarApp.prototype.shiftMonth = function (delta) {
        this.selectDate(D.ymd(D.addMonths(D.parse(this.selected), delta)));
    };

    /**
     * Sélectionne un jour ; change de mois si nécessaire. Le focus n'est déplacé
     * que si options.focus (navigation clavier dans la grille).
     */
    CalendarApp.prototype.selectDate = function (ymd, options) {
        options = options || {};
        var date = D.parse(ymd);
        var monthChanged = date.getFullYear() !== this.month.getFullYear() || date.getMonth() !== this.month.getMonth();

        this.selected = ymd;
        if (monthChanged) {
            this.month = D.startOfMonth(date);
            this.renderMonth();
        } else {
            this.updateSelection();
            if (this.view === 'list') {
                this.renderList();
            }
        }
        if (options.focus) {
            var cell = this.cells.get(ymd);
            if (cell) { cell.focus(); }
        }
        this.renderDayPanel();
        this.syncUrl();
        return this.pendingLoad;
    };

    CalendarApp.prototype.syncUrl = function () {
        var url = new URL(window.location.href);
        url.searchParams.set('date', this.selected);
        url.searchParams.delete('event');
        window.history.replaceState(null, '', url.pathname + url.search);
    };

    CalendarApp.prototype.focusDayPanel = function () {
        var first = this.dom.dayEvents.querySelector('button');
        (first || this.dom.addOnDayBtn).focus();
    };

    CalendarApp.prototype.setView = function (view, persist) {
        this.view = view === 'list' ? 'list' : 'month';
        this.dom.monthView.hidden = this.view !== 'month';
        this.dom.listView.hidden = this.view !== 'list';
        document.querySelectorAll('.segmented__btn[data-view]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.view === this.view));
        }, this);
        if (persist) {
            storageSet(STORAGE_VIEW, this.view);
        }
        if (this.view === 'list') {
            this.renderList();
        }
    };

    CalendarApp.prototype.toggleCategory = function (category) {
        if (this.hidden.has(category)) {
            this.hidden.delete(category);
        } else {
            this.hidden.add(category);
        }
        storageSet(STORAGE_FILTERS, JSON.stringify(Array.from(this.hidden)));
        this.syncFilterChips();
        this.renderEvents();
        this.renderUpcoming();
        A.announce((this.hidden.has(category) ? 'Catégorie masquée : ' : 'Catégorie affichée : ') + this.categoryLabel(category));
    };

    CalendarApp.prototype.syncFilterChips = function () {
        document.querySelectorAll('.filter-chip[data-category]').forEach(function (chip) {
            chip.setAttribute('aria-pressed', String(!this.hidden.has(chip.dataset.category)));
        }, this);
    };

    // ------------------------------------------------------------------
    // Données
    // ------------------------------------------------------------------

    CalendarApp.prototype.gridRange = function () {
        var start = D.startOfWeek(this.month);
        return { from: D.ymd(start), to: D.ymd(D.addDays(start, 41)) };
    };

    CalendarApp.prototype.loadGrid = function () {
        var self = this;
        var range = this.gridRange();
        var cacheKey = range.from + '|' + range.to;
        var seq = ++this.loadSeq;

        if (this.cache.has(cacheKey)) {
            this.setOccurrences(this.cache.get(cacheKey));
            this.pendingLoad = Promise.resolve();
            return this.pendingLoad;
        }

        this.setOccurrences([]);
        this.dom.monthGrid.setAttribute('aria-busy', 'true');
        this.dom.monthGrid.classList.add('is-loading');

        this.pendingLoad = A.api('/api/events?from=' + range.from + '&to=' + range.to)
            .then(function (data) {
                self.cache.set(cacheKey, data.events);
                if (seq === self.loadSeq) {
                    self.setOccurrences(data.events);
                    self.hideError();
                }
            })
            .catch(function (error) {
                if (seq === self.loadSeq) {
                    self.showError('Impossible de charger les événements : ' + error.message);
                }
            })
            .finally(function () {
                if (seq === self.loadSeq) {
                    self.dom.monthGrid.removeAttribute('aria-busy');
                    self.dom.monthGrid.classList.remove('is-loading');
                }
            });
        return this.pendingLoad;
    };

    CalendarApp.prototype.loadUpcoming = function () {
        var self = this;
        var from = this.today;
        var to = D.ymd(D.addDays(D.parse(from), UPCOMING_DAYS - 1));
        return A.api('/api/events?from=' + from + '&to=' + to)
            .then(function (data) {
                self.upcoming = data.events;
                self.renderUpcoming();
            })
            .catch(function () {
                self.upcoming = [];
                self.renderUpcoming();
            });
    };

    /** Rechargement complet après une modification. */
    CalendarApp.prototype.reload = function () {
        this.cache.clear();
        return Promise.all([this.loadGrid(), this.loadUpcoming()]);
    };

    CalendarApp.prototype.setOccurrences = function (list) {
        this.occurrences = list;
        this.byDate = new Map();
        list.forEach(function (occ) {
            if (!this.byDate.has(occ.date)) {
                this.byDate.set(occ.date, []);
            }
            this.byDate.get(occ.date).push(occ);
        }, this);
        this.renderEvents();
    };

    CalendarApp.prototype.findByKey = function (key) {
        var all = this.occurrences.concat(this.upcoming, this.search ? this.search.results : []);
        for (var i = 0; i < all.length; i++) {
            if (keyOf(all[i]) === key) {
                return all[i];
            }
        }
        return null;
    };

    /** Ouvre les détails d'un événement (depuis une notification, un lien d'e-mail…). */
    CalendarApp.prototype.openEvent = function (id, date, fallback) {
        var self = this;
        return this.selectDate(date).then(function () {
            var found = (self.byDate.get(date) || []).find(function (occ) { return occ.id === id; }) || fallback;
            if (found) {
                self.details.open(found);
            } else {
                A.toast('Cet événement est introuvable : il a peut-être été supprimé ou déplacé.', 'info');
            }
        });
    };

    CalendarApp.prototype.showError = function (message) {
        var self = this;
        this.dom.calError.replaceChildren(
            A.icon('circle-exclamation'),
            el('span', { text: message }),
            el('button', { type: 'button', class: 'btn btn-secondary btn-sm', text: 'Réessayer', on: { click: function () { self.reload(); } } })
        );
        this.dom.calError.hidden = false;
    };

    CalendarApp.prototype.hideError = function () {
        this.dom.calError.hidden = true;
    };

    // ------------------------------------------------------------------
    // Rendu de la grille
    // ------------------------------------------------------------------

    CalendarApp.prototype.renderMonth = function () {
        this.dom.calendarTitle.textContent = D.monthYear(this.month);
        this.dom.monthPicker.value = D.ymd(this.month).slice(0, 7);
        this.buildGrid();
        this.loadGrid();
        if (this.view === 'list') {
            this.renderList();
        }
    };

    CalendarApp.prototype.buildGrid = function () {
        var grid = this.dom.monthGrid;
        var start = D.startOfWeek(this.month);
        var month = this.month.getMonth();
        grid.replaceChildren();
        this.cells.clear();

        var head = el('div', { role: 'row', class: 'month__weekdays' });
        for (var i = 0; i < 7; i++) {
            var weekday = D.addDays(start, i);
            head.append(el('div', { role: 'columnheader', class: 'month__weekday' }, [
                el('abbr', { title: D.weekdayLong(weekday), text: D.weekdayShort(weekday) })
            ]));
        }
        grid.append(head);

        for (var week = 0; week < 6; week++) {
            var row = el('div', { role: 'row', class: 'month__week' });
            for (var day = 0; day < 7; day++) {
                var date = D.addDays(start, week * 7 + day);
                var ymd = D.ymd(date);
                var dayNumber = date.getDate() === 1 ? '1 ' + D.monthShort(date) : String(date.getDate());
                var classes = ['day'];
                if (date.getMonth() !== month) { classes.push('day--outside'); }
                if (day >= 5) { classes.push('day--weekend'); }
                if (ymd === this.today) { classes.push('day--today'); }

                var cell = el('div', {
                    role: 'gridcell',
                    class: classes.join(' '),
                    tabindex: '-1',
                    'aria-selected': 'false',
                    'aria-current': ymd === this.today ? 'date' : null,
                    dataset: { date: ymd }
                }, [
                    el('span', { class: 'day__num', 'aria-hidden': 'true', text: dayNumber }),
                    el('div', { class: 'day__events', 'aria-hidden': 'true' })
                ]);
                row.append(cell);
                this.cells.set(ymd, cell);
            }
            grid.append(row);
        }
        this.updateSelection();
        this.renderEvents();
    };

    CalendarApp.prototype.updateSelection = function () {
        var previous = this.dom.monthGrid.querySelector('.day--selected');
        if (previous) {
            previous.classList.remove('day--selected');
            previous.setAttribute('aria-selected', 'false');
            previous.setAttribute('tabindex', '-1');
        }
        var cell = this.cells.get(this.selected);
        if (cell) {
            cell.classList.add('day--selected');
            cell.setAttribute('aria-selected', 'true');
            cell.setAttribute('tabindex', '0');
        }
    };

    CalendarApp.prototype.renderEvents = function () {
        var self = this;
        this.cells.forEach(function (cell, ymd) {
            var all = self.byDate.get(ymd) || [];
            var visible = all.filter(self.isVisible, self);
            var container = cell.querySelector('.day__events');
            var shown = visible.length > MAX_CHIPS ? visible.slice(0, MAX_CHIPS - 1) : visible;

            container.replaceChildren();
            shown.forEach(function (occ) {
                var time = occ.allDay ? '' : occ.start;
                container.append(el('span', {
                    class: 'chip cat--' + occ.category + (occ.allDay ? ' chip--allday' : ''),
                    title: (time ? time + ' · ' : '') + occ.title,
                    dataset: { key: keyOf(occ) }
                }, [
                    time ? el('span', { class: 'chip__time', text: time }) : null,
                    el('span', { class: 'chip__title', text: occ.title })
                ]));
            });
            if (visible.length > shown.length) {
                container.append(el('span', { class: 'day__more', text: '+' + (visible.length - shown.length) }));
            }

            cell.classList.toggle('day--has-events', visible.length > 0);
            var label = D.capitalize(D.long(ymd));
            if (ymd === self.today) { label += ", aujourd'hui"; }
            if (visible.length > 0) {
                label += ', ' + visible.length + ' événement' + (visible.length > 1 ? 's' : '');
            }
            cell.setAttribute('aria-label', label);
        });

        this.renderDayPanel();
        if (this.view === 'list') {
            this.renderList();
        }
    };

    // ------------------------------------------------------------------
    // Listes d'événements (panneau du jour, à venir, vue liste)
    // ------------------------------------------------------------------

    CalendarApp.prototype.eventRow = function (occ, options) {
        options = options || {};
        var timeVisual;
        var timeSpoken;
        if (occ.allDay) {
            timeVisual = [el('span', { text: 'Journée' })];
            timeSpoken = 'Journée entière';
        } else {
            timeVisual = [el('span', { text: occ.start }), occ.end ? el('span', { class: 'event-row__end', text: occ.end }) : null];
            timeSpoken = occ.end ? 'De ' + occ.start + ' à ' + occ.end : 'À ' + occ.start;
        }

        var meta = [el('span', { class: 'event-row__category', text: this.categoryLabel(occ.category) })];
        if (occ.location) {
            meta.push(el('span', { class: 'event-row__meta-item' }, [A.icon('location-dot'), ' ', occ.location]));
        }
        if (occ.recurrence !== 'none') {
            meta.push(el('span', { class: 'event-row__meta-item', title: 'Événement récurrent' }, [A.icon('rotate'), A.srOnly('Récurrent')]));
        }
        if (occ.reminders.length > 0) {
            var reminderText = occ.reminders.length + ' rappel' + (occ.reminders.length > 1 ? 's' : '');
            meta.push(el('span', { class: 'event-row__meta-item', title: reminderText }, [A.icon('bell'), A.srOnly(reminderText)]));
        }

        return el('button', {
            type: 'button',
            class: 'event-row cat--' + occ.category,
            dataset: { key: keyOf(occ), eventId: occ.id }
        }, [
            el('span', { class: 'event-row__time', 'aria-hidden': 'true' }, timeVisual),
            el('span', { class: 'event-row__body' }, [
                options.showDate ? el('span', { class: 'event-row__date', text: options.dateLabel ? options.dateLabel(occ) : D.relativeDay(occ.date) }) : null,
                el('span', { class: 'sr-only', text: timeSpoken + '. ' }),
                el('span', { class: 'event-row__title', text: occ.title }),
                el('span', { class: 'event-row__meta' }, meta)
            ])
        ]);
    };

    CalendarApp.prototype.renderDayPanel = function () {
        var ymd = this.selected;
        var diff = D.diffDays(this.today, ymd);
        this.dom.dayEyebrow.textContent = diff === 0 ? "Aujourd'hui" : diff === 1 ? 'Demain' : diff === -1 ? 'Hier' : String(D.parse(ymd).getFullYear());
        this.dom.dayTitle.textContent = D.capitalize(D.dayMonth(ymd));
        this.dom.addOnDayBtn.setAttribute('aria-label', 'Ajouter un événement le ' + D.long(ymd));

        var all = this.byDate.get(ymd) || [];
        var visible = all.filter(this.isVisible, this);
        this.dom.dayEvents.replaceChildren.apply(this.dom.dayEvents, visible.map(function (occ) {
            return el('li', {}, [this.eventRow(occ)]);
        }, this));

        var hiddenCount = all.length - visible.length;
        this.dom.dayEmpty.hidden = visible.length > 0;
        this.dom.dayEmpty.querySelector('p').textContent = hiddenCount > 0
            ? hiddenCount + ' événement' + (hiddenCount > 1 ? 's masqués' : ' masqué') + ' par les filtres de catégorie.'
            : 'Aucun événement ce jour-là.';
    };

    CalendarApp.prototype.renderUpcoming = function () {
        var now = nowTime();
        var today = this.today;
        var items = this.upcoming.filter(function (occ) {
            if (!this.isVisible(occ)) { return false; }
            if (occ.date === today && !occ.allDay) {
                return (occ.end || occ.start) >= now;
            }
            return true;
        }, this).slice(0, UPCOMING_MAX);

        this.dom.upcomingList.replaceChildren.apply(this.dom.upcomingList, items.map(function (occ) {
            return el('li', {}, [this.eventRow(occ, { showDate: true })]);
        }, this));
        this.dom.upcomingEmpty.hidden = items.length > 0;
    };

    CalendarApp.prototype.renderList = function () {
        var container = this.dom.listView;
        var month = this.month.getMonth();
        var days = Array.from(this.byDate.keys()).sort().filter(function (ymd) {
            return D.parse(ymd).getMonth() === month;
        });

        container.replaceChildren();
        var count = 0;
        days.forEach(function (ymd) {
            var visible = this.byDate.get(ymd).filter(this.isVisible, this);
            if (visible.length === 0) { return; }
            count += visible.length;
            var diff = D.diffDays(this.today, ymd);
            container.append(el('section', { class: 'agenda__day' + (ymd === this.selected ? ' agenda__day--selected' : '') }, [
                el('h2', { class: 'agenda__date' }, [
                    D.capitalize(D.dayMonth(ymd)),
                    diff === 0 ? el('span', { class: 'badge agenda__today', text: "Aujourd'hui" }) : null
                ]),
                el('ul', { class: 'event-list' }, visible.map(function (occ) {
                    return el('li', {}, [this.eventRow(occ)]);
                }, this))
            ]));
        }, this);

        if (count === 0) {
            container.append(el('div', { class: 'empty-state empty-state--large' }, [
                A.icon('calendar-plus'),
                el('p', { text: 'Aucun événement en ' + D.monthYear(this.month).toLowerCase() + '.' }),
                el('button', { type: 'button', class: 'btn btn-primary', 'data-action': 'create' }, [A.icon('plus'), ' Créer un événement'])
            ]));
        }
    };

    /** Après création / modification / suppression : données rechargées, focus replacé. */
    CalendarApp.prototype.afterMutation = function (message, targetDate, eventId) {
        var self = this;
        var monthChanged = false;
        this.cache.clear();

        if (targetDate && targetDate !== this.selected) {
            var target = D.parse(targetDate);
            monthChanged = target.getFullYear() !== this.month.getFullYear() || target.getMonth() !== this.month.getMonth();
            this.selectDate(targetDate);
        }

        Promise.all([monthChanged ? this.pendingLoad : this.loadGrid(), this.loadUpcoming()]).then(function () {
            var row = eventId ? self.dom.dayEvents.querySelector('[data-event-id="' + eventId + '"]') : null;
            (row || self.dom.dayTitle).focus();
        });
        A.toast(message, 'success');
    };

    // ==================================================================
    // Détails d'un événement
    // ==================================================================

    function EventDetails(app) {
        this.app = app;
        this.dialog = document.getElementById('detailsDialog');
        this.current = null;
        var self = this;

        this.dialog.querySelectorAll('[data-close]').forEach(function (button) {
            button.addEventListener('click', function () { self.dialog.close(); });
        });
        this.dialog.addEventListener('click', function (event) {
            if (event.target === self.dialog) { self.dialog.close(); }
        });
        // Le formulaire remplace les détails : à sa fermeture, le focus revient à
        // l'élément qui avait ouvert les détails (et non au bouton d'un dialogue fermé).
        document.getElementById('detailsEdit').addEventListener('click', function () {
            var occ = self.current;
            self.dialog.close();
            app.form.returnFocus = self.opener;
            app.form.openEdit(occ);
        });
        document.getElementById('detailsDuplicate').addEventListener('click', function () {
            var occ = self.current;
            self.dialog.close();
            app.form.returnFocus = self.opener;
            app.form.openDuplicate(occ);
        });
        document.getElementById('detailsDelete').addEventListener('click', function () { self.remove(); });
    }

    EventDetails.prototype.open = function (occ) {
        var app = this.app;
        var config = app.config;
        this.current = occ;
        if (!this.dialog.contains(document.activeElement)) {
            this.opener = document.activeElement;
        }

        var header = document.getElementById('detailsHeader');
        header.className = 'dialog__header details-header cat--' + occ.category;
        document.getElementById('detailsCategory').textContent = app.categoryLabel(occ.category);
        document.getElementById('detailsTitle').textContent = occ.title;

        var when = D.capitalize(D.long(occ.date)) + ' · ';
        when += occ.allDay ? 'Journée entière' : (occ.end ? occ.start + ' – ' + occ.end : occ.start);
        document.getElementById('detailsWhen').textContent = when;

        document.getElementById('detailsLocationRow').hidden = !occ.location;
        document.getElementById('detailsLocation').textContent = occ.location;

        document.getElementById('detailsRecurrenceRow').hidden = occ.recurrence === 'none';
        document.getElementById('detailsRecurrence').textContent = occ.recurrence === 'none' ? ''
            : recurrenceLabel(occ.recurrence, occ.seriesStart)
                + (occ.recurrenceUntil ? ", jusqu'au " + D.long(occ.recurrenceUntil) : '');

        var reminders = document.getElementById('detailsReminders');
        if (occ.reminders.length === 0) {
            reminders.textContent = 'Aucun rappel';
        } else {
            var labels = occ.reminders.map(function (minutes) {
                var option = config.reminderOffsets.find(function (o) { return o.value === minutes; });
                return option ? option.label : minutes + ' min avant';
            });
            reminders.textContent = labels.join(', ') + (config.emailReminders
                ? ' — par e-mail et dans l’application'
                : ' — dans l’application (e-mails désactivés dans vos préférences)');
        }

        document.getElementById('detailsDescriptionRow').hidden = !occ.description;
        document.getElementById('detailsDescription').textContent = occ.description;

        if (!this.dialog.open) {
            this.dialog.showModal();
        }
        document.getElementById('detailsEdit').focus();
    };

    EventDetails.prototype.remove = async function () {
        var occ = this.current;
        var app = this.app;
        var button = document.getElementById('detailsDelete');
        var confirmed = await A.confirm({
            title: 'Supprimer « ' + occ.title + ' » ?',
            message: occ.recurrence !== 'none'
                ? 'Toutes les occurrences de cet événement récurrent et leurs rappels seront supprimés. Cette action est définitive.'
                : "L'événement et ses rappels seront supprimés. Cette action est définitive.",
            confirmLabel: 'Supprimer',
            danger: true
        });
        if (!confirmed) {
            button.focus();
            return;
        }

        A.setBusy(button, true, 'Suppression…');
        try {
            await A.api('/api/events/delete', { body: { eventId: occ.id } });
            this.dialog.close();
            app.afterMutation('« ' + occ.title + ' » a été supprimé.', null, null);
        } catch (error) {
            A.toast(error.message, 'error');
            if (error.status === 404) {
                this.dialog.close();
                app.reload();
            }
        } finally {
            A.setBusy(button, false);
        }
    };

    function recurrenceLabel(type, startYmd) {
        var date = D.parse(startYmd);
        switch (type) {
            case 'daily': return 'Tous les jours';
            case 'weekly': return 'Toutes les semaines, le ' + D.weekdayLong(date);
            case 'monthly': return 'Tous les mois, le ' + date.getDate();
            case 'yearly': return 'Tous les ans, le ' + date.getDate() + ' ' + D.monthName(date);
            default: return 'Ne se répète pas';
        }
    }

    // ==================================================================
    // Formulaire de création / modification
    // ==================================================================

    var FIELD_INPUTS = {
        title: 'eventTitle',
        date: 'eventDate',
        time: 'eventStart',
        endTime: 'eventEnd',
        location: 'eventLocation',
        description: 'eventDescription',
        recurrence: 'eventRecurrence',
        recurrenceUntil: 'eventUntil'
    };

    function EventForm(app) {
        this.app = app;
        this.config = app.config;
        this.dialog = document.getElementById('eventDialog');
        this.form = document.getElementById('eventForm');
        this.submitBtn = document.getElementById('eventSubmit');
        this.errorBox = document.getElementById('eventFormError');
        this.moreOptions = document.getElementById('moreOptions');
        this.mode = 'create';
        this.saving = false;
        this.snapshot = '';
        this.endTouched = false;

        this.inputs = {};
        Object.keys(FIELD_INPUTS).forEach(function (key) {
            this.inputs[key] = document.getElementById(FIELD_INPUTS[key]);
        }, this);
        this.allDay = document.getElementById('eventAllDay');
        this.eventId = document.getElementById('eventId');

        this.buildRecurrenceOptions();
        this.bind();
    }

    EventForm.prototype.buildRecurrenceOptions = function () {
        var select = this.inputs.recurrence;
        Object.entries(this.config.recurrences).forEach(function (entry) {
            select.append(el('option', { value: entry[0], text: entry[1] }));
        });
    };

    EventForm.prototype.bind = function () {
        var self = this;

        this.form.addEventListener('submit', function (event) {
            event.preventDefault();
            self.submit();
        });
        this.dialog.querySelectorAll('[data-close]').forEach(function (button) {
            button.addEventListener('click', function () { self.requestClose(); });
        });
        // Échap : demande de fermeture (avec confirmation si des données ont été saisies)
        this.dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            self.requestClose();
        });
        this.dialog.addEventListener('click', function (event) {
            if (event.target === self.dialog) { self.requestClose(); }
        });
        this.dialog.addEventListener('close', function () {
            if (self.returnFocus && self.returnFocus.isConnected) {
                self.returnFocus.focus();
            }
            self.returnFocus = null;
        });

        this.allDay.addEventListener('change', function () { self.syncAllDay(); });
        this.inputs.recurrence.addEventListener('change', function () { self.syncRecurrence(); });
        this.inputs.date.addEventListener('change', function () { self.updateRecurrenceLabels(); });
        this.inputs.description.addEventListener('input', function () { self.updateCounter(); });
        this.inputs.endTime.addEventListener('input', function () { self.endTouched = true; });
        this.inputs.time.addEventListener('change', function () { self.syncEndTime(); });

        // Effacement de l'erreur d'un champ dès qu'il est corrigé
        this.form.addEventListener('input', function (event) {
            var field = event.target;
            if (field.getAttribute('aria-invalid') === 'true') {
                field.removeAttribute('aria-invalid');
                var key = Object.keys(FIELD_INPUTS).find(function (k) { return FIELD_INPUTS[k] === field.id; });
                if (key) { self.setFieldError(key, ''); }
            }
        });

        this.form.querySelectorAll('input[name="reminders[]"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () { self.enforceReminderLimit(); });
        });
    };

    EventForm.prototype.defaults = function (date) {
        var start = '09:00';
        if (date === D.today()) {
            var now = new Date();
            var next = Math.ceil((now.getHours() * 60 + now.getMinutes() + 1) / 30) * 30;
            start = minutesToTime(Math.min(next, 23 * 60 + 30));
        }
        var endMinutes = timeToMinutes(start) + 60;
        return {
            title: '',
            date: date,
            allDay: false,
            start: start,
            end: endMinutes < 24 * 60 ? minutesToTime(endMinutes) : '',
            category: this.config.defaultCategory,
            reminders: this.config.defaultReminder === null ? [] : [this.config.defaultReminder],
            location: '',
            description: '',
            recurrence: 'none',
            recurrenceUntil: null
        };
    };

    EventForm.prototype.openCreate = function (prefill) {
        var values = Object.assign(this.defaults(prefill.date || this.app.selected), prefill);
        this.open('create', values, 'Nouvel événement', 'Ajouter');
    };

    EventForm.prototype.openDuplicate = function (occ) {
        var values = Object.assign({}, occ, { id: null });
        this.open('create', values, "Dupliquer l'événement", 'Ajouter');
    };

    EventForm.prototype.openEdit = function (occ) {
        // Pour une série, on édite la série entière : la date affichée est sa date de début
        var values = Object.assign({}, occ, { date: occ.recurrence !== 'none' ? occ.seriesStart : occ.date });
        this.open('edit', values, "Modifier l'événement", 'Enregistrer');
    };

    EventForm.prototype.open = function (mode, values, title, submitLabel) {
        this.mode = mode;
        this.form.reset();
        this.clearErrors();
        document.getElementById('eventDialogTitle').textContent = title;
        this.submitBtn.textContent = submitLabel;

        var isSeries = mode === 'edit' && values.recurrence !== 'none';
        document.getElementById('seriesNote').hidden = !isSeries;
        document.getElementById('eventDateLabel').textContent = isSeries ? 'Date de début de la série' : 'Date';

        this.eventId.value = mode === 'edit' ? values.id : '';
        this.inputs.title.value = values.title || '';
        this.inputs.date.value = values.date;
        this.allDay.checked = !!values.allDay;
        this.inputs.time.value = values.start || '09:00';
        this.inputs.endTime.value = values.end || '';
        this.endTouched = mode === 'edit';
        this.inputs.location.value = values.location || '';
        this.inputs.description.value = values.description || '';
        this.inputs.recurrence.value = values.recurrence || 'none';
        this.inputs.recurrenceUntil.value = values.recurrenceUntil || '';

        var category = this.form.querySelector('input[name="category"][value="' + (values.category || this.config.defaultCategory) + '"]')
            || this.form.querySelector('input[name="category"]');
        category.checked = true;

        var reminders = (values.reminders || []).map(String);
        this.form.querySelectorAll('input[name="reminders[]"]').forEach(function (checkbox) {
            checkbox.checked = reminders.indexOf(checkbox.value) !== -1;
        });

        this.moreOptions.open = !!(values.location || values.description || (values.recurrence && values.recurrence !== 'none'));
        this.syncAllDay();
        this.syncRecurrence();
        this.updateRecurrenceLabels();
        this.updateCounter();
        this.enforceReminderLimit();

        this.dialog.showModal();
        this.snapshot = this.serialize();
        this.inputs.title.focus();
        if (mode === 'edit') {
            this.inputs.title.select();
        }
    };

    EventForm.prototype.serialize = function () {
        return JSON.stringify(Array.from(new FormData(this.form).entries()));
    };

    EventForm.prototype.requestClose = async function () {
        if (this.saving) {
            return;
        }
        if (this.serialize() !== this.snapshot) {
            var discard = await A.confirm({
                title: 'Abandonner la saisie ?',
                message: 'Les informations saisies dans ce formulaire seront perdues.',
                confirmLabel: 'Abandonner',
                cancelLabel: 'Continuer la saisie',
                danger: true
            });
            if (!discard) {
                return;
            }
        }
        this.dialog.close();
    };

    EventForm.prototype.syncAllDay = function () {
        var allDay = this.allDay.checked;
        document.getElementById('timeFields').hidden = allDay;
        this.inputs.time.disabled = allDay;
        this.inputs.endTime.disabled = allDay;

        var hint = allDay
            ? 'Journée entière : les rappels sont calculés à partir de ' + this.config.allDayReminderTime.replace(':', ' h ') + '. '
            : '';
        hint += this.config.emailReminders
            ? 'Rappels envoyés par e-mail et affichés dans l’application.'
            : 'E-mails désactivés dans vos préférences : rappels dans l’application uniquement.';
        document.getElementById('reminderHint').textContent = hint;
    };

    EventForm.prototype.syncRecurrence = function () {
        var repeating = this.inputs.recurrence.value !== 'none';
        document.getElementById('untilField').hidden = !repeating;
        this.inputs.recurrenceUntil.disabled = !repeating;
    };

    EventForm.prototype.updateRecurrenceLabels = function () {
        var value = this.inputs.date.value;
        if (!D.isValid(value)) {
            return;
        }
        Array.from(this.inputs.recurrence.options).forEach(function (option) {
            option.textContent = option.value === 'none' ? this.config.recurrences.none : recurrenceLabel(option.value, value);
        }, this);
    };

    EventForm.prototype.syncEndTime = function () {
        var start = this.inputs.time.value;
        if (this.endTouched || !/^\d{2}:\d{2}$/.test(start)) {
            return;
        }
        var end = timeToMinutes(start) + 60;
        this.inputs.endTime.value = end < 24 * 60 ? minutesToTime(end) : '';
    };

    EventForm.prototype.updateCounter = function () {
        var length = this.inputs.description.value.length;
        var max = Number(this.inputs.description.getAttribute('maxlength'));
        document.getElementById('descriptionCounter').textContent = length > 0 ? length + ' / ' + max + ' caractères' : '';
    };

    EventForm.prototype.enforceReminderLimit = function () {
        var boxes = Array.from(this.form.querySelectorAll('input[name="reminders[]"]'));
        var checked = boxes.filter(function (box) { return box.checked; }).length;
        var max = this.config.maxReminders;
        boxes.forEach(function (box) {
            box.disabled = !box.checked && checked >= max;
        });
    };

    EventForm.prototype.validate = function () {
        var errors = {};
        var title = this.inputs.title.value.trim();
        var date = this.inputs.date.value;
        var allDay = this.allDay.checked;
        var start = this.inputs.time.value;
        var end = this.inputs.endTime.value;

        if (title === '') {
            errors.title = 'Indiquez un titre.';
        }
        if (!D.isValid(date)) {
            errors.date = 'Indiquez une date valide.';
        }
        if (!allDay) {
            if (!/^\d{2}:\d{2}/.test(start)) {
                errors.time = 'Indiquez une heure de début.';
            } else if (end && end <= start) {
                errors.endTime = "L'heure de fin doit être postérieure à l'heure de début.";
            }
        }
        var until = this.inputs.recurrenceUntil.value;
        if (this.inputs.recurrence.value !== 'none' && until) {
            if (!D.isValid(until)) {
                errors.recurrenceUntil = 'Date de fin de répétition invalide.';
            } else if (D.isValid(date) && until <= date) {
                errors.recurrenceUntil = "La fin de la répétition doit être postérieure à la date de l'événement.";
            }
        }
        return errors;
    };

    EventForm.prototype.setFieldError = function (key, message) {
        var target = this.form.querySelector('[data-error-for="' + key + '"]');
        if (target) {
            target.replaceChildren();
            if (message) {
                target.append(A.icon('circle-exclamation'), ' ' + message);
            }
        }
        var input = this.inputs[key];
        if (input) {
            if (message) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        }
    };

    EventForm.prototype.clearErrors = function () {
        this.errorBox.hidden = true;
        this.errorBox.replaceChildren();
        this.form.querySelectorAll('[data-error-for]').forEach(function (node) { node.replaceChildren(); });
        this.form.querySelectorAll('[aria-invalid]').forEach(function (node) { node.removeAttribute('aria-invalid'); });
    };

    EventForm.prototype.showErrors = function (errors, summary) {
        var keys = Object.keys(errors);
        keys.forEach(function (key) { this.setFieldError(key, errors[key]); }, this);

        this.errorBox.replaceChildren(A.icon('circle-exclamation'), el('p', {
            text: summary || (keys.length > 1 ? 'Veuillez corriger les ' + keys.length + ' champs signalés.' : 'Veuillez corriger le champ signalé.')
        }));
        this.errorBox.hidden = false;

        // Focus sur le premier champ en erreur (ouvre « plus d'options » si besoin)
        var firstError = this.form.querySelector('[data-error-for]:not(:empty)');
        if (firstError) {
            if (this.moreOptions.contains(firstError)) {
                this.moreOptions.open = true;
            }
            var field = firstError.closest('.field');
            var focusable = field && field.querySelector('input:not([type="hidden"]):not(:disabled), select, textarea');
            if (focusable) {
                focusable.focus();
            }
        }
    };

    EventForm.prototype.submit = async function () {
        if (this.saving) {
            return;
        }
        this.clearErrors();
        var errors = this.validate();
        if (Object.keys(errors).length > 0) {
            this.showErrors(errors);
            return;
        }

        var app = this.app;
        var isEdit = this.mode === 'edit';
        this.saving = true;
        A.setBusy(this.submitBtn, true, 'Enregistrement…');

        try {
            var data = await A.api(isEdit ? '/api/events/update' : '/api/events', { body: new FormData(this.form) });
            this.dialog.close();

            var event = data.event;
            var target = event.recurrence === 'none' ? event.date : app.selected;
            var when = event.allDay ? ' (journée entière)' : ' à ' + event.start;
            app.afterMutation(
                (isEdit ? 'Événement modifié' : 'Événement ajouté') + ' : « ' + event.title + ' », '
                    + D.long(event.recurrence === 'none' ? event.date : event.seriesStart) + when + '.',
                target,
                event.id
            );
        } catch (error) {
            if (error.status === 422 && error.data && error.data.fields) {
                this.showErrors(error.data.fields, error.message);
            } else {
                this.errorBox.replaceChildren(A.icon('circle-exclamation'), el('p', { text: error.message }));
                this.errorBox.hidden = false;
                this.errorBox.scrollIntoView({ block: 'nearest' });
            }
        } finally {
            this.saving = false;
            A.setBusy(this.submitBtn, false);
        }
    };

    // ==================================================================
    // Recherche
    // ==================================================================

    function EventSearch(app) {
        this.app = app;
        this.input = app.dom.searchInput;
        this.panel = app.dom.searchResults;
        this.status = app.dom.searchStatus;
        this.list = app.dom.searchList;
        this.results = [];
        this.controller = null;

        var self = this;
        var run = A.debounce(function () { self.run(); }, 250);

        this.input.addEventListener('input', run);
        this.input.addEventListener('focus', function () {
            if (self.input.value.trim().length >= 2) { self.show(); }
        });
        this.input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                var first = self.list.querySelector('button');
                if (first) { event.preventDefault(); self.show(); first.focus(); }
            } else if (event.key === 'Escape') {
                if (!self.panel.hidden) { self.hide(); } else { self.input.value = ''; }
            }
        });
        this.list.addEventListener('keydown', function (event) {
            var buttons = Array.from(self.list.querySelectorAll('button'));
            var index = buttons.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' && index < buttons.length - 1) {
                event.preventDefault();
                buttons[index + 1].focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                (index > 0 ? buttons[index - 1] : self.input).focus();
            } else if (event.key === 'Escape') {
                self.hide();
                self.input.focus();
            }
        });
        this.list.addEventListener('click', function (event) {
            var row = event.target.closest('[data-key]');
            if (!row) { return; }
            var occ = self.results.find(function (item) { return keyOf(item) === row.dataset.key; });
            self.hide();
            if (occ) { app.openEvent(occ.id, occ.date, occ); }
        });
        document.addEventListener('click', function (event) {
            if (!self.panel.hidden && !self.panel.contains(event.target) && event.target !== self.input) {
                self.hide();
            }
        });
    }

    EventSearch.prototype.show = function () {
        this.panel.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    };

    EventSearch.prototype.hide = function () {
        this.panel.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
    };

    EventSearch.prototype.run = async function () {
        var query = this.input.value.trim();
        if (this.controller) {
            this.controller.abort();
        }
        if (query.length < 2) {
            this.results = [];
            this.list.replaceChildren();
            this.hide();
            return;
        }

        this.controller = new AbortController();
        this.status.textContent = 'Recherche…';
        this.show();

        try {
            var data = await A.api('/api/events/search?q=' + encodeURIComponent(query), { signal: this.controller.signal });
            this.results = data.results;
            var app = this.app;
            this.list.replaceChildren.apply(this.list, this.results.map(function (occ) {
                return el('li', {}, [app.eventRow(occ, {
                    showDate: true,
                    dateLabel: function (item) {
                        var label = D.capitalize(D.short(item.date)) + ' ' + D.parse(item.date).getFullYear();
                        return item.recurrence !== 'none' ? 'Prochaine : ' + label : label;
                    }
                })]);
            }));
            this.status.textContent = this.results.length === 0
                ? 'Aucun événement ne correspond à « ' + query + ' ».'
                : this.results.length + ' résultat' + (this.results.length > 1 ? 's' : '')
                    + (this.results.length >= 30 ? ' (30 premiers affichés)' : '');
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            this.status.textContent = error.message;
        }
    };

    // ==================================================================
    // Démarrage
    // ==================================================================

    document.addEventListener('DOMContentLoaded', function () {
        var configNode = document.getElementById('calendarConfig');
        if (!configNode) {
            return;
        }
        A.calendar = new CalendarApp(JSON.parse(configNode.textContent));
    });
})(window.AgendDAY);
