    import 'bootstrap';
    import './helpers/all-helpers'
    import SimpleBar from 'simplebar';

    /**
     * We'll load the axios HTTP library which allows us to easily issue requests
     * to our Laravel back-end. This library automatically handles sending the
     * CSRF token as a header based on the value of the "XSRF" token cookie.
     */

    import axios from 'axios';

    window.axios = axios;

    window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

    /**
     * Echo exposes an expressive API for subscribing to channels and listening
     * for events that are broadcast by Laravel. Echo and event broadcasting
     * allows your team to easily build robust real-time web applications.
     */

    // import Echo from 'laravel-echo';

    // import Pusher from 'pusher-js';
    // window.Pusher = Pusher;

    // window.Echo = new Echo({
    //     broadcaster: 'pusher',
    //     key: import.meta.env.VITE_PUSHER_APP_KEY,
    //     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
    //     wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    //     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    //     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    //     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    //     enabledTransports: ['ws', 'wss'],
    // });


    /**
     * Выход
     */
    eventClick('#logout', () => $('.logout-from').submit());


    /**
     * Открыть выпадающее меню главный (header)
     */
    eventClick('.header__author', () => {
        $('html').toggleClass('shadow-open');
        $('.header__account').toggleClass('active');
    })

    /**
     * Открыть/закрыть панель уведомлений
     */
    eventClick('.notification-header__icon', (elem) => {
        $(elem).closest('.header__notification').toggleClass('active');
        $('html').toggleClass('shadow-open');
    })


    /**
     * Like/Dislike
     */
    eventClick('.like', (elem) => {

        if ($(elem).data('route') === undefined || $(elem).hasClass('popup-my-interests__button')) {

            return false;

        }

        pageLoadingStart();

        request('POST', $(elem).data('route'), (resp) => {

            pageLoadingEnd();

            $(elem).toggleClass('_active');
            $(elem).find('.like-count').text(resp.count);
        });
    })

    /**
     * Скопировать
     */
    eventClick('.copy-link', (elem) => {

        const $temp = $('<input>');

        $('body').append($temp);

        $temp.val($(elem).data('route')).select();

        document.execCommand('copy');

        $temp.remove();

        showSuccess('Успешно скопировано');

    })

    /**
     * Открыть модалку для жалобы
     */
    eventClick('.complain', (elem) => {
        const $modal = $('#report');
        $modal.addClass('popup_show');
        $('html').addClass('popup-show');
        $modal.find('form').attr('data-action',  $(elem).attr('href'));
        $modal.find('.complain-model').val($(elem).data('model'));
        $modal.find('textarea').focus();
    })

    /**
     * Отправить жалобу
     */
    eventClick('.send-complain', (elem) => {

        const form = $(elem).closest('form');

        doSubmitForm(form, (resp) => {
            $('#report').removeClass('popup_show')
            $('#report-thanks').addClass('popup_show');
            form[0].reset();
        })

    })

    /**
     * Пометить прочитанным уведомления
     * @param ids
     * @returns {boolean}
     */
    function markNotificationsAsRead(ids) {
        const $notification_count = $('.notification-count')
        const current_notification_count = parseInt($notification_count.first().text());

        if (current_notification_count < 1) {
            return false
        }

        post(route('mark-notification-as-read'), {ids}, (resp) => {

            $notification_count.text(resp);

            ids.forEach((value, index) => {
                $(`.notification-header__column[data-id="${value}"]`)
                    .removeAttr('data-id')
                    .find('.notifications__icon')
                    .remove();
            });

            if (resp === 0) {
                $('.mark-all-as-read').hide();
                $notification_count.hide();
                $('.notification-header__number').hide();
            }

        })

    }

    eventClick('.mark-all-as-read', () => {
        const ids = [];

        $('.notification-header__column').each((index, elem) => {
            ids.push($(elem).data('id'));
        })

        ids.push('all')

        if (ids.length > 0) {
            markNotificationsAsRead(ids)
        }
    })

    eventClick('.notification-header__column', (elem, event) => {

        if ($(event.target).closest('.notifications__icon').length) {
            return;
        }
        event.preventDefault();

        const deletedMessage = $(elem).data('deleted-message');

        if (deletedMessage) {
            showError(deletedMessage);
        } else {
            window.open(elem.attr('href'), '_blank');
        }

        const ids = [$(elem).data('id')];

        if (ids.length > 0) {
            markNotificationsAsRead(ids)
        }
    })

    eventClick('.notifications__icon', (elem, event) => {

        event.preventDefault();

        const ids = [$(elem).closest('.notification-header__column').data('id')];

        if (ids.length > 0) {
            markNotificationsAsRead(ids)
        }
    })


    const $settings_unread = $('.settings-notification__unread');
    const $settings_all = $('.settings-notification__all');

    function updateButtonStates() {
        if ($settings_unread.hasClass('_hidden')) {
            $settings_all.addClass('_active');
        } else {
            $settings_all.removeClass('_active');
        }
    }

    eventClick('.settings-notification__unread', () => {

        $('.notifications__column').each(function () {
            const $notif = $(this);
            if (!$notif.attr('data-id')) {
                $notif.hide();
            } else {
                $notif.show();
            }
        });

        $settings_unread.addClass('_hidden');

        $settings_all.removeClass('_hidden');

        updateButtonStates();
    });


    eventClick('.settings-notification__all', () => {

        $('.notifications__column').show();

        $settings_all.addClass('_hidden').removeClass('_active');

        $settings_unread.removeClass('_hidden');

        updateButtonStates();
    });
