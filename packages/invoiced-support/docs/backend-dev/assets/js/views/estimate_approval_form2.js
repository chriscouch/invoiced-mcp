/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    InvoicedBillingPortal.bootstrap(run);

    function run() {
        var sending = false;

        $('#approvedCheckbox')
            .change(function () {
                if ($(this).is(':checked')) {
                    $('.initials-holder').removeClass('hidden');
                } else {
                    $('.initials-holder').addClass('hidden');
                }
            })
            .change();

        $('#approvalForm').submit(function (e) {
            if (sending) {
                return;
            }

            e.preventDefault();

            sending = true;
            sendForm(
                function () {
                    sending = false;
                },
                function () {
                    sending = false;
                }
            );
        });

        function sendForm(resolve, reject) {
            // NOTE: not using promises here because they
            // are not supported in IE
            var url = $('#approvalForm').attr('action');
            $.ajax({
                method: 'POST',
                url: url,
                data: $('#approvalForm').serialize(),
                headers: {
                    Accept: 'application/json',
                },
            })
                .then(function (data) {
                    window.location.href = data.url;
                    resolve();
                })
                .fail(function (data) {
                    // show the error message
                    var message;
                    try {
                        message = JSON.parse(data.responseText).error;
                    } catch (err) {
                        message = 'An unknown error has occurred';
                    }
                    InvoicedBillingPortal.util.showError(message, 'estimate-approval-errors');
                    reject();
                });
        }
    }
})();
