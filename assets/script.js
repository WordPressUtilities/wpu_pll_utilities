/* ----------------------------------------------------------
  Cookies
---------------------------------------------------------- */

function wpu_pll_set_cookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (parseInt(exdays, 10) * 24 * 60 * 60 * 1000));
    var expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

/* ----------------------------------------------------------
  Better language select
---------------------------------------------------------- */

window.addEventListener("DOMContentLoaded", function() {
    [].forEach.call(document.querySelectorAll('.wpu-pll-lang'), function(el, i) {
        el.addEventListener('change', function(e) {
            var $item = e.target.options[e.target.selectedIndex];
            wpu_pll_set_cookie('pll_language', $item.getAttribute('data-lang'), 1);
            window.location.href = this.value;
        }, false);
    });
});

/* ----------------------------------------------------------
  Auto-redirect based on lang
---------------------------------------------------------- */

(function() {

    if (!localStorage) {
        return;
    }
    var localstoragekey = 'wpu_pll_auto_redirect';

    /* Already redirected */
    var _auto_lang = localStorage.getItem(localstoragekey);
    if (_auto_lang) {
        return;
    }

    /* Extract current lang */
    var $main_wrapper = document.querySelector('html[lang]');
    if (!$main_wrapper) {
        return;
    }
    var _current_lang = $main_wrapper.getAttribute('lang').toLowerCase().replace('-', '_');
    if (!_current_lang) {
        return;
    }
    var _current_lang_parts = _current_lang.split('_');
    var _current_lang_short = _current_lang_parts[0];

    /* Extract browser lang */
    var _browser_lang = navigator.languages != undefined ? navigator.languages[0] : navigator.language;
    if (!_browser_lang) {
        return;
    }
    _browser_lang = _browser_lang.toLowerCase().replace('-', '_');
    var _browser_lang_parts = _browser_lang.split('_');
    var _browser_lang_short = _browser_lang_parts[0];

    /* Extract alternate langs */
    var _nav_langs = document.querySelectorAll('link[hreflang][href]'),
        _langs = {};
    if (_nav_langs.length <= 1) {
        return;
    }
    for (var i = 0, len = _nav_langs.length; i < len; i++) {
        _langs[_nav_langs[i].hreflang.toLowerCase().replace('-', '_')] = _nav_langs[i].href;
    }

    /* Default URL is the shortest URL */
    var _default_lang = Object.values(_langs).reduce(function(a, b) {
        return a.length <= b.length ? a : b
    });

    var _final_url = '',
        _final_lang = '';

    /* Select the same lang if it matches */
    if (_langs[_browser_lang] != undefined) {
        _final_lang = _browser_lang;
        _final_url = _langs[_browser_lang];
    }
    /* Select the same short lang if it matches */
    else if (_langs[_browser_lang_short] != undefined) {
        _final_lang = _browser_lang_short;
        _final_url = _langs[_browser_lang_short];
    }
    /* Select the default lang */
    else {
        _final_url = _default_lang;
    }

    if (_final_lang) {
        wpu_pll_set_cookie('pll_language', _final_lang, 1);
    }
    else {
        wpu_pll_set_cookie('pll_language', '', -10);
    }

    /* Save redir */
    localStorage.setItem(localstoragekey, 1);

    /* Prevent redirection if current lang seems ok */
    if (_final_url == window.location.href || _current_lang == _browser_lang || _current_lang_short == _browser_lang_short) {
        return;
    }

    window.location.href = _final_url;

}());
