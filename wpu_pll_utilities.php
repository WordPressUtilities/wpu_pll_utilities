<?php
/*
Plugin Name: WPU Pll Utilities
Plugin URI: https://github.com/WordPressUtilities/wpu_pll_utilities
Description: Utilities for Polylang
Version: 0.1.2
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
                pll_register_string($string, $string, $context);
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
