(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('NewAPBankAccountController', NewAPBankAccountController);

    NewAPBankAccountController.$inject = [
        '$scope',
        '$modalInstance',
        '$window',
        'Core',
        'InvoicedConfig',
        'selectedCompany',
        'Feature',
        'BankAccount',
        'PlaidLinkService',
        'item',
        'LeavePageWarning',
    ];

    //copied from components/angular-signature/src/signature.js
    let EMPTY_IMAGE =
        'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAjgAAADcCAQAAADXNhPAAAACIklEQVR42u3UIQEAAAzDsM+/6UsYG0okFDQHMBIJAMMBDAfAcADDATAcwHAAwwEwHMBwAAwHMBzAcAAMBzAcAMMBDAcwHADDAQwHwHAAwwEMB8BwAMMBMBzAcADDATAcwHAADAcwHADDAQwHMBwAwwEMB8BwAMMBDAfAcADDATAcwHAAwwEwHMBwAAwHMBzAcAAMBzAcAMMBDAcwHADDAQwHwHAAwwEwHMBwAMMBMBzAcAAMBzAcwHAADAcwHADDAQwHMBwAwwEMB8BwAMMBDAfAcADDATAcwHAAwwEwHMBwAAwHMBzAcCQADAcwHADDAQwHwHAAwwEMB8BwAMMBMBzAcADDATAcwHAADAcwHMBwAAwHMBwAwwEMBzAcAMMBDAfAcADDAQwHwHAAwwEwHMBwAAwHMBzAcAAMBzAcAMMBDAcwHADDAQwHwHAAwwEMB8BwAMMBMBzAcADDATAcwHAADAcwHMBwAAwHMBwAwwEMB8BwAMMBDAfAcADDATAcwHAAwwEwHMBwAAwHMBzAcAAMBzAcAMMBDAcwHADDAQwHwHAAwwEMB8BwAMMBMBzAcADDkQAwHMBwAAwHMBwAwwEMBzAcAMMBDAfAcADDAQwHwHAAwwEwHMBwAMMBMBzAcAAMBzAcwHAADAcwHADDAQwHMBwAwwEMB8BwAMMBMBzAcADDATAcwHAADAcwHMBwAAwHMBwAwwEMBzAcAMMBDAegeayZAN3dLgwnAAAAAElFTkSuQmCC';

    function NewAPBankAccountController(
        $scope,
        $modalInstance,
        $window,
        Core,
        InvoicedConfig,
        selectedCompany,
        Feature,
        BankAccount,
        PlaidLinkService,
        item,
        LeavePageWarning,
    ) {
        if (!item) {
            item = {
                name: null,
                check_number: null,
                plaid_id: null,
                signature: null,
            };
        }

        $scope.item = item;
        $scope.initialSignature = item.signature;
        $scope.signed = !!item.signature;
        $scope.emptyImage = EMPTY_IMAGE;
        $scope.accept = function () {};
        $scope.clear = function () {};

        $scope.doAccept = function (signature) {
            $scope.signed = signature && !signature.isEmpty;
            signature = signature ? signature.dataUrl : null;
            $scope.item.signature = signature;
            $scope.signature = signature;
        };

        $scope.signature = null;
        $scope.token = null;
        $scope.purpose_print_check = !!item.check_number || !Feature.hasFeature('ap_plaid');
        $scope.purpose_e_check = item.signature && Feature.hasFeature('ap_plaid');
        $scope.nonSavable = function () {
            if (!$scope.purpose_print_check && !$scope.purpose_e_check) {
                return true;
            }
            if ($scope.purpose_print_check && !$scope.item.check_number) {
                return true;
            }
            return !!($scope.purpose_e_check && !$scope.signed);
        };

        if (item.id) {
            $scope.signature = item.signature;
        }

        $scope.verify = function () {
            PlaidLinkService.upgradeToken($scope.item.plaid_id, plaidSuccessVerify, plaidExit);
        };

        function plaidSuccessVerify() {
            PlaidLinkService.verificationFinish(
                {
                    id: $scope.item.plaid_id,
                },
                {},
                function () {
                    $scope.item.plaid.verified = true;
                    Core.showMessage('Your account has been successfully verified', 'success');
                },
                plaidExit,
            );
        }

        function plaidExit(err) {
            $scope.$apply(() => {
                $scope.saving = false;
            });

            // error or else user closed the dialog
            if (err) {
                Core.showMessage(err, 'error');
            }
        }

        function getVendorBankAccountParameters(token) {
            let params = {
                name: $scope.item.name,
                check_number: $scope.item.check_number,
                plaid_id: $scope.item.plaid_id,
                signature: $scope.signature,
                check_layout: $scope.item.check_layout,
            };
            if (token) {
                params.token = token;
            }

            return params;
        }

        $scope.save = function () {
            $scope.saving = true;
            $scope.error = null;

            if (
                $scope.purpose_e_check &&
                (!$scope.item.id || !$scope.item.plaid_id || ($scope.item.plaid && $scope.item.plaid.verified))
            ) {
                if ($scope.item.plaid_id) {
                    PlaidLinkService.upgradeToken($scope.item.plaid_id, plaidSuccess, plaidExit);
                } else {
                    PlaidLinkService.createToken(plaidSuccess, plaidExit);
                }
            } else {
                if ($scope.item.id) {
                    edit();
                } else {
                    create();
                }
            }
        };

        $scope.close = function () {
            $modalInstance.dismiss('cancel');
        };

        // close the modal if the parent state changes
        $scope.$on('$stateChangeSuccess', function () {
            $scope.close();
        });

        function edit(token) {
            let params = getVendorBankAccountParameters(token);
            BankAccount.edit(
                {
                    id: $scope.item.id,
                },
                params,
                function (data) {
                    LeavePageWarning.unblock();
                    $scope.saving = false;
                    $modalInstance.close(data);
                },
                function (result) {
                    LeavePageWarning.unblock();
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function create(token) {
            let params = getVendorBankAccountParameters(token);
            BankAccount.create(
                {
                    expand: 'plaid',
                },
                params,
                function (data) {
                    LeavePageWarning.unblock();
                    $scope.saving = false;
                    $scope.item = data;
                    if (!data.plaid_id || data.plaid.verified) {
                        $modalInstance.close(data);
                    }
                },
                function (result) {
                    LeavePageWarning.unblock();
                    $scope.saving = false;
                    $scope.error = result.data;
                },
            );
        }

        function plaidSuccess(public_token, metadata) {
            BankAccount.link(
                {
                    token: public_token,
                    metadata: metadata,
                },
                function (links) {
                    // success
                    if (links[links.length - 1].message) {
                        Core.showMessage(links[links.length - 1].message, 'error');
                        delete links[links.length - 1];
                    }
                    angular.forEach(links, function (link) {
                        $scope.item.plaid_id = link.id;
                    });

                    if ($scope.item.id) {
                        edit(public_token);
                    } else {
                        create(public_token);
                    }
                },
                function (result) {
                    // error
                    $scope.saving = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }
    }
})();
