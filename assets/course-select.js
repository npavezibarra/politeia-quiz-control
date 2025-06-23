jQuery(document).ready(function($) {
    console.log('✅ Select2 initialized');
    console.log(PoliteiaCourseSelect);
    $('#linked_course_id').select2({
        ajax: {
            url: PoliteiaCourseSelect.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'politeia_search_courses',
                    nonce: PoliteiaCourseSelect.nonce,
                    q: params.term
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: 'Search for a course...',
        minimumInputLength: 2,
        width: 'resolve'
    });
});
