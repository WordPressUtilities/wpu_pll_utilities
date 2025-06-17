<?php
defined('ABSPATH') || die;
/*
Plugin Name: WPU Pll Utilities
Plugin URI: https://github.com/WordPressUtilities/wpu_pll_utilities
Update URI: https://github.com/WordPressUtilities/wpu_pll_utilities
Description: Utilities for Polylang
Version: 1.5.1
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_pll_utilities
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

define('WPUPLLUTILITIES_VERSION', '1.5.1');

class WPUPllUtilities {
    private $api_endpoint_deepl = 'https://api-free.deepl.com';
    private $user_level = 'manage_options';
    private $excluded_folders = array(
        'node_modules',
        'gulp',
        'assets'
    );
    private $translated_strings = array();
    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_filter('wpu_options_boxes', array(&$this, 'wpu_options_boxes'));
        add_filter('wpu_options_fields', array(&$this, 'wpu_options_fields'));
        add_filter('wputh_translated_url', array(&$this, 'wputh_translated_url'), 50, 2);
        add_filter('pll_rel_hreflang_attributes', array(&$this, 'pll_rel_hreflang_attributes'), 10, 1);
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_wpuplltranslatestring', array(&$this, 'wpuplltranslatestring'));
        add_filter('wp_robots', array(&$this, 'wp_robots'), 90, 1);
    }

    public function plugins_loaded() {
        $this->user_level = apply_filters('wpupllutilities__user_level', $this->user_level);
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET['page']) || $_GET['page'] != 'mlang_strings') {
            return;
        }
        $this->excluded_folders = apply_filters('wpupllutilities__excluded_folders', $this->excluded_folders);
        $this->scan_folders();
        add_action('admin_footer', array(&$this, 'admin_footer_load_translation_js'));
    }

    /* ----------------------------------------------------------
      Admin assets
    ---------------------------------------------------------- */

    public function admin_enqueue_scripts() {
        if (!isset($_GET['page']) || $_GET['page'] != 'mlang_strings') {
            return;
        }
        $plugins_url = str_replace(ABSPATH, site_url() . '/', plugin_dir_path(__FILE__));
        wp_enqueue_style('wpu_pll_utilities-style-admin', $plugins_url . 'assets/admin-style.css', array(), WPUPLLUTILITIES_VERSION);
        wp_enqueue_script('wpu_pll_utilities-script-admin', $plugins_url . 'assets/admin-script.js', array(), WPUPLLUTILITIES_VERSION, true);
        wp_localize_script('wpu_pll_utilities-script-admin', 'wpu_pll_utilities_admin_obj', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'user_level_ok' => current_user_can($this->user_level),
            'str_translate' => __('Translate', 'wpu_pll_utilities'),
            'has_deepl' => defined('WPUPLLUTILITIES_DEEPL_API_KEY')
        ));
    }

    /* ----------------------------------------------------------
      Ajax translation
    ---------------------------------------------------------- */

    public function wpuplltranslatestring() {
        if (!current_user_can($this->user_level)) {
            wp_send_json_error();
        }
        if (!isset($_POST['string']) || !isset($_POST['lang'])) {
            wp_send_json_error();
        }
        $string = sanitize_text_field($_POST['string']);
        $lang = sanitize_text_field($_POST['lang']);

        $deepl_api_key = '';
        $polylang_option = get_option('polylang');
        $formality = 'default';
        if (is_array($polylang_option) && isset($polylang_option['machine_translation_enabled'], $polylang_option['machine_translation_services'], $polylang_option['machine_translation_services']['deepl'], $polylang_option['machine_translation_services']['deepl']['api_key']) && $polylang_option['machine_translation_enabled']) {
            $deepl_api_key = $polylang_option['machine_translation_services']['deepl']['api_key'];
            if (isset($polylang_option['machine_translation_services']['deepl']['formality'])) {
                $formality = $polylang_option['machine_translation_services']['deepl']['formality'];
            }
        }
        if (defined('WPUPLLUTILITIES_DEEPL_API_KEY') && WPUPLLUTILITIES_DEEPL_API_KEY) {
            $deepl_api_key = WPUPLLUTILITIES_DEEPL_API_KEY;
        }

        if ($deepl_api_key) {
            if (substr($deepl_api_key, -3) != ':fx') {
                $this->api_endpoint_deepl = 'https://api.deepl.com';
            }
            // Send a POST request to Deepl API
            $this->api_endpoint_deepl = apply_filters('wpupllutilities__deepl_api_endpoint_deepl', $this->api_endpoint_deepl);
            $response = wp_remote_post($this->api_endpoint_deepl . '/v2/translate', array(
                'body' => array(
                    'auth_key' => $deepl_api_key,
                    'text' => $string,
                    'target_lang' => $lang,
                    'formality' => $formality
                )
            ));
        } else {
            $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s';
            $response = wp_remote_get(sprintf($url, 'en', $lang, urlencode(remove_accents($string))));
        }

        // Check if the request was successful
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            wp_send_json_success(json_decode(wp_remote_retrieve_body($response), 1));
        } else {
            wp_send_json_error();
        }
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
            if (!$this->is_lang_disabled($code)) {
                $enabled_languages[$code] = $lang;
            }
        }
        return $enabled_languages;
    }

    /* ----------------------------------------------------------
      Hreflangs
    ---------------------------------------------------------- */

    public function pll_rel_hreflang_attributes($hreflangs) {
        $final_hreflangs = array();
        foreach ($hreflangs as $code => $url) {
            if (!$this->is_lang_disabled($code)) {
                $final_hreflangs[$code] = $url;
            }
        }
        return $final_hreflangs;
    }

    /* ----------------------------------------------------------
      Scan
    ---------------------------------------------------------- */

    public function scan_folders() {
        $folders_to_scan = apply_filters('wpupllutilities__folders_to_scan', array());
        foreach ($folders_to_scan as $folder) {
            $this->file_scanner($this->get_files_in_folder($folder));
        }
        $files_to_scan = apply_filters('wpupllutilities__files_to_scan', array());
        if ($files_to_scan) {
            $this->file_scanner($files_to_scan);
        }
    }

    public function get_files_in_folder($folder = false) {
        if (!is_dir($folder)) {
            return array();
        }

        $_files = $this->glob_recursive($folder, '*.php');
        $files = array();
        foreach ($_files as $file) {
            $key = $this->get_file_key($file);
            $files[$key] = $file;
        }
        return $files;
    }

    public function get_file_key($file) {
        $folder = dirname($file);
        $folder_key = basename($folder);
        $_f = str_replace($folder, '', $file);
        $_f = str_replace('/', '--', $_f);
        return $folder_key . '--' . str_replace('.php', '', $_f);
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
            if (!file_exists($file)) {
                continue;
            }
            if (is_numeric($f_id)) {
                $f_id = $this->get_file_key($file);
            }
            $_file_content = file_get_contents($file);

            // find wp functions: __(), _e()
            preg_match_all("/_[_e]\([\s]*['\"]([^'\"]*?)['\"][\s]*,?/s", $_file_content, $matches);
            if (!empty($matches[1])) {
                $master_strings[$f_id] = $matches[1];
            }
        }

        /* Register in PLL */
        foreach ($master_strings as $context => $strings) {
            foreach ($strings as $string) {
                $this->translated_strings[] = $string;
                pll_register_string($string, $string, $context, strlen($string) > 30);
            }
        }
    }

    /* Allow editor to access string translations */
    public function admin_menu() {
        if (!current_user_can($this->user_level) && function_exists('PLL')) {
            add_menu_page(__('Strings translations', 'polylang'), __('Languages', 'polylang'), 'edit_users', 'mlang_strings', array(PLL(), 'languages_page'), 'dashicons-translation');
        }
    }

    function admin_footer_load_translation_js() {
        if (!function_exists('pll_the_languages') || !function_exists('pll_translate_string')) {
            return;
        }

        $source_language = apply_filters('wpupllutilities__load_translation_source_language', 'en');
        $translation_key = apply_filters('wpupllutilities__load_translation_key', get_stylesheet());

        /* Get all languages */
        $languages = pll_the_languages(array(
            'raw' => 1
        ));

        /* Load all translations in each language and add it to a JS var */
        $translations_by_lang = array();
        foreach ($languages as $lang => $lang_data) {
            $translations_by_lang[$lang] = array();
            switch_to_locale(str_replace('-', '_', $lang_data['locale']));
            foreach ($this->translated_strings as $string) {
                $translations_by_lang[$lang][$string] = ($lang == $source_language) ? $string : __($string, $translation_key);
            }
            restore_previous_locale();
        }

        /* Add languages to JS */
        echo '<script>var wpu_pll_utilities_translations_by_lang = ' . json_encode($translations_by_lang) . ';</script>';

    }

    /* ----------------------------------------------------------
      Robots
    ---------------------------------------------------------- */

    public function wp_robots($opts = array()) {
        if (function_exists('pll_current_language') && $this->is_lang_disabled(pll_current_language())) {
            $opts['noindex'] = true;
        }

        return $opts;
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    /* Is a lang disabled
    -------------------------- */

    public function is_lang_disabled($lang) {
        return get_option('wpu_pll_utilities__hide__' . $lang);
    }

    /* Remove accents
    -------------------------- */

    public function remove_accents($str, $charset = 'utf-8') {
        $str = htmlentities($str, ENT_NOQUOTES, $charset);
        $str = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
        $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
        $str = preg_replace('#&[^;]+;#', '', $str);
        return $str;
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

require_once __DIR__ . '/inc/live-translation.php';

/* ----------------------------------------------------------
  Helper for lang selects
---------------------------------------------------------- */

add_action('wp_enqueue_scripts', function () {
    $plugins_url = str_replace(ABSPATH, site_url() . '/', plugin_dir_path(__FILE__));
    wp_enqueue_script('wpu_pll_utilities-script-front', $plugins_url . 'assets/script.js', array(), WPUPLLUTILITIES_VERSION, true);
    wp_localize_script('wpu_pll_utilities-script-front', 'wpu_pll_utilities_obj', apply_filters('wpu_pll_utilities_settings_front', array(
        'autoredirect_localstoragekey' => 'wpu_pll_auto_redirect',
        'autoredirect' => '1'
    )));
});

/* ----------------------------------------------------------
  Force x-default hreflang
---------------------------------------------------------- */

add_filter('pll_rel_hreflang_attributes', function ($hreflangs) {
    if (isset($hreflangs['x-default'])) {
        return $hreflangs;
    }
    $default_lang = pll_default_language();
    if (isset($hreflangs[$default_lang])) {
        $hreflangs['x-default'] = $hreflangs[$default_lang];
    }
    return $hreflangs;
}, 99, 1);
