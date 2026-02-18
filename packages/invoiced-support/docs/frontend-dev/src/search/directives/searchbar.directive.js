/* globals _, Mustache */
(function () {
    'use strict';

    angular.module('app.search').directive('searchbar', searchbar);

    searchbar.$inject = [
        '$state',
        '$translate',
        '$filter',
        '$modal',
        'Money',
        'selectedCompany',
        'Search',
        'ObjectDeepLink',
    ];

    function searchbar($state, $translate, $filter, $modal, Money, selectedCompany, Search, ObjectDeepLink) {
        return {
            restrict: 'A',
            template:
                '<div class="bar">' +
                '<div class="icon"><span class="fas fa-search"></span></div>' +
                '<input type="text" class="typeahead form-control" id="search-field" placeholder="Search for anything" spellcheck="false" />' +
                '<div class="loading"></div>' +
                '<div class="icon right search-help-icon"><a href="" ng-click="help()"><span class="fas fa-question-circle"></span></a></div>' +
                '</div>',
            link: function ($scope, element) {
                // use Mustache for templating (http://mustache.github.io/)
                let template =
                    '<div class="search-subscription">' +
                    '<div class="icon"><img src="/img/event-icons/{{icon}}.png" /></div>' +
                    '<div class="title">{{title}} <small>{{subTitle}}</small></div>' +
                    '<div class="identifier">{{id}}</div>' +
                    '</div>';

                // pre-parse mustache templates for better perf
                Mustache.parse(template);

                // build the sources
                let sources = [
                    {
                        source: _.debounce(function (query, render) {
                            Search.search(
                                {
                                    query: query,
                                    per_page: 5,
                                },
                                render,
                            );
                        }, 300),
                        name: 'all',
                        display: displayAutocomplete,
                        templates: {
                            suggestion: function (hit) {
                                let params = {
                                    icon: hit.object,
                                };

                                if (hit.object === 'contact') {
                                    params.title = hit.name;
                                    params.id = hit.customer.name;
                                } else if (hit.object === 'customer') {
                                    params.title = hit.name;
                                    params.id = hit.number;
                                } else if (
                                    hit.object === 'credit_note' ||
                                    hit.object === 'estimate' ||
                                    hit.object === 'invoice'
                                ) {
                                    let amount = Money.currencyFormat(
                                        hit.total,
                                        hit.currency,
                                        selectedCompany.moneyFormat,
                                        true,
                                    );

                                    params.title = hit.name;
                                    params.subTitle = hit.number;
                                    params.id = hit.customer.name + ' - ' + amount;
                                } else if (hit.object === 'payment') {
                                    params.title = Money.currencyFormat(
                                        hit.amount,
                                        hit.currency,
                                        selectedCompany.moneyFormat,
                                        true,
                                    );

                                    params.id = $translate.instant('payment_method.' + hit.method);
                                    if (hit.customer) {
                                        params.id += ' - ' + hit.customer.name;
                                    }
                                } else if (hit.object === 'subscription') {
                                    let total =
                                        Money.currencyFormat(
                                            hit.recurring_total,
                                            hit.plan.currency,
                                            selectedCompany.moneyFormat,
                                            true,
                                        ) +
                                        ' ' +
                                        $filter('recurringFrequency')(hit.plan.interval_count, hit.plan.interval);

                                    params.title = hit.customer.name;
                                    params.id = hit.plan.name + ' - ' + total;
                                } else if (hit.object === 'vendor') {
                                    params.title = hit.name;
                                    params.id = hit.number;
                                }

                                return Mustache.render(template, params);
                            },
                            empty: function () {
                                return '<div class="no-results"></div>';
                            },
                        },
                    },
                ];

                let typeaheadEl = $('.typeahead', element);

                // typeahead callbacks
                function displayAutocomplete() {
                    return typeaheadEl.typeahead('val');
                }

                // add a view more link to the last dataset
                sources[sources.length - 1].templates.footer = function () {
                    return '<div class="view-all"><a href="#">View All Results <span class="fas fa-caret-right"></span></a></div>';
                };

                // typeahead.js initialization
                // TODO support loading indicator
                // v0.11 of typeahead.js is supposed to support
                // events for network calls but it is not BC with
                // current version we are using
                typeaheadEl
                    .typeahead(
                        {
                            hint: false,
                            minLength: 2,
                        },
                        sources,
                    )
                    .on('typeahead:selected', function (e, hit, name) {
                        if (name === 'contacts' || hit.object === 'contact') {
                            ObjectDeepLink.goTo('contact', hit._customer);
                        } else if (name === 'credit_notes' || hit.object === 'credit_note') {
                            ObjectDeepLink.goTo('credit_note', hit.id);
                        } else if (name === 'customers' || hit.object === 'customer') {
                            ObjectDeepLink.goTo('customer', hit.id);
                        } else if (name === 'estimates' || hit.object === 'estimate') {
                            ObjectDeepLink.goTo('estimate', hit.id);
                        } else if (name === 'invoices' || hit.object === 'invoice') {
                            ObjectDeepLink.goTo('invoice', hit.id);
                        } else if (name === 'payments' || hit.object === 'payment') {
                            ObjectDeepLink.goTo('payment', hit.id);
                        } else if (name === 'subscriptions' || hit.object === 'subscription') {
                            ObjectDeepLink.goTo('subscription', hit.id);
                        } else if (name === 'vendors' || hit.object === 'vendor') {
                            ObjectDeepLink.goTo('vendor', hit.id);
                        }

                        window.snapper.close();
                    })
                    .on('focus', function () {
                        element.addClass('focus');
                    })
                    .on('blur', function () {
                        element.removeClass('focus');
                    })
                    .on('keypress', function (e) {
                        let query = $(this).typeahead('val');
                        if (query && e.keyCode == 13) {
                            goToSearch(query);
                        }
                    });

                $(element).delegate('.view-all', 'click', function (e) {
                    e.preventDefault();
                    let query = typeaheadEl.typeahead('val');
                    goToSearch(query);
                });

                $scope.help = function () {
                    $modal.open({
                        templateUrl: 'search/views/search-help.html',
                        controller: 'ModalController',
                    });
                };

                function goToSearch(query) {
                    typeaheadEl.blur().typeahead('val', '');
                    $state.go('manage.search', {
                        q: query,
                    });
                }
            },
        };
    }
})();
