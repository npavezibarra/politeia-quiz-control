<?php
/**
 * Archive template for LearnDash courses with metadata driven quiz CTAs.
 *
 * @package Politeia_Quiz_Control
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$user_id       = get_current_user_id();
$is_logged_in  = is_user_logged_in();
$has_polis_obj = class_exists( 'PoliteiaCourse' );
?>
<div id="primary" class="content-area">
    <main id="main" class="site-main pqc-course-archive" role="main">
        <?php if ( have_posts() ) : ?>
            <header class="page-header">
                <?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
                <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
            </header>

            <div class="pqc-course-archive__grid">
                <?php
                while ( have_posts() ) :
                    the_post();

                    $course_id   = get_the_ID();
                    $course_post = get_post( $course_id );

                    $course_entity = $has_polis_obj ? new PoliteiaCourse( $course_id ) : null;
                    $first_quiz_id = $course_entity ? $course_entity->getFirstQuizId() : (int) get_post_meta( $course_id, '_first_quiz_id', true );
                    $final_quiz_id = $course_entity ? $course_entity->getFinalQuizId() : (int) get_post_meta( $course_id, '_final_quiz_id', true );

                    $first_quiz_url = $first_quiz_id ? get_permalink( $first_quiz_id ) : '';
                    $final_quiz_url = $final_quiz_id ? get_permalink( $final_quiz_id ) : '';

                    $course_pricing = function_exists( 'learndash_get_course_price' )
                        ? learndash_get_course_price( $course_id )
                        : [];
                    $pricing_type   = isset( $course_pricing['type'] ) ? $course_pricing['type'] : 'free';
                    $is_paid_course = in_array( $pricing_type, [ 'paynow', 'closed', 'subscribe' ], true );

                    $related_product_id = $course_entity ? $course_entity->getRelatedProductId() : 0;
                    $product_url        = $related_product_id ? get_permalink( $related_product_id ) : '';

                    $has_course_on_hold     = false;
                    $has_completed_purchase = false;

                    if ( $user_id && $related_product_id && class_exists( 'PoliteiaOrderFinder' ) ) {
                        $order_finder = new PoliteiaOrderFinder();
                        $order_id     = $order_finder->findOrderForUser( $user_id, $related_product_id );

                        if ( $order_id ) {
                            $order = wc_get_order( $order_id );
                            if ( $order ) {
                                if ( $order->has_status( 'course-on-hold' ) ) {
                                    $has_course_on_hold = true;
                                }
                                if ( $order->has_status( [ 'completed', 'processing' ] ) ) {
                                    $has_completed_purchase = true;
                                }
                            }
                        }
                    }

                    $is_enrolled = $user_id && function_exists( 'sfwd_lms_has_access' )
                        ? sfwd_lms_has_access( $course_id, $user_id )
                        : false;

                    $progress = ( $user_id && function_exists( 'learndash_course_progress' ) )
                        ? learndash_course_progress(
                            [
                                'course_id' => $course_id,
                                'user_id'   => $user_id,
                                'array'     => true,
                            ]
                        )
                        : [ 'percentage' => 0 ];

                    $progress_percentage   = isset( $progress['percentage'] ) ? (int) $progress['percentage'] : 0;
                    $all_lessons_completed = $progress_percentage >= 100;

                    $has_purchased_course = $is_paid_course ? $has_completed_purchase : $is_enrolled;
                    $can_take_final_quiz  = $final_quiz_id && $has_purchased_course && $all_lessons_completed;
                    ?>

                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'pqc-course-archive__item' ); ?>>
                        <header class="entry-header">
                            <?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
                        </header>

                        <div class="entry-summary">
                            <?php the_excerpt(); ?>
                        </div>

                        <div class="pqc-course-archive__tests">
                            <?php if ( $first_quiz_id ) : ?>
                                <?php if ( ! $is_logged_in ) : ?>
                                    <a id="first-test-button"
                                       class="pqc-course-archive__button pqc-course-archive__button--primary"
                                       href="<?php echo esc_url( wp_login_url( $first_quiz_url ) ); ?>">
                                        <?php esc_html_e( 'Take First Quiz', 'politeia-quiz-control' ); ?>
                                    </a>
                                <?php else : ?>
                                    <a id="first-test-button"
                                       class="pqc-course-archive__button pqc-course-archive__button--primary"
                                       href="<?php echo esc_url( $first_quiz_url ); ?>">
                                        <?php esc_html_e( 'Take First Quiz', 'politeia-quiz-control' ); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ( $final_quiz_id && $is_logged_in ) : ?>
                                <?php if ( $can_take_final_quiz ) : ?>
                                    <a class="pqc-course-archive__button pqc-course-archive__button--primary"
                                       href="<?php echo esc_url( $final_quiz_url ); ?>">
                                        <?php esc_html_e( 'Take Final Quiz', 'politeia-quiz-control' ); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="pqc-course-archive__button pqc-course-archive__button--disabled">
                                        <?php esc_html_e( 'Take Final Quiz', 'politeia-quiz-control' ); ?>
                                    </span>
                                    <p class="pqc-course-archive__message">
                                        <?php
                                        if ( ! $has_purchased_course ) {
                                            esc_html_e( 'Purchase the course first', 'politeia-quiz-control' );
                                        } elseif ( ! $all_lessons_completed ) {
                                            esc_html_e( 'Complete all lessons to unlock the Final Quiz', 'politeia-quiz-control' );
                                        } else {
                                            esc_html_e( 'Final Quiz locked', 'politeia-quiz-control' );
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="pqc-course-archive__cta">
                            <?php if ( ! $is_logged_in ) : ?>
                                <a class="pqc-course-archive__button pqc-course-archive__button--secondary"
                                   href="<?php echo esc_url( wp_login_url( get_permalink( $course_id ) ) ); ?>">
                                    <?php esc_html_e( 'Log in to enroll', 'politeia-quiz-control' ); ?>
                                </a>
                            <?php elseif ( $is_enrolled ) : ?>
                                <a class="pqc-course-archive__button pqc-course-archive__button--secondary"
                                   href="<?php echo esc_url( get_permalink( $course_id ) ); ?>">
                                    <?php esc_html_e( 'View Course', 'politeia-quiz-control' ); ?>
                                </a>
                            <?php elseif ( $product_url && ! $has_course_on_hold ) : ?>
                                <a class="pqc-course-archive__button pqc-course-archive__button--secondary"
                                   href="<?php echo esc_url( $product_url ); ?>">
                                    <?php esc_html_e( 'Buy Course', 'politeia-quiz-control' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>

                    </article>
                    <?php
                endwhile;
                ?>
            </div>

            <?php the_posts_pagination(); ?>
        <?php else : ?>
            <p class="pqc-course-archive__empty">
                <?php esc_html_e( 'No courses found.', 'politeia-quiz-control' ); ?>
            </p>
        <?php endif; ?>
    </main>
</div>
<?php
get_footer();
