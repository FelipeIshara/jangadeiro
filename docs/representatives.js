document.addEventListener('DOMContentLoaded', () => {
  const map = document.querySelector('#svg-map');
  const modal = document.querySelector('#representatives-modal');
  const closeButton = modal?.querySelector('.representatives-modal-close');

  if (!map || !modal || !closeButton) return;

  let activeState = null;

  const closeModal = () => {
    if (modal.open) modal.close();
  };

  map.addEventListener('click', event => {
    const state = event.target.closest('.representative-state');

    if (!state || !map.contains(state)) return;

    event.preventDefault();
    if (modal.open) return;

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
