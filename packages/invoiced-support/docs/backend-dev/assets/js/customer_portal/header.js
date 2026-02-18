/* globals bootstrap, Snap */
(function () {
    'use strict';

    $(function () {
        // tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl)); // jshint ignore:line

        // google analytics
        /* jshint ignore:start */
        var googleAnalyticsId = $('#gaid').data('id');
        if (googleAnalyticsId) {
            (function (i, s, o, g, r, a, m) {
                i['GoogleAnalyticsObject'] = r;
                (i[r] =
                    i[r] ||
                    function () {
                        (i[r].q = i[r].q || []).push(arguments);
                    }),
                    (i[r].l = 1 * new Date());
                (a = s.createElement(o)), (m = s.getElementsByTagName(o)[0]);
                a.async = 1;
                a.src = g;
                m.parentNode.insertBefore(a, m);
            })(window, document, 'script', 'https://www.google-analytics.com/analytics.js', 'ga');

            ga('create', googleAnalyticsId, 'auto');
            ga('send', 'pageview');

            $('.gaev').each(function () {
                var el = $(this);
                var category = el.data('category');
                var action = el.data('action');
                var label = el.data('label');
                ga('send', 'event', category, action, label);
            });
        }
        /* jshint ignore:end */

        // page sizing + snap.js
        $(window).resize(function () {
            initSnapper();
            resizeAppPage();
        });

        initSnapper();
        resizeAppPage();

        $('.toggle-drawer').click(function (e) {
            toggleDrawer();
            e.preventDefault();
            return false;
        });

        $('.confirm-action-first').click(function () {
            return window.confirm($(this).data('confirm-msg'));
        });
    });

    // App content-holder minimum height
    function resizeAppPage() {
        // resize main app page
        var page = $('#page');
        var headerHeight = 0;
        var footerHeight = 0;
        if (page.length === 1) {
            var height = $(window).height() - headerHeight - footerHeight;
            var navHeight = $('#left-navbar').height();
            page.css('min-height', Math.max(height, navHeight));
        }
    }

    // snap.js
    function initSnapper() {
        if ($('#page').length === 0) {
            return;
        }

        var snapper = (window.snapper = new Snap({
            element: document.getElementById('page'),
            disable: 'right',
            maxPosition: 230,
            minPosition: 230,
            touchToDrag: false,
            tapToClose: false,
        }));

        function resizeSnapper() {
            snapper.close();

            if ($(window).width() <= 991) {
                snapper.enable();
            } else {
                snapper.disable();
            }
        }

        $(window).resize(resizeSnapper);
        resizeSnapper();
    }

    function toggleDrawer() {
        if (window.snapper.state().state === 'left') {
            window.snapper.close();
            $('.hamburger').removeClass('open');
        } else {
            window.snapper.open('left');
            $('.hamburger').addClass('open');
        }
    }
})();
