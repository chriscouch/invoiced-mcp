/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_receivable').factory('DocumentControllerHelper', DocumentControllerHelper);

    DocumentControllerHelper.$inject = ['Core'];

    function DocumentControllerHelper(Core) {
        return {
            void: voidDocument,
        };

        function voidDocument($scope, documentModel, onSuccess) {
            vex.dialog.confirm({
                message: $scope.deleteMessage(documentModel, 'void'),
                callback: function (result) {
                    if (result) {
                        let original = angular.copy(documentModel);
                        documentModel.$void(
                            {
                                id: documentModel.id,
                            },
                            function () {
                                $scope.deleting = false;

                                // restore expanded objects
                                documentModel.customer = original.customer;

                                onSuccess(original);
                            },
                            function (result) {
                                $scope.deleting = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }
    }
})();
