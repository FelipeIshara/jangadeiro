document.addEventListener('DOMContentLoaded', () => {
  const originText = document.querySelector('.origin-text-container');
  const originImg = document.querySelector('.origin-img-container');
  const platformText = document.querySelector('.platform-text-container');
  const platformImg = document.querySelector('.platform-img-container');
  const workWithImg = document.querySelector('.work-with-text-container');

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

        if (
          el.classList.contains('work-with-text-container')
        ) {
          el.classList.add('animate-work-with');
        }

        obs.unobserve(el); // anima só uma vez
      });
    },
    { threshold: 0.25 }
  );

  observer.observe(workWithImg);
  observer.observe(originText);
  observer.observe(originImg);
  observer.observe(platformText);
  observer.observe(platformImg);
});
