<?php
/*
Plugin Name: WPU Pll Utilities
Plugin URI: https://github.com/WordPressUtilities/wpu_pll_utilities
Description: Utilities for Polylang
Version: 0.5.3
Author: Darklg
Author URI: https://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUPllUtilities {
    private $excluded_folders = array(
        'node_modules',
        'gulp',
        'assets'
    );
    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_filter('wpu_options_boxes', array(&$this, 'wpu_options_boxes'));
        add_filter('wpu_options_fields', array(&$this, 'wpu_options_fields'));
        add_filter('wputh_translated_url', array(&$this, 'wputh_translated_url'), 50, 2);
    }

    public function plugins_loaded() {
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET['page']) || $_GET['page'] != 'mlang_strings') {
            return;
        }
        $this->excluded_folders = apply_filters('wpupllutilities__excluded_folders', $this->excluded_folders);
        $this->scan_folders();
    }

    /* ----------------------------------------------------------
      Options
    ---------------------------------------------------------- */

    /* Boxes */
    public function wpu_options_boxes($boxes) {
        $boxes['wpu_pll_utilities'] = array(
            'name' => 'WPU Pll Utilities'
        );
        return $boxes;
    }

    /* Fields */
    public function wpu_options_fields($options) {
        if (!function_exists('pll_languages_list')) {
            return $options;
        }
        $poly_langs = pll_languages_list();
        foreach ($poly_langs as $code) {
            $options['wpu_pll_utilities__hide__' . $code] = array(
                'label' => sprintf('Hide %s', $code),
                'box' => 'wpu_pll_utilities',
                'type' => 'checkbox'
            );
        }
        return $options;
    }

    /* ----------------------------------------------------------
      WPUTheme Hooks
    ---------------------------------------------------------- */

    public function wputh_translated_url($display_languages, $current_url) {
        $enabled_languages = array();
        foreach ($display_languages as $code => $lang) {
            $is_disabled = get_option('wpu_pll_utilities__hide__' . $code);
            if (!$is_disabled) {
                $enabled_languages[$code] = $lang;
            }
        }
        return $enabled_languages;
    }

    /* ----------------------------------------------------------
      Scan
    ---------------------------------------------------------- */

    public function scan_folders() {
        $folders_to_scan = apply_filters('wpupllutilities__folders_to_scan', array());
        foreach ($folders_to_scan as $folder) {
            $this->file_scanner($this->get_files_in_folder($folder));
        }
    }

    public function get_files_in_folder($folder = false) {
        if (!is_dir($folder)) {
            return array();
        }

        $folder_key = basename($folder);
        $_files = $this->glob_recursive($folder, '*.php');
        $files = array();
        foreach ($_files as $file) {
            $_f = str_replace($folder, '', $file);
            $_f = str_replace('/', '--', $_f);
            $key = $folder_key . '--' . str_replace('.php', '', $_f);
            $files[$key] = $file;
        }
        return $files;
    }

    /* Thanks to https://gist.github.com/UziTech/3b65b2543cee57cd6d2ecfcccf846f20 */
    public function glob_recursive($base, $pattern, $flags = 0) {
        if (substr($base, -1) !== DIRECTORY_SEPARATOR) {
            $base .= DIRECTORY_SEPARATOR;
        }

        $files = glob($base . $pattern, $flags);

        if ($files === false) {
            $files = array();
        }

        foreach (glob($base . '*', GLOB_ONLYDIR | GLOB_NOSORT | GLOB_MARK) as $dir) {
            $dirname = basename($dir);
            if (in_array($dirname, $this->excluded_folders)) {
                continue;
            }
            $dirFiles = $this->glob_recursive($dir, $pattern, $flags);
            if ($dirFiles !== false) {
                $files = array_merge($files, $dirFiles);
            }
        }

        return $files;
    }

    /* Thanks to TTfp */
    private function file_scanner($files) {
        $master_strings = array();
        foreach ($files as $f_id => $file) {
            $_file_content = file_get_contents($file);

            // find wp functions: __(), _e()
            preg_match_all("/[\s=\(\.]+_[_e][\s]*\([\s]*[\'\"](.*?)[\'\"][\s]*,[\s]*[\'\"](.*?)[\'\"][\s]*\)/s", $_file_content, $matches);
            if (!empty($matches[1])) {
                $master_strings[$f_id] = $matches[1];
            }
        }

        /* Register in PLL */
        foreach ($master_strings as $context => $strings) {
            foreach ($strings as $string) {
                pll_register_string($string, $string, $context, strlen($string) > 30);
            }
        }
    }

    /* Allow editor to access string translations */
    public function admin_menu() {
        if (!current_user_can('manage_options') && function_exists('PLL')) {
            add_menu_page(__('Strings translations', 'polylang'), __('Languages', 'polylang'), 'edit_users', 'mlang_strings', array(PLL(), 'languages_page'), 'dashicons-translation');
        }
    }

}

$WPUPllUtilities = new WPUPllUtilities();

/* ----------------------------------------------------------
  Hook on gettext
---------------------------------------------------------- */

/* Thanks to TTfp */
add_filter('gettext', 'wpupllutilities_gettext_filter', 1, 2);
function wpupllutilities_gettext_filter($original, $text) {
    $translations = get_translations_for_domain('pll_string');

    $tt = $translations->translate($text);

    if (empty($tt) || $tt === $text) {
        $translation = $translations->translate($original);
    } else {
        $translation = $translations->translate($text);
    }

    if (empty($translation)) {
        return $original;
    }

    return $translation;
}

/* ----------------------------------------------------------
  Helper
---------------------------------------------------------- */

function wpu_pll_utilities_get_languages() {
    $poly_langs = array();
    $polylang_opt = get_transient('pll_languages_list');
    if (!$polylang_opt) {
        return array();
    }
    if (function_exists('pll_the_languages')) {
        $poly_langs = pll_the_languages(array(
            'raw' => 1,
            'echo' => 0
        ));
    }
    return $poly_langs;
}

/* ----------------------------------------------------------
  Handle non multilingual post type archives
---------------------------------------------------------- */

/*
add_filter('wpu_pll_archives', function ($tax) {
    $tax[] = 'my_custom_post_type';
    return $tax;
}, 10, 1);
*/

/* Force lang slug on links
-------------------------- */

add_filter('post_type_archive_link', function ($link, $post_type) {
    $wpu_pll_archives = apply_filters('wpu_pll_archives', array());
    if (in_array($post_type, $wpu_pll_archives) && function_exists('pll_current_language') && function_exists('pll_default_language') && pll_default_language('slug') != pll_current_language()) {
        $link = str_replace(site_url(), site_url() . '/' . pll_current_language(), $link);
    }
    return $link;
}, 10, 2);

/* Force rewrite
-------------------------- */

add_action('init', function () {
    $wpu_pll_archives = apply_filters('wpu_pll_archives', array());
    $langs = wpu_pll_utilities_get_languages();
    $default_lang = '';
    if (function_exists('pll_default_language')) {
        $default_lang = pll_default_language('slug');
    }
    foreach ($wpu_pll_archives as $slug) {
        foreach ($langs as $i => $lang) {
            if ($lang['slug'] == $default_lang) {
                continue;
            }
            add_rewrite_rule($lang['slug'] . '/' . $slug . '[/]?$', 'index.php?lang=' . $lang['slug'] . '&post_type=' . $slug, 'top');
        }
    }
});

/* ----------------------------------------------------------
  Live translation
---------------------------------------------------------- */

include dirname(__FILE__) . '/inc/live-translation.php';

/* ----------------------------------------------------------
  Helper for lang selects
---------------------------------------------------------- */

add_action('wp_enqueue_scripts', function () {
    $plugins_url = str_replace(ABSPATH, site_url() . '/', plugin_dir_path(__FILE__));
    wp_enqueue_script('wpu_pll_utilities-script-front', $plugins_url . 'assets/script.js', array(), '0.1.0', true);
});
