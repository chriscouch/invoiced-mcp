/* globals _, moment */
(function () {
    'use strict';

    angular.module('app.core').factory('Core', Core);

    Core.$inject = ['$document', '$timeout', '$window', '$translate', '$filter', 'InvoicedConfig'];

    function Core($document, $timeout, $window, $translate, $filter, InvoicedConfig) {
        // php -> moment.js
        let phpToMomentMap = {
            // days
            d: 'DD',
            D: 'ddd',
            j: 'D',
            l: 'dddd',
            N: 'E',
            S: 'Do',
            w: 'd',
            z: 'DDD',
            // week
            W: 'w',
            // months
            F: 'MMMM',
            m: 'MM',
            M: 'MMM',
            n: 'M',
            //      't':
            // years
            //      'L':
            o: 'GGGG',
            Y: 'YYYY',
            y: 'YY',
            // time
            a: 'a',
            A: 'A',
            //      'B':
            g: 'h',
            G: 'H',
            h: 'hh',
            H: 'HH',
            i: 'mm',
            s: 'ss',
            u: 'SSS',
            // timezone
            e: 'z',
            //      'I':
            O: 'ZZ',
            P: 'Z',
            T: 'z',
            //      'Z':
            // full date/time
            //      'c':
            //      'r':
            U: 'X',
        };
        let phpToMomentConversions = {};

        // php -> jquery ui datepicker
        let phpToDatepickerMap = {
            // days
            d: 'dd',
            D: 'D',
            j: 'd',
            l: 'DD',
            //  'N': '',
            //  'S': '',
            //  'w': '',
            z: 'o',
            // week
            //  'W': '',
            // months
            F: 'MM',
            m: 'mm',
            M: 'M',
            n: 'm',
            //      't':
            // years
            //  'L':
            //  'o': '',
            Y: 'yy',
            y: 'y',
            // time
            //  'a': '',
            //  'A': '',
            //  'B':
            //  'g': '',
            //  'G': '',
            //  'h': '',
            //  'H': '',
            //  'i': '',
            //  's': '',
            //  'u': '',
            // timezone
            //  'e': '',
            //  'I':
            //  'O': '',
            //  'P': '',
            //  'T': '',
            //  'Z':
            // full date/time
            c: 'yy-mm-dd',
            r: 'D, d M yy',
            U: '@',
        };
        let phpToDatepickerConversions = {};

        let metricOrder = [24, 21, 18, 15, 12, 9, 6, 3, 0];

        let metricAbbrevs = {
            24: 'Y',
            21: 'Z',
            18: 'E',
            15: 'P',
            12: 'T',
            9: 'G',
            6: 'M',
            3: 'K',
            0: '',
        };

        let ips = {}; // cached ipinfo.io lookups
        let loadingIps = {};

        return {
            setTitle: setTitle,
            // messages
            flashMessage: flashMessage,
            flashTranslatedMessage: flashTranslatedMessage,
            showMessage: showMessage,
            showTranslatedMessage: showTranslatedMessage,
            // loading screen
            closeLoadingScreen: closeLoadingScreen,
            showLoadingScreen: showLoadingScreen,
            showFailedMessage: showFailedMessage,
            // dates
            phpDateFormatToMoment: phpDateFormatToMoment,
            phpDateFormatToDatepicker: phpDateFormatToDatepicker,
            // locale
            determineTimezone: determineTimezone,
            determineCountry: determineCountry,
            getCountryFromCode: getCountryFromCode,
            countryHasSellerTaxId: countryHasSellerTaxId,
            countryHasBuyerTaxId: countryHasBuyerTaxId,
            taxLabelForCountry: taxLabelForCountry,
            // ipinfo
            lookupIp: lookupIp,
            // http
            parseLinkHeader: parseLinkHeader,
            // user
            generateInitials: generateInitials,
            // numbers
            parseFormattedNumber: parseFormattedNumber,
            numberAbbreviate: numberAbbreviate,
            // billing
            upgradeUrl: upgradeUrl,
            // files
            createAndDownloadBlobFile: createAndDownloadBlobFile,
            downloadUrl: downloadUrl,
            getDispositionFilename: getDispositionFilename,
            decodeBlobError: decodeBlobError,
        };

        function setTitle(title) {
            $document[0].title = title + ' - Invoiced';
        }

        function flashMessage(message, type) {
            showMessage(message, type, true, false);
        }

        function flashTranslatedMessage(translationId, type) {
            $translate(translationId).then(function (val) {
                flashMessage(val, type);
            });
        }

        // message cannot contain any HTML
        function showMessage(message, type, flash, timestamp) {
            type = type || 'error';
            if (type == 'error') {
                type = 'danger';
            }
            flash = flash || false;
            timestamp = typeof timestamp === 'undefined' ? true : timestamp;
            message = $filter('nl2br')($filter('escapeHtml')(message));

            let html =
                '<div class="alert alert-' + type + ' alert-dismissible ' + (timestamp ? ' with-timestamp' : '') + '">';
            html +=
                '<button type="button" class="close" aria-label="Close">' +
                '<span aria-hidden="true">&times;</span>' +
                '<span class="sr-only">Close</span>' +
                '</button>';
            html += '<div class="message">' + message + '</div>';

            if (timestamp) {
                html += '<div class="time">' + moment().format('h:mm a') + '</div>';
            }

            html += '</div>';

            let el = $(html);

            $('#app-messages').append(el);
            el.fadeIn();

            function fadeOut() {
                el.fadeOut('normal', function () {
                    $(this).remove();
                });
            }

            if (flash) {
                $timeout(function () {
                    fadeOut();
                }, 5000); // 5s
            }

            $('.close', $(el)).click(function () {
                fadeOut();
            });
        }

        function showTranslatedMessage(translationId) {
            $translate(translationId).then(function (val) {
                showMessage(val);
            });
        }

        function showFailedMessage(msg) {
            closeLoadingScreen();
            if (msg) {
                $('#failed-connection .message').html(msg);
            }
            $('#failed-connection').removeClass('hidden');
        }

        function closeLoadingScreen() {
            $window.loadingScreen.finish();
        }

        function showLoadingScreen(msg) {
            let options = angular.copy($window.loadingScreen.options);
            if (msg) {
                options.loadingHtml =
                    "<p class='loading-message'>" +
                    msg +
                    "</p><div class='sk-spinner sk-spinner-wave'><div class='sk-rect1'></div> <div class='sk-rect2'></div> <div class='sk-rect3'></div> <div class='sk-rect4'></div> <div class='sk-rect5'></div></div>";
            }
            $window.loadingScreen = $window.pleaseWait(options);
        }

        function convertDateFormat(format, fromMap, cache) {
            if (typeof format === 'undefined') {
                return '';
            }

            // look for a cached result
            if (typeof cache[format] !== 'undefined') {
                return cache[format];
            }

            // 1. tokenize format string into characters
            let tokens = format.split('');

            // 2. map each token from php date format to moment
            for (let i in tokens) {
                tokens[i] = typeof fromMap[tokens[i]] !== 'undefined' ? fromMap[tokens[i]] : tokens[i];
            }

            // 3. rebuild the format string
            let result = tokens.join('');

            // 4. cache the result
            cache[format] = result;

            return result;
        }

        function phpDateFormatToMoment(format) {
            return convertDateFormat(format, phpToMomentMap, phpToMomentConversions);
        }

        function phpDateFormatToDatepicker(format) {
            return convertDateFormat(format, phpToDatepickerMap, phpToDatepickerConversions);
        }

        function determineTimezone(cb) {
            let tzName = moment.tz.guess();
            if (_.contains(InvoicedConfig.timezones, tzName)) {
                cb(tzName);
            }
        }

        function determineCountry(cb) {
            //determine country based on ip address
            lookupIp(false, function (response) {
                cb(response.country);
            });
        }

        function lookupIp(ip, cb) {
            let url = InvoicedConfig.ipinfoUrl;
            if (ip) {
                url += ip;

                // check for a cached response
                if (typeof ips[ip] !== 'undefined') {
                    $timeout(function () {
                        cb(ips[ip]);
                    });
                    return;
                }
            }

            // prevent a bunch of requests for the same IP
            if (typeof loadingIps[ip] === 'undefined') {
                loadingIps[ip] = [];

                $.ajax({
                    url: url,
                    method: 'GET',
                    data: {
                        token: InvoicedConfig.ipinfoToken,
                    },
                    dataType: 'jsonp',
                    success: function (response) {
                        // cache the response
                        if (ip) {
                            ips[ip] = response;
                        }

                        // call all of the callbacks
                        angular.forEach(loadingIps[ip], function (_cb) {
                            _cb(response);
                        });

                        delete loadingIps[ip];
                    },
                    statusCode: {
                        429: function () {
                            throw new Error('ipinfo.io: number of daily API calls exceeded');
                        },
                    },
                });
            }

            loadingIps[ip].push(cb);
        }

        function getCountryFromCode(code) {
            for (let i in InvoicedConfig.countries) {
                if (InvoicedConfig.countries[i].code === code) {
                    return InvoicedConfig.countries[i];
                }
            }

            return false;
        }

        function countryHasSellerTaxId(code) {
            let country = getCountryFromCode(code);

            if (country && typeof country.seller_has_tax_id !== 'undefined') {
                return country.seller_has_tax_id;
            }

            return false;
        }

        function countryHasBuyerTaxId(code) {
            let country = getCountryFromCode(code);

            if (country && typeof country.buyer_has_tax_id !== 'undefined') {
                return country.buyer_has_tax_id;
            }

            return false;
        }

        function taxLabelForCountry(id) {
            let country = getCountryFromCode(id);
            if (country && typeof country.tax_label !== 'undefined') {
                return country.tax_label;
            }

            return 'Tax';
        }

        // found on https://gist.github.com/niallo/3109252
        function parseLinkHeader(header) {
            if (header.length === 0) {
                throw new Error('input must not be of zero length');
            }

            // Split parts by comma
            let parts = header.split(',');
            let links = {};
            // Parse each part into a named link
            for (let i = 0; i < parts.length; i++) {
                let section = parts[i].split(';');
                if (section.length !== 2) {
                    throw new Error("section could not be split on ';'");
                }
                let url = section[0].replace(/<(.*)>/, '$1').trim();
                let name = section[1].replace(/rel="(.*)"/, '$1').trim();
                links[name] = url;
            }
            return links;
        }

        function generateInitials(name) {
            return name.split(' ').reduce(function (previous, current) {
                return getInitial(previous) + getInitial(current);
            });
        }

        function getInitial(str) {
            let initial = str.substr(0, 1);
            let i = 0;
            while (!isNaN(initial) && i < str.length - 1) {
                initial = str.substr(++i, 1);
            }
            return initial;
        }

        function parseFormattedNumber(val, decimal_separator, thousands_separator) {
            let dRegex = new RegExp('\\' + decimal_separator, 'g');
            let tRegex = new RegExp('\\' + thousands_separator, 'g');
            return val.toString().replace(tRegex, '').replace(dRegex, '.');
        }

        function numberAbbreviate(number, decimals) {
            decimals = typeof decimals === 'undefined' ? 1 : decimals;
            for (let i in metricOrder) {
                let exponent = metricOrder[i];
                if (number >= Math.pow(10, exponent)) {
                    let remainder = number % Math.pow(10, exponent);
                    let decimal =
                        remainder > 0
                            ? Math.round(Math.round(remainder, decimals) / Math.pow(10, exponent), decimals)
                            : 0;

                    return parseInt(number / Math.pow(10, exponent)) + decimal + metricAbbrevs[exponent];
                }
            }

            return number;
        }

        function upgradeUrl(company, user, notActivated) {
            let params = {
                tenant_id: company.id,
                company: company.name,
                person_name: user.name,
                firstname: user.first_name,
                lastname: user.last_name,
                email: user.email,
                utm_campaign: 'upgrade',
                utm_medium: 'landing',
                utm_content: 'Upgrade',
                utm_source: 'In-App',
                environment: InvoicedConfig.environment,
            };
            let query = [];
            angular.forEach(params, function (value, key) {
                query.push(key + '=' + encodeURIComponent(value));
            });

            let url = InvoicedConfig.upgradeUrl;
            if (notActivated) {
                url = InvoicedConfig.activateUrl;
            }

            return url + '?' + query.join('&', query);
        }

        function createAndDownloadBlobFile(body, filename) {
            let blob;
            if (body instanceof Blob) {
                blob = body;
            } else {
                blob = new Blob([body]);
            }
            if (navigator.msSaveBlob) {
                // IE 10+
                navigator.msSaveBlob(blob, filename);
            } else {
                let url = URL.createObjectURL(blob);
                downloadUrl(url, filename);
            }
        }

        function downloadUrl(url, filename) {
            let link = document.createElement('a');
            // Browsers that support HTML5 download attribute
            if (link.download !== undefined) {
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        function getDispositionFilename(headers) {
            let header = headers();
            let disposition = header['content-disposition'];
            if (disposition && disposition.indexOf('attachment') !== -1) {
                let filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                let matches = filenameRegex.exec(disposition);
                if (matches != null && matches[1]) {
                    return matches[1].replace(/['"]/g, '');
                }
            }

            return null;
        }

        function decodeBlobError(response, cb) {
            let header = response.headers();
            if (
                response.config &&
                response.config.responseType &&
                response.config.responseType === 'blob' &&
                header['content-type'] === 'application/json'
            ) {
                let fr = new FileReader();
                fr.addEventListener('load', function () {
                    cb(JSON.parse(fr.result));
                });
                fr.readAsText(response.data);
            } else {
                cb(response.data);
            }
        }
    }
})();
