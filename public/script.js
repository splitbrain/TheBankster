/**
 * Handle the burger menu
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
            if (!window.confirm('Really delete? This can\'t be undone!')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
            return true;
        });
    });
});

/**
 * Close modals
 */
document.addEventListener('DOMContentLoaded', function () {
    var elements = Array.prototype.slice.call(document.getElementsByClassName('modal-close'), 0);
    elements.forEach(function (el) {
        el.addEventListener('click', function (e) {
            el.parentNode.classList.remove('is-active');
        })
    });
});

/**
 * Attach modal to assign links and method to trigger saving
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('assign-category-form');
    var modal = document.getElementById('assign-category-modal');
    if (!form) return;

    var elements = Array.prototype.slice.call(document.querySelectorAll('.assign-category'), 0);
    elements.forEach(function (el) {
        el.addEventListener('click', function (e) {
            el.innerText = '⌛';
            el.title='You already clicked here, category changes are only visible after a reload';
            form.elements.txid.value = el.dataset.txid;
            modal.classList.add('is-active');
            e.preventDefault();
            e.stopPropagation();
            return false;
        })
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var txid = form.elements.txid.value;
        var catid = form.elements.categoryId.value;
        if (typeof txid === 'undefined') return false;
        if (typeof catid === 'undefined') return false;

        fetch(BASE_URL + '/assign/' + txid + '/' + catid); // FIXME handle response
        modal.classList.remove('is-active');

        return false;
    })

});
