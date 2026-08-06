const header    = document.querySelector('.site-header');
const navToggle = document.querySelector('.nav-toggle');
const mainNav   = document.querySelector('.main-nav');

if (header) {
    const updateHeaderOpacity = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 0);
    };

    updateHeaderOpacity();
    window.addEventListener('scroll', updateHeaderOpacity, { passive: true });
}

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

const productCarousels = document.querySelectorAll('[data-products-carousel]');

productCarousels.forEach(carousel => {
    const track = carousel.querySelector('[data-products-track]');
    const previousButton = carousel.querySelector('[data-products-prev]');
    const nextButton = carousel.querySelector('[data-products-next]');
    const cards = Array.from(carousel.querySelectorAll('.product-item'));

    if (!track || !previousButton || !nextButton || !cards.length) return;

    const carouselQuery = window.matchMedia('(max-width: 1200px)');
    const mobileQuery = window.matchMedia('(max-width: 860px)');
    const singleCardQuery = window.matchMedia('(max-width: 550px)');
    const touchQuery = window.matchMedia('(hover: none), (pointer: coarse)');
    let pointerStartX = 0;
    let pointerStartY = 0;
    let pointerMoved = false;
    let scrollFrame = null;

    const closeCardDetails = except => {
        cards.forEach(card => {
            if (card === except) return;

            card.classList.remove('is-details-visible');
            card.setAttribute('aria-expanded', 'false');
        });
    };

    const updateControls = () => {
        if (!carouselQuery.matches) {
            previousButton.disabled = true;
            nextButton.disabled = true;
            return;
        }

        const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
        previousButton.disabled = track.scrollLeft <= 2;
        nextButton.disabled = track.scrollLeft >= maxScroll - 2;
    };

    const getPageDistance = () => {
        const firstCard = cards[0];
        const styles = window.getComputedStyle(track);
        const gap = Number.parseFloat(styles.columnGap || styles.gap) || 0;
        const visibleCards = singleCardQuery.matches ? 1 : mobileQuery.matches ? 2 : 3;

        return (firstCard.getBoundingClientRect().width + gap) * visibleCards;
    };

    const scrollPage = direction => {
        track.scrollBy({
            left: direction * getPageDistance(),
            behavior: 'smooth'
        });
    };

    previousButton.addEventListener('click', () => scrollPage(-1));
    nextButton.addEventListener('click', () => scrollPage(1));

    track.addEventListener('scroll', () => {
        if (scrollFrame !== null) return;

        scrollFrame = window.requestAnimationFrame(() => {
            updateControls();
            scrollFrame = null;
        });
    }, { passive: true });

    track.addEventListener('pointerdown', event => {
        pointerStartX = event.clientX;
        pointerStartY = event.clientY;
        pointerMoved = false;
    }, { passive: true });

    track.addEventListener('pointermove', event => {
        const horizontalMovement = Math.abs(event.clientX - pointerStartX);
        const verticalMovement = Math.abs(event.clientY - pointerStartY);

        if (horizontalMovement > 10 || verticalMovement > 10) {
            pointerMoved = true;
        }
    }, { passive: true });

    cards.forEach(card => {
        card.addEventListener('click', event => {
            if (pointerMoved) {
                event.preventDefault();
                return;
            }

            const keyboardActivation = event.detail === 0;

            if (!touchQuery.matches && !keyboardActivation) return;

            const willOpen = !card.classList.contains('is-details-visible');
            closeCardDetails(card);
            card.classList.toggle('is-details-visible', willOpen);
            card.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    const handleLayoutChange = () => {
        if (!carouselQuery.matches) {
            track.scrollLeft = 0;
            closeCardDetails();
        }

        window.requestAnimationFrame(updateControls);
    };

    carouselQuery.addEventListener('change', handleLayoutChange);
    mobileQuery.addEventListener('change', handleLayoutChange);
    singleCardQuery.addEventListener('change', handleLayoutChange);
    window.addEventListener('resize', handleLayoutChange, { passive: true });

    updateControls();
});

const workWithForm = document.querySelector('.work-with-form');

if (workWithForm) {
    const turnstileWidget = workWithForm.querySelector('.cf-turnstile');

    if (turnstileWidget && window.matchMedia('(max-width: 580px)').matches) {
        turnstileWidget.dataset.size = 'compact';
    }

    const submitButton = workWithForm.querySelector('.work-with-submit');
    const feedback = workWithForm.querySelector('.form-feedback');
    const defaultButtonText = submitButton ? submitButton.textContent : '';

    const showFeedback = (message, type) => {
        if (!feedback) return;

        feedback.textContent = message;
        feedback.hidden = false;
        feedback.classList.toggle('is-success', type === 'success');
        feedback.classList.toggle('is-error', type === 'error');
    };

    workWithForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!workWithForm.checkValidity()) {
            workWithForm.reportValidity();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Enviando...';
        }

        if (feedback) {
            feedback.hidden = true;
            feedback.classList.remove('is-success', 'is-error');
        }

        try {
            const response = await fetch(workWithForm.action, {
                method: 'POST',
                body: new FormData(workWithForm),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/plain'
                }
            });

            const message = (await response.text()).trim();

            if (!response.ok) {
                throw new Error(message || 'Nao foi possivel enviar sua mensagem. Tente novamente em instantes.');
            }

            workWithForm.reset();
            showFeedback(message || 'Mensagem enviada com sucesso. Obrigado pelo contato!', 'success');
        } catch (error) {
            showFeedback(error.message || 'Nao foi possivel enviar sua mensagem. Tente novamente em instantes.', 'error');
        } finally {
            if (window.turnstile) {
                window.turnstile.reset();
            }

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = defaultButtonText;
            }
        }
    });
}
