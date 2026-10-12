// ===== AGENDDAY — BRIQUES COMMUNES DE L'APPLICATION =====
// Construction DOM sûre, appels API, dates, annonces accessibles, toasts,
// confirmation, menus déroulants. Exposé sous window.AgendDAY.
window.AgendDAY = window.AgendDAY || {};

(function (A) {
    'use strict';

    // ------------------------------------------------------------------
    // DOM : création d'éléments sans innerHTML (aucun risque d'injection)
    // ------------------------------------------------------------------

    /**
     * el('button', { class: 'btn', type: 'button', text: 'OK', dataset: {id: 3}, on: {click: fn} }, [enfants])
     */
    A.el = function (tag, props, children) {
        var node = document.createElement(tag);
        Object.entries(props || {}).forEach(function (entry) {
            var key = entry[0];
            var value = entry[1];
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (key === 'class') {
                node.className = value;
            } else if (key === 'text') {
                node.textContent = value;
            } else if (key === 'dataset') {
                Object.assign(node.dataset, value);
            } else if (key === 'on') {
                Object.entries(value).forEach(function (handler) {
                    node.addEventListener(handler[0], handler[1]);
                });
            } else if (value === true) {
                node.setAttribute(key, '');
            } else {
                node.setAttribute(key, String(value));
            }
        });
        (children || []).forEach(function (child) {
            if (child === null || child === undefined || child === false) {
                return;
            }
            node.append(child instanceof Node ? child : document.createTextNode(String(child)));
        });
        return node;
    };

    A.icon = function (name, extraClass) {
        return A.el('i', { class: 'fas fa-' + name + (extraClass ? ' ' + extraClass : ''), 'aria-hidden': 'true' });
    };

    A.srOnly = function (text) {
        return A.el('span', { class: 'sr-only', text: text });
    };

    A.debounce = function (fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var self = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(self, args); }, delay);
        };
    };

    // ------------------------------------------------------------------
    // API JSON
    // ------------------------------------------------------------------

    A.csrfToken = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    function ApiError(message, status, data) {
        this.name = 'ApiError';
        this.message = message;
        this.status = status;
        this.data = data;
    }
    ApiError.prototype = Object.create(Error.prototype);
    A.ApiError = ApiError;

    /**
     * Requête vers l'API. Avec options.body (objet ou FormData), la requête est
     * un POST portant le jeton CSRF. Lève une ApiError au message compréhensible.
     */
    A.api = async function (url, options) {
        options = options || {};
        var init = {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: options.signal
        };

        if (options.body) {
            var body = options.body instanceof FormData ? options.body : toFormData(options.body);
            init.method = 'POST';
            init.headers['X-CSRF-Token'] = A.csrfToken();
            init.body = body;
        }

        var response;
        try {
            response = await fetch(url, init);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw error;
            }
            throw new ApiError('Connexion au serveur impossible. Vérifiez votre réseau puis réessayez.', 0, null);
        }

        var data = null;
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        if (!response.ok) {
            if (response.status === 401) {
                sessionExpired();
            }
            var message = data && data.error
                ? data.error
                : 'Le serveur a rencontré une erreur (' + response.status + '). Veuillez réessayer.';
            throw new ApiError(message, response.status, data);
        }
        if (data === null) {
            throw new ApiError('Réponse inattendue du serveur. Veuillez recharger la page.', response.status, null);
        }
        return data;
    };

    function toFormData(values) {
        var form = new FormData();
        Object.entries(values).forEach(function (entry) {
            if (Array.isArray(entry[1])) {
                entry[1].forEach(function (item) { form.append(entry[0] + '[]', item); });
            } else if (entry[1] !== null && entry[1] !== undefined) {
                form.append(entry[0], entry[1]);
            }
        });
        return form;
    }

    var sessionWarned = false;
    function sessionExpired() {
        if (sessionWarned) {
            return;
        }
        sessionWarned = true;
        A.toast('Votre session a expiré. Reconnectez-vous pour continuer.', 'error', {
            persistent: true,
            action: {
                label: 'Se reconnecter',
                run: function () {
                    window.location.href = '/connexion';
                }
            }
        });
    }

    // ------------------------------------------------------------------
    // Dates (heure locale ; format d'échange AAAA-MM-JJ)
    // ------------------------------------------------------------------

    var LOCALE = 'fr-FR';
    var formatters = {
        long: new Intl.DateTimeFormat(LOCALE, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
        dayMonth: new Intl.DateTimeFormat(LOCALE, { weekday: 'long', day: 'numeric', month: 'long' }),
        short: new Intl.DateTimeFormat(LOCALE, { weekday: 'short', day: 'numeric', month: 'short' }),
        monthYear: new Intl.DateTimeFormat(LOCALE, { month: 'long', year: 'numeric' }),
        monthName: new Intl.DateTimeFormat(LOCALE, { month: 'long' }),
        monthShort: new Intl.DateTimeFormat(LOCALE, { month: 'short' }),
        weekdayLong: new Intl.DateTimeFormat(LOCALE, { weekday: 'long' }),
        weekdayShort: new Intl.DateTimeFormat(LOCALE, { weekday: 'short' })
    };

    function capitalize(text) {
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    A.dates = {
        parse: function (ymd) {
            var parts = String(ymd).split('-').map(Number);
            return new Date(parts[0], parts[1] - 1, parts[2]);
        },
        isValid: function (ymd) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(ymd))) {
                return false;
            }
            var date = A.dates.parse(ymd);
            return A.dates.ymd(date) === ymd;
        },
        ymd: function (date) {
            return date.getFullYear() + '-'
                + String(date.getMonth() + 1).padStart(2, '0') + '-'
                + String(date.getDate()).padStart(2, '0');
        },
        today: function () {
            return A.dates.ymd(new Date());
        },
        addDays: function (date, days) {
            var copy = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            copy.setDate(copy.getDate() + days);
            return copy;
        },
        /** Même jour du mois, borné à la longueur du mois cible (31 → 30/28…). */
        addMonths: function (date, months) {
            var target = new Date(date.getFullYear(), date.getMonth() + months, 1);
            var lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();
            target.setDate(Math.min(date.getDate(), lastDay));
            return target;
        },
        startOfMonth: function (date) {
            return new Date(date.getFullYear(), date.getMonth(), 1);
        },
        /** Lundi de la semaine contenant la date. */
        startOfWeek: function (date) {
            return A.dates.addDays(date, -((date.getDay() + 6) % 7));
        },
        diffDays: function (fromYmd, toYmd) {
            return Math.round((A.dates.parse(toYmd) - A.dates.parse(fromYmd)) / 86400000);
        },
        long: function (ymd) { return formatters.long.format(A.dates.parse(ymd)); },
        dayMonth: function (ymd) { return formatters.dayMonth.format(A.dates.parse(ymd)); },
        short: function (ymd) { return formatters.short.format(A.dates.parse(ymd)); },
        monthYear: function (date) { return capitalize(formatters.monthYear.format(date)); },
        monthName: function (date) { return formatters.monthName.format(date); },
        monthShort: function (date) { return formatters.monthShort.format(date); },
        weekdayLong: function (date) { return formatters.weekdayLong.format(date); },
        weekdayShort: function (date) { return capitalize(formatters.weekdayShort.format(date).replace('.', '')); },
        /** « Aujourd'hui », « Demain », « Hier » ou la date courte. */
        relativeDay: function (ymd) {
            var diff = A.dates.diffDays(A.dates.today(), ymd);
            if (diff === 0) { return "Aujourd'hui"; }
            if (diff === 1) { return 'Demain'; }
            if (diff === -1) { return 'Hier'; }
            return capitalize(A.dates.short(ymd));
        },
        capitalize: capitalize
    };

    // ------------------------------------------------------------------
    // Annonces pour lecteurs d'écran
    // ------------------------------------------------------------------

    var liveRegions = {};

    function ensureLiveRegions() {
        if (liveRegions.polite) {
            return;
        }
        liveRegions.polite = A.el('div', { class: 'sr-only', 'aria-live': 'polite', 'aria-atomic': 'true' });
        liveRegions.assertive = A.el('div', { class: 'sr-only', 'aria-live': 'assertive', 'aria-atomic': 'true' });
        document.body.append(liveRegions.polite, liveRegions.assertive);
    }

    A.announce = function (message, assertive) {
        ensureLiveRegions();
        var region = assertive ? liveRegions.assertive : liveRegions.polite;
        region.textContent = '';
        // Délai : garantit une nouvelle annonce même si le texte est identique
        setTimeout(function () { region.textContent = message; }, 60);
    };

    // ------------------------------------------------------------------
    // Toasts (retour visuel des actions)
    // ------------------------------------------------------------------

    var TOAST_ICONS = { success: 'circle-check', error: 'circle-exclamation', info: 'circle-info' };
    var toastStack = null;

    A.toast = function (message, type, options) {
        type = TOAST_ICONS[type] ? type : 'info';
        options = options || {};

        if (!toastStack) {
            toastStack = A.el('div', { class: 'toasts' });
            document.body.append(toastStack);
        }

        var toast = A.el('div', { class: 'toast toast--' + type });
        var close = function () {
            toast.classList.add('toast--leaving');
            setTimeout(function () { toast.remove(); }, 200);
        };

        toast.append(
            A.icon(TOAST_ICONS[type], 'toast__icon'),
            A.el('p', { class: 'toast__message', text: message })
        );
        if (options.action) {
            toast.append(A.el('button', {
                type: 'button',
                class: 'toast__action',
                text: options.action.label,
                on: { click: function () { close(); options.action.run(); } }
            }));
        }
        toast.append(A.el('button', {
            type: 'button',
            class: 'toast__close',
            'aria-label': 'Fermer le message',
            on: { click: close }
        }, [A.icon('xmark')]));

        toastStack.append(toast);
        A.announce(message, type === 'error');

        if (!options.persistent) {
            var delay = type === 'error' ? 8000 : 5000;
            var timer = setTimeout(close, delay);
            // Pas de disparition pendant que l'utilisateur interagit avec le toast
            toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
            toast.addEventListener('focusin', function () { clearTimeout(timer); });
            toast.addEventListener('mouseleave', function () { timer = setTimeout(close, 3000); });
        }
        return toast;
    };

    // ------------------------------------------------------------------
    // Boutons occupés (protection contre les doubles soumissions)
    // ------------------------------------------------------------------

    A.setBusy = function (button, busy, busyLabel) {
        if (!button) {
            return;
        }
        if (busy) {
            if (button.dataset.busy === '1') {
                return;
            }
            button.dataset.busy = '1';
            button.dataset.label = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.replaceChildren(A.el('span', { class: 'spinner', 'aria-hidden': 'true' }), busyLabel || 'Patientez…');
        } else if (button.dataset.busy === '1') {
            button.innerHTML = button.dataset.label;
            button.disabled = false;
            button.removeAttribute('aria-busy');
            delete button.dataset.busy;
        }
    };

    // Formulaires classiques (rechargement de page) : bouton désactivé à l'envoi
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-busy-label') || event.defaultPrevented) {
            return;
        }
        var button = form.querySelector('[type="submit"]');
        if (button && button.dataset.busy === '1') {
            event.preventDefault();
            return;
        }
        A.setBusy(button, true, form.getAttribute('data-busy-label'));
    });

    // Retour arrière depuis le cache du navigateur : réactive les boutons
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-busy="1"]').forEach(function (button) {
            A.setBusy(button, false);
        });
    });

    // ------------------------------------------------------------------
    // Dialogue de confirmation (remplace window.confirm)
    // ------------------------------------------------------------------

    var confirmDialog = null;

    A.confirm = function (options) {
        options = options || {};
        if (!confirmDialog) {
            confirmDialog = A.el('dialog', { class: 'dialog dialog--confirm', 'aria-labelledby': 'confirmTitle', 'aria-describedby': 'confirmMessage' }, [
                A.el('div', { class: 'dialog__body' }, [
                    A.el('h2', { class: 'dialog__title', id: 'confirmTitle' }),
                    A.el('p', { class: 'dialog__text', id: 'confirmMessage' })
                ]),
                A.el('div', { class: 'dialog__footer' }, [
                    A.el('button', { type: 'button', class: 'btn btn-secondary', 'data-action': 'cancel' }),
                    A.el('button', { type: 'button', class: 'btn', 'data-action': 'confirm' })
                ])
            ]);
            document.body.append(confirmDialog);
        }

        var title = confirmDialog.querySelector('#confirmTitle');
        var message = confirmDialog.querySelector('#confirmMessage');
        var cancel = confirmDialog.querySelector('[data-action="cancel"]');
        var confirm = confirmDialog.querySelector('[data-action="confirm"]');

        title.textContent = options.title || 'Confirmer';
        message.textContent = options.message || '';
        cancel.textContent = options.cancelLabel || 'Annuler';
        confirm.textContent = options.confirmLabel || 'Confirmer';
        confirm.className = 'btn ' + (options.danger ? 'btn-danger' : 'btn-primary');

        return new Promise(function (resolve) {
            var finish = function (result) {
                confirmDialog.removeEventListener('close', onClose);
                cancel.onclick = null;
                confirm.onclick = null;
                if (confirmDialog.open) {
                    confirmDialog.close();
                }
                resolve(result);
            };
            var onClose = function () { finish(false); };

            cancel.onclick = function () { finish(false); };
            confirm.onclick = function () { finish(true); };
            confirmDialog.addEventListener('close', onClose);
            confirmDialog.showModal();
            // Focus par défaut sur l'action la moins destructrice
            (options.danger ? cancel : confirm).focus();
        });
    };

    // ------------------------------------------------------------------
    // Menus déroulants (motif « disclosure » : bouton + panneau)
    // ------------------------------------------------------------------

    var openDisclosures = new Set();

    A.disclosure = function (toggle, panel, hooks) {
        hooks = hooks || {};
        var api = {
            isOpen: function () { return !panel.hidden; },
            open: function () {
                openDisclosures.forEach(function (other) { if (other !== api) { other.close(false); } });
                panel.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                openDisclosures.add(api);
                if (hooks.onOpen) { hooks.onOpen(); }
            },
            close: function (restoreFocus) {
                if (panel.hidden) { return; }
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
                openDisclosures.delete(api);
                if (restoreFocus) { toggle.focus(); }
            }
        };

        toggle.addEventListener('click', function () {
            if (api.isOpen()) { api.close(false); } else { api.open(); }
        });
        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                api.close(true);
            }
        });
        toggle.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && api.isOpen()) {
                api.close(true);
            }
        });
        document.addEventListener('click', function (event) {
            if (api.isOpen() && !panel.contains(event.target) && !toggle.contains(event.target)) {
                api.close(false);
            }
        });
        panel.addEventListener('focusout', function (event) {
            if (api.isOpen() && event.relatedTarget && !panel.contains(event.relatedTarget) && !toggle.contains(event.relatedTarget)) {
                api.close(false);
            }
        });
        return api;
    };

    // Menu du compte (présent sur toutes les pages applicatives)
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('userMenuToggle');
        var panel = document.getElementById('userMenu');
        if (toggle && panel) {
            A.disclosure(toggle, panel);
        }
    });
})(window.AgendDAY);
