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

const workWithForm = document.querySelector('.work-with-form');

if (workWithForm) {
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
