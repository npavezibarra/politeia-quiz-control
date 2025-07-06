<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Carga la clase de stats (asegúrate de que la ruta sea correcta).
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-politeia-quiz-stats.php';

/* =============================================================================
 * 1) REDIRECT BUTTON "START COURSE" → INSCRIPTION + REDIRECT TO LESSON
 * ============================================================================= */
add_action( 'template_redirect', 'politeia_handle_course_join' );
function politeia_handle_course_join() {
    if ( is_singular( 'sfwd-courses' )
         && ! empty( $_POST['course_join'] )
         && ! empty( $_POST['course_id'] )
    ) {
        $user_id   = get_current_user_id();
        $course_id = absint( $_POST['course_id'] );

        // Nonce check
        if ( ! wp_verify_nonce( $_POST['course_join'], 'course_join_' . $user_id . '_' . $course_id ) ) {
            return;
        }

        // Inscribir al usuario
        if ( function_exists( 'ld_update_course_access' ) ) {
            ld_update_course_access( $user_id, $course_id );
        } else {
            sfwd_lms()->learner()->add_course_access( $user_id, $course_id );
        }

        // Redirigir al primer lesson o resume_link
        $redirect = ! empty( $_POST['redirect_to'] )
                    ? esc_url_raw( $_POST['redirect_to'] )
                    : get_permalink( $course_id );

        wp_safe_redirect( $redirect );
        exit;
    }
}

