(function () {
    'use strict';
    angular.module('app.inboxes').directive('emailThread', emailThread);

    function emailThread() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/email-thread.html',
            scope: {
                thread: '=',
                withHeader: '=?',
                threadOptions: '=?',
                prefillReply: '=?',
            },
            controller: [
                '$scope',
                '$modal',
                '$state',
                '$timeout',
                'Core',
                'EmailThread',
                'Invoice',
                'Estimate',
                'CreditNote',
                'InboxEmail',
                'TextMessage',
                'Letter',
                'Inbox',
                'Member',
                'CurrentUser',
                'selectedCompany',
                'LeavePageWarning',
                'ObjectDeepLink',
                'Bill',
                'VendorCredit',
                function (
                    $scope,
                    $modal,
                    $state,
                    $timeout,
                    Core,
                    EmailThread,
                    Invoice,
                    Estimate,
                    CreditNote,
                    InboxEmail,
                    TextMessage,
                    Letter,
                    Inbox,
                    Member,
                    CurrentUser,
                    selectedCompany,
                    LeavePageWarning,
                    ObjectDeepLink,
                    Bill,
                    VendorCredit,
                ) {
                    $scope.loading = 0;
                    $scope.currentUser = CurrentUser;
                    $scope.users = [];
                    $scope.company = selectedCompany;
                    $scope.withHeader = $scope.withHeader || false;
                    $scope.inbox = null;
                    $scope.notes = [];
                    $scope.emails = [];
                    $scope.threadItems = [];
                    $scope.doc = null;
                    $scope.sendReply = true;
                    $scope.texts = [];
                    $scope.letters = [];
                    $scope.newNote = {
                        note: '',
                    };
                    $scope.threadItemsChunk = 10;
                    $scope.threadItemsToShow = $scope.threadItemsChunk;
                    $scope.newReply = {};

                    if (!angular.isDefined($scope.threadOptions)) {
                        $scope.threadOptions = {
                            documentType: null,
                            documentId: null,
                            includeTexts: false,
                            includeLetters: false,
                        };
                    }

                    let initializedNewReply = false;
                    let initializedNewNote = false;

                    Member.dropDownList(
                        function (members) {
                            $scope.users = members;
                            if ($scope.notes.length > 0) {
                                $scope.notes = $scope.notes.map(mapNote);
                                createThreadItems();
                            }
                        },
                        function (result) {
                            Core.showMessage(result.data.message, 'error');
                        },
                        'emails_send',
                    );

                    $scope.changeStatus = function (thread, status) {
                        if (thread.status === status) {
                            return;
                        }

                        EmailThread.edit(
                            {
                                id: thread.id,
                            },
                            {
                                status: status,
                            },
                            function () {
                                thread.status = status;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    };

                    $scope.changeOwner = function (thread, userId) {
                        if (thread.assignee === null) {
                            if (userId === -1) {
                                return;
                            }
                        } else if (thread.assignee.id === userId) {
                            return;
                        }

                        EmailThread.edit(
                            {
                                id: thread.id,
                                expand: 'assignee',
                            },
                            {
                                assignee_id: userId === -1 ? null : userId,
                            },
                            function (result) {
                                thread.assignee = result.assignee;
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    };

                    $scope.goToObject = function (type, id) {
                        ObjectDeepLink.goTo(type, id);
                    };

                    $scope.$on('refreshEmailThread', loadData);
                    $scope.$watch('prefillReply', function (prefill) {
                        angular.extend($scope.newReply, generateNewReplyFromPrefill(prefill));
                    });

                    $scope.send = function (newReply) {
                        $scope.saving = true;
                        $scope.error = null;

                        let params = angular.copy(newReply);
                        params.related_to_type = $scope.thread.object_type;
                        params.related_to_id = $scope.thread.related_to_id;

                        Inbox.send(
                            {
                                id: $scope.thread.inbox_id,
                            },
                            params,
                            function (result) {
                                result.sent_by = angular.copy(CurrentUser.profile);
                                $scope.saving = false;
                                loadEmailData(result);
                                $scope.emails.push(result);
                                createThreadItems();
                                $scope.newReply.attachments = [];
                                $scope.newReply.message = '';
                                $scope.emailForm.$setPristine();
                            },
                            function (result) {
                                $scope.saving = false;
                                errorHandler(result);
                            },
                        );
                    };

                    $scope.addNote = function (note) {
                        EmailThread.noteCreate(
                            {
                                thread_id: $scope.thread.id,
                            },
                            {
                                note: note.note,
                            },
                            function (_note) {
                                $scope.saving = false;
                                Core.flashMessage('Your note has been added', 'success');
                                $scope.newNote.note = '';
                                $scope.notes.push(mapNote(_note));
                                createThreadItems();
                                $scope.notesForm.$setPristine();
                            },
                            function (result) {
                                $scope.saving = false;
                                $scope.error = result.data;
                            },
                        );
                    };

                    $scope.deleteNote = function (note) {
                        for (let i in $scope.notes) {
                            if ($scope.notes[i].id === note.id) {
                                $scope.notes.splice(i, 1);
                                break;
                            }
                        }

                        createThreadItems();
                    };

                    $scope.editThread = function (thread) {
                        const modalInstance = $modal.open({
                            templateUrl: 'inboxes/views/edit-thread.html',
                            controller: 'EditThreadController',
                            backdrop: 'static',
                            keyboard: false,
                            resolve: {
                                thread: function () {
                                    return thread;
                                },
                            },
                        });

                        modalInstance.result.then(
                            function (_thread) {
                                $scope.$emit('onThreadUpdate', _thread);
                                angular.extend(thread, _thread);
                            },
                            function () {
                                // canceled
                            },
                        );
                    };

                    $scope.shouldDisplayThreadItem = function (index) {
                        return (
                            index >= $scope.threadItems.length - $scope.threadItemsToShow &&
                            index < $scope.threadItems.length
                        );
                    };

                    $scope.loadMore = loadMore;

                    function mapNote(note) {
                        return mapToUser(note, 'user_id');
                    }

                    function mapToUser(data, userIdProp) {
                        for (let i in $scope.users) {
                            if ($scope.users[i].id === data[userIdProp]) {
                                data.user = $scope.users[i];
                                break;
                            }
                        }
                        return data;
                    }

                    function isCompany(email) {
                        // Check if the email address matches the company email address or the inbox email address
                        return (
                            ($scope.inbox && email.email_address === $scope.inbox.email) ||
                            email.email_address === selectedCompany.email
                        );
                    }

                    function loadVisibleEmailData() {
                        for (
                            let i = $scope.threadItems.length - 1;
                            i >= $scope.threadItems.length - $scope.threadItemsToShow && i >= 0;
                            i--
                        ) {
                            let item = $scope.threadItems[i];
                            if (item.type === 'email' && !item.email.visible) {
                                item.email.visible = true;
                                loadEmailData(item.email);
                            }
                        }
                    }

                    function loadEmailData(email) {
                        InboxEmail.getEmailAttachments(email);
                        InboxEmail.getEmailMessage(email);
                    }

                    function loadData(event, thread) {
                        // It is possible for this directive to be used if there
                        // is no thread created yet, like on the invoice messages page.
                        // This is an extra layer of protection in case loadData is called
                        // for a non-existent thread. Ideally this would not be called in the
                        // first place if the thread did not have an ID.
                        if (thread.id && thread.inbox_id) {
                            loadInbox(thread.inbox_id, thread.id);

                            if ($scope.withHeader && thread.object_type && thread.related_to_id) {
                                loadRelatedDocument(thread.object_type, thread.related_to_id);
                            }
                        }

                        // Texts and letters do not require a thread
                        // to exist.
                        if ($scope.threadOptions.includeTexts) {
                            loadTexts();
                        }

                        if ($scope.threadOptions.includeLetters) {
                            loadLetters();
                        }
                    }

                    function loadInbox(inboxId, threadId) {
                        Inbox.find(
                            { id: inboxId },
                            function (_inbox) {
                                $scope.inbox = _inbox;
                                loadNotes(threadId);
                                loadEmails(threadId);
                            },
                            function (result) {
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    }

                    function loadTexts() {
                        $scope.loading++;

                        TextMessage.relatedTexts(
                            {
                                documentType: $scope.threadOptions.documentType,
                                documentId: $scope.threadOptions.documentId,
                                expand: 'sent_by',
                            },
                            function (texts) {
                                $scope.loading--;
                                $scope.texts = texts.map(function (text) {
                                    return mapToUser(text, 'sent_by_id');
                                });
                                createThreadItems();
                            },
                            function (result) {
                                errorHandler(result);
                                $scope.loading--;
                            },
                        );
                    }

                    function loadLetters() {
                        $scope.loading++;

                        Letter.relatedLetters(
                            {
                                include: 'detail',
                                expand: 'sent_by',
                                documentType: $scope.threadOptions.documentType,
                                documentId: $scope.threadOptions.documentId,
                            },
                            function (letters) {
                                $scope.loading--;
                                $scope.letters = letters.map(function (letter) {
                                    return mapToUser(letter, 'sent_by_id');
                                });
                                createThreadItems();
                            },
                            function (result) {
                                errorHandler(result);
                                $scope.loading--;
                            },
                        );
                    }

                    function loadNotes(threadId) {
                        $scope.loading++;

                        EmailThread.allNotes(
                            threadId,
                            function (notes) {
                                $scope.loading--;
                                $scope.notes = notes.map(mapNote);
                                createThreadItems();

                                if (!initializedNewNote) {
                                    LeavePageWarning.watchForm($scope, 'notesForm');
                                    initializedNewNote = true;
                                }
                            },
                            function (result) {
                                errorHandler(result);
                                $scope.loading--;
                            },
                        );
                    }

                    function loadMore() {
                        $scope.threadItemsToShow += $scope.threadItemsChunk;
                        loadVisibleEmailData();
                    }

                    function loadEmails(threadId) {
                        $scope.loading++;

                        EmailThread.allEmails(
                            threadId,
                            function (emails) {
                                $scope.loading--;

                                $scope.emails = emails;
                                createThreadItems();

                                // Prefill the reply form using the information from
                                // the latest email in the chain.
                                if (emails.length > 0 && !initializedNewReply) {
                                    angular.extend(
                                        $scope.newReply,
                                        generateNewReplyFromEmail(emails[emails.length - 1]),
                                    );
                                    initializedNewReply = true;
                                    $timeout(function () {
                                        if ($scope.emailForm) {
                                            $scope.emailForm.$setPristine();
                                        }
                                        LeavePageWarning.watchForm($scope, 'emailForm');
                                    }, 100);
                                }
                            },
                            function (result) {
                                errorHandler(result);
                                $scope.loading--;
                            },
                        );
                    }

                    function loadRelatedDocument(documentType, documentId) {
                        let provider = null;
                        switch (documentType) {
                            case 'invoice':
                                provider = Invoice;
                                break;
                            case 'estimate':
                                provider = Estimate;
                                break;
                            case 'credit_note':
                                provider = CreditNote;
                                break;
                            case 'bill':
                                provider = Bill;
                                break;
                            case 'vendor_credit':
                                provider = VendorCredit;
                                break;
                            default:
                                return;
                        }

                        provider.find(
                            {
                                id: documentId,
                                exclude: 'items,discounts,taxes,shipping,ship_to,metadata',
                            },
                            function (result) {
                                $scope.doc = result.number;
                            },
                            function (response) {
                                if (response.status !== 404) {
                                    errorHandler(response);
                                }
                            },
                        );
                    }

                    function errorHandler(result) {
                        $scope.loading = false;
                        Core.showMessage(result.data.message, 'error');
                    }

                    function createThreadItems() {
                        // build the list of thread items
                        $scope.threadItems = [];
                        angular.forEach($scope.emails, function (email) {
                            $scope.threadItems.push({
                                type: 'email',
                                id: email.id,
                                timestamp: email.date,
                                email: email,
                            });
                        });
                        angular.forEach($scope.notes, function (note) {
                            $scope.threadItems.push({
                                type: 'note',
                                id: note.id,
                                timestamp: note.created_at,
                                note: note,
                            });
                        });
                        angular.forEach($scope.texts, function (text) {
                            $scope.threadItems.push({
                                type: 'text',
                                id: text.id,
                                timestamp: text.created_at,
                                text: text,
                            });
                        });
                        angular.forEach($scope.letters, function (letter) {
                            $scope.threadItems.push({
                                type: 'letter',
                                id: letter.id,
                                timestamp: letter.created_at,
                                letter: letter,
                            });
                        });

                        // sort by timestamp
                        $scope.threadItems.sort(function (a, b) {
                            if (a.timestamp === b.timestamp) {
                                return 0;
                            }

                            return a.timestamp > b.timestamp ? 1 : -1;
                        });

                        loadVisibleEmailData();
                    }

                    function generateNewReplyFromPrefill(prefill) {
                        return prefill && typeof prefill === 'object' ? angular.copy(prefill) : {};
                    }

                    function generateNewReplyFromEmail(email) {
                        let params = {
                            thread_id: email.thread_id,
                            cc: [],
                            bcc: email.bcc,
                            subject: email.subject,
                            message: '',
                            reply_to_id: email.id,
                            attachments: [],
                        };

                        angular.forEach(email.cc, function (recipient) {
                            if (!isCompany(recipient)) {
                                params.cc.push(recipient);
                            }
                        });

                        // The value of the To: field depends on whether
                        // the email is incoming or outgoing.
                        // If the email is an incoming email then combine
                        // the From: and To: fields
                        // If the email is an outgoing email then use
                        // the previous To: field
                        if (email.incoming) {
                            params.to = [angular.copy(email.from)];
                            angular.forEach(email.to, function (recipient) {
                                if (!isCompany(recipient)) {
                                    params.to.push(recipient);
                                }
                            });
                        } else {
                            params.to = angular.copy(email.to);
                        }

                        return params;
                    }
                },
            ],
        };
    }
})();
