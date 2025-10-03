<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="politeia-results-modal" class="politeia-results-modal" aria-hidden="true">
    <div class="politeia-results-modal__backdrop" data-close-modal></div>
    <div class="politeia-results-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="politeia-results-modal__title">
        <button type="button" class="politeia-results-modal__close" data-close-modal aria-label="<?php esc_attr_e( 'Close results', 'politeia-quiz-control' ); ?>">&times;</button>
        <div class="politeia-results-modal__body">
            <div class="politeia-results-modal__loading"><?php esc_html_e( 'Loading results…', 'politeia-quiz-control' ); ?></div>
        </div>
    </div>
</div>

<style>
.politeia-results-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.politeia-results-modal.is-open {
    display: flex;
}

.politeia-results-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
}

.politeia-results-modal__dialog {
    position: relative;
    z-index: 1;
    background: #fff;
    border-radius: 10px;
    padding: 24px;
    width: min(640px, 92vw);
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
}

.politeia-results-modal__close {
    position: absolute;
    top: 12px;
    right: 12px;
    border: none;
    background: transparent;
    font-size: 26px;
    line-height: 1;
    cursor: pointer;
    color: #444;
}

.politeia-results-modal__body {
    margin-top: 12px;
}

.politeia-results-modal__loading {
    text-align: center;
    padding: 30px 0;
    font-size: 16px;
    color: #444;
}

.politeia-results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 24px;
    margin-top: 18px;
}

.politeia-results-card {
    border: 1px solid #e2e2e2;
    border-radius: 8px;
    padding: 16px;
    background: #f9fafb;
    text-align: center;
}

.politeia-results-card strong {
    display: block;
    font-size: 18px;
    margin-bottom: 8px;
}

.politeia-results-card .politeia-results-score {
    font-size: 36px;
    font-weight: 700;
    color: #222;
}

.politeia-results-meta {
    margin-top: 12px;
    font-size: 14px;
    color: #555;
}

.politeia-results-messages {
    margin-top: 16px;
    padding: 12px 16px;
    border-radius: 6px;
    background: #fff7e6;
    color: #8a6d3b;
}
</style>
