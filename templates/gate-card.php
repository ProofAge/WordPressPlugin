<?php

if (! defined('ABSPATH')) {
    exit;
}
?>
<div
    class="proofage-gate__card"
    role="dialog"
    aria-modal="true"
    aria-labelledby="proofage-gate-title"
>
    <h1 class="proofage-gate__title" id="proofage-gate-title"><?php echo esc_html($gateTitle); ?></h1>
    <p class="proofage-gate__description"><?php echo esc_html($gateDescription); ?></p>
    <button
        class="proofage-gate__button"
        type="button"
        autofocus
        data-proofage-start="1"
        data-proofage-return-url="<?php echo esc_url($currentUrl); ?>"
    >
        <?php echo esc_html($buttonLabel); ?>
    </button>
</div>
