(function () {
    'use strict';

    angular.module('app.search').controller('SearchController', SearchController);

    SearchController.$inject = [
        '$scope',
        '$state',
        '$stateParams',
        '$location',
        'Core',
        'Search',
        'selectedCompany',
        'Feature',
        'ObjectDeepLink',
    ];

    function SearchController(
        $scope,
        $state,
        $stateParams,
        $location,
        Core,
        Search,
        selectedCompany,
        Feature,
        ObjectDeepLink,
    ) {
        $scope.company = selectedCompany;

        let queryParams = $location.search() || {};
        $scope.object = queryParams.object || false;

        let query = $stateParams.q || false;
        $scope.query = query;

        if (!query) {
            $state.go('manage.index');
            return;
        }

        $scope.selectObject = function (object) {
            let params = $location.search() || {};
            params.object = object;
            $location.search(params);
            $scope.object = object;
        };

        $scope.goToObject = function (type, obj) {
            if (type === 'contact') {
                ObjectDeepLink.goTo(type, obj._customer);
            } else {
                ObjectDeepLink.goTo(type, obj.id);
            }
        };

        Core.setTitle(query + ' | Search');

        $scope.loading = true;

        Search.search(
            {
                query: query,
                per_page: 1000,
            },
            function (hits) {
                $scope.loading = false;

                // determine tab to select
                if (hits.length > 0) {
                    $scope.selectObject(hits[0].object);
                } else if (Feature.hasFeature('accounts_receivable')) {
                    $scope.selectObject('customer');
                } else if (Feature.hasFeature('accounts_payable')) {
                    $scope.selectObject('vendor');
                }

                $scope.results = {
                    contact: [],
                    credit_note: [],
                    customer: [],
                    estimate: [],
                    invoice: [],
                    payment: [],
                    subscription: [],
                    vendor: [],
                };

                angular.forEach(hits, function (hit) {
                    if (typeof $scope.results[hit.object] === 'undefined') {
                        $scope.results[hit.object] = [];
                    }

                    $scope.results[hit.object].push(hit);
                });
            },
            function (result) {
                $scope.loading = false;
                Core.showMessage(result.data.message, 'error');
            },
        );
    }
})();
