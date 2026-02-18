(function () {
    'use strict';

    $('.cancel-subscription').click(function (e) {
        e.preventDefault();
        var url = $(this).data('url') + '/cancel';
        if (window.confirm($(this).data('confirm-msg'))) {
            $('#postForm').attr('action', url).submit();
        }
    });

    $('.payment-source-make-default').click(function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        $('#postForm').attr('action', url).submit();
    });

    $('.payment-source-remove').click(function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        if (window.confirm($(this).data('confirm-msg'))) {
            $('#postForm').attr('action', url).submit();
        }
    });
})();
