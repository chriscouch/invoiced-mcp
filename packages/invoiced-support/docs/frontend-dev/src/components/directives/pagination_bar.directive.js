(function () {
    'use strict';

    angular.module('app.components').directive('paginationBar', paginationBar);

    function paginationBar() {
        return {
            restrict: 'E',
            templateUrl: 'components/views/pagination-bar.html',
            scope: {
                filter: '=ngModel',
                pageCount: '=',
                totalCount: '=',
                callback: '&?ngChange',
            },
            controller: [
                '$scope',
                function ($scope) {
                    $scope.prevPage = function () {
                        $scope.goToPage($scope.filter.page - 1);
                    };

                    $scope.nextPage = function () {
                        $scope.goToPage($scope.filter.page + 1);
                    };

                    $scope.goToPage = function (page) {
                        $scope.filter.page = page;

                        if (typeof $scope.callback !== 'function') {
                            return;
                        }

                        page = parseInt(page) || 1;

                        if (page < 1 || page > $scope.pageCount) {
                            return;
                        }

                        $scope.callback({
                            page: page,
                            perPage: $scope.filter.per_page,
                        });
                    };

                    $scope.changePerPage = function (perPage) {
                        $scope.filter.per_page = perPage;
                        $scope.goToPage(1);
                    };
                },
            ],
        };
    }
})();
