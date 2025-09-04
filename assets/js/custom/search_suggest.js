// assets/js/custom/search_suggest.js
(function () {
  function debounce(fn, wait) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function createContainer(formEl) {
    // Ensure parent form is positioned for absolute children
    if (getComputedStyle(formEl).position === 'static') {
      formEl.style.position = 'relative';
    }
    let el = formEl.querySelector('.search-suggestions');
    if (!el) {
      el = document.createElement('div');
      el.className = 'search-suggestions shadow-sm';
      el.setAttribute('role', 'listbox');
      el.style.display = 'none';
      formEl.appendChild(el);
    }
    return el;
  }

  function hideContainer(cont) {
    if (cont) cont.style.display = 'none';
  }

  function showContainer(cont) {
    if (cont) cont.style.display = 'block';
  }

  function renderSuggestions(cont, input, items, query) {
    if (!items || items.length === 0) {
      cont.innerHTML = '';
      hideContainer(cont);
      return;
    }
    const rect = input.getBoundingClientRect();
    const parentRect = cont.parentElement.getBoundingClientRect();
    // Position below the input
    cont.style.position = 'absolute';
    cont.style.left = (input.offsetLeft) + 'px';
    cont.style.top = (input.offsetTop + input.offsetHeight + 4) + 'px';
    cont.style.width = input.offsetWidth + 'px';

    const escapeHtml = (s) => (s + '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));

    cont.innerHTML = '';
    items.forEach((it, idx) => {
      const a = document.createElement('a');
      a.className = 'search-suggestion-item d-flex align-items-center text-decoration-none';
      a.setAttribute('role', 'option');
      a.href = it.url;
      a.innerHTML = `
        <img src="${escapeHtml(it.image || '')}" alt="" class="me-2 flex-shrink-0" style="width:32px;height:32px;object-fit:contain;" />
        <div class="flex-grow-1 overflow-hidden">
          <div class="text-truncate">${escapeHtml(it.name)}</div>
          <div class="small text-muted">${Number(it.price).toFixed(2)} PKR</div>
        </div>`;
      cont.appendChild(a);
    });

    // View all link
    const viewAll = document.createElement('a');
    viewAll.className = 'search-suggestion-viewall text-decoration-none';
    viewAll.href = 'products.php?query=' + encodeURIComponent(query);
    viewAll.textContent = `View all results for "${query}"`;
    cont.appendChild(viewAll);

    showContainer(cont);
  }

  async function fetchSuggestions(q, limit = 8) {
    const url = 'search_suggest.php?query=' + encodeURIComponent(q) + '&limit=' + encodeURIComponent(limit);
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return [];
    return await res.json();
  }

  function attachAutocomplete(form) {
    const input = form.querySelector('input[name="query"]');
    if (!input) return;
    const cont = createContainer(form);

    let lastQuery = '';
    const doSearch = debounce(async () => {
      const q = input.value.trim();
      lastQuery = q;
      if (q.length < 1) {
        hideContainer(cont);
        return;
      }
      try {
        const items = await fetchSuggestions(q, 8);
        // Ensure this response is for the latest query
        if (q !== lastQuery) return;
        renderSuggestions(cont, input, items, q);
      } catch (e) {
        hideContainer(cont);
      }
    }, 200);

    input.addEventListener('input', doSearch);
    input.addEventListener('focus', doSearch);

    // Hide on outside click
    document.addEventListener('click', function (e) {
      if (!form.contains(e.target)) {
        hideContainer(cont);
      }
    });

    // Basic keyboard navigation
    input.addEventListener('keydown', function (e) {
      if (cont.style.display === 'none') return;
      const items = Array.from(cont.querySelectorAll('.search-suggestion-item, .search-suggestion-viewall'));
      if (items.length === 0) return;
      const active = cont.querySelector('.active');
      let idx = items.indexOf(active);
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        idx = (idx + 1) % items.length;
        items.forEach(el => el.classList.remove('active'));
        items[idx].classList.add('active');
        items[idx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        idx = (idx <= 0 ? items.length - 1 : idx - 1);
        items.forEach(el => el.classList.remove('active'));
        items[idx].classList.add('active');
        items[idx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        const target = cont.querySelector('.active') || items[0];
        if (target && target.href) {
          window.location.href = target.href;
          e.preventDefault();
        }
      } else if (e.key === 'Escape') {
        hideContainer(cont);
      }
    });
  }

  function init() {
    // Attach to all forms explicitly marked as role=search
    const forms = document.querySelectorAll('form[role="search"]');
    forms.forEach(attachAutocomplete);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
