<?php

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/* ----------------------------------------------------------
  Add lang
---------------------------------------------------------- */

WP_CLI::add_command('wpupll add_lang', function ($args) {
    $locale = $args[0];
    $slug = $args[1];
    $name = $args[2];

    if (!function_exists('pll_the_languages')) {
        WP_CLI::error('Polylang is not active.');
    }

    if (!class_exists('PLL_Model')) {
        WP_CLI::error('Polylang model not found.');
    }

    $locales = pll_languages_list(array('fields' => 'locale'));
    if (in_array($locale, $locales)) {
        WP_CLI::error('Locale already exists.');
    }

    $slugs = pll_languages_list(array('fields' => 'slug'));
    if (in_array($slug, $slugs)) {
        WP_CLI::error('Slug already exists.');
    }

    $args = array(
        'term_group' => 0,
        'slug' => $slug,
        'flag' => $slug,
        'locale' => $locale,
        'name' => $name
    );

    $result = PLL()->model->add_language($args);

    if ($result) {
        WP_CLI::success("Language '{$name}' ({$locale}) added.");
    } else {
        WP_CLI::error('Failed to add language.');
    }
}, array(
    'shortdesc' => 'Add a new language to Polylang.',
    'synopsis' => array(
        array(
            'type' => 'positional',
            'name' => 'locale',
            'description' => 'The locale code of the language (e.g., en_US).',
            'required' => true,
        ),
        array(
            'type' => 'positional',
            'name' => 'slug',
            'description' => 'The slug for the language (e.g., en).',
            'required' => true,
        ),
        array(
            'type' => 'positional',
            'name' => 'name',
            'description' => 'The name of the language (e.g., English).',
            'required' => true,
        ),
    ),

));
