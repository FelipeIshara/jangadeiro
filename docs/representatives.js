document.addEventListener('DOMContentLoaded', () => {
  const regionContacts = {
    nordeste: {
      title: 'Nordeste',
      displayPhone: '(85) 4009-3196',
      whatsappHref: 'https://wa.me/558540093196'
    },
    'sul-sudeste': {
      title: 'Sul e Sudeste',
      displayPhone: '(85) 4009-3214',
      whatsappHref: 'https://wa.me/558540093214'
    }
  };

  const map = document.querySelector('#svg-map');
  const popover = document.querySelector('.representatives-popover');
  const popoverTitle = popover?.querySelector('.representatives-popover-title');
  const popoverPhone = popover?.querySelector('.representatives-popover-phone span');
  const popoverWhatsapp = popover?.querySelector('.representatives-popover-whatsapp');

  if (
    !map ||
    !popover ||
    !popoverTitle ||
    !popoverPhone ||
    !popoverWhatsapp
  ) return;

  let popoverCloseTimer = null;
  const popoverWidthQuery = window.matchMedia('(min-width: 861px)');
  const popoverHoverQuery = window.matchMedia('(min-width: 861px) and (hover: hover)');

  const getStateContact = state => {
    const contact = regionContacts[state?.dataset.region];

    if (!contact) return null;

    return {
      contact,
      stateName: state.getAttribute('name') || 'estado selecionado'
    };
  };

  const clearPopoverClose = () => {
    if (popoverCloseTimer === null) return;

    window.clearTimeout(popoverCloseTimer);
    popoverCloseTimer = null;
  };

  const hidePopover = () => {
    clearPopoverClose();
    popover.classList.remove('is-visible');
    popover.setAttribute('aria-hidden', 'true');
    popoverWhatsapp.tabIndex = -1;
  };

  const schedulePopoverClose = () => {
    clearPopoverClose();
    popoverCloseTimer = window.setTimeout(hidePopover, 220);
  };

  const showPopover = state => {
    const stateData = getStateContact(state);

    if (!stateData) return;

    const { contact, stateName } = stateData;

    clearPopoverClose();
    popoverTitle.textContent = contact.title;
    popoverPhone.textContent = contact.displayPhone;
    popoverWhatsapp.href = contact.whatsappHref;
    popoverWhatsapp.setAttribute('aria-label', `Falar no WhatsApp com o representante de ${stateName}`);
    popoverWhatsapp.tabIndex = 0;
    popover.classList.add('is-visible');
    popover.setAttribute('aria-hidden', 'false');
  };

  map.addEventListener('click', event => {
    const state = event.target.closest('.representative-state');

    if (!state || !map.contains(state)) return;

    event.preventDefault();
    showPopover(state);
  });

  map.querySelectorAll('.representative-state').forEach(state => {
    state.addEventListener('pointerenter', () => {
      if (popoverHoverQuery.matches) showPopover(state);
    });

    state.addEventListener('pointerleave', () => {
      if (popoverHoverQuery.matches) schedulePopoverClose();
    });

    state.addEventListener('focus', () => {
      const keyboardFocus = state.matches(':focus-visible');

      if (popoverWidthQuery.matches && (popoverHoverQuery.matches || keyboardFocus)) {
        showPopover(state);
      }
    });

    state.addEventListener('blur', () => {
      if (popoverWidthQuery.matches) schedulePopoverClose();
    });
  });

  popover.addEventListener('pointerenter', clearPopoverClose);
  popover.addEventListener('pointerleave', schedulePopoverClose);
  popover.addEventListener('focusin', clearPopoverClose);
  popover.addEventListener('focusout', event => {
    if (!popover.contains(event.relatedTarget)) schedulePopoverClose();
  });

  document.addEventListener('click', event => {
    if (popoverWidthQuery.matches || popover.contains(event.target)) return;
    if (event.target.closest('.representative-state')) return;

    hidePopover();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') hidePopover();
  });
});
