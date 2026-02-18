/* globals vex */
(function () {
    'use strict';

    angular.module('app.settings').controller('UsersSettingsController', UsersSettingsController);

    UsersSettingsController.$inject = [
        '$scope',
        '$state',
        '$modal',
        'Permission',
        'Member',
        'Role',
        'Company',
        'selectedCompany',
        'Core',
        'CurrentUser',
        'LeavePageWarning',
    ];

    function UsersSettingsController(
        $scope,
        $state,
        $modal,
        Permission,
        Member,
        Role,
        Company,
        selectedCompany,
        Core,
        CurrentUser,
        LeavePageWarning,
    ) {
        let user = CurrentUser.profile;
        $scope.currentUser = angular.copy(user);
        $scope.company = angular.copy(selectedCompany);

        $scope.billingInfo = {};
        $scope.userPricingPlan = null;
        $scope.members = [];
        $scope.roles = [];
        $scope.deleting = {};
        $scope.memberUsageOpts = {
            name: 'User',
            namePlural: 'Users',
        };
        $scope.pendingOnly = false;

        $scope.loading = 0;

        $scope.reload = reload;

        $scope.isPending = function (member) {
            if ($scope.pendingOnly) {
                return !member.user.registered;
            }

            return true;
        };

        $scope.inviteMemberModal = function () {
            LeavePageWarning.block();
            const modalInstance = $modal.open({
                templateUrl: 'user_management/views/invite-user.html',
                controller: 'InviteUserController',
                backdrop: 'static',
                keyboard: false,
                resolve: {
                    company: function () {
                        return $scope.company;
                    },
                    roles: function () {
                        return $scope.roles;
                    },
                },
            });

            modalInstance.result.then(
                function (_member) {
                    LeavePageWarning.unblock();
                    _member.name = _member.user.first_name + ' ' + _member.user.last_name;
                    $scope.members.push(_member);
                },
                function () {
                    LeavePageWarning.unblock();
                    // canceled
                },
            );
        };

        $scope.bulkInviteModal = function () {
            LeavePageWarning.block();
            const modalInstance = $modal.open({
                templateUrl: 'user_management/views/bulk-invite.html',
                controller: 'BulkInviteUserController',
                backdrop: 'static',
                keyboard: false,
                size: 'lg',
                resolve: {
                    company: function () {
                        return $scope.company;
                    },
                    roles: function () {
                        return $scope.roles;
                    },
                },
            });

            modalInstance.result.then(
                function () {
                    LeavePageWarning.unblock();
                    loadMembers();
                },
                function () {
                    LeavePageWarning.unblock();
                    // canceled
                },
            );
        };

        $scope.roleName = function (id) {
            let name = '';
            angular.forEach($scope.roles, function (role) {
                if (role.id == id) {
                    name = role.name;
                }
            });

            return name;
        };

        $scope.resendInvite = function (member) {
            $scope.sending = true;
            Member.resendInvite(
                {
                    id: member.id,
                },
                function () {
                    Core.flashMessage(
                        'The invitation for ' +
                            member.user.name +
                            ' was resent to ' +
                            member.user.email +
                            '. Please have them check their inbox and spam folder for the invite email.',
                        'success',
                    );
                    member.user.invite_resent = true;

                    $scope.sending = false;
                },
                function (result) {
                    $scope.sending = false;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        };

        $scope.editMember = function (member) {
            LeavePageWarning.block();

            const modalInstance = $modal.open({
                templateUrl: 'user_management/views/edit-user.html',
                controller: 'EditUserController',
                keyboard: false,
                backdrop: 'static',
                resolve: {
                    company: function () {
                        return $scope.company;
                    },
                    member: function () {
                        return member;
                    },
                    roles: function () {
                        return $scope.roles;
                    },
                },
            });

            modalInstance.result.then(
                function (_member) {
                    LeavePageWarning.unblock();
                    angular.extend(member, _member);
                },
                function () {
                    // canceled
                    LeavePageWarning.unblock();
                },
            );
        };

        $scope.removeMember = function (member) {
            let userId = member.user.id;
            if (userId === $scope.currentUser.id) {
                return;
            }

            vex.dialog.confirm({
                message: 'Are you sure you want to remove this member?',
                callback: function (result) {
                    if (result) {
                        $scope.deleting[userId] = true;

                        Member.delete(
                            {
                                id: member.id,
                            },
                            function () {
                                $scope.deleting[userId] = false;

                                // remove the member locally
                                for (let i in $scope.members) {
                                    if ($scope.members[i].user.id === userId) {
                                        $scope.members.splice(i, 1);
                                        break;
                                    }
                                }
                            },
                            function (result) {
                                $scope.deleting[userId] = false;
                                $scope.error = result.data;
                            },
                        );
                    }
                },
            });
        };

        //
        // Initialization
        //

        Core.setTitle('Users');
        if (Permission.hasPermission('settings.edit')) {
            loadBilling();
        }
        loadMembers();
        loadRoles();

        function loadBilling() {
            $scope.loading++;

            Company.billingInfo(
                {
                    id: $scope.company.id,
                },
                function (result) {
                    $scope.billingInfo = result;

                    // ind the user pricing plan
                    angular.forEach(result.usage_pricing_plans, function (usagePricingPlan) {
                        if (usagePricingPlan.type === 'user') {
                            $scope.userPricingPlan = usagePricingPlan;
                        }
                    });

                    $scope.loading--;
                },
                function () {
                    // Intentionally ignore errors when billing cannot be loaded
                    // to gracefully degrade the screen.
                    $scope.loading--;
                },
            );
        }

        function loadMembers() {
            $scope.loading++;

            Member.all(
                function (members) {
                    angular.forEach(members, function (member) {
                        member.name = member.user.first_name + ' ' + member.user.last_name;
                    });
                    $scope.members = members;

                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function loadRoles() {
            $scope.loading++;

            Role.findAll(
                { paginate: 'none' },
                function (roles) {
                    $scope.roles = roles;

                    $scope.loading--;
                },
                function (result) {
                    $scope.loading--;
                    Core.showMessage(result.data.message, 'error');
                },
            );
        }

        function reload() {
            $state.reload();
        }
    }
})();
