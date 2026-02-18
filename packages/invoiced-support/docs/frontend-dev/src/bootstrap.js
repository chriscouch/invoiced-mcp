/* globals heap, InvoicedConfig, Invoiced, Modernizr, Snap, vex, zE */
(function () {
    'use strict';

    /*
    ------------
    -- Global Startup Logic
    -- (would be nice to move into Angular if possible)
    ------------
    */

    // https only
    if (window.location.protocol == 'http:' && InvoicedConfig.environment == 'production') {
        let restOfUrl = window.location.href.substr(5);
        window.location = 'https:' + restOfUrl;
    }

    // Invoiced
    if (typeof Invoiced !== 'undefined') {
        Invoiced.setPublishableKey(InvoicedConfig.paymentsPublishableKey, InvoicedConfig.environment);
    }

    // App content-holder minimum height
    const resizeAppPage = (window.resizeAppPage = function () {
        // resize main app page
        const page = $('#page');
        const headerHeight = 0;
        const footerHeight = 0;
        if (page.length === 1) {
            const height = $(window).height() - headerHeight - footerHeight;
            const navHeight = $('#left-navbar').height();
            page.css('min-height', Math.max(height, navHeight));
        }
    });

    // snap.js
    const initSnapper = (window.initSnapper = function () {
        if ($('#page').length === 0) {
            return;
        }

        const snapper = (window.snapper = new Snap({
            element: document.getElementById('page'),
            disable: 'right',
            maxPosition: 230,
            minPosition: 230,
            touchToDrag: false,
            tapToClose: false,
        }));

        const resize = function () {
            snapper.close();

            if ($(window).width() <= 991) {
                snapper.enable();
            } else {
                snapper.disable();
            }
        };

        $(window).resize(resize);
        resize();
    });

    // Zendesk
    if (typeof zE !== 'undefined') {
        zE('webWidget', 'hide');
        // availability is not supported by zendesk
        window.chatOperatorsAvailable = false;
        zE('webWidget:on', 'chat:status', function (status) {
            // status can be online, away, or offline
            // https://developer.zendesk.com/api-reference/widget/chat-api/#on-chatstatus
            window.chatOperatorsAvailable = status === 'online';
        });
    }

    // Heap
    if (InvoicedConfig.heapProjectId) {
        /* jshint ignore:start */
        (window.heapReadyCb = window.heapReadyCb || []),
            (window.heap = window.heap || []),
            (heap.load = function (e, t) {
                (window.heap.envId = e),
                    (window.heap.clientConfig = t = t || {}),
                    (window.heap.clientConfig.shouldFetchServerConfig = !1);
                var a = document.createElement('script');
                (a.type = 'text/javascript'),
                    (a.async = !0),
                    (a.src = 'https://cdn.us.heap-api.com/config/' + e + '/heap_config.js');
                var r = document.getElementsByTagName('script')[0];
                r.parentNode.insertBefore(a, r);
                var n = [
                        'init',
                        'startTracking',
                        'stopTracking',
                        'track',
                        'resetIdentity',
                        'identify',
                        'getSessionId',
                        'getUserId',
                        'getIdentity',
                        'addUserProperties',
                        'addEventProperties',
                        'removeEventProperty',
                        'clearEventProperties',
                        'addAccountProperties',
                        'addAdapter',
                        'addTransformer',
                        'addTransformerFn',
                        'onReady',
                        'addPageviewProperties',
                        'removePageviewProperty',
                        'clearPageviewProperties',
                        'trackPageview',
                    ],
                    i = function (e) {
                        return function () {
                            var t = Array.prototype.slice.call(arguments, 0);
                            window.heapReadyCb.push({
                                name: e,
                                fn: function () {
                                    heap[e] && heap[e].apply(heap, t);
                                },
                            });
                        };
                    };
                for (var p = 0; p < n.length; p++) heap[n[p]] = i(n[p]);
            });
        /* jshint ignore:end */
        heap.load(InvoicedConfig.heapProjectId);
    }

    $(function () {
        // placeholders
        if (Modernizr.input.placeholder) {
            $('html').addClass('placeholder');
        }

        // vex
        vex.defaultOptions.className = 'vex-theme-default';

        // sort countries list
        InvoicedConfig.countries.sort(function (a, b) {
            return a.country.localeCompare(b.country);
        });

        // page sizing + snap.js
        $(window).resize(function () {
            initSnapper();
            resizeAppPage();
        });

        initSnapper();
        resizeAppPage();
    });

    /*
    ------------
    -- jQuery UI Modifications
    ------------
    */

    // This will modify the jQuery UI datepicker "Today" button
    // to actually change the date.
    // Reference: http://stackoverflow.com/questions/1073410/today-button-in-jquery-datepicker-doesnt-work/7613795#7613795
    $.datepicker._gotoToday = function (id) {
        const target = $(id);
        const inst = this._getInst(target[0]);
        if (this._get(inst, 'gotoCurrent') && inst.currentDay) {
            inst.selectedDay = inst.currentDay;
            inst.drawMonth = inst.selectedMonth = inst.currentMonth;
            inst.drawYear = inst.selectedYear = inst.currentYear;
        } else {
            const date = new Date();
            inst.selectedDay = date.getDate();
            inst.drawMonth = inst.selectedMonth = date.getMonth();
            inst.drawYear = inst.selectedYear = date.getFullYear();
            // the below two lines are new
            this._setDateDatepicker(target, date);
            this._selectDate(id, this._getDateDatepicker(target));
        }
        this._notifyChange(inst);
        this._adjustDate(target);
    };

    /*
    ------------
    -- Native Object Extensions
    ------------
    */

    // Extend the default Number object with a formatMoney() method:
    // usage: someVar.formatMoney(decimalPlaces, decimalSeparator, thousandsSeparator)
    // defaults: (2, ",", ".")
    Number.prototype.formatMoney = function (places, decimal, thousand) {
        places = !isNaN((places = Math.abs(places))) ? places : 2;
        thousand = typeof thousand !== 'undefined' ? thousand : ',';
        decimal = typeof decimal !== 'undefined' ? decimal : '.';
        let number = this,
            negative = number < 0 ? '-' : '';
        let i = parseInt((number = Math.abs(+number || 0).toFixed(places)), 10) + '';
        let j = i.length > 3 ? i.length % 3 : 0;
        return (
            negative +
            (j ? i.substr(0, j) + thousand : '') +
            i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) +
            (places
                ? decimal +
                  Math.abs(number - i)
                      .toFixed(places)
                      .slice(2)
                : '')
        );
    };

    // found on http://stackoverflow.com/questions/5223/length-of-javascript-object-ie-associative-array
    if (!Object.size) {
        Object.size = function (obj) {
            let size = 0,
                key;
            for (key in obj) {
                if (obj.hasOwnProperty(key)) {
                    size++;
                }
            }
            return size;
        };
    }
})();
