(function () {
    'use strict';

    angular.module('app.accounts_receivable').directive('creditNoteStatus', creditNoteStatus);

    function creditNoteStatus() {
        return {
            restrict: 'E',
            template:
                '<a href="" class="status-label" tooltip="The credit note has not been added to the customer\'s account yet." ng-if="creditNote.status==\'draft\'"><span class="label label-default">Draft</span></a>' +
                '<a href="" class="status-label" tooltip="The credit note is outstanding." ng-if="creditNote.status==\'open\'"><span class="label label-primary">Open</span></a>' +
                '<a href="" class="status-label" tooltip="The credit note was closed before it was paid in full." ng-if="creditNote.status==\'closed\'"><span class="label label-danger">Closed</span></a>' +
                '<a href="" class="status-label" tooltip="The credit note was voided." ng-if="creditNote.status==\'voided\'"><span class="label label-danger">Voided</span></a>' +
                '<a href="" class="status-label" tooltip="Paid in full." ng-if="creditNote.status==\'paid\'"><span class="label label-success">Paid</span></a>',
            scope: {
                creditNote: '=',
            },
        };
    }
})();
