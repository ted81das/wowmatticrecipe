(function($) {
    'use strict';

    $(document).ready(function() {
        $('#generate-token').on('click', function() {
            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $tokensList = $('#aiomatic-tokens-list');
            var userId = $tokensList.data('user-id');

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: aiomaticBearerToken.ajaxurl,
                type: 'POST',
                data: {
                    action: 'generate_aiomatic_bearer_token',
                    user_id: userId,
                    nonce: aiomaticBearerToken.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.no-tokens').remove();
                        var tokenHtml = '<div class="token-item">' +
                            '<code>' + response.data.token + '</code>' +
                            '<button type="button" class="button button-small revoke-token" ' +
                            'data-token="' + response.data.token + '">Revoke</button>' +
                            '</div>';
                        $tokensList.append(tokenHtml);
                    } else {
                        alert(aiomaticBearerToken.generateError);
                    }
                },
                error: function() {
                    alert(aiomaticBearerToken.generateError);
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        });

        $(document).on('click', '.revoke-token', function() {
            var $button = $(this);
            var $tokenItem = $button.closest('.token-item');
            var token = $button.data('token');

            if (confirm(aiomaticBearerToken.confirmRevoke)) {
                $button.prop('disabled', true);

                $.ajax({
                    url: aiomaticBearerToken.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'revoke_aiomatic_bearer_token',
                        token: token,
                        nonce: aiomaticBearerToken.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $tokenItem.remove();
                            if ($('#aiomatic-tokens-list .token-item').length === 0) {
                                $('#aiomatic-tokens-list').append('<p class="no-tokens">' +
                                    'No active tokens</p>');
                            }
                        } else {
                            alert(aiomaticBearerToken.revokeError);
                            $button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert(aiomaticBearerToken.revokeError);
                        $button.prop('disabled', false);
                    }
                });
            }
        });
    });
})(jQuery);