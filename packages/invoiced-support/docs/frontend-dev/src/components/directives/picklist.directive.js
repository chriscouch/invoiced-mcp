(function () {
    'use strict';

    angular.module('app.components').directive('picklist', picklist);

    picklist.$inject = ['$filter', 'InvoicedConfig'];

    function picklist($filter, InvoicedConfig) {
        return {
            restrict: 'E',
            replace: true,
            template:
                '<div class="picklist">' +
                '<input type="hidden" id="{{id}}" ng-model="model" ui-select2="selectOptions" tabindex="{{tabi}}" ng-required="required" />' +
                '</div>',
            scope: {
                type: '=',
                model: '=ngModel',
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
                    $scope.id = 'picklist-' + Math.round(Math.random() * 1000);
                    let escapeHtml = $filter('escapeHtml');

                    // Customize the picklist for the selected data type
                    let parameters = {};
                    if ($scope.type === 'customer') {
                        parameters = makeCustomerPicklist();
                    } else if ($scope.type === 'vendor') {
                        parameters = makeVendorPicklist();
                    }

                    let numHits = 5;

                    // search using the API
                    $scope.selectOptions = {
                        minimumInputLength: 1,
                        placeholder: parameters.placeholder,
                        width: '100%',
                        allowClear: Boolean($scope.allowClear),
                        createSearchChoice: function (term) {
                            if ($scope.allowNew) {
                                return {
                                    id: -1,
                                    name: term,
                                };
                            }

                            return null;
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
                                    type: $scope.type,
                                    per_page: numHits,
                                };
                            },
                            results: function (data) {
                                // Remove any excluded items
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
                                    url: parameters.retrieveUrl + id,
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
                        formatResult: parameters.formatResult,
                        formatSelection: parameters.formatSelection,
                    };

                    // hack to allow the DOM to render so we can
                    // listen to select2 events
                    $timeout(function () {
                        $('#' + $scope.id).on('select2-selecting', function (event) {
                            // when the id = -1 then the user has selected
                            // the add _ as a new result
                            if (event.choice.id === -1) {
                                create(event.choice.name);
                            }
                        });
                    }, 50);

                    function create(name) {
                        $('.modal').hide();
                        name = name || '';

                        parameters.create(name).then(
                            function (result) {
                                $scope.model = result;
                                $('.modal').show();
                            },
                            function () {
                                // canceled
                                $('.modal').show();

                                if (name.length > 0) {
                                    $scope.model = null;
                                }
                            },
                        );
                    }

                    function makeCustomerPicklist() {
                        let parameters = {};
                        parameters.placeholder = 'Select a customer';
                        if ($scope.allowNew) {
                            parameters.placeholder = 'Find or create a customer';
                        }

                        parameters.retrieveUrl = InvoicedConfig.apiBaseUrl + '/customers/';
                        parameters.formatResult = function (customer) {
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
                        };

                        parameters.formatSelection = function (customer) {
                            return escapeHtml(customer.name);
                        };

                        parameters.create = function (name) {
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

                            return modalInstance.result;
                        };

                        return parameters;
                    }

                    function makeVendorPicklist() {
                        let parameters = {};
                        parameters.placeholder = 'Select a vendor';
                        if ($scope.allowNew) {
                            parameters.placeholder = 'Find or create a vendor';
                        }

                        parameters.retrieveUrl = InvoicedConfig.apiBaseUrl + '/vendors/';
                        parameters.formatResult = function (vendor) {
                            // adds a 'Add "search_term" as a new vendor' result
                            if (vendor.id === -1) {
                                return (
                                    "<div class='create'>Add <span>" +
                                    escapeHtml(vendor.name) +
                                    '</span> as a new vendor</div>'
                                );
                            }

                            let markup = "<div class='title'>" + escapeHtml(vendor.name) + '</div>';
                            let pieces = [];
                            if (vendor.email) {
                                pieces.push(escapeHtml(vendor.email));
                            }
                            if (vendor.address1) {
                                pieces.push(escapeHtml(vendor.address1));
                            }
                            markup += "<div class='details'>" + pieces.join('<br/>') + '</div>';
                            return markup;
                        };

                        parameters.formatSelection = function (vendor) {
                            return escapeHtml(vendor.name);
                        };

                        parameters.create = function (name) {
                            const modalInstance = $modal.open({
                                templateUrl: 'accounts_payable/views/vendors/edit.html',
                                controller: 'EditVendorController',
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

                            return modalInstance.result;
                        };

                        return parameters;
                    }
                },
            ],
        };
    }
})();
