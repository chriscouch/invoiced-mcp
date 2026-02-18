/* globals vex */
(function () {
    'use strict';

    angular.module('app.accounts_payable').controller('ViewBillController', ViewBillController);

    ViewBillController.$inject = [
        '$scope',
        '$stateParams',
        '$filter',
        '$modal',
        '$q',
        'selectedCompany',
        'Core',
        'NetworkDocument',
        'BrowsingHistory',
        'LeavePageWarning',
        'Member',
        'Bill',
        'Permission',
    ];

    function ViewBillController(
        $scope,
        $stateParams,
        $filter,
        $modal,
        $q,
        selectedCompany,
        Core,
        NetworkDocument,
        BrowsingHistory,
        LeavePageWarning,
        Member,
        Bill,
        Permission,
    ) {
        $scope.rejectDocument = rejectDocument;
        $scope.approveDocument = approveDocument;
        $scope.voidDocument = voidDocument;
        $scope.changeStatus = changeStatus;
        $scope.addPayment = addPayment;
        $scope.addAdjustment = addAdjustment;
        $scope.approverModal = approverModal;
        $scope.assignWorkflow = assignWorkflow;
        $scope.delete = deleteBill;

        load();

        $scope.transactions = [];
        $scope.attachments = [];
        $scope.loaded = {
            attachments: false,
        };
        $scope.resolutions = [];
        $scope.hasApprovalPermission = true;
        $scope.lastRejection = '';
        $scope.isUSA = 'US' === selectedCompany.country;

        function load() {
            $scope.loading = 1;
            let billPromise = $q(function (resolve) {
                Bill.find(
                    {
                        id: $stateParams.id,
                        expand: 'vendor,approval_workflow,approval_workflow_step',
                    },
                    function (bill) {
                        resolve(bill);
                        $scope.bill = bill;
                        Core.setTitle('Bill # ' + bill.number);

                        BrowsingHistory.push({
                            id: bill.id,
                            type: 'bill',
                            title: bill.number,
                        });

                        // prefill the email reply box
                        $scope.prefillEmailReply = {
                            network_connection: bill.vendor.network_connection,
                            subject: 'Invoice # ' + bill.number,
                        };

                        loadBalance(bill.id);
                        loadAttachments(bill.id);

                        if (bill.network_document) {
                            loadNetworkDocument(bill.network_document);
                        }
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
            let memberPromise = $q(function (resolve) {
                Member.current(
                    function (member) {
                        resolve(member);
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });

            $q.all([billPromise, memberPromise]).then(applyWorkflowPermissions);
            loadResolutions();
        }

        function loadResolutions() {
            ++$scope.loading;
            let approvalPromise = $q(function (resolve) {
                Bill.approvals(
                    {
                        id: $stateParams.id,
                        expand: 'member,approval_workflow_step',
                        include: 'approval_workflow',
                    },
                    function (approvals) {
                        resolve(approvals);
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });
            let rejectionPromise = $q(function (resolve) {
                Bill.rejections(
                    {
                        id: $stateParams.id,
                        expand: 'member,approval_workflow_step',
                        include: 'approval_workflow',
                    },
                    function (rejections) {
                        resolve(rejections);
                    },
                    function (result) {
                        Core.showMessage(result.data.message, 'error');
                    },
                );
            });

            $q.all([approvalPromise, rejectionPromise]).then(function (results) {
                let resolutions = [];
                angular.forEach(results[0], function (approval) {
                    approval.type = 'approval';
                    resolutions.push(approval);
                });
                angular.forEach(results[1], function (rejection) {
                    rejection.type = 'rejection';
                    resolutions.push(rejection);
                    $scope.lastRejection = rejection.note;
                });
                resolutions.sort(function (a, b) {
                    let ax = a.created_at;
                    let bx = b.created_at;
                    if (ax < bx) {
                        return -1;
                    }
                    if (ax > bx) {
                        return 1;
                    }

                    // names must be equal
                    return 0;
                });
                --$scope.loading;
                $scope.resolutions = resolutions;
            });
        }

        function loadNetworkDocument(id) {
            // never reload the network document
            if ($scope.doc) {
                return;
            }

            NetworkDocument.find(
                {
                    id: id,
                    include: 'detail,current_status_reason',
                },
                function (doc) {
                    $scope.doc = doc;
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadBalance(id) {
            Bill.balance(
                {
                    id: id,
                },
                function (balance) {
                    $scope.balance = balance;
                    $scope.transactions = [];
                    angular.forEach(balance.transactions, function (transaction) {
                        if (transaction.document_type !== 'Invoice' || transaction.reference != id) {
                            $scope.transactions.push(transaction);
                        }
                    });
                },
                function (result) {
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadAttachments(id) {
            if ($scope.loaded.attachments) {
                return;
            }

            Bill.attachments(
                {
                    id: id,
                },
                function (attachments) {
                    $scope.attachments = attachments;
                    $scope.loaded.attachments = true;
                },
            );
        }

        function rejectDocument(bill) {
            vex.dialog.prompt({
                message: '<p>Why are you rejecting this document?</p>',
                callback: function (result) {
                    if (result) {
                        $scope.changingStatus = true;
                        Bill.reject(
                            { id: bill.id },
                            {
                                description: result,
                            },
                            function (data) {
                                $scope.changingStatus = false;
                                $scope.bill.status = data.status;
                                loadResolutions();
                            },
                            function (result) {
                                $scope.changingStatus = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }

        function approveDocument(bill) {
            $scope.changingStatus = true;
            Bill.approve(
                { id: bill.id },
                {},
                function (data) {
                    $scope.changingStatus = false;
                    $scope.bill.status = data.status;
                    loadResolutions();
                },
                function (result) {
                    $scope.changingStatus = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function voidDocument(doc) {
            let escapeHtml = $filter('escapeHtml');
            vex.dialog.confirm({
                message:
                    '<p>Are you sure you want to void this document? This cannot be undone.</p>' +
                    '<p><strong>' +
                    escapeHtml(doc.type) +
                    ' <small>' +
                    escapeHtml(doc.reference) +
                    '</small></strong></p>',
                callback: function (result) {
                    if (result) {
                        changeStatus(doc, 'Voided');
                    }
                },
            });
        }

        function changeStatus(doc, newStatus, description) {
            description = description || null;
            $scope.changingStatus = true;
            NetworkDocument.setStatus(
                { id: doc.id },
                {
                    status: newStatus,
                    description: description,
                },
                function () {
                    $scope.changingStatus = false;
                    doc.current_status = newStatus;
                    doc.current_status_reason = description;
                },
                function (result) {
                    $scope.changingStatus = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function addPayment(bill) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/payments/new.html',
                controller: 'NewVendorPaymentController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    bill: function () {
                        return bill;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    load(); // reload
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function addAdjustment(bill) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/adjustments/new.html',
                controller: 'NewVendorAdjustmentController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    doc: function () {
                        return bill;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    load(); // reload
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function approverModal() {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'accounts_payable/views/bills/assign-approver.html',
                controller: 'AssignApproverController',
                backdrop: 'static',
                keyboard: false,
                size: 'md',
                resolve: {
                    doc: function () {
                        return $scope.bill;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();

                    Core.flashMessage('User was subscribed to the bill', 'success');
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        }

        function assignWorkflow(workflow) {
            let response = workflow ? 'assigned' : 'unassigned';
            let workflowId = workflow ? workflow.id : null;
            Bill.edit(
                {
                    id: $scope.bill.id,
                },
                {
                    approval_workflow: workflowId,
                },
                function () {
                    $scope.bill.approval_workflow = workflow;
                    $scope.bill.approval_workflow_id = workflowId;
                    Core.flashMessage('Approval workflow has been ' + response, 'success');
                },
                function (error) {
                    Core.showMessage(error.message, 'error');
                },
            );
        }

        function applyWorkflowPermissions(results) {
            --$scope.loading;
            let bill = results[0];
            let member = results[1];
            if (!bill.approval_workflow_step) {
                $scope.hasApprovalPermission = true;
                return;
            }
            let ret = Permission.hasPermission('bills.edit');
            if (bill.approval_workflow_step.members.length) {
                ret = false;
                for (let i in bill.approval_workflow_step.members) {
                    if (member && bill.approval_workflow_step.members[i].id === member.id) {
                        $scope.hasApprovalPermission = true;
                        return;
                    }
                }
            }
            if (bill.approval_workflow_step.roles.length) {
                ret = false;
                for (let j in bill.approval_workflow_step.roles) {
                    if (member && bill.approval_workflow_step.roles[j].id === member.role) {
                        $scope.hasApprovalPermission = true;
                        return;
                    }
                }
            }

            $scope.hasApprovalPermission = ret;
        }

        function deleteBill(bill) {
            vex.dialog.confirm({
                message: 'Are you sure you want to void this bill? This operation is irreversible.',
                callback: function (result) {
                    if (result) {
                        Bill.delete(
                            {
                                id: bill.id,
                            },
                            function (bill2) {
                                angular.extend(bill, bill2);
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }
                },
            });
        }
    }
})();
