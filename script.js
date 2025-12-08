document.addEventListener('DOMContentLoaded', () => {
  const originText = document.querySelector('.origin-text-container');
  const originImg = document.querySelector('.origin-img-container');
 
  if (!originText || !originImg) return;

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-origin');
          obs.unobserve(entry.target); 
        }
      });
    },
    {
      threshold: 0.3
    }
  );

  observer.observe(originText);
  observer.observe(originImg);
});
