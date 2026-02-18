/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('CouponsSettingsController', CouponsSettingsController);

    CouponsSettingsController.$inject = ['$scope', '$modal', 'LeavePageWarning', 'Core', 'selectedCompany', 'Coupon'];

    function CouponsSettingsController($scope, $modal, LeavePageWarning, Core, selectedCompany, Coupon) {
        $scope.company = angular.copy(selectedCompany);

        $scope.coupons = [];
        $scope.deleting = {};
        $scope.search = '';

        $scope.newCouponModal = function () {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-rate.html',
                controller: 'EditRateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return {
                            id: '',
                            name: '',
                            is_percent: true,
                            currency: selectedCompany.currency,
                            value: '',
                            duration: 0,
                            metadata: {},
                        };
                    },
                    type: function () {
                        return 'coupon';
                    },
                },
            });

            modalInstance.result.then(
                function (newCoupon) {
                    LeavePageWarning.unblock();

                    $scope.coupons.push(newCoupon);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.editCouponModal = function (coupon) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'catalog/views/edit-rate.html',
                controller: 'EditRateController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    model: function () {
                        return coupon;
                    },
                    type: function () {
                        return 'coupon';
                    },
                },
            });

            modalInstance.result.then(
                function (updatedCoupon) {
                    LeavePageWarning.unblock();

                    angular.extend(coupon, updatedCoupon);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.delete = function (coupon) {
            vex.dialog.confirm({
                message: 'Are you sure you want to delete this coupon?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[coupon.id] = true;
                        $scope.error = null;

                        Coupon.delete(
                            {
                                id: coupon.id,
                            },
                            function () {
                                $scope.deleting[coupon.id] = false;

                                Core.flashMessage('The coupon, ' + coupon.name + ', has been deleted', 'success');

                                // remove locally
                                for (let i in $scope.coupons) {
                                    if ($scope.coupons[i].id == coupon.id) {
                                        $scope.coupons.splice(i, 1);
                                        break;
                                    }
                                }

                                // clear HTTP cache
                                // this is necessary until angular.js is updated to support
                                // transformResponse when the result is a 204
                                Coupon.clearCache();
                            },
                            function (result) {
                                $scope.deleting[coupon.id] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        Core.setTitle('Coupons');
        loadCoupons();

        function loadCoupons() {
            $scope.loading = true;
            Coupon.all(
                function (coupons) {
                    $scope.loading = false;
                    $scope.coupons = coupons;
                },
                function (result) {
                    $scope.loading = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
