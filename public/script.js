/**
 * Handle the burge menu
 */
document.addEventListener('DOMContentLoaded', function () {
    // Get all "navbar-burger" elements
    var navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Check if there are any navbar burgers
    if (navbarBurgers.length > 0) {

        // Add a click event on each of them
        navbarBurgers.forEach(function (el) {
            el.addEventListener('click', function () {

                // Get the target from the "data-target" attribute
                var target = el.dataset.target;
                var $target = document.getElementById(target);

                // Toggle the class on both the "navbar-burger" and the "navbar-menu"
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');

            });
        });
    }
});

/**
 * Safety net on deletes
 */
document.addEventListener('DOMContentLoaded', function () {
    var elements = Array.prototype.slice.call(document.getElementsByClassName('really-del'), 0);
    elements.forEach(function (el) {
        el.addEventListener('click', function (e) {
            if(!window.confirm('Really delete? This can\'t be undone!')){
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            return true;
        });
    });
});