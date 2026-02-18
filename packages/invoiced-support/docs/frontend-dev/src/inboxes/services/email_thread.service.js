(function () {
    'use strict';

    angular.module('app.settings').factory('EmailThread', EmailThread);

    EmailThread.$inject = ['$resource', '$http', 'InvoicedConfig'];

    function EmailThread($resource, $http, InvoicedConfig) {
        let emailsCache = {};
        let notesCache = {};

        let url = InvoicedConfig.apiBaseUrl + '/threads';

        let Thread = $resource(
            InvoicedConfig.apiBaseUrl + '/inboxes/:inboxid/threads',
            {
                id: '@id',
                inboxid: '@inboxid',
                thread_id: '@thread_id',
            },
            {
                findAll: {
                    method: 'GET',
                    isArray: true,
                },
                get: {
                    url: url + '/:id',
                    method: 'GET',
                },
                edit: {
                    url: url + '/:id',
                    method: 'PATCH',
                },
                emails: {
                    url: url + '/:id/emails',
                    method: 'GET',
                    isArray: true,
                },
                relatedThread: {
                    url: url + '/document/:documentType/:documentId',
                    method: 'GET',
                },
                notes: {
                    url: url + '/:thread_id/notes',
                    method: 'GET',
                    isArray: true,
                },
                noteCreate: {
                    url: url + '/:thread_id/notes',
                    method: 'POST',
                    transformResponse: $http.defaults.transformResponse.concat(clearNotesCache),
                },
                noteUpdate: {
                    url: url + '/:thread_id/notes/:id',
                    method: 'PATCH',
                    transformResponse: $http.defaults.transformResponse.concat(clearNotesCache),
                },
                noteDelete: {
                    url: url + '/:thread_id/notes/:id',
                    method: 'DELETE',
                    transformResponse: $http.defaults.transformResponse.concat(clearNotesCache),
                },
            },
        );

        Thread.allEmails = function (id, success, error) {
            // These results are not memoized between function calls
            emailsCache[id] = [];
            loadEmailPage(id, 1, success, error);
        };

        Thread.allNotes = function (id, success, error) {
            // These results are not memoized between function calls
            notesCache[id] = [];
            loadNotesPage(id, 1, success, error);
        };

        return Thread;

        function loadEmailPage(id, page, success, error) {
            Thread.emails(
                {
                    id: id,
                    expand: 'sent_by,participants',
                },
                {
                    page: page,
                },
                function (emails, headers) {
                    emailsCache[id] = emailsCache[id].concat(emails);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > emailsCache[id].length;
                    if (hasMore) {
                        loadEmailPage(id, page + 1, success, error);
                    } else {
                        success(emailsCache[id]);
                    }
                },
                error,
            );
        }
        function loadNotesPage(id, page, success, error) {
            Thread.notes(
                {
                    thread_id: id,
                    page: page,
                    sort: 'id DESC',
                },
                function (notes, headers) {
                    if (notesCache[id] === undefined) {
                        notesCache[id] = [];
                    }
                    notesCache[id] = notesCache[id].concat(notes);

                    // is there another page?
                    let hasMore = headers('X-Total-Count') > notesCache[id].length;
                    if (hasMore) {
                        loadNotesPage(id, page + 1, success, error);
                    } else {
                        success(notesCache[id]);
                    }
                },
                error,
            );
        }

        function clearNotesCache(response) {
            // nuke the entire notes cache
            notesCache = {};

            return response;
        }
    }
})();
