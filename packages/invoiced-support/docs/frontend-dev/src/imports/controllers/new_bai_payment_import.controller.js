(function () {
    'use strict';

    angular.module('app.imports').controller('NewBAIPaymentImportController', NewBAIPaymentImportController);

    NewBAIPaymentImportController.$inject = ['$scope', '$state', 'selectedCompany', 'Import', 'Core'];

    function NewBAIPaymentImportController($scope, $state, selectedCompany, Import, Core) {
        //
        // Methods
        //

        let type = 'bank_feed_transaction_bai';
        $scope.updateExisting = false;

        $scope.goImport = function (id) {
            $state.go('manage.import.view', {
                id: id,
            });
        };

        $scope.import = function (file) {
            let fr = new FileReader();
            fr.onload = function () {
                let lines = {
                    bai_text: fr.result,
                };

                $scope.importing = true;
                Import.create(
                    {
                        type: type,
                        lines: lines,
                    },
                    function (_import) {
                        $scope.importing = false;
                        $scope.goImport(_import.id);
                    },
                    function (result) {
                        $scope.error = result;
                    },
                );
            };
            fr.readAsText(file);
        };

        //
        // Initialization
        //

        Core.setTitle('BAI Payments Import');
    }
})();
