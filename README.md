# WPU Pll Utilities

Utilities for Polylang

## Scan a folder for translations

```php
add_filter('wpupllutilities__folders_to_scan','example_wpupllutilities__folders_to_scan',10,1);
function example_wpupllutilities__folders_to_scan($folders){
    $folders[] = ABSPATH . '/wp-content/themes/mytheme';
    return $folders;
}
```

## About

* Big thanks to theme-translation-for-polylang.
