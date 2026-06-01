document.addEventListener('DOMContentLoaded', () => {
  const pendingHashKey = 'jangadeiroPendingHash';

  const getPendingHash = () => {
    try {
      return window.sessionStorage.getItem(pendingHashKey);
    } catch (error) {
      return null;
    }
  };

  const setPendingHash = hash => {
    try {
      window.sessionStorage.setItem(pendingHashKey, hash);
    } catch (error) {
      return;
    }
  };

  const clearPendingHash = () => {
    try {
      window.sessionStorage.removeItem(pendingHashKey);
    } catch (error) {
      return;
    }
  };

  const scrollToHash = (hash, shouldUpdateHistory = true) => {
    if (!hash || hash === '#') return false;

    const targetId = decodeURIComponent(hash.slice(1));
    const target = document.getElementById(targetId);

    if (!target) return false;

    target.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });

    if (shouldUpdateHistory) {
      history.pushState(null, '', hash);
    }

    return true;
  };

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');

    if (!link || link.target || link.hasAttribute('download')) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin) {
      return;
    }

    const isSamePage =
      url.pathname === window.location.pathname &&
      url.search === window.location.search;

    if (!isSamePage && url.hash) {
      event.preventDefault();
      setPendingHash(url.hash);
      window.location.href = `${url.pathname}${url.search}`;
      return;
    }

    if (!scrollToHash(url.hash)) return;

    event.preventDefault();
  });

  const pendingHash = getPendingHash();

  if (pendingHash) {
    clearPendingHash();
    window.setTimeout(() => {
      scrollToHash(pendingHash);
    }, 0);
    return;
  }

  if (window.location.hash) {
    window.setTimeout(() => {
      scrollToHash(window.location.hash, false);
    }, 0);
  }
});
