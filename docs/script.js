document.addEventListener('DOMContentLoaded', () => {
  const animatedElements = document.querySelectorAll(
    '.origin-text-container, .origin-img-container, .platform-text-container, .platform-img-container, .work-with-text-container'
  );

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

  animatedElements.forEach(el => observer.observe(el));
});
