<?php

add_action( 'template_redirect', 'politeia_handle_course_join' );
function politeia_handle_course_join() {
    if ( is_singular( 'sfwd-courses' )
         && ! empty( $_POST['course_join'] )
         && ! empty( $_POST['course_id'] )
    ) {
        $user_id   = get_current_user_id();
        $course_id = absint( $_POST['course_id'] );
        // Verificar nonce
        if ( ! wp_verify_nonce( $_POST['course_join'], 'course_join_' . $user_id . '_' . $course_id ) ) {
            return;
        }
        // Inscribir al usuario (usando la API de LearnDash)
        if ( function_exists( 'ld_update_course_access' ) ) {
            ld_update_course_access( $user_id, $course_id );
        } else {
            // Alternativa genérica:
            sfwd_lms()->learner()->add_course_access( $user_id, $course_id );
        }
        // Redirigir a la primera lección (o al resume_link que enviaste)
        $redirect = ! empty( $_POST['redirect_to'] )
                    ? esc_url_raw( $_POST['redirect_to'] )
                    : get_permalink( $course_id );
        wp_safe_redirect( $redirect );
        exit;
    }
}
