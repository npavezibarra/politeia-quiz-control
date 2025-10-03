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
  
    if ( typeof $.fn.select2 === 'undefined' ) {
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
        placeholder: ( cfg.i18n?.placeholder || '— None —' ),
        language: {
          searching:   () => ( cfg.i18n?.searching   || 'Buscando…' ),
          noResults:   () => ( cfg.i18n?.noResults   || 'No se encontraron resultados' ),
          errorLoading:() => ( cfg.i18n?.noResults   || 'No se encontraron resultados' ),
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
      $('select.pqc-select2').each(function () {
        const $el = $(this);
        if ( !$el.data('select2') ) {
          $el.select2( getSelect2Options() );
        }
      });
    });

    $(function () {
      const $body = $('body');

      if ( !$body.hasClass('post-type-sfwd-courses') ) {
        return;
      }

      const $editor = $('#editor');
      const $classicEditor = $('#postdivrich');
      const $editorContainer = $editor.length ? $editor : $classicEditor;

      if ( !$editorContainer.length ) {
        return;
      }

      const $tabs = $('.learndash-tabs__tab, .ld-tabs__tab, [data-tab-target], [data-tab_target], [data-target]');

      if ( !$tabs.length ) {
        return;
      }

      const COURSE_PAGE_LABELS = [
        'página curso',
        'pagina curso',
        'course page',
        'course content',
        'página del curso',
      ];

      function isCoursePageTab( $tab ) {
        if ( !$tab || !$tab.length ) {
          return false;
        }

        const dataTarget = $tab.data('tabTarget')
          || $tab.data('tab-target')
          || $tab.data('tab_target')
          || $tab.attr('data-target')
          || '';

        if ( dataTarget && /course[-_]?page|ld-course-page|course[-_]?content/i.test( dataTarget ) ) {
          return true;
        }

        const label = $.trim( $tab.text() ).toLowerCase();

        return COURSE_PAGE_LABELS.includes( label );
      }

      function getActiveTab() {
        return $tabs.filter(function () {
          const $tab = $(this);
          return $tab.hasClass('is-active')
            || $tab.hasClass('learndash-tabs__tab--active')
            || $tab.hasClass('ld-tabs__tab--active')
            || $tab.attr('aria-selected') === 'true';
        }).first();
      }

      function toggleEditorVisibility() {
        const $active = getActiveTab();
        const isCoursePageActive = isCoursePageTab( $active );

        $body.toggleClass('pqc-course-page-tab-active', isCoursePageActive);
        $body.toggleClass('pqc-course-page-tab-hidden', ! isCoursePageActive );
      }

      toggleEditorVisibility();

      $tabs.on('click', function () {
        window.setTimeout( toggleEditorVisibility, 25 );
      });

      if ( 'MutationObserver' in window ) {
        const observer = new MutationObserver( toggleEditorVisibility );
        $tabs.each(function ( index, element ) {
          observer.observe( element, {
            attributes: true,
            attributeFilter: [ 'class', 'aria-selected' ],
          } );
        });
      }

      window.setTimeout( toggleEditorVisibility, 250 );
    });

  })(jQuery);
  
