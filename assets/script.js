window.addEventListener("DOMContentLoaded", function() {
    function set_cookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (parseInt(exdays, 10) * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }
    [].forEach.call(document.querySelectorAll('.wpu-pll-lang'), function(el, i) {
        el.addEventListener('change', function(e) {
            var $item = e.target.options[e.target.selectedIndex];
            set_cookie('pll_language', $item.getAttribute('data-lang'), 1);
            window.location.href = this.value;
        }, false);
    });
});
