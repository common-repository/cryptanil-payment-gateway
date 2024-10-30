<?php

add_action('init', 'cpg_cryptanil_redirectCryptanil', 9);

function cpg_cryptanil_redirectCryptanil()
{
    if (isset($_REQUEST['action']) && sanitize_text_field( wp_unslash($_REQUEST['action'])) == 'redirect_cryptanil_form' ) {
        wp_redirect(sanitize_text_field( wp_unslash($_REQUEST['redirect_url'])));
        die;
    }
}
