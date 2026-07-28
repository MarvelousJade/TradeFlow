(() => {
  const button = document.querySelector('.tf-menu-button');
  const menu = document.querySelector('.tf-mobile-menu');
  if (!button || !menu) return;

  const closeMenu = () => {
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', 'Open navigation');
    menu.classList.remove('is-open');
    document.body.classList.remove('tf-menu-open');
  };

  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!open));
    button.setAttribute('aria-label', open ? 'Open navigation' : 'Close navigation');
    menu.classList.toggle('is-open', !open);
    document.body.classList.toggle('tf-menu-open', !open);
  });
  menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) closeMenu();
  });
})();

