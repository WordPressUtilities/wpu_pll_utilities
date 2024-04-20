jQuery(document).ready(function($) {
    'use strict';

    if (wpu_pll_utilities_admin_obj.has_deepl != '1') {
        return;
    }

    jQuery('.column-translations .translation').each(function(i, el) {
        var $el = jQuery(el),
            $inp = $el.find('[name*=translation]');

        /* Check if the input can be translated */
        if (!$inp || !$inp.attr('name')) {
            return;
        }
        var lang = $inp.attr('name').match(/\[(.*?)\]/)[1];
        if (!lang) {
            return;
        }

        /* Create button */
        var $btn = jQuery('<button>').text(wpu_pll_utilities_admin_obj.str_translate).attr('data-id', $el.data('id'));
        $btn.appendTo($el);

        /* Find translation */
        var $string = $inp.closest('tr').find('.column-string').clone();
        $string.find('a,button').remove();

        /* On click : call ajax action */
        $btn.on('click', function(e) {
            e.preventDefault();
            jQuery.post(ajaxurl, {
                action: 'wpuplltranslatestring',
                string: $string.text(),
                lang: lang
            }, function(response) {
                if (response.data.translations[0].text) {
                    $inp.val(response.data.translations[0].text);
                }
            });
        });
    });
});
