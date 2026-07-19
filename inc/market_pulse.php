<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<section class="market-pulse-dock" aria-label="Live market pulse">
  <div class="market-pulse-shell">
    <div class="market-pulse-switch" role="tablist" aria-label="Market category">
      <button type="button" class="market-pulse-tab active" role="tab" aria-selected="true" data-pulse-group="us">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9h-2a7 7 0 1 1-7-7V3zm1 4v6l5 3 .9-1.7-3.9-2.3V7h-2z"/></svg>
        <span>US Markets</span></button>
      <button type="button" class="market-pulse-tab" role="tab" aria-selected="false" data-pulse-group="crypto">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.7 10.4c1.4-.7 2.1-1.9 1.9-3.6-.3-2.3-2.3-3-4.8-3.2V1h-1.6v2.5H9.9V1H8.3v2.6H5v1.7l1.7.3c.7.1.8.6.8 1.1v9.1c-.1.4-.3.7-.8.7l-1.7.2v1.8h3.3V21h1.6v-2.5h1.3V21h1.6v-2.6c3.2-.2 5.5-1 5.8-4 .2-2.1-.8-3.4-2.9-4zm-5.8-4.7c1.8 0 4.1-.2 4.1 1.7 0 1.8-2.2 1.7-4.1 1.7V5.7zm0 10.2v-4.4c2.2 0 4.9-.2 4.9 2.2 0 2.2-2.6 2.2-4.9 2.2z"/></svg>
        <span>Cryptocurrencies</span></button>
      <span class="market-pulse-provenance" title="Each instrument identifies its actual provider">
        <i></i><span>Moomoo primary · Yahoo backup</span></span>
    </div>
    <div class="market-pulse-viewport" id="marketPulseViewport" role="tabpanel" aria-live="polite">
      <div class="market-pulse-loading"><i></i>Connecting to live market sources…</div>
    </div>
  </div>
</section>
<script>
(() => {
  const root = document.currentScript.previousElementSibling;
  const viewport = root?.querySelector('#marketPulseViewport');
  const tabs = [...(root?.querySelectorAll('.market-pulse-tab') || [])];
  const provenance = root?.querySelector('.market-pulse-provenance span');
  if (!root || !viewport) return;
  let active = 'us', groups = {}, changing = false;
  const svgNS = 'http://www.w3.org/2000/svg';
  const formatPrice = value => {
    const n = Number(value);
    if (!Number.isFinite(n)) return 'Unavailable';
    const digits = n >= 100 ? 2 : n >= 1 ? 2 : n >= .1 ? 4 : 6;
    return n.toLocaleString('en-US', {minimumFractionDigits: digits, maximumFractionDigits: digits});
  };
  function sparkline(points, positive) {
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 82 28'); svg.classList.add('market-pulse-spark');
    const usable = (Array.isArray(points) ? points : []).map(Number).filter(Number.isFinite);
    if (usable.length < 2) {
      const dot = document.createElementNS(svgNS, 'circle');
      dot.setAttribute('cx','41'); dot.setAttribute('cy','14'); dot.setAttribute('r','2.4');
      svg.appendChild(dot); return svg;
    }
    const min = Math.min(...usable), max = Math.max(...usable), range = max - min || 1;
    const polyline = document.createElementNS(svgNS, 'polyline');
    polyline.setAttribute('points', usable.map((v, i) =>
      `${(i/(usable.length-1)*80+1).toFixed(1)},${(26-(v-min)/range*24).toFixed(1)}`).join(' '));
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
        const text = document.createElement('div');
        const label = document.createElement('span'); label.textContent = row.label; text.appendChild(label);
        const price = document.createElement('b'); price.textContent = formatPrice(quote.price); text.appendChild(price);
        const changeLine = document.createElement('small');
        if (Number.isFinite(change) && Number.isFinite(pct)) {
          changeLine.textContent = `${change >= 0 ? '+' : '−'}${formatPrice(Math.abs(change))}  ${pct >= 0 ? '+' : '−'}${Math.abs(pct).toFixed(2)}%`;
        } else changeLine.textContent = 'Waiting for quote';
        text.appendChild(changeLine); card.appendChild(text);
        card.appendChild(sparkline(quote.spark, positive));
        const source = document.createElement('em');
        source.className = quote.source?.startsWith('Moomoo') ? 'broker' : 'backup';
        source.textContent = quote.source?.startsWith('Moomoo') ? 'M' : quote.source ? 'Y' : '—';
        source.setAttribute('aria-label', quote.source || 'Source unavailable'); card.appendChild(source);
        track.appendChild(card);
      });
      viewport.appendChild(track);
      viewport.classList.remove('switching'); changing = false;
    }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 150);
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
