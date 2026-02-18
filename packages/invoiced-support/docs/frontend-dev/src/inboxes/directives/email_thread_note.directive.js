/* globals vex */
(function () {
    'use strict';
    angular.module('app.inboxes').directive('emailThreadNote', emailThreadNote);

    function emailThreadNote() {
        return {
            restrict: 'E',
            templateUrl: 'inboxes/views/note.html',
            scope: {
                note: '=',
                thread: '=',
                deleteCallback: '&',
            },
            controller: [
                '$scope',
                'EmailThread',
                'Core',
                function ($scope, EmailThread, Core) {
                    $scope.avatarOptions = {
                        height: 35,
                        width: 35,
                    };

                    $scope.edit = function (note) {
                        $scope.editing = true;
                        if (!$scope.editNote) {
                            $scope.editNote = note.note;
                        }
                    };

                    $scope.cancelEdit = function () {
                        $scope.editing = false;
                    };

                    $scope.saveEdit = function (contents) {
                        $scope.saving = true;
                        EmailThread.noteUpdate(
                            {
                                thread_id: $scope.thread.id,
                                id: $scope.note.id,
                            },
                            {
                                note: contents,
                            },
                            function (_note) {
                                $scope.saving = false;
                                $scope.editing = false;
                                $scope.note.note = _note.note;
                            },
                            function (result) {
                                $scope.saving = false;
                                Core.showMessage(result.data.message, 'error');
                            },
                        );
                    };

                    $scope.delete = function (note) {
                        vex.dialog.confirm({
                            message: 'Are you sure you want to delete this note?',
                            callback: function (result) {
                                if (result) {
                                    $scope.deleting = true;

                                    EmailThread.noteDelete(
                                        {
                                            id: note.id,
                                            thread_id: $scope.thread.id,
                                        },
                                        function () {
                                            $scope.deleting = false;
                                            $scope.deleteCallback({ note: note });
                                        },
                                        function (result) {
                                            $scope.deleting = false;
                                            Core.showMessage(result.data.message, 'error');
                                        },
                                    );
                                }
                            },
                        });
                    };
                },
            ],
        };
    }
})();
