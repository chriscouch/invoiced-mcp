(function () {
    'use strict';

    angular.module('app.components').directive('currencyInput', currencyInput);

    currencyInput.$inject = ['Money', 'selectedCompany'];

    function currencyInput(Money, selectedCompany) {
        return {
            restrict: 'A',
            require: 'ngModel',
            scope: {
                currency: '=',
                precision: '=?',
            },
            link: function ($scope, element, attrs, ngModelCtrl) {
                let options;
                let hasPlaceholder = typeof element.attr('placeholder') !== 'undefined';
                let placeholderCurrency = null;

                function formatMoney(value) {
                    if (value === null) {
                        return '';
                    }

                    if (!options) {
                        options = $scope.precision !== null ? { precision: $scope.precision } : {};
                        options = angular.extend(options, selectedCompany.moneyFormat);
                    }

                    return Money.currencyFormat(value, $scope.currency, options);
                }

                function getSeparator(lang) {
                    return (1.1).toLocaleString(lang).replace(/1/g, '');
                }

                function getLang() {
                    let separators =
                        navigator.languages !== undefined
                            ? navigator.languages.map(getSeparator)
                            : [getSeparator(navigator.language)];
                    //unique
                    return separators
                        .filter(function (value, index, self) {
                            return self.indexOf(value) === index;
                        })
                        .join();
                }

                function parseMoney(value) {
                    if (value) {
                        let separator = getLang();
                        let reg = '[' + separator + ']';
                        let replace = new RegExp(reg, 'g');
                        //we replace all possible separator signs to . which js understands
                        return (
                            parseFloat(
                                value
                                    .toString()
                                    .replace(replace, '.')
                                    //next we strip all . but last (which is real separator)
                                    .replace(/\.(?=.*\.)/g, '')
                                    //we finish by striping all special characters
                                    .replace(/[^\d._-]/g, ''),
                            ) || 0
                        );
                    }

                    return null;
                }

                function formatter(value) {
                    value = parseMoney(value);
                    ngModelCtrl.$modelValue = value;

                    // Return what we want the value displayed to the user to be
                    return formatMoney(value);
                }

                function parser(value) {
                    return parseMoney(value);
                }

                ngModelCtrl.$formatters.push(formatter);
                ngModelCtrl.$parsers.push(parser);

                element.bind('blur', updateElement);

                $scope.$watch('currency', updateElement);

                function updateElement() {
                    element.val(formatMoney(parseMoney(element.val())));
                    if (!hasPlaceholder && placeholderCurrency !== $scope.currency) {
                        element.attr('placeholder', formatMoney(0));
                        placeholderCurrency = $scope.currency;
                    }
                }
            },
        };
    }
})();
