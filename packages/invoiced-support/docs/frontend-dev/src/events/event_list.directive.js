/* globals moment */
(function () {
    'use strict';

    angular.module('app.events').directive('eventList', eventList);

    eventList.$inject = ['Core'];

    function eventList(Core) {
        return {
            restrict: 'E',
            templateUrl: 'events/views/event-list.html',
            scope: {
                filter: '=?',
                options: '=?',
            },
            controller: [
                '$scope',
                'Event',
                'CurrentUser',
                'UiFilterService',
                function ($scope, Event, CurrentUser, UiFilterService) {
                    $scope.options = angular.extend(
                        {
                            perPage: 5,
                            relatedTo: false,
                            byDay: false,
                            loadMore: true,
                            filterFields: [],
                        },
                        $scope.options || {},
                    );

                    $scope.events = [];
                    $scope.matched = [];
                    $scope.buckets = [];
                    $scope.hasMore = false;
                    let page = 1;

                    $scope.$watch(
                        'filter',
                        function () {
                            page = 1;
                            loadData();
                        },
                        true,
                    );

                    $scope.nextPage = function () {
                        page++;
                        loadData();
                    };

                    loadData();

                    function loadData() {
                        if ($scope.loading) {
                            return;
                        }

                        // apply filtering
                        let filter = UiFilterService.getDefaultFilter(
                            $scope.options.filterFields,
                            $scope.options.perPage,
                        );
                        if ($scope.filter) {
                            filter = angular.extend(filter, $scope.filter);
                        }

                        let params = buildFindParams(filter);

                        params.paginate = 'none';
                        params.page = page;
                        params.per_page = $scope.options.perPage;

                        if ($scope.options.relatedTo) {
                            params.related_to = $scope.options.relatedTo;
                        }

                        $scope.loading = true;
                        Event.findAll(
                            params,
                            function (events) {
                                $scope.loading = false;
                                if (params.page == 1) {
                                    $scope.events = events;
                                } else {
                                    $scope.events = $scope.events.concat(events);
                                }

                                $scope.hasMore = $scope.options.loadMore && events.length >= params.per_page;
                                refreshEvents();
                            },
                            function (result) {
                                $scope.loading = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }

                    function refreshEvents() {
                        $scope.matched = [];
                        $scope.buckets = [];

                        let j = 0;
                        let first = true;
                        angular.forEach($scope.events, function (event) {
                            if ($scope.options.byDay) {
                                // figure out which bucket to add element to
                                let bucket = moment.unix(event.timestamp).format('ddd, MMM D, YYYY');

                                // create a new bucket if one does not exist
                                if (first || $scope.buckets[j] != bucket) {
                                    $scope.matched.push([]);
                                    $scope.buckets.push(bucket);

                                    if (!first) {
                                        j++;
                                    }

                                    first = false;
                                }

                                // show time of day when bucketed
                                event.time = moment.unix(event.timestamp).format('h:mm a');

                                $scope.matched[j].push(event);
                            } else {
                                // show time ago string, i.e. 5 minutes ago
                                event.time = moment.unix(event.timestamp).fromNow();

                                $scope.matched.push(event);
                            }
                        });
                    }

                    function buildFindParams(input) {
                        let params = {
                            filter: {},
                            advanced_filter: UiFilterService.serializeFilter(input, $scope.options.filterFields),
                        };

                        if (input.object_type && input.object_type.value) {
                            params.type = input.object_type.value;
                        }

                        if (input.from && input.from.value === 'me') {
                            params.filter.user_id = CurrentUser.profile.id;
                        } else if (input.from && input.from.value) {
                            params.from = input.from.value;
                        }

                        let findAllParams = {};
                        angular.forEach(params, function (value, key) {
                            // encode structured objects
                            if (typeof value === 'object') {
                                angular.forEach(value, function (value2, key2) {
                                    findAllParams[key + '[' + key2 + ']'] = value2;
                                });
                                // encode boolean values
                            } else if (typeof value === 'boolean') {
                                findAllParams[key] = value ? '1' : '0';
                            } else if (value instanceof Array) {
                                findAllParams[key] = value.join(',');
                                // otherwise value directly maps
                            } else {
                                findAllParams[key] = value;
                            }
                        });

                        return findAllParams;
                    }
                },
            ],
        };
    }
})();
