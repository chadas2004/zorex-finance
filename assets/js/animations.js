document.addEventListener('alpine:init', () => {
  Alpine.directive('fade', (el) => {
    el.classList.add('opacity-0', 'translate-y-6', 'transition', 'duration-700');
    const observer = new IntersectionObserver(([entry]) => {
      if(entry.isIntersecting){
        el.classList.remove('opacity-0', 'translate-y-6');
        el.classList.add('opacity-100','translate-y-0');
        observer.unobserve(el);
      }
    }, {threshold:0.2});
    observer.observe(el);
  });
});
