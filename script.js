document.addEventListener('DOMContentLoaded', () => {
  const originText = document.querySelector('.origin-text-container');
  const originImg = document.querySelector('.origin-img-container');
  const platformText = document.querySelector('.platform-text-container');
  const platformImg = document.querySelector('.platform-img-container');

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        const el = entry.target;

        if (
          el.classList.contains('origin-text-container') ||
          el.classList.contains('origin-img-container')
        ) {
          el.classList.add('animate-origin');
        }

        if (
          el.classList.contains('platform-text-container') ||
          el.classList.contains('platform-img-container')
        ) {
          el.classList.add('animate-platform');
        }

        obs.unobserve(el); // anima só uma vez
      });
    },
    { threshold: 0.3 }
  );

  observer.observe(originText);
  observer.observe(originImg);
  observer.observe(platformText);
  observer.observe(platformImg);
});
