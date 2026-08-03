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
  const modal = document.querySelector('#representatives-modal');
  const closeButton = modal?.querySelector('.representatives-modal-close');
  const modalTitle = modal?.querySelector('#representatives-modal-title');
  const modalState = modal?.querySelector('#representatives-modal-state');
  const phoneText = modal?.querySelector('.representatives-modal-phone span');
  const whatsappLink = modal?.querySelector('.representatives-modal-whatsapp');

  if (
    !map ||
    !modal ||
    !closeButton ||
    !modalTitle ||
    !modalState ||
    !phoneText ||
    !whatsappLink
  ) return;

  let activeState = null;

  const closeModal = () => {
    if (modal.open) modal.close();
  };

  map.addEventListener('click', event => {
    const state = event.target.closest('.representative-state');

    if (!state || !map.contains(state)) return;

    const contact = regionContacts[state.dataset.region];

    if (!contact) return;

    event.preventDefault();
    if (modal.open) return;

    const stateName = state.getAttribute('name') || 'estado selecionado';

    modalTitle.textContent = contact.title;
    modalState.textContent = `Atendimento para ${stateName}`;
    phoneText.textContent = contact.displayPhone;
    whatsappLink.href = contact.whatsappHref;
    whatsappLink.setAttribute('aria-label', `Falar no WhatsApp com o representante de ${stateName}`);

    activeState = state;
    document.body.classList.add('representatives-modal-open');
    modal.showModal();
  });

  closeButton.addEventListener('click', closeModal);

  modal.addEventListener('click', event => {
    if (event.target === modal) closeModal();
  });

  modal.addEventListener('close', () => {
    document.body.classList.remove('representatives-modal-open');
    activeState?.focus();
    activeState = null;
  });
});
