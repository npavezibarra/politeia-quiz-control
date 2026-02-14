// assets/admin.js – Inicializa Select2 en los metaboxes "First Quiz" y "Final Quiz".
// Requiere jQuery, Select2 y el objeto global `pqcData` inyectado desde PHP:
// pqcData = {
//   restUrl : '.../wp-json/politeia-quiz-control/v1/quiz-search',
//   nonce   : 'XXXXXXXX',
//   i18n    : { searching: 'Buscando…', noResults: 'No se encontraron resultados' }
// };

(function ($) {
  'use strict';

  // Configuración pasada desde PHP.
  const cfg = window.pqcData || {};

  if (typeof $.fn.select2 === 'undefined') {
    // Select2 no está cargado: salimos silenciosamente para evitar errores JS.
    return;
  }

  /**
   * Opciones comunes de Select2 para nuestros <select>.
   * @returns {Object}
   */
  function getSelect2Options() {
    return {
      width: '100%',
      minimumInputLength: 1,
      allowClear: true,
      placeholder: '—Ninguno—',
      language: {
        searching: () => (cfg.i18n?.searching || 'Buscando…'),
        noResults: () => (cfg.i18n?.noResults || 'No se encontraron resultados'),
        errorLoading: () => (cfg.i18n?.noResults || 'No se encontraron resultados'),
      },
      ajax: {
        url: cfg.restUrl,
        dataType: 'json',
        delay: 300,
        headers: { 'X-WP-Nonce': cfg.nonce },
        data: params => ({
          term: params.term || '',
          page: params.page || 1,
        }),
        processResults: (data, params) => ({
          results: data.results || [],
          pagination: { more: !!data.more }
        }),
        cache: true,
      },
      escapeMarkup: markup => markup, // permite HTML seguro en los <option> si fuese necesario.
    };
  }

  $(function () {
    // Inicializamos todos los <select> con clase .pqc-select2
    $('select#_first_quiz_id, select#_final_quiz_id, select[name="pqctl_evaluation_quiz_id"]').each(function () {
      const $el = $(this);
      if (!$el.data('select2')) {
        $el.select2(getSelect2Options());
      }
    });
  });

})(jQuery);
