<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="market-pulse-widget">
  <button type="button" class="market-pulse-launch" aria-label="Open live market panel"
          aria-expanded="false">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17h2.6l3.1-5.2 3.1 3.1 4.1-7.1 2.4 3.2H21v2h-3.7l-1.2-1.6-3.9 6.8-3.1-3.1L6.7 19H3v-2z"/></svg>
    <span>Markets</span>
  </button>
  <div class="market-pulse-scrim" aria-hidden="true"></div>
  <aside class="market-pulse-dock" aria-label="Live market pulse">
    <header class="market-pulse-head">
      <div><span>LIVE MARKET PULSE</span><strong>United States markets &amp; crypto</strong></div>
      <button type="button" class="market-pulse-close" aria-label="Close live market panel">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.4 5 5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6l5.6-5.6L5 6.4 6.4 5z"/></svg>
      </button>
    </header>
    <div class="market-pulse-switch" role="tablist" aria-label="Market category">
      <button type="button" class="market-pulse-tab active" role="tab" aria-selected="true" data-pulse-group="us">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9h-2a7 7 0 1 1-7-7V3zm1 4v6l5 3 .9-1.7-3.9-2.3V7h-2z"/></svg>
        <span>US Markets</span></button>
      <button type="button" class="market-pulse-tab" role="tab" aria-selected="false" data-pulse-group="crypto">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.7 10.4c1.4-.7 2.1-1.9 1.9-3.6-.3-2.3-2.3-3-4.8-3.2V1h-1.6v2.5H9.9V1H8.3v2.6H5v1.7l1.7.3c.7.1.8.6.8 1.1v9.1c-.1.4-.3.7-.8.7l-1.7.2v1.8h3.3V21h1.6v-2.5h1.3V21h1.6v-2.6c3.2-.2 5.5-1 5.8-4 .2-2.1-.8-3.4-2.9-4zm-5.8-4.7c1.8 0 4.1-.2 4.1 1.7 0 1.8-2.2 1.7-4.1 1.7V5.7zm0 10.2v-4.4c2.2 0 4.9-.2 4.9 2.2 0 2.2-2.6 2.2-4.9 2.2z"/></svg>
        <span>Crypto</span></button>
    </div>
    <div class="market-pulse-provenance" title="Every row identifies its actual provider">
      <i></i><span>Moomoo primary · Yahoo backup</span>
    </div>
    <div class="market-pulse-viewport" role="tabpanel" aria-live="polite">
      <div class="market-pulse-loading"><i></i>Connecting to live market sources…</div>
    </div>
    <footer class="market-pulse-foot">
      <span><i class="broker"></i>Moomoo</span><span><i class="backup"></i>Yahoo backup</span>
      <small>Hover for quote time</small>
    </footer>
  </aside>
</div>
<script>
(() => {
  const root = document.currentScript.previousElementSibling;
  const dock = root?.querySelector('.market-pulse-dock');
  const viewport = root?.querySelector('.market-pulse-viewport');
  const tabs = [...(root?.querySelectorAll('.market-pulse-tab') || [])];
  const provenance = root?.querySelector('.market-pulse-provenance span');
  const launch = root?.querySelector('.market-pulse-launch');
  const close = root?.querySelector('.market-pulse-close');
  const scrim = root?.querySelector('.market-pulse-scrim');
  if (!root || !dock || !viewport || !provenance) return;
  let active = 'us', groups = {}, changing = false;
  const svgNS = 'http://www.w3.org/2000/svg';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const setOpen = open => {
    root.classList.toggle('open', open);
    document.body.classList.toggle('market-pulse-open', open);
    launch?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) close?.focus({preventScroll:true});
  };
  launch?.addEventListener('click', () => setOpen(true));
  close?.addEventListener('click', () => { setOpen(false); launch?.focus({preventScroll:true}); });
  scrim?.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });

  const formatPrice = value => {
    const n = Number(value);
    if (!Number.isFinite(n)) return 'Unavailable';
    const digits = n >= 1 ? 2 : n >= .1 ? 4 : 6;
    return n.toLocaleString('en-US', {minimumFractionDigits: digits, maximumFractionDigits: digits});
  };
  function sparkline(points) {
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 72 25'); svg.classList.add('market-pulse-spark');
    const usable = (Array.isArray(points) ? points : []).map(Number).filter(Number.isFinite);
    if (usable.length < 2) {
      const line = document.createElementNS(svgNS, 'line');
      line.setAttribute('x1','4'); line.setAttribute('y1','13'); line.setAttribute('x2','68'); line.setAttribute('y2','13');
      line.classList.add('waiting'); svg.appendChild(line); return svg;
    }
    const min = Math.min(...usable), max = Math.max(...usable), range = max - min || 1;
    const polyline = document.createElementNS(svgNS, 'polyline');
    polyline.setAttribute('points', usable.map((v, i) =>
      `${(i/(usable.length-1)*68+2).toFixed(1)},${(23-(v-min)/range*21).toFixed(1)}`).join(' '));
    svg.appendChild(polyline); return svg;
  }
  function render() {
    const rows = groups[active] || [];
    const sources = rows.map(row => row.quote?.source || '');
    const brokerCount = sources.filter(source => source.startsWith('Moomoo')).length;
    const backupCount = sources.filter(source => source.startsWith('Yahoo')).length;
    provenance.textContent = `${brokerCount} Moomoo · ${backupCount} Yahoo backup`;
    viewport.classList.add('switching');
    setTimeout(() => {
      viewport.replaceChildren();
      const track = document.createElement('div'); track.className = 'market-pulse-track';
      rows.forEach(row => {
        const quote = row.quote || {}, change = Number(quote.change), pct = Number(quote.change_pct);
        const positive = Number.isFinite(change) ? change >= 0 : true;
        const card = document.createElement('article');
        card.className = `market-pulse-item ${positive ? 'positive' : 'negative'}${quote.stale ? ' stale' : ''}`;
        card.title = quote.source ? `${row.label} · ${quote.source}${quote.quote_time ? ' · '+quote.quote_time : ''}` : `${row.label} unavailable`;
        const name = document.createElement('span'); name.className = 'market-pulse-name'; name.textContent = row.label;
        const price = document.createElement('b'); price.className = 'market-pulse-price'; price.textContent = formatPrice(quote.price);
        const changeLine = document.createElement('small'); changeLine.className = 'market-pulse-change';
        if (Number.isFinite(change) && Number.isFinite(pct)) {
          changeLine.textContent = `${change >= 0 ? '+' : '−'}${formatPrice(Math.abs(change))}  ${pct >= 0 ? '+' : '−'}${Math.abs(pct).toFixed(2)}%`;
        } else changeLine.textContent = 'Waiting for quote';
        const source = document.createElement('em');
        source.className = quote.source?.startsWith('Moomoo') ? 'broker' : 'backup';
        source.textContent = quote.source?.startsWith('Moomoo') ? 'M' : quote.source ? 'Y' : '—';
        source.setAttribute('aria-label', quote.source || 'Source unavailable');
        card.append(name, price, changeLine, sparkline(quote.spark), source);
        track.appendChild(card);
      });
      viewport.appendChild(track);
      viewport.classList.remove('switching'); changing = false;
    }, reducedMotion.matches ? 0 : 130);
  }
  async function refresh() {
    if (document.hidden) return;
    try {
      const response = await fetch('api/market_pulse.php', {cache:'no-store'});
      if (!response.ok) throw new Error('market feed unavailable');
      const data = await response.json(); groups = data.groups || {}; render();
      root.classList.remove('feed-error');
    } catch (error) {
      root.classList.add('feed-error');
      provenance.textContent = 'Market sources temporarily unavailable';
    }
  }
  tabs.forEach(tab => tab.addEventListener('click', () => {
    if (changing || tab.dataset.pulseGroup === active) return;
    changing = true; active = tab.dataset.pulseGroup;
    tabs.forEach(item => { const selected = item === tab; item.classList.toggle('active', selected);
      item.setAttribute('aria-selected', selected ? 'true' : 'false'); });
    render();
  }));
  refresh(); setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
</script>
