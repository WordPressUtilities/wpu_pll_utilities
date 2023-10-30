# WPU Pll Utilities

[![PHP workflow](https://github.com/WordPressUtilities/wpu_pll_utilities/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpu_pll_utilities/actions)
[![JS workflow](https://github.com/WordPressUtilities/wpu_pll_utilities/actions/workflows/js.yml/badge.svg 'JS workflow')](https://github.com/WordPressUtilities/wpu_pll_utilities/actions)

Utilities for Polylang

## Help

### Scan a folder for translations

```php
add_filter('wpupllutilities__folders_to_scan','example_wpupllutilities__folders_to_scan',10,1);
function example_wpupllutilities__folders_to_scan($folders){
    $folders[] = ABSPATH . '/wp-content/themes/mytheme';
    return $folders;
}
```

## About

* Big thanks to theme-translation-for-polylang.

## Roadmap

- [ ] Add optional auto-redirection to same page in correct language.
