(function () {
    'use strict';

    $(function () {
        var checkAllBox = $('th.checkbox-column :checkbox');
        var paySelectedBtn = $('.pay-selected');

        // toggle checkbox when clicking on row
        $('tbody > tr').click(function (event) {
            var tgt = $(event.target);
            if (tgt.is(':checkbox') || tgt.is('a') || tgt.is('button') || tgt.attr('for')) {
                return;
            }

            $(this)
                .find('input:checkbox')
                .each(function () {
                    $(this).prop('checked', !$(this).is(':checked'));
                });

            updateCheckAll();
            updateSelectedCount();
        });

        // update check all/uncheck all when single row is clicked
        $('td.checkbox-column :checkbox').change(function () {
            updateCheckAll();
            updateSelectedCount();
        });

        // check all/uncheck all
        checkAllBox.change(function () {
            var isChecked = $(this).is(':checked');
            $('td.checkbox-column input:checkbox:not(#col-checkbox-advance)').prop('checked', isChecked);
            updateSelectedCount();
        });

        $('.clear-selection').click(function (e) {
            $('td.checkbox-column input:checkbox').prop('checked', false);
            updateCheckAll();
            updateSelectedCount();
            e.preventDefault();
            return false;
        });

        updateSelectedCount();
        updateCheckAll();

        function updateCheckAll() {
            var numUnchecked = $('td.checkbox-column :checkbox:not(:checked)').length;
            if (numUnchecked === 0) {
                checkAllBox.prop('checked', true);
            } else {
                checkAllBox.prop('checked', false);
            }
        }

        function updateSelectedCount() {
            var numChecked = $('td.checkbox-column :checkbox:checked').length;
            $('.num-checkbox-rows-selected').text(numChecked);
            if (numChecked > 0) {
                $('.selected').removeClass('hidden');
                $('.filter').addClass('hidden');
            } else {
                $('.selected').addClass('hidden');
                $('.filter').removeClass('hidden');
            }

            var numPayable = $('input[data-payable=1]:checkbox:checked').length;
            if (numPayable > 0) {
                paySelectedBtn.removeAttr('disabled');
            } else {
                paySelectedBtn.attr('disabled', 'disabled');
            }
        }
    });
})();
