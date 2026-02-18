(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('selectCustomer', selectCustomer);

    selectCustomer.$inject = ['$filter', 'InvoicedConfig'];

    function selectCustomer($filter, InvoicedConfig) {
        return {
            restrict: 'E',
            replace: true,
            template:
                '<div class="select-customer">' +
                '<input type="hidden" id="{{id}}" ng-model="customer" ui-select2="customerSelectOptions" tabindex="{{tabi}}" ng-required="required" />' +
                '</div>',
            scope: {
                customer: '=ngModel',
                watch: '=',
                allowNew: '=',
                allowClear: '=',
                tabi: '=',
                excluded: '=',
                required: '=isRequired',
            },
            controller: [
                '$scope',
                '$modal',
                '$timeout',
                'CurrentUser',
                'selectedCompany',
                function ($scope, $modal, $timeout, CurrentUser, selectedCompany) {
                    $scope.id = 'select-customer-' + Math.round(Math.random() * 1000);
                    let escapeHtml = $filter('escapeHtml');

                    if ($scope.watch) {
                        $scope.$watch('customer', $scope.watch, true);
                    }

                    let placeholder = 'Select a customer';
                    if ($scope.allowNew) {
                        placeholder = 'Find or create a customer';
                    }

                    let numHits = 5;

                    // search using the API
                    $scope.customerSelectOptions = {
                        minimumInputLength: 1,
                        placeholder: placeholder,
                        width: '100%',
                        allowClear: Boolean($scope.allowClear),
                        createSearchChoice: function (term) {
                            if (!$scope.allowNew) {
                                return;
                            }

                            return {
                                id: -1,
                                name: term,
                            };
                        },
                        createSearchChoicePosition: 'bottom',
                        ajax: {
                            url: InvoicedConfig.apiBaseUrl + '/search',
                            dataType: 'json',
                            params: {
                                headers: {
                                    Authorization: selectedCompany.auth_header,
                                },
                                xhrFields: {
                                    withCredentials: false,
                                },
                            },
                            data: function (term) {
                                return {
                                    query: term,
                                    type: 'customer',
                                    per_page: numHits,
                                };
                            },
                            results: function (data) {
                                // Remove any excluded customer accounts
                                if ($scope.excluded instanceof Array) {
                                    let data2 = [];
                                    for (let i in data) {
                                        if ($scope.excluded.indexOf(data[i].id) === -1) {
                                            data2.push(data[i]);
                                        }
                                    }
                                    data = data2;
                                }

                                // Return the results in the format expected by Select2
                                return {
                                    results: data,
                                };
                            },
                        },
                        // load the object when an initial value is present
                        initSelection: function (element, callback) {
                            let id = $(element).val();
                            if (id > 0) {
                                let jqxhr = $.ajax({
                                    url: InvoicedConfig.apiBaseUrl + '/customers/' + id,
                                    method: 'GET',
                                    dataType: 'json',
                                    headers: {
                                        Authorization: selectedCompany.auth_header,
                                    },
                                    xhrFields: {
                                        withCredentials: false,
                                    },
                                });

                                jqxhr
                                    .done(function (result) {
                                        callback(result);
                                    })
                                    .fail(function () {
                                        callback(null);
                                    });
                            }
                        },
                        // build result
                        formatResult: function (customer) {
                            // adds a 'Add "search_term" as a new customer' result
                            if (customer.id === -1) {
                                return (
                                    "<div class='create'>Add <span>" +
                                    escapeHtml(customer.name) +
                                    '</span> as a new customer</div>'
                                );
                            }

                            let markup = "<div class='title'>" + escapeHtml(customer.name) + '</div>';
                            let pieces = [];
                            if (customer.email) {
                                pieces.push(escapeHtml(customer.email));
                            }
                            if (customer.address1) {
                                pieces.push(escapeHtml(customer.address1));
                            }
                            markup += "<div class='details'>" + pieces.join('<br/>') + '</div>';
                            return markup;
                        },
                        formatSelection: function (customer) {
                            return escapeHtml(customer.name);
                        },
                    };

                    $scope.newCustomerModal = function (name) {
                        $('.modal').hide();

                        name = name || '';

                        const modalInstance = $modal.open({
                            templateUrl: 'accounts_receivable/views/customers/edit-customer.html',
                            controller: 'EditCustomerController',
                            backdrop: 'static',
                            keyboard: false,
                            size: 'lg',
                            resolve: {
                                model: function () {
                                    return {
                                        name: name,
                                    };
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (newCustomer) {
                                $scope.customer = newCustomer;

                                $('.modal').show();
                            },
                            function () {
                                // canceled
                                $('.modal').show();

                                if (name.length > 0) {
                                    $scope.customer = null;
                                }
                            },
                        );
                    };

                    // hack to allow the DOM to render so we can
                    // listen to select2 events
                    $timeout(function () {
                        $('#' + $scope.id).on('select2-selecting', function (event) {
                            // when the id = -1 then the user has selected
                            // the add _ as a new customer result
                            if (event.choice.id === -1) {
                                $scope.newCustomerModal(event.choice.name);
                            }
                        });
                    }, 50);
                },
            ],
        };
    }
})();
