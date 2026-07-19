<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<style id="marketPulseRightRailCritical">
/* Cache-independent component geometry. Hostinger can briefly combine fresh
   PHP markup with an older app.css; the v2 prefix prevents the retired
   full-width strip rules from winning during that mixed-version window. */
.market-pulse-v2 .market-pulse-dock{position:fixed;top:104px;right:24px;left:auto;bottom:auto;z-index:50;
  display:flex;flex-direction:column;width:306px;min-width:0;max-width:none;height:auto;max-height:calc(100vh - 132px);
  overflow:hidden;border:1px solid rgba(99,102,241,.28);border-radius:18px;
  background:linear-gradient(155deg,rgba(38,31,70,.96),rgba(10,24,37,.97));
  box-shadow:0 22px 58px rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.055);
  backdrop-filter:blur(20px) saturate(1.25);-webkit-backdrop-filter:blur(20px) saturate(1.25)}
.market-pulse-v2 .market-pulse-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:15px 16px 11px}
.market-pulse-v2 .market-pulse-head span{display:block;color:var(--brand2);font-size:9px;font-weight:900;letter-spacing:.16em}
.market-pulse-v2 .market-pulse-head strong{display:block;margin-top:4px;color:var(--ink);font-size:12px;line-height:1.25}
.market-pulse-v2 .market-pulse-close{display:none;width:32px;height:32px;border:1px solid var(--line);border-radius:10px;background:rgba(255,255,255,.035);color:var(--muted);cursor:pointer}
.market-pulse-v2 .market-pulse-close svg{width:16px;height:16px;fill:currentColor}
.market-pulse-v2 .market-pulse-switch{display:grid;grid-template-columns:1fr 1fr;align-content:normal;gap:5px;flex:none;margin:0 14px 8px;padding:4px;border:1px solid rgba(255,255,255,.07);border-radius:11px;background:rgba(4,8,17,.28)}
.market-pulse-v2 .market-pulse-tab{display:flex;align-items:center;justify-content:center;gap:6px;min-height:34px;padding:7px 8px;border:1px solid transparent;border-radius:8px;background:transparent;color:var(--muted);font:700 10px 'Segoe UI',system-ui,sans-serif;cursor:pointer}
.market-pulse-v2 .market-pulse-tab svg{width:14px;height:14px;fill:currentColor}.market-pulse-v2 .market-pulse-tab.active{color:#cffafe;border-color:rgba(34,211,238,.22);background:linear-gradient(110deg,rgba(99,102,241,.24),rgba(34,211,238,.13))}
.market-pulse-v2 .market-pulse-provenance{display:flex;align-items:center;justify-content:center;gap:6px;grid-column:auto;flex:none;padding:0 14px 9px;color:var(--muted);font-size:8.5px;letter-spacing:.025em;white-space:nowrap}
.market-pulse-v2 .market-pulse-provenance>i{width:5px;height:5px;border-radius:50%;background:var(--ok);box-shadow:0 0 8px var(--ok)}
.market-pulse-v2 .market-pulse-viewport{display:block;min-width:0;min-height:0;flex:1 1 auto;overflow-x:hidden;overflow-y:auto;margin:0 8px;opacity:1;scrollbar-width:thin;scrollbar-color:rgba(99,102,241,.38) transparent}
.market-pulse-v2 .market-pulse-viewport.switching{opacity:.12;transform:translateX(5px)}
.market-pulse-v2 .market-pulse-track{display:grid;align-items:initial;width:auto;min-width:0;height:auto;gap:4px;padding:0 4px 4px}
.market-pulse-v2 .market-pulse-item{position:relative;display:grid;grid-template-columns:minmax(0,1fr) 73px;
  grid-template-areas:'name spark' 'price spark' 'change source';column-gap:9px;align-items:center;
  flex:none;min-width:0;max-width:none;min-height:58px;padding:8px 10px;border:1px solid transparent;
  border-bottom-color:rgba(255,255,255,.065);border-radius:10px;background:rgba(255,255,255,.012)}
.market-pulse-v2 .market-pulse-name{grid-area:name;display:block;overflow:hidden;color:#93c5fd;font-size:9.5px;font-weight:760;white-space:nowrap;text-overflow:ellipsis}
.market-pulse-v2 .market-pulse-price{grid-area:price;display:block;margin:2px 0 0;color:var(--ink);font-size:14px;font-variant-numeric:tabular-nums}
.market-pulse-v2 .market-pulse-change{grid-area:change;display:block;margin-top:2px;font-size:9.5px;font-weight:680;font-variant-numeric:tabular-nums;white-space:nowrap}
.market-pulse-v2 .market-pulse-item.positive .market-pulse-change{color:var(--ok)}.market-pulse-v2 .market-pulse-item.negative .market-pulse-change{color:#fb7185}
.market-pulse-v2 .market-pulse-item>em{position:static;grid-area:source;justify-self:end;display:grid;place-items:center;width:16px;height:16px;border-radius:50%;font-style:normal;font-size:7px;font-weight:900}
.market-pulse-v2 .market-pulse-item>em.broker{color:#6ee7b7;border:1px solid rgba(52,211,153,.3);background:rgba(52,211,153,.1)}
.market-pulse-v2 .market-pulse-item>em.backup{color:#fde68a;border:1px solid rgba(251,191,36,.28);background:rgba(251,191,36,.09)}
.market-pulse-v2 .market-pulse-spark{grid-area:spark;flex:none;width:72px;height:25px;overflow:visible}.market-pulse-v2 .market-pulse-spark polyline{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.market-pulse-v2 .market-pulse-item.positive .market-pulse-spark{color:#10b981}.market-pulse-v2 .market-pulse-item.negative .market-pulse-spark{color:#f43f5e}
.market-pulse-v2 .market-pulse-loading{display:flex;align-items:center;justify-content:center;gap:9px;height:auto;min-height:220px;color:var(--muted);font-size:10px}
.market-pulse-v2 .market-pulse-foot{display:flex;align-items:center;gap:10px;flex:none;padding:9px 14px 11px;border-top:1px solid rgba(255,255,255,.07);color:var(--muted);font-size:8px}
.market-pulse-v2 .market-pulse-foot span{display:flex;align-items:center;gap:4px}.market-pulse-v2 .market-pulse-foot small{margin-left:auto;font-size:8px}.market-pulse-v2 .market-pulse-foot i{width:5px;height:5px;border-radius:50%}
.market-pulse-v2 .market-pulse-launch,.market-pulse-v2 .market-pulse-scrim{display:none}
@media(max-width:1439px){
  .market-pulse-v2 .market-pulse-dock{top:55px;right:0;left:auto;bottom:0;z-index:91;width:min(350px,calc(100vw - 18px));height:auto;max-height:none;border-radius:18px 0 0 18px;transform:translateX(104%);transition:transform .26s cubic-bezier(.2,.8,.2,1)}
  .market-pulse-v2.open .market-pulse-dock{transform:none}.market-pulse-v2 .market-pulse-close{display:grid;place-items:center;flex:0 0 32px}
  .market-pulse-v2 .market-pulse-launch{position:fixed;top:76px;right:14px;left:auto;z-index:65;display:flex;align-items:center;gap:7px;min-height:39px;padding:8px 11px;border:1px solid rgba(34,211,238,.28);border-radius:12px;background:rgba(15,20,35,.91);color:#bae6fd;box-shadow:0 12px 30px rgba(0,0,0,.3);font:750 10px 'Segoe UI',system-ui,sans-serif;cursor:pointer}
  .market-pulse-v2 .market-pulse-launch svg{width:16px;height:16px;fill:currentColor}.market-pulse-v2.open .market-pulse-launch{opacity:0;pointer-events:none}
  .market-pulse-v2 .market-pulse-scrim{position:fixed;inset:55px 0 0;z-index:90;display:block;background:rgba(3,7,15,.42);opacity:0;pointer-events:none}.market-pulse-v2.open .market-pulse-scrim{opacity:1;pointer-events:auto}}
@media(max-width:430px){.market-pulse-v2 .market-pulse-launch{right:10px;padding:8px}.market-pulse-v2 .market-pulse-launch span{display:none}}
@media(max-height:680px) and (min-width:1440px){.market-pulse-v2 .market-pulse-dock{top:68px;right:14px;max-height:calc(100vh - 80px)}.market-pulse-v2 .market-pulse-item{min-height:53px;padding-top:6px;padding-bottom:6px}.market-pulse-v2 .market-pulse-foot{display:none}}
</style>
<div class="market-pulse-widget market-pulse-v2">
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
