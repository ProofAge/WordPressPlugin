<?php

if (! defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($gateTitle); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class('proofage-gate-screen proofage-display-mode-' . sanitize_html_class($displayMode)); ?>>
    <div class="proofage-gate proofage-gate--full-page">
        <?php include PROOFAGE_WP_PLUGIN_DIR . 'templates/gate-card.php'; ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
