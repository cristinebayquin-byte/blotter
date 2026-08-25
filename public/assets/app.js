(() => {
  const sidebar = document.querySelector('.app-sidebar');
  if (!sidebar) return;

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'sidebar-toggle';
  toggle.setAttribute('aria-label', 'Open navigation menu');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.innerHTML = '<span></span>';

  const overlay = document.createElement('div');
  overlay.className = 'nav-overlay';
  const tools = document.createElement('div');
  tools.className = 'sidebar-tools';
  tools.append(toggle);
  sidebar.prepend(tools);
  document.body.append(overlay);

  const isMobile = () => window.matchMedia('(max-width: 767px)').matches;
  toggle.setAttribute('aria-expanded', String(!isMobile()));
  toggle.setAttribute('aria-label', isMobile() ? 'Open navigation menu' : 'Collapse sidebar');
  toggle.title = isMobile() ? 'Open navigation menu' : 'Collapse sidebar';
  const closeMenu = () => {
    sidebar.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.classList.remove('menu-open');
    if (isMobile()) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open navigation menu');
      toggle.title = 'Open navigation menu';
    }
  };
  const openMenu = () => {
    sidebar.classList.add('is-open');
    overlay.classList.add('is-visible');
    document.body.classList.add('menu-open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Close navigation menu');
    toggle.title = 'Close navigation menu';
  };

  const toggleSidebar = () => {
    if (isMobile()) {
      sidebar.classList.contains('is-open') ? closeMenu() : openMenu();
      return;
    }
    const collapsed = sidebar.classList.toggle('is-collapsed');
    toggle.classList.toggle('is-collapsed', collapsed);
    toggle.setAttribute('aria-expanded', String(!collapsed));
    toggle.setAttribute('aria-label', collapsed ? 'Expand navigation menu' : 'Collapse navigation menu');
    toggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
  };

  toggle.addEventListener('click', toggleSidebar);
  overlay.addEventListener('click', closeMenu);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  window.addEventListener('resize', () => {
    if (!isMobile()) closeMenu();
  });
})();
