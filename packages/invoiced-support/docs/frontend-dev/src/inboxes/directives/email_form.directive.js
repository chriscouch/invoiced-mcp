/* globals vex */
(function () {
    'use strict';

    angular.module('app.inboxes').directive('emailForm', emailForm);

    function emailForm() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/email-form.html',
            scope: {
                options: '=',
            },
            controller: [
                '$scope',
                '$translate',
                '$filter',
                'InvoicedConfig',
                'selectedCompany',
                'CurrentUser',
                function ($scope, $translate, $filter, InvoicedConfig, selectedCompany, CurrentUser) {
                    let escapeHtml = $filter('escapeHtml');

                    $scope.copyMe = function () {
                        for (let i in $scope.options.bcc) {
                            if ($scope.options.bcc[i].email_address === CurrentUser.profile.email) {
                                return;
                            }
                        }

                        $scope.options.bcc.push({
                            name: 'Me',
                            email_address: CurrentUser.profile.email,
                        });
                    };

                    $scope.$watch('options.to', formatEmailList);
                    $scope.$watch('options.cc', formatEmailList);
                    $scope.$watch('options.bcc', formatEmailList);

                    $scope.autocompleteOptions = {
                        ajax: {
                            url: InvoicedConfig.apiBaseUrl + '/autocomplete/emails?limit=3',
                            dataType: 'json',
                            params: {
                                headers: {
                                    Authorization: selectedCompany.auth_header,
                                },
                                xhrFields: {
                                    withCredentials: false,
                                },
                            },
                            data: function (term) {
                                return {
                                    term: term,
                                };
                            },
                            results: function (data) {
                                return {
                                    results: data,
                                };
                            },
                        },
                        tags: true,
                        minimumInputLength: 3,
                        formatInputTooShort: false,
                        width: '100%',
                        dropdownCssClass: 'email-recipient-dropdown',
                        createSearchChoice: function (term) {
                            let atIndex = term.indexOf('@');
                            if (atIndex === -1 || atIndex === term.length - 1) {
                                return null;
                            }

                            return {
                                email_address: term,
                                id: term,
                                name: '',
                            };
                        },
                        createSearchChoicePosition: 'top',
                        formatSelection: function (email) {
                            if (email.name) {
                                return (
                                    '<a href="#" class="is-tooltip" title="' +
                                    escapeHtml(email.email_address) +
                                    '">' +
                                    escapeHtml(email.name) +
                                    '</a>'
                                );
                            }

                            return escapeHtml(email.email_address);
                        },
                        formatResult: function (email) {
                            // creates a "Add search_term" result
                            if (email.id === email.email_address) {
                                return "<div class='create'>" + email.email_address + '</div>';
                            }

                            return formatSelect2(email);
                        },
                    };

                    function formatEmailList(options) {
                        for (let i in options) {
                            if (!options[i].text) {
                                options[i].text = formatSelect2(options[i]);
                            }
                        }
                    }

                    function formatSelect2(email) {
                        if (!email.name) {
                            return escapeHtml(email.email_address);
                        }

                        return (
                            '<div class="title">' +
                            escapeHtml(email.name) +
                            '</div>' +
                            '<div class="email">' +
                            escapeHtml(email.email_address) +
                            '</div>'
                        );
                    }

                    $scope.addedFiles = function (file) {
                        $scope.options.attachments.push(file);
                    };

                    $scope.deleteAttachment = function (attachment) {
                        vex.dialog.confirm({
                            message: 'Are you sure you want to remove this attachment?',
                            callback: function (result) {
                                if (result) {
                                    $scope.$apply(function () {
                                        deleteAttachment(attachment);
                                    });
                                }
                            },
                        });
                    };

                    function deleteAttachment(attachment) {
                        for (let i in $scope.options.attachments) {
                            if ($scope.options.attachments[i].id === attachment.id) {
                                $scope.options.attachments.splice(i, 1);
                                break;
                            }
                        }
                    }
                },
            ],
        };
    }
})();
