<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('proofage_age_verification_settings');
