jQuery(document).ready(function($) {
    console.log('✅ Quiz Select2 initialized');

    $('#politeia_first_quiz_select').select2({
        ajax: {
            url: PoliteiaQuizSelect.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'politeia_search_quizzes',
                    nonce: PoliteiaQuizSelect.nonce,
                    q: params.term
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: 'Search for a quiz...',
        minimumInputLength: 2,
        width: 'resolve'
    });
});
