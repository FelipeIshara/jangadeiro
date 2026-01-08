const header    = document.querySelector('.site-header');
const navToggle = document.querySelector('.nav-toggle');
const mainNav   = document.querySelector('.main-nav');

if (navToggle && header && mainNav) {
    navToggle.addEventListener('click', () => {
        const isOpen = header.classList.toggle('menu-open');
        navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    mainNav.addEventListener('click', (event) => {
        if (event.target.matches('a')) {
            header.classList.remove('menu-open');
            navToggle.setAttribute('aria-expanded', 'false');
        }
    });
}
