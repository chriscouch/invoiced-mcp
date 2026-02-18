/* globals InvoicedBillingPortal */
(function () {
    'use strict';

    $(function () {
        enableDateField('startDate');
        enableDateField('endDate');

        $('.pay-selected').click(function () {
            let doSubmit = false;
            let form = '<form action="/pay" method="post">';
            $('td.checkbox-column input:checkbox').each(function () {
                const $this = $(this);
                if ($this.is(':checked') && $this.data('payable') === 1) {
                    doSubmit = true;
                    form += '<input type="hidden"  name="invoices[]" value="' + $this.data('id') + '" />';
                }
            });
            form += '</form>';

            if (doSubmit) {
                const formHTML = $(form);
                $('body').append(formHTML);
                formHTML.submit();
            }
        });

        function enableDateField(fieldId) {
            const date = $('#' + fieldId);
            const altId = fieldId + 'Alt';
            if (!date.length) {
                return;
            }

            const initialValue = $('#' + altId).val();
            date.datepicker({
                altField: '#' + altId,
                altFormat: 'yy-mm-dd',
            });
            const currentLanguage = InvoicedBillingPortal.util.getCurrentLanguage();
            if (typeof $.datepicker.regional[currentLanguage.code] !== 'undefined') {
                date.datepicker('option', $.datepicker.regional[currentLanguage.code]);
            }

            // Set initial date value
            if (initialValue) {
                date.datepicker('setDate', new Date(initialValue + 'T00:00:00'));
            }
        }
    });
})();
