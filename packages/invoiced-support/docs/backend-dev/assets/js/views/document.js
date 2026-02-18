/* globals FormData, InvoicedBillingPortal */
(function () {
    'use strict';

    $(function () {
        $('.save-to-invoiced').click(function () {
            $(this).toggleClass('active');
            $('#saveToInvoicedBanner').toggleClass('d-none');
        });

        $('#documentCommentForm').submit(function (e) {
            e.preventDefault();

            if (!$('#commentEmail').val()) {
                showError('Please provide your email address.');
                return;
            }

            if (!$('#commentBox').val()) {
                showError('The message box cannot be empty.');
                return;
            }

            clearMessages();
            $('#commentSend').attr('disabled', 'disabled').html('Sending...');

            $.ajax({
                method: 'POST',
                url: $(this).attr('action'),
                data: new FormData($(this)[0]),

                // Tell jQuery not to process data or worry about content-type
                // You *must* include these options!
                cache: false,
                contentType: false,
                processData: false,

                success: function (result) {
                    // render newly added comment
                    addComment(result);

                    // clear the input
                    $('#commentBox').val('');
                    $('#commentFile').val('');
                    $('.selected-file').addClass('hidden');
                    $('.select-file').removeClass('hidden');
                    $('#commentSend').removeAttr('disabled', 'disabled').html('Send');

                    showSuccess('Your message has been received. Thanks!');
                },
                error: function (result) {
                    $('#commentSend').removeAttr('disabled', 'disabled').html('Send');

                    if (result.responseJSON) {
                        showError(result.responseJSON.message);
                    } else {
                        showError('There was a problem submitting your message. :-(');
                    }
                },
            });
        });

        $('#commentFile').change(function () {
            var filename = document.getElementById('commentFile').files[0].name;
            $('#selectedCommentFile').html(filename);
            $('.selected-file').removeClass('hidden');
            $('.select-file').addClass('hidden');
        });

        $('.deselect-comment-file').click(function (e) {
            e.preventDefault();

            $('#commentFile').val('');
            $('.selected-file').addClass('hidden');
            $('.select-file').removeClass('hidden');

            return false;
        });

        $('.view-all-line-items').click(function (e) {
            e.preventDefault();

            $('.line-item').removeClass('hidden');
            $('.line-item-detail').removeClass('hidden');
            $(this).parents('tr.view-all').remove();

            return false;
        });

        var showModal = $('#commentsModal.show-on-load');
        if (showModal.length > 0) {
            showModal.modal('show');
            window.setTimeout(function () {
                scrollToCommentBottom();
            }, 500);
        }
    });

    function clearMessages() {
        $('#commentMessages').addClass('hidden');
    }

    function showSuccess(msg) {
        var html = '<p class="alert alert-success">' + msg + '</p>';
        $('#commentMessages').html(html).removeClass('hidden');
        scrollToCommentBottom();
    }

    function showError(msg) {
        var html = '<p class="alert alert-danger">' + msg + '</p>';
        $('#commentMessages').html(html).removeClass('hidden');
        scrollToCommentBottom();
    }

    function addComment(comment) {
        var fromClass = comment.from_customer ? 'me' : 'not_me';
        var text = InvoicedBillingPortal.util.nl2br(comment.text);
        var html = '<li class="' + fromClass + '">';
        html += '<div class="body">';
        html += '<div class="words">';
        html += text;
        html += '</div>';
        html += '</div>';

        if (comment.attachments.length > 0) {
            html += '<div class="file-attachments clearfix">';
            for (var i in comment.attachments) {
                if (comment.attachments.hasOwnProperty(i)) {
                    var attachment = comment.attachments[i];
                    html += '<div class="cell">';
                    html +=
                        '<a class="attachment" href="' +
                        attachment.file.url +
                        '" target="_blank" rel="noopener noreferrer" title="Download">';
                    html += '<div class="square">';
                    html += '<div class="icon">';
                    html += '<span class="fas fa-arrow-to-bottom"></span>';
                    html += '</div>';
                    html += attachment.file.name;
                    html += '<div class="size">';
                    html += attachment.size;
                    html += '</div></div></a></div>';
                }
            }
            html += '</div>';
        }

        html += '<div class="when">';
        html += comment.name + ' &middot; ' + comment.when;
        html += '</div>';
        html += '</li>';

        $('.comment-list').append(html);
        scrollToCommentBottom();
        $('.no-comments').addClass('hidden');
    }

    function scrollToCommentBottom() {
        $('.modal-body').animate({ scrollTop: $('.modal-body')[0].scrollHeight }, 300);
    }
})();
