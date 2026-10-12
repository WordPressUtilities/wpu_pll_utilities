<?php
defined('ABSPATH') || die;

/* ----------------------------------------------------------
  Translated alt for attachments
  Only when Polylang media translation is disabled.
---------------------------------------------------------- */

/* Hooks are registered late so themes can enable the feature */
add_action('after_setup_theme', function () {
    if (!apply_filters('wpupllutilities__media_alt_enabled', false)) {
        return;
    }
    if (!function_exists('pll_languages_list')) {
        return;
    }
    $pll_options = get_option('polylang');
    if (!empty($pll_options['media_support'])) {
        return;
    }
    add_filter('attachment_fields_to_edit', 'wpu_pll_utilities_media_alt_fields_to_edit', 10, 2);
    add_filter('attachment_fields_to_save', 'wpu_pll_utilities_media_alt_fields_to_save', 10, 2);
    add_filter('get_post_metadata', 'wpu_pll_utilities_media_alt_get_post_metadata', 10, 4);
});

function wpu_pll_utilities_media_alt_get_languages() {
    $slugs = pll_languages_list(array('fields' => 'slug'));
    $names = pll_languages_list(array('fields' => 'name'));
    return array_combine($slugs, $names);
}

/* Admin fields
-------------------------- */

function wpu_pll_utilities_media_alt_fields_to_edit($form_fields, $post) {
    if (!wp_attachment_is_image($post->ID)) {
        return $form_fields;
    }
    $native_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
    foreach (wpu_pll_utilities_media_alt_get_languages() as $slug => $name) {
        $key = 'wpu_pll_alt_' . $slug;
        $form_fields[$key] = array(
            'label' => sprintf(__('Alt Text (%s)', 'wpu_pll_utilities'), $name),
            'input' => 'html',
            'html' => sprintf(
                '<textarea id="attachments-%1$d-%2$s" name="attachments[%1$d][%2$s]" placeholder="%4$s" rows="2" style="width:100%%">%3$s</textarea>',
                $post->ID,
                esc_attr($key),
                esc_textarea(get_post_meta($post->ID, '_' . $key, true)),
                esc_attr($native_alt)
            )
        );
    }
    return $form_fields;
}

function wpu_pll_utilities_media_alt_fields_to_save($post, $attachment) {
    foreach (wpu_pll_utilities_media_alt_get_languages() as $slug => $name) {
        $key = 'wpu_pll_alt_' . $slug;
        if (!isset($attachment[$key])) {
            continue;
        }
        $value = sanitize_text_field(wp_unslash($attachment[$key]));
        if ($value === '') {
            delete_post_meta($post['ID'], '_' . $key);
        } else {
            update_post_meta($post['ID'], '_' . $key, wp_slash($value));
        }
    }
    return $post;
}

/* Front : serve the alt of the current language
-------------------------- */

function wpu_pll_utilities_media_alt_get_post_metadata($value, $object_id, $meta_key, $single) {
    if ($meta_key !== '_wp_attachment_image_alt') {
        return $value;
    }
    /* Never in admin, AJAX or REST : the native alt would be overwritten on save */
    $is_front = !is_admin() && !(defined('REST_REQUEST') && REST_REQUEST);
    if (!apply_filters('wpupllutilities__media_alt_is_front', $is_front, $object_id)) {
        return $value;
    }
    $lang = pll_current_language();
    if (!$lang) {
        return $value;
    }
    $alt = get_post_meta($object_id, '_wpu_pll_alt_' . $lang, true);
    if ($alt === '') {
        return $value;
    }
    return $single ? $alt : array($alt);
}
