(function () {
    'use strict';

    $(function () {
        $('a.source-description').click(function (e) {
            $(this).toggleClass('is-open');
            $($(this).data('target')).toggleClass('is-open');
            e.preventDefault();
        });
    });
})();
