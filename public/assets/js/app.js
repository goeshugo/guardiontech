(function(){
  const KEY = 'theme';
  function apply(theme){ document.documentElement.setAttribute('data-bs-theme', theme); }
  apply(localStorage.getItem(KEY) || 'light');

document.addEventListener('DOMContentLoaded', () => {
  const cont = document.getElementById('toastContainer');
  if (cont) cont.querySelectorAll('.toast').forEach(t => new bootstrap.Toast(t).show());
});

    // theme toggle
    const btn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    function refreshIcon(){
      const t = document.documentElement.getAttribute('data-bs-theme') || 'light';
      if (icon) icon.className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon');
    }
    refreshIcon();
    if (btn) btn.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      localStorage.setItem(KEY, cur); apply(cur); refreshIcon();
    });

    // dropdown on hover (desktop)
    document.querySelectorAll('.user-menu').forEach(menu => {
      const trigger = menu.querySelector('.user-toggle');
      if (!trigger) return;
      const dd = bootstrap.Dropdown.getOrCreateInstance(trigger);
      if (window.matchMedia('(hover:hover)').matches) {
        menu.addEventListener('mouseenter', () => dd.show());
        menu.addEventListener('mouseleave', () => dd.hide());
      }
    });
  });
})();
