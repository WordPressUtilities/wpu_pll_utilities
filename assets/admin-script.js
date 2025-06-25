jQuery(document).ready(function() {
    'use strict';

    if (wpu_pll_utilities_admin_obj.user_level_ok != '1') {
        return;
    }

    jQuery('.column-translations .translation').each(function(i, el) {
        var $el = jQuery(el),
            $tr = $el.closest('tr'),
            _string_name = $tr.find('.column-name').text().trim(),
            $inp = $el.find('[name*=translation]');

        /* Check if the input can be translated */
        if (!$inp || !$inp.attr('name')) {
            return;
        }
        var lang = $inp.attr('name').match(/\[(.*?)\]/)[1];
        if (!lang) {
            return;
        }

        var _icon_class = 'dashicons-translation';
        var _icon_class_loading = 'dashicons-cloud-upload';

        /* Create button */
        var $btn = jQuery('<button>')
            .addClass('wpu-pll-translate-btn')
            .html('<span class="dashicons dashicons-translation"></span>')
            .attr('title', wpu_pll_utilities_admin_obj.str_translate)
            .attr('data-id', $el.data('id'));
        $btn.appendTo($el);

        /* Find translation */
        var $string = $inp.closest('tr').find('.column-string').clone();
        $string.find('a,button').remove();

        /* If input value equals original string and a translation exists, clear value and set placeholder */
        var originalString = $string.text().trim();
        var translationsByLang = window.wpu_pll_utilities_translations_by_lang || {};
        var _inputVal = $inp.val().trim();
        var _hasTranslation = translationsByLang[lang] && translationsByLang[lang][originalString];
        if (
            (_inputVal === originalString || _inputVal === '') &&
            _hasTranslation
        ) {
            $inp.val('');
        }
        if (_hasTranslation) {
            $inp.attr('placeholder', translationsByLang[lang][originalString]);
        }

        /* On click : call ajax action */
        var $icon = $btn.find('.dashicons');
        $btn.on('click', function(e) {
            $btn.addClass('is-loading');
            $inp.addClass('is-loading');
            $icon.removeClass(_icon_class).addClass(_icon_class_loading);
            e.preventDefault();
            jQuery.post(wpu_pll_utilities_admin_obj.ajaxurl, {
                action: 'wpuplltranslatestring',
                string: $string.text(),
                lang: lang
            }, function(response) {
                $icon.removeClass(_icon_class_loading).addClass(_icon_class);
                $btn.removeClass('is-loading');
                $inp.removeClass('is-loading');
                var _val = '';

                if (response.data.translations && response.data.translations[0].text) {
                    _val =response.data.translations[0].text;
                } else if (response.data[0] && response.data[0][0] && response.data[0][0][0]) {
                    _val =response.data[0][0][0];
                }

                if(_val) {
                    if (_string_name.indexOf('slug') !== -1){
                        _val = wpu_pll_sanitize_string(_val);
                    }
                    $inp.val(_val);
                }
            });
        });
    });
});

function wpu_pll_sanitize_string(str) {
    'use strict';
    if (typeof str !== 'string') {
        return '';
    }
    str = str.toLowerCase().trim();
    str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return str.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}
