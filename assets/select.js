/* Turns every <select> into a modern custom dropdown (glass panel, icons, colored badges,
   bottom sheet on phones). The native <select> stays in the DOM (hidden) so forms and PHP
   keep working unchanged. Without JavaScript the normal select is shown.
   Add data-native to a <select> to skip it; data-icon="spec|pin|user" adds a leading icon. */
(() => {
  const S = (d, extra = '') => `<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" ${extra}>${d}</svg>`;
  const ICONS = {
    spec: S('<path d="M6 3v5a4 4 0 0 0 8 0V3M4.5 3h3M12.5 3h3M10 12v1.5a3.5 3.5 0 0 0 7 0V12"/><circle cx="17" cy="10.5" r="1.5"/>'),
    pin: S('<path d="M10 18s6-5.2 6-9.5a6 6 0 1 0-12 0C4 12.8 10 18 10 18Z"/><circle cx="10" cy="8.5" r="2.2"/>'),
    user: S('<circle cx="10" cy="6.5" r="3.3"/><path d="M3.5 17c.6-3.4 3.1-5.2 6.5-5.2s5.9 1.8 6.5 5.2"/>'),
  };
  const CHEV = S('<path d="m5 8 5 5 5-5"/>', 'class="dd-chev"');
  const CHECK = S('<path d="m4.5 10.5 3.5 3.5 7.5-8"/>', 'stroke-width="2.6"');
  const ALL = S('<rect x="3" y="3" width="5.5" height="5.5" rx="1.5"/><rect x="11.5" y="3" width="5.5" height="5.5" rx="1.5"/><rect x="3" y="11.5" width="5.5" height="5.5" rx="1.5"/><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1.5"/>');
  let uid = 0, openDd = null;

  function enhance(sel) {
    if (sel.multiple || sel.size > 1 || 'native' in sel.dataset || sel.closest('.dd')) return;
    const id = 'dd' + (++uid), label = sel.getAttribute('aria-label') || '', icon = ICONS[sel.dataset.icon] || '';
    const wrap = document.createElement('div'); wrap.className = 'dd';
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel);
    sel.classList.add('dd-native'); sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');

    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'dd-btn';
    btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-controls', id);
    btn.innerHTML = (icon ? `<span class="dd-ico">${icon}</span>` : '') + '<span class="dd-val"></span>' + CHEV;
    const val = btn.querySelector('.dd-val');

    const back = document.createElement('div'); back.className = 'dd-back'; back.hidden = true;
    const list = document.createElement('ul');
    list.id = id; list.className = 'dd-list'; list.setAttribute('role', 'listbox'); list.tabIndex = -1; list.hidden = true;
    if (label) list.setAttribute('aria-label', label);
    const items = [...sel.options].map((o, i) => {
      const li = document.createElement('li');
      li.className = 'dd-opt'; li.id = id + '-' + i; li.setAttribute('role', 'option'); li.dataset.i = i; li.style.setProperty('--i', i);
      const first = [...o.text.replace(/^(Dr|Pr)\.?\s+/i, '')][0] || '';
      const useIcon = o.value === '' || sel.dataset.icon === 'spec' || sel.dataset.icon === 'pin';
      li.innerHTML = `<span class="dd-badge b${i % 3}">${o.value === '' ? ALL : useIcon ? icon : ''}</span><span class="dd-txt"></span><span class="dd-ck">${CHECK}</span>`;
      if (!useIcon) li.querySelector('.dd-badge').textContent = first.toUpperCase();
      li.querySelector('.dd-txt').textContent = o.text;
      if (o.disabled) li.setAttribute('aria-disabled', 'true');
      list.appendChild(li); return li;
    });
    wrap.append(btn, back, list);

    let active = sel.selectedIndex, typed = '', typedAt = 0;
    const sync = () => {
      val.textContent = sel.options[sel.selectedIndex]?.text || '';
      if (label) btn.setAttribute('aria-label', label + ' : ' + val.textContent);
      items.forEach((li, i) => li.setAttribute('aria-selected', i === sel.selectedIndex));
    };
    const setActive = (i) => {
      if (i < 0 || i >= items.length) return;
      active = i; items.forEach((li, k) => li.classList.toggle('act', k === i));
      list.setAttribute('aria-activedescendant', items[i].id); items[i].scrollIntoView({ block: 'nearest' });
    };
    const step = (from, dir) => { let i = from + dir; while (items[i] && items[i].hasAttribute('aria-disabled')) i += dir; return items[i] ? i : from; };
    const open = () => {
      if (openDd && openDd !== close) openDd();
      list.hidden = false; back.hidden = false; btn.setAttribute('aria-expanded', 'true');
      const r = btn.getBoundingClientRect();
      wrap.classList.toggle('dd-up', innerHeight - r.bottom < Math.min(list.offsetHeight, 300) + 16 && r.top > innerHeight - r.bottom);
      setActive(sel.selectedIndex); list.focus({ preventScroll: true }); openDd = close;
    };
    const close = (refocus) => {
      list.hidden = true; back.hidden = true; btn.setAttribute('aria-expanded', 'false'); if (openDd === close) openDd = null;
      if (refocus === true) btn.focus();
    };
    const choose = (i) => {
      if (items[i].hasAttribute('aria-disabled')) return;
      const changed = sel.selectedIndex !== i; sel.selectedIndex = i; sync(); close(true);
      if (changed) sel.dispatchEvent(new Event('change', { bubbles: true }));
    };

    btn.addEventListener('click', () => (list.hidden ? open() : close(true)));
    btn.addEventListener('keydown', (e) => { if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(); } });
    back.addEventListener('click', () => close(true));
    list.addEventListener('keydown', (e) => {
      const k = e.key;
      if (k === 'ArrowDown') setActive(step(active, 1));
      else if (k === 'ArrowUp') setActive(step(active, -1));
      else if (k === 'Home') setActive(step(-1, 1));
      else if (k === 'End') setActive(step(items.length, -1));
      else if (k === 'Enter' || k === ' ') choose(active);
      else if (k === 'Escape') close(true);
      else if (k === 'Tab') close();
      else if (k.length === 1) {
        const now = Date.now(); typed = (now - typedAt > 600 ? '' : typed) + k.toLowerCase(); typedAt = now;
        const hit = items.findIndex((li, i) => !li.hasAttribute('aria-disabled') && sel.options[i].text.toLowerCase().startsWith(typed));
        if (hit > -1) setActive(hit);
      } else return;
      e.preventDefault();
    });
    list.addEventListener('mousemove', (e) => { const li = e.target.closest('.dd-opt'); if (li && +li.dataset.i !== active && !li.hasAttribute('aria-disabled')) setActive(+li.dataset.i); });
    list.addEventListener('click', (e) => { const li = e.target.closest('.dd-opt'); if (li) choose(+li.dataset.i); });
    list.addEventListener('blur', () => setTimeout(() => { if (!list.hidden && !wrap.contains(document.activeElement)) close(); }, 0));
    sel.addEventListener('change', sync);
    sel.form?.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
  }

  document.addEventListener('pointerdown', (e) => { if (openDd && !e.target.closest('.dd')) openDd(); });
  const init = () => document.querySelectorAll('select').forEach(enhance);
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
