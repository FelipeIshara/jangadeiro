document.addEventListener('DOMContentLoaded', () => {
  const animatedElements = document.querySelectorAll(
    '.animate-left, .animate-right'
  );

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;

        const el = entry.target;
        el.classList.add('is-visible');

        obs.unobserve(el); // anima só uma vez
      });
    },
    { threshold: 0.25 }
  );

  animatedElements.forEach(el => observer.observe(el));
});
