/* globals vex, moment */
(function () {
    'use strict';

    angular.module('app.collections').directive('invoiceCadenceEditor', invoiceCadenceEditor);

    function invoiceCadenceEditor() {
        return {
            restrict: 'E',
            templateUrl: 'collections/views/invoice-cadence-editor.html',
            scope: {
                chaseState: '=?',
                chaseSchedule: '=?',
                changeHandler: '=', // called when the chase schedule changes
                invoice: '=?',
            },
            controller: [
                '$scope',
                '$timeout',
                'Feature',
                'InvoiceChaseState',
                'Customer',
                function ($scope, $timeout, Feature, InvoiceChaseState, Customer) {
                    /**
                     * Initialization
                     */
                    $scope.hasSms = Feature.hasFeature('sms');
                    $scope.hasLetters = Feature.hasFeature('letters');

                    // contact roles
                    $scope.contactRoles = [];
                    $scope.loadingContactRoles = false;
                    loadContactRoles();

                    $scope.showOnIssueHour = true;
                    if ($scope.invoice) {
                        // start of the day on the invoice date
                        let startOfInvoiceDate = moment($scope.invoice.date).set({ hour: 0, minutes: 0, seconds: 0 });
                        $scope.showOnIssueHour = startOfInvoiceDate.isAfter(moment());
                    }

                    $scope.dpOpened = {}; // date picker state

                    if (!$scope.chaseSchedule) {
                        $scope.chaseSchedule = [];
                    }

                    if ($scope.chaseState) {
                        $scope.chaseState = InvoiceChaseState.map(angular.copy($scope.chaseState));
                    }

                    // set up change callback to allow parent to access modifications
                    $scope.$watch('chaseSchedule', $scope.changeHandler, true);

                    /**
                     * Adds step to the cadence.
                     */
                    $scope.addStep = function () {
                        $scope.chaseSchedule.push({
                            trigger: 0, // on_issue
                            options: {
                                hour: '7',
                                role: null,
                                // When email is the only option, the checkbox is hidden and the
                                // option should default to true.
                                email: !$scope.hasSms && !$scope.hasLetters,
                                sms: false,
                                letter: false,
                            },
                        });
                    };

                    /**
                     * Removes a step from the cadence.
                     */
                    $scope.deleteStep = function (i) {
                        vex.dialog.confirm({
                            message: 'Are you sure you want to delete this step?',
                            callback: function (result) {
                                if (result) {
                                    $scope.$apply(function () {
                                        $scope.chaseSchedule.splice(i, 1);
                                    });
                                }
                            },
                        });
                    };

                    /**
                     * Performs necessary actions when a specific step has
                     * its trigger changed.
                     */
                    $scope.handleTriggerChange = function (i) {
                        // reset the timing options on type change
                        let step = $scope.chaseSchedule[i];

                        // retain hour and channel values
                        let options = {};
                        angular.forEach(['email', 'sms', 'letter', 'hour'], function (key) {
                            options[key] = step.options[key];
                        });

                        step.options = options;
                    };

                    /**
                     * Determines whether a channel checkbox is required on the form.
                     *
                     * NOTE: A checkbox is required when no other checkbox in the row
                     * is selected.
                     */
                    $scope.isCheckboxRequired = function (index) {
                        let options = $scope.chaseSchedule[index].options;
                        return !options.email && !options.sms && !options.letter;
                    };

                    /**
                     * Opens date picker for the absolute date trigger.
                     */
                    $scope.openDatepicker = function ($event, name) {
                        $event.stopPropagation();
                        $scope.dpOpened[name] = true;
                        // this is needed to ensure the datepicker
                        // can be opened again
                        $timeout(function () {
                            $scope.dpOpened[name] = false;
                        });
                    };

                    /**
                     * Returns whether or not the step with the provided id is editable.
                     */
                    $scope.isStepEditable = function (id) {
                        if (!id || !$scope.chaseState) {
                            return true;
                        }

                        let state = $scope.chaseState[id] ? $scope.chaseState[id] : {};
                        return !state.sent && !InvoiceChaseState.hasFailure(state);
                    };

                    if ($scope.chaseSchedule.length === 0) {
                        $scope.addStep();
                    }

                    function loadContactRoles() {
                        $scope.loadingContactRoles = true;
                        Customer.contactRoles(
                            function (roles) {
                                $scope.contactRoles = roles;
                                $scope.loadingContactRoles = false;
                            },
                            function (result) {
                                $scope.error = result.data;
                                $scope.loadingContactRoles = false;
                            },
                        );
                    }
                },
            ],
        };
    }
})();
