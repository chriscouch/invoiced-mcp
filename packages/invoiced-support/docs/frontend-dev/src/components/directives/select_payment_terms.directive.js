(function () {
    'use strict';

    angular.module('app.components').directive('selectPaymentTerms', selectPaymentTerms);

    function selectPaymentTerms() {
        return {
            restrict: 'E',
            template:
                '<div class="loading inline" ng-hide="paymentTermsSelectOptions"></div>' +
                '<div class="select-payment-terms" ng-if="paymentTermsSelectOptions">' +
                '<input type="hidden" id="{{id}}" ng-model="$parent.paymentTerms" ui-select2="paymentTermsSelectOptions" tabindex="{{tabi}}" ng-required="required" />' +
                '</div>',
            scope: {
                paymentTerms: '=ngModel',
                allowNew: '=',
                allowClear: '=',
                tabi: '=',
                required: '=isRequired',
            },
            controller: [
                '$scope',
                '$modal',
                '$timeout',
                '$filter',
                'CurrentUser',
                'selectedCompany',
                'PaymentTerms',
                function ($scope, $modal, $timeout, $filter, CurrentUser, selectedCompany, PaymentTerms) {
                    $scope.id = 'select-payment-terms-' + Math.round(Math.random() * 1000);
                    let escapeHtml = $filter('escapeHtml');

                    let placeholder = 'Select payment terms';
                    if ($scope.allowNew) {
                        placeholder = 'Find or create payment terms';
                    }

                    PaymentTerms.findAll(
                        function (terms) {
                            let activeTerms = [];
                            angular.forEach(terms, function (term) {
                                if (term.active) {
                                    term = angular.copy(term);
                                    term.text = term.name;
                                    activeTerms.push(term);
                                }
                            });
                            buildOptions(activeTerms);
                        },
                        function () {
                            buildOptions([]);
                        },
                    );

                    $scope.newPaymentTermsModal = function (name) {
                        $('.modal').hide();

                        name = name || '';

                        const modalInstance = $modal.open({
                            templateUrl: 'settings/views/edit-payment-terms.html',
                            controller: 'EditPaymentTermsController',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                paymentTerm: function () {
                                    return {
                                        name: name,
                                        active: true,
                                    };
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (newPaymentTerms) {
                                $scope.paymentTerms = newPaymentTerms;

                                $('.modal').show();
                            },
                            function () {
                                // canceled
                                $('.modal').show();

                                if (name.length > 0) {
                                    $scope.paymentTerms = null;
                                }
                            },
                        );
                    };

                    function buildOptions(data) {
                        $scope.paymentTermsSelectOptions = {
                            placeholder: placeholder,
                            width: '100%',
                            allowClear: Boolean($scope.allowClear),
                            createSearchChoice: function (term) {
                                if ($scope.allowNew) {
                                    return {
                                        id: -1,
                                        name: term,
                                    };
                                }
                            },
                            createSearchChoicePosition: 'bottom',
                            data: data,
                            // load the object when an initial value is present
                            initSelection: function (element, callback) {
                                let value = $(element).val();
                                if (!value) {
                                    callback(null);
                                    return;
                                }

                                let found = false;
                                angular.forEach(data, function (paymentTerm) {
                                    if (paymentTerm.name === value) {
                                        found = true;
                                        callback(paymentTerm);
                                    }
                                });

                                if (!found) {
                                    let paymentTerms = {
                                        name: value,
                                        due_in_days: null,
                                        discount_expires_in_days: null,
                                        discount_is_percent: true,
                                        discount_value: null,
                                        active: true,
                                    };

                                    // this checks for an early payment discount
                                    // i.e. 2% 10 net 30 = 2% discount if paid in 10 days
                                    let matchesEarlyDiscount = paymentTerms.name
                                        .toLowerCase()
                                        .match(/([\d]+)% ([\d]+) net ([\d]+)/i);

                                    // this checks for net D payment terms
                                    let matchesNetTerms = paymentTerms.name.toLowerCase().match(/net[\s-]([\d]+)/i);

                                    if (matchesEarlyDiscount) {
                                        paymentTerms.discount_value = parseInt(matchesEarlyDiscount[1]);
                                        paymentTerms.discount_expires_in_days = parseInt(matchesEarlyDiscount[2]);
                                        paymentTerms.due_in_days = parseInt(matchesEarlyDiscount[3]);
                                    } else if (matchesNetTerms) {
                                        paymentTerms.due_in_days = parseInt(matchesNetTerms[1]);
                                    }

                                    callback(paymentTerms);
                                }
                            },
                            // build result
                            formatResult: function (paymentTerms) {
                                // adds a 'Add "search_term" as a new payment terms' result
                                if (paymentTerms.id === -1) {
                                    return (
                                        "<div class='create'>Create <span>" +
                                        escapeHtml(paymentTerms.name) +
                                        '</span></div>'
                                    );
                                }

                                return "<div class='title'>" + escapeHtml(paymentTerms.name) + '</div>';
                            },
                            formatSelection: function (paymentTerms) {
                                return escapeHtml(paymentTerms.name);
                            },
                        };

                        addDomListener();
                    }

                    // hack to allow the DOM to render so we can
                    // listen to select2 events
                    function addDomListener() {
                        $timeout(function () {
                            $('#' + $scope.id).on('select2-selecting', function (event) {
                                // when the id = -1 then the user has selected
                                // the add _ as a new payment terms result
                                if (event.choice.id === -1) {
                                    $scope.newPaymentTermsModal(event.choice.name);
                                }
                            });
                        }, 50);
                    }
                },
            ],
        };
    }
})();
