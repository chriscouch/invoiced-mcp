(function () {
    'use strict';

    angular.module('app.user_management').factory('Member', MemberService);

    MemberService.$inject = [
        '$resource',
        '$q',
        'localStorageService',
        'InvoicedConfig',
        'selectedCompany',
        'CurrentUser',
        '$translate',
        'Role',
        'HeapAnalytics',
    ];

    function MemberService(
        $resource,
        $q,
        localStorageService,
        InvoicedConfig,
        selectedCompany,
        CurrentUser,
        $translate,
        Role,
        HeapAnalytics,
    ) {
        let MemberCache = {};
        let currentMemberCache = {};

        let Member = $resource(
            InvoicedConfig.apiBaseUrl + '/members/:id',
            {
                id: '@id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                    params: {
                        per_page: 100,
                    },
                },
                find: {
                    method: 'GET',
                },
                create: {
                    method: 'POST',
                },
                edit: {
                    method: 'PATCH',
                },
                delete: {
                    method: 'DELETE',
                },
                resendInvite: {
                    method: 'POST',
                    url: InvoicedConfig.apiBaseUrl + '/members/:id/invites',
                },
                setUpdateFrequency: {
                    method: 'PATCH',
                    url: InvoicedConfig.apiBaseUrl + '/members/:id/frequency',
                },
            },
        );

        Member.all = function (success, error) {
            if (MemberCache[selectedCompany.id] === undefined) {
                //promise is needed to handle multiply concurrent requests
                MemberCache[selectedCompany.id] = $q(function (resolve, reject) {
                    let perPage = 100;
                    Member.findAll(
                        {
                            page: 1,
                            per_page: perPage,
                        },
                        function (data, headers) {
                            let total = headers('X-Total-Count');
                            let page = 2;
                            if (total > data.length) {
                                let promises = [];
                                let pages = Math.ceil(total / perPage);
                                for (page = 2; page <= pages; ++page) {
                                    promises.push($q(pagePromise));
                                }

                                $q.all(promises).then(function resolveValues(values) {
                                    for (let i = 0; i < values.length; ++i) {
                                        data = data.concat(values[i]);
                                    }
                                    resolve(data);
                                });
                            } else {
                                resolve(data);
                            }
                            function pagePromise(resolve2, reject2) {
                                Member.findAll(
                                    {
                                        page: page,
                                        per_page: perPage,
                                        paginate: 'none',
                                    },
                                    resolve2,
                                    reject2,
                                );
                            }
                        },
                        reject,
                    );
                });
            }
            MemberCache[selectedCompany.id].then(success, error);
        };

        Member.dropDownList = function (success, error, hasPermission) {
            let rolePromise = $q(function (resolve, reject) {
                if (!hasPermission) {
                    resolve(null);
                    return;
                }
                Role.all(
                    { paginate: 'none' },
                    function (roles) {
                        let roleIds = [];
                        angular.forEach(roles, function (role) {
                            if (role[hasPermission]) {
                                roleIds.push(role.id);
                            }
                        });
                        resolve(roleIds);
                    },
                    reject,
                );
            });
            Member.all(function (members) {
                rolePromise.then(function (roles) {
                    let users = [
                        {
                            id: -1,
                            name: $translate.instant('settings.members.no_one'),
                        },
                        {
                            id: CurrentUser.profile.id,
                            name: CurrentUser.profile.first_name + ' ' + CurrentUser.profile.last_name,
                        },
                    ];
                    angular.forEach(members, function (member) {
                        if (
                            member.user.id !== CurrentUser.profile.id &&
                            (!hasPermission || roles.indexOf(member.role) !== -1)
                        ) {
                            users.push({
                                name: member.user.first_name + ' ' + member.user.last_name,
                                id: member.user.id,
                            });
                        }
                    });
                    success(users);
                }, error);
            }, error);
        };

        Member.current = function (success, error) {
            if (typeof currentMemberCache[selectedCompany.id] !== 'undefined') {
                success(currentMemberCache[selectedCompany.id]);
                return;
            }
            Member.findAll(
                {
                    'filter[user_id]': CurrentUser.profile.id,
                    paginate: 'none',
                },
                function (members) {
                    if (members.length !== 1) {
                        success(null);
                        return;
                    }
                    // pass analytics to Heap
                    HeapAnalytics.selectMember(selectedCompany, CurrentUser.profile, members[0]);
                    currentMemberCache[selectedCompany.id] = members[0];
                    localStorageService.set(
                        'last_notification_viewed',
                        currentMemberCache[selectedCompany.id].notification_viewed,
                    );
                    success(currentMemberCache[selectedCompany.id]);
                },
                error,
            );
        };

        Member.clearCache = clearCache;
        Member.clearCurrentCache = clearCurrentCache;

        return Member;

        function clearCurrentCache() {
            if (typeof currentMemberCache[selectedCompany.id] !== 'undefined') {
                delete currentMemberCache[selectedCompany.id];
            }
        }

        function clearCache(response) {
            if (typeof MemberCache[selectedCompany.id] !== 'undefined') {
                delete MemberCache[selectedCompany.id];
            }

            return response;
        }
    }
})();
