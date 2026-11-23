
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // Automatically close the mobile navigation after clicking a link.
    const navLinks = document.querySelectorAll(
        '.navbar-collapse .nav-link'
    );

    const navbarCollapse = document.getElementById('foodNavbar');

    navLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (
                navbarCollapse &&
                navbarCollapse.classList.contains('show') &&
                window.bootstrap
            ) {
                bootstrap.Collapse
                    .getOrCreateInstance(navbarCollapse)
                    .hide();
            }
        });
    });
});
