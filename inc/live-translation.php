<?php

/* ----------------------------------------------------------
  Helper for live translation
---------------------------------------------------------- */

function wpu_pll_utilities_can_use_helper_translate() {
    if (is_admin()) {
        return false;
    }
    if (!is_user_logged_in()) {
        return false;
    }
    if (!current_user_can('edit_pages')) {
        return false;
    }
    $gettext_domain = apply_filters('wpu_pll_utilities_helper_translate_domain', '');
    if (!$gettext_domain) {
        return false;
    }
    return true;
}

/* Menu bar item
-------------------------- */

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!wpu_pll_utilities_can_use_helper_translate()) {
        return;
    }

    global $wp;
    $target_url = home_url($wp->request);
    if (!isset($_GET['live_translation'])) {
        $target_url = add_query_arg('live_translation', '1', $target_url);
    }
    else {
        $target_url = remove_query_arg('live_translation',  $target_url);
    }

    $args = array(
        'id' => 'wpu-pll-utilities-live',
        'title' => '🌍 Live translate' . (isset($_GET['live_translation']) ? ' : ✅' : ''),
        'href' => $target_url
    );
    $wp_admin_bar->add_node($args);
}, 999);

/* Display translation
-------------------------- */

add_action('plugins_loaded', function () {
    if (!wpu_pll_utilities_can_use_helper_translate()) {
        return false;
    }
    if (!isset($_GET['live_translation'])) {
        return false;
    }
    $gettext_domain = apply_filters('wpu_pll_utilities_helper_translate_domain', '');
    add_filter('gettext_' . $gettext_domain, function ($translation, $text) {
        $admin_url = admin_url('admin.php?page=mlang_strings&s=' . urlencode($translation));
        $title = __('Strings translations', 'polylang');
        $styles = array(
            'cursor:help',
            'z-index:9999999',
            'font:inherit',
            'position:relative',
            'outline:2px dashed rgba(0,0,0,0.5)'
        );
        if ($translation == $text) {
            $styles[] = 'background-color:#FF0000';
        } else {
            $styles[] = 'background-color:#00FF00';
            $title .= ' : ok';
        }
        return '<i title="' . esc_attr($title) . '" onclick="window.open(\'' . $admin_url . '\');return false;" style="' . implode(';', $styles) . '">' . $translation . '</i>';
    }, 10, 2);
});
