/* Adamových 50 — režisérská kabina (administrace) */
(() => {
  'use strict';
  const P50 = window.P50;
  const $ = (s, r = document) => r.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const post = (url, body) => fetch(`${P50.base}/api/admin/${url}`, { method: 'POST', headers: { 'X-P50': '1', 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(r => r.json());
  let S;

  async function load() {
    S = await (await fetch(`${P50.base}/api/admin/state`)).json();
    const mem = S.memories, hh = S.households;
    const photos = mem.reduce((n, m) => n + m.media.filter(x => x.kind === 'photo').length, 0);
    const audio = mem.reduce((n, m) => n + m.media.filter(x => x.kind === 'audio').length, 0);
    const video = mem.reduce((n, m) => n + m.media.filter(x => x.kind === 'video').length, 0);
    const active = hh.filter(x => x.memories > 0).length, visited = hh.filter(x => x.last_seen).length;
    $('[data-kpis]').innerHTML = [
      [mem.length, 'vzpomínek'], [photos, 'fotek'], [audio + video, 'hlasových / videí'],
      [`${active}/${hh.length}`, 'domácností přispělo'], [`${visited}/${hh.length}`, 'otevřelo odkaz'],
      [mem.filter(m => m.wish_public && (m.wish_text || m.media.some(x => x.kind === 'audio'))).length, 'veřejných přání'],
      [mem.filter(m => !m.wish_public && (m.wish_text || m.media.some(x => x.kind === 'audio'))).length, 'soukromých přání 🔒'],
    ].map(([v, l]) => `<div class="kpi"><b>${v}</b><span>${l}</span></div>`).join('');

    $('[data-households] tbody').innerHTML = hh.map(x => `
      <tr class="${x.memories ? '' : 'is-silent'}" data-id="${x.id}">
        <td><strong>${esc(x.label)}</strong><br><span class="muted">${esc(x.names.join(' · '))}</span></td>
        <td>${esc(x.relation || '')}</td>
        <td>${x.memories}</td>
        <td class="muted">${x.last_seen ? new Date(x.last_seen).toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'}</td>
        <td><code>${esc(x.link.replace(/^https?:\/\//, ''))}</code></td>
        <td class="row-actions"><div>
          <button class="btn btn--ghost btn--sm" data-qr-id="${x.id}">QR + pozvánka</button>
          <button class="btn btn--ghost btn--sm" data-edit="${x.id}">Upravit</button>
          <button class="btn btn--ghost btn--sm btn--danger" data-del="${x.id}">×</button>
        </div></td>
      </tr>`).join('');

    $('[data-mem-count]').textContent = `(${mem.length})`;
    const url = (m, f) => `${S.media_base}/${m.id}/${f}`;
    $('[data-memories]').innerHTML = mem.map(m => {
      const ph = m.media.filter(x => x.kind === 'photo');
      const wish = m.wish_text || m.media.some(x => x.kind === 'audio');
      return `<article class="mem ${wish ? 'mem--wish' : ''} ${m.status === 'hidden' ? 'mem--hidden' : ''}">
        ${ph.length ? `<div class="mem__media" style="--cols:${Math.min(2, ph.length)}">${ph.slice(0, 4).map(p => `<img loading="lazy" src="${url(m, p.thumb)}" alt="">`).join('')}${ph.length > 4 ? `<span class="more">+${ph.length - 4}</span>` : ''}</div>` : `<div class="mem__quote">${esc(m.title || (m.body || '').slice(0, 80) || '🎙️')}</div>`}
        <div class="mem__body">
          <div class="mem__meta">${m.year ? `<span class="mem__year">${m.year}</span>` : ''}${m.tags.map(t => `<span class="mem__tag">${esc(t)}</span>`).join('')}${m.media.some(x => x.kind === 'audio') ? '<span class="mem__tag">🎙️</span>' : ''}${m.media.some(x => x.kind === 'video') ? '<span class="mem__tag">🎬</span>' : ''}</div>
          ${m.title ? `<h3 class="mem__title">${esc(m.title)}</h3>` : ''}
          ${m.body ? `<p class="mem__text">${esc(m.body)}</p>` : ''}
          ${m.wish_text ? `<p class="mem__wish">🥂 ${esc(m.wish_text)}<small>${m.wish_public ? 'veřejné' : '🔒 soukromé – jen pro tebe'}</small></p>` : ''}
          ${m.media.some(x => x.kind === 'audio') ? `<audio controls preload="none" src="${url(m, m.media.find(x => x.kind === 'audio').file)}"></audio>${m.wish_public ? '' : '<small class="hint">🔒 soukromé hlasové přání</small>'}` : ''}
          <div class="mem__by">— ${esc(m.authors.join(' a '))} <span class="muted">(${esc(m.household.label)})</span></div>
        </div>
        <div class="mem__actions">
          <button class="btn btn--ghost btn--sm" data-hide="${m.id}" data-status="${m.status}">${m.status === 'hidden' ? 'Zobrazit' : 'Skrýt z kina'}</button>
          <button class="btn btn--ghost btn--sm btn--danger" data-mdel="${m.id}">Smazat</button>
        </div>
      </article>`;
    }).join('') || '<p class="empty">Zatím žádné vzpomínky.</p>';
  }

  document.addEventListener('submit', async e => {
    if (!e.target.matches('[data-import]')) return;
    e.preventDefault();
    const r = await post('households', { lines: e.target.lines.value });
    e.target.reset(); toast(`Přidáno: ${r.added}`); load();
  });

  document.addEventListener('click', async e => {
    const t = e.target.closest('button'); if (!t) return;
    if (t.dataset.qrId) openQr(S.households.find(x => x.id == t.dataset.qrId));
    if (t.dataset.edit) {
      const x = S.households.find(y => y.id == t.dataset.edit);
      const label = prompt('Jména (např. „Petra a Honza“):', x.label); if (label == null) return;
      const relation = prompt('Vztah k oslavenci (nepovinné):', x.relation || ''); if (relation == null) return;
      await post(`households/${x.id}`, { label, relation }); load();
    }
    if (t.dataset.del && confirm('Smazat domácnost i všechny její příspěvky?')) { await post(`households/${t.dataset.del}`, { delete: true }); load(); }
    if (t.dataset.hide) { await post(`memories/${t.dataset.hide}`, { status: t.dataset.status === 'hidden' ? 'visible' : 'hidden' }); load(); }
    if (t.dataset.mdel && confirm('Natrvalo smazat vzpomínku?')) { await post(`memories/${t.dataset.mdel}`, { delete: true }); load(); }
    if (t.dataset.action === 'close-qr') $('[data-qr]').close();
    if (t.dataset.action === 'copy-msg') { await navigator.clipboard.writeText($('[data-qr-msg]').value); toast('Zkopírováno – vlož do WhatsAppu nebo e-mailu.'); }
  });

  function openQr(x) {
    const qr = qrcode(0, 'M'); qr.addData(x.link); qr.make();
    $('[data-qr-title]').textContent = x.label;
    $('[data-qr-svg]').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
    const c = S.config, date = new Date(c.party_date.replace(' ', 'T')).toLocaleDateString('cs-CZ', { day: 'numeric', month: 'long' });
    $('[data-qr-msg]').value =
`Ahoj ${x.names.join(' a ')}! 🎉
${date} slavím padesátku a chystám pro všechny malé překvapení – film z vašich vzpomínek.
Máte nějakou historku, fotku z šuplíku nebo vzkaz? Nahrajte to prosím tady (odkaz je jen pro vás):
${x.link}
Děkuju a těším se! ${c.celebrant}`;
    $('[data-qr]').showModal();
  }

  let tt; function toast(m) { const t = $('[data-toast]'); t.textContent = m; t.classList.add('is-on'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('is-on'), 2500); }
  load();
})();
