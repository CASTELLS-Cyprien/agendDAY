// ===== CENTRE DE NOTIFICATIONS =====
// Affiche les notifications créées par le moteur de rappels (cron) : aucune
// notification n'est fabriquée côté navigateur. Le compteur est actualisé
// toutes les minutes tant que la page est visible.
(function (A) {
    'use strict';

    var POLL_INTERVAL = 60000;

    var EMAIL_STATUS = {
        sent: { icon: 'envelope-circle-check', text: "E-mail transmis au serveur d'envoi" },
        pending: { icon: 'clock', text: "E-mail en cours d'envoi" },
        sending: { icon: 'clock', text: "E-mail en cours d'envoi" },
        failed: { icon: 'triangle-exclamation', text: "Échec de l'envoi de l'e-mail" },
        disabled: { icon: 'bell', text: 'Rappel dans l’application uniquement' },
        cancelled: { icon: 'ban', text: 'E-mail annulé (événement modifié ou supprimé)' },
        skipped: { icon: 'ban', text: 'E-mail non envoyé : trop tard' }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('notifToggle');
        var panel = document.getElementById('notifPanel');
        if (!toggle || !panel) {
            return;
        }

        var badge = document.getElementById('notifBadge');
        var list = document.getElementById('notifList');
        var empty = document.getElementById('notifEmpty');
        var errorBox = document.getElementById('notifError');
        var markAll = document.getElementById('notifMarkAll');

        var items = [];
        var unread = 0;
        var knownIds = null;
        var loading = false;

        var disclosure = A.disclosure(toggle, panel, {
            onOpen: function () {
                render();
                refresh();
            }
        });

        function setUnread(count) {
            unread = count;
            badge.hidden = count === 0;
            badge.textContent = count > 9 ? '9+' : String(count);
            toggle.setAttribute('aria-label', count > 0
                ? 'Notifications, ' + count + ' non lue' + (count > 1 ? 's' : '')
                : 'Notifications');
            markAll.disabled = count === 0;
        }

        function describeWhen(item) {
            if (!item.date) {
                return '';
            }
            var day = A.dates.relativeDay(item.date);
            return item.allDay ? day + ' · journée entière' : day + ' à ' + item.time;
        }

        function timeAgo(iso) {
            var minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (minutes < 1) { return "à l'instant"; }
            if (minutes < 60) { return 'il y a ' + minutes + ' min'; }
            var hours = Math.round(minutes / 60);
            if (hours < 24) { return 'il y a ' + hours + ' h'; }
            var days = Math.round(hours / 24);
            return 'il y a ' + days + ' jour' + (days > 1 ? 's' : '');
        }

        function render() {
            list.replaceChildren();
            empty.hidden = items.length > 0;

            items.forEach(function (item) {
                var status = EMAIL_STATUS[item.emailStatus];
                var title = A.el('span', { class: 'notif-item__title' }, [
                    'Rappel : ',
                    A.el('strong', { text: item.title })
                ]);
                var content = [
                    item.read ? null : A.el('span', { class: 'notif-item__dot', 'aria-hidden': 'true' }),
                    A.el('span', { class: 'notif-item__body' }, [
                        title,
                        A.el('span', { class: 'notif-item__when', text: describeWhen(item) + (item.eventId === null ? ' — événement supprimé' : '') }),
                        status ? A.el('span', { class: 'notif-item__status notif-item__status--' + item.emailStatus }, [
                            A.icon(status.icon),
                            ' ' + status.text
                        ]) : null,
                        A.el('span', { class: 'notif-item__ago', text: timeAgo(item.createdAt) })
                    ]),
                    item.read ? null : A.srOnly('Non lue.')
                ];

                list.append(A.el('li', {}, [
                    A.el('button', {
                        type: 'button',
                        class: 'notif-item' + (item.read ? '' : ' notif-item--unread'),
                        on: { click: function () { openItem(item); } }
                    }, content)
                ]));
            });
        }

        async function refresh() {
            if (loading) {
                return;
            }
            loading = true;
            try {
                var data = await A.api('/api/notifications');
                var previousIds = knownIds;
                items = data.items;
                knownIds = new Set(items.map(function (item) { return item.id; }));
                setUnread(data.unread);
                errorBox.hidden = true;

                // Rappel arrivé pendant que la page est ouverte : signalé discrètement
                if (previousIds) {
                    items.filter(function (item) { return !item.read && !previousIds.has(item.id); })
                        .slice(0, 3)
                        .forEach(function (item) {
                            A.toast('Rappel : ' + item.title + ' — ' + describeWhen(item), 'info', {
                                action: { label: 'Voir', run: function () { openItem(item); } }
                            });
                        });
                }
                if (disclosure.isOpen()) {
                    render();
                }
            } catch (error) {
                if (disclosure.isOpen()) {
                    errorBox.textContent = error.message;
                    errorBox.hidden = false;
                }
            } finally {
                loading = false;
            }
        }

        async function markRead(body) {
            try {
                var data = await A.api('/api/notifications/read', { body: body });
                setUnread(data.unread);
            } catch (error) {
                A.toast(error.message, 'error');
            }
        }

        function openItem(item) {
            if (!item.read) {
                item.read = true;
                markRead({ id: item.id });
                render();
            }
            disclosure.close(false);

            if (item.eventId === null || !item.date) {
                A.toast("L'événement de ce rappel a été supprimé.", 'info');
                return;
            }
            if (A.calendar) {
                A.calendar.openEvent(item.eventId, item.date);
            } else {
                window.location.href = '/calendrier?date=' + encodeURIComponent(item.date) + '&event=' + item.eventId;
            }
        }

        markAll.addEventListener('click', function () {
            items.forEach(function (item) { item.read = true; });
            render();
            markRead({ all: '1' });
            A.announce('Toutes les notifications sont marquées comme lues.');
        });

        refresh();
        setInterval(function () {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        }, POLL_INTERVAL);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        });
    });
})(window.AgendDAY);
