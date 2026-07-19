<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }
$NAV_ACTIVE = $NAV_ACTIVE ?? '';
$SHOW_MARKET_PULSE = in_array($NAV_ACTIVE,
    ['dash', 'auto-paper', 'auto-live', 'paper', 'live'], true); ?>
<style>
/* Component-critical rules live with this shared navigation markup. Hostinger
   may briefly serve a cached parent PHP page with an older app.css version;
   keeping these rules here prevents mixed-version menu layout breakage. */
.topbar{position:fixed!important;top:0!important;left:0!important;right:0!important;width:100%!important}
body.has-topbar{padding-top:55px!important}
body.has-market-pulse{padding-top:139px!important}
.tb-nav .tb-menu{position:relative; display:block; flex:0 0 auto}
.tb-nav .tb-menu-trigger{display:flex!important; align-items:center!important;
  justify-content:center!important; gap:7px!important; width:auto!important;
  min-width:0!important; min-height:34px!important; height:34px!important;
  margin:0!important; padding:8px 14px!important; border:0!important;
  border-radius:10px!important; background:transparent!important;
  color:var(--muted)!important; font:13px 'Segoe UI',system-ui,-apple-system,sans-serif!important;
  line-height:1!important; white-space:nowrap!important; cursor:pointer!important;
  box-shadow:none!important; appearance:none!important; -webkit-appearance:none!important}
.tb-nav .tb-menu-trigger:hover{color:var(--ink)!important;
  background:rgba(255,255,255,.05)!important}
.tb-nav .tb-menu-trigger.on{color:var(--ink)!important;
  background:linear-gradient(100deg,rgba(99,102,241,.22),rgba(34,211,238,.18))!important;
  box-shadow:inset 0 0 0 1px rgba(99,102,241,.35)!important}
.tb-nav .tb-menu-trigger svg{display:block!important; width:15px!important;
  min-width:15px!important; max-width:15px!important; height:15px!important;
  min-height:15px!important; max-height:15px!important; fill:currentColor!important;
  opacity:.8!important; flex:0 0 15px!important}
.tb-nav .tb-menu-trigger .tb-chevron{width:10px!important; min-width:10px!important;
  max-width:10px!important; flex-basis:10px!important; margin-left:-2px!important}
.tb-nav .tb-submenu{display:block!important; visibility:hidden!important;
  opacity:0!important; pointer-events:none!important; position:absolute!important;
  top:100%!important; left:50%!important; width:176px!important; height:auto!important;
  padding:8px 0 0!important; margin:0!important; transform:translate(-50%,5px)!important;
  z-index:80!important; transition:opacity .14s ease,transform .14s ease,visibility .14s!important}
.tb-nav .tb-menu:hover .tb-submenu,.tb-nav .tb-menu:focus-within .tb-submenu,
.tb-nav .tb-menu.is-open .tb-submenu{visibility:visible!important; opacity:1!important;
  pointer-events:auto!important; transform:translate(-50%,0)!important}
.tb-nav .tb-submenu-panel{display:grid!important; gap:3px!important; padding:7px!important;
  border:1px solid var(--line)!important; border-radius:13px!important;
  background:rgba(15,20,35,.98)!important; box-shadow:0 18px 48px rgba(0,0,0,.45)!important}
.tb-nav .tb-submenu a{display:flex!important; align-items:center!important; gap:10px!important;
  height:auto!important; min-height:42px!important; padding:8px 10px!important;
  border-radius:9px!important; background:transparent!important; color:var(--muted)!important;
  text-decoration:none!important; box-shadow:none!important}
.tb-nav .tb-submenu a:hover,.tb-nav .tb-submenu a.on{color:var(--ink)!important;
  background:rgba(255,255,255,.06)!important}
.tb-nav .tb-submenu a.on{box-shadow:inset 3px 0 0 var(--brand2)!important}
.tb-nav .tb-submenu a svg{display:block!important; width:16px!important; height:16px!important;
  min-width:16px!important; max-width:16px!important; flex:0 0 16px!important;
  fill:currentColor!important}
.tb-nav .tb-submenu span{display:grid!important; gap:1px!important; text-align:left!important}
.tb-nav .tb-submenu b{font-size:13px!important; line-height:1.2!important}
.tb-nav .tb-submenu small{font-size:10px!important; line-height:1.2!important;
  color:var(--muted)!important; font-weight:400!important}
@media(max-width:560px){
  .tb-nav .tb-menu-trigger{padding:8px 10px!important}
  .tb-nav .tb-menu-trigger>svg:first-child{display:none!important}
  .tb-nav .tb-submenu{left:auto!important;right:0!important;transform:translateY(5px)!important}
  .tb-nav .tb-menu:hover .tb-submenu,.tb-nav .tb-menu:focus-within .tb-submenu,
  .tb-nav .tb-menu.is-open .tb-submenu{transform:translateY(0)!important}
}
</style>
<header class="topbar">
  <div class="topbar-in">
    <a class="tb-brand" href="index.php" aria-label="Trading AI Horizon">
      <svg width="26" height="26" viewBox="0 0 48 48" aria-hidden="true">
        <defs><linearGradient id="nvhz" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
        <rect x="3" y="3" width="42" height="42" rx="12" fill="none" stroke="url(#nvhz)" stroke-width="3"/>
        <circle cx="24" cy="27" r="7" fill="url(#nvhz)"/>
        <line x1="12" y1="33" x2="36" y2="33" stroke="#0b0f1a" stroke-width="3.5"/>
      </svg>
      <span>Trading&nbsp;AI&nbsp;<b>Horizon</b></span>
    </a>
    <nav class="tb-nav">
      <a href="index.php" class="<?= $NAV_ACTIVE === 'dash' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        Dashboard</a>
      <div class="tb-menu">
        <button type="button" class="tb-menu-trigger <?= in_array($NAV_ACTIVE, ['auto-paper', 'auto-live'], true) ? 'on' : '' ?>"
                aria-haspopup="true" aria-expanded="false">
          <svg width="15" height="15" viewBox="0 0 24 24"><path d="M3 3v18h18v-2H5V3H3zm18 4-6 6-4-4-5 5 1.4 1.4L11 12l4 4 7.4-7.4L21 7z"/></svg>
          Auto Trade
          <svg class="tb-chevron" width="10" height="15" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5z"/></svg>
        </button>
        <div class="tb-submenu">
          <div class="tb-submenu-panel">
            <a href="monitor.php" class="<?= $NAV_ACTIVE === 'auto-paper' ? 'on' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V5zm0 5h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9zm5 3v2h6v-2H9z"/></svg>
              <span><b>Paper</b><small>Moomoo SIM</small></span>
            </a>
            <a href="auto_trade_live.php" class="<?= $NAV_ACTIVE === 'auto-live' ? 'on' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7v3H3v9h18v-9h-2V9a7 7 0 0 0-7-7zm-5 10V9a5 5 0 0 1 10 0v3H7zm5 3a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/></svg>
              <span><b>Live</b><small>Preparation only</small></span>
            </a>
          </div>
        </div>
      </div>
      <a href="markets.php" class="<?= $NAV_ACTIVE === 'mkt' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 5v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2zm2 0h14v3H5V5zm0 5h6v9H5v-9zm8 0h6v9h-6v-9z"/></svg>
        Markets</a>
      <div class="tb-menu">
        <button type="button" class="tb-menu-trigger <?= in_array($NAV_ACTIVE, ['paper', 'live'], true) ? 'on' : '' ?>"
                aria-haspopup="true" aria-expanded="false">
          <svg width="15" height="15" viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 5h16V6H4v3zm3 4v2h5v-2H7z"/></svg>
          Accounts
          <svg class="tb-chevron" width="10" height="15" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5z"/></svg>
        </button>
        <div class="tb-submenu">
          <div class="tb-submenu-panel">
            <a href="account_paper.php" class="<?= $NAV_ACTIVE === 'paper' ? 'on' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V5zm0 5h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-9zm5 3v2h6v-2H9z"/></svg>
              <span><b>Paper</b><small>Read only</small></span>
            </a>
            <a href="account_live.php" class="<?= $NAV_ACTIVE === 'live' ? 'on' : '' ?>">
              <svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7v3H3v9h18v-9h-2V9a7 7 0 0 0-7-7zm-5 10V9a5 5 0 0 1 10 0v3H7zm5 3a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/></svg>
              <span><b>Live</b><small>Read only</small></span>
            </a>
          </div>
        </div>
      </div>
      <a href="settings.php" class="<?= $NAV_ACTIVE === 'set' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M19.4 13a7.9 7.9 0 0 0 .1-1 7.9 7.9 0 0 0-.1-1l2.1-1.7a.5.5 0 0 0 .1-.6l-2-3.5a.5.5 0 0 0-.6-.2l-2.5 1a7.7 7.7 0 0 0-1.7-1l-.4-2.6a.5.5 0 0 0-.5-.4h-4a.5.5 0 0 0-.5.4l-.4 2.6c-.6.3-1.2.6-1.7 1l-2.5-1a.5.5 0 0 0-.6.2l-2 3.5a.5.5 0 0 0 .1.6L4.5 11a7.9 7.9 0 0 0 0 2l-2.1 1.7a.5.5 0 0 0-.1.6l2 3.5c.1.2.4.3.6.2l2.5-1c.5.4 1.1.8 1.7 1l.4 2.6c0 .2.2.4.5.4h4c.2 0 .5-.2.5-.4l.4-2.6c.6-.3 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5a.5.5 0 0 0-.1-.6L19.4 13zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z"/></svg>
        Settings</a>
    </nav>
    <a class="tb-out" href="logout.php" title="Log out">
      <svg viewBox="0 0 24 24"><path d="M10 17l1.4-1.4L8.8 13H18v-2H8.8l2.6-2.6L10 7l-5 5 5 5zm9-14H5a2 2 0 0 0-2 2v4h2V5h14v14H5v-4H3v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/></svg>
    </a>
  </div>
</header>
<?php if ($SHOW_MARKET_PULSE) { require __DIR__ . '/market_pulse.php'; } ?>
<script>
(() => {
  document.body.classList.add('has-topbar');
  <?php if ($SHOW_MARKET_PULSE): ?>document.body.classList.add('has-market-pulse');<?php endif; ?>
  const menus = [...document.querySelectorAll('.tb-menu')];
  const setOpen = (menu, open) => {
    menu.classList.toggle('is-open', open);
    menu.querySelector('.tb-menu-trigger')?.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  menus.forEach(menu => menu.querySelector('.tb-menu-trigger')?.addEventListener('click', e => {
    e.stopPropagation();
    const next = !menu.classList.contains('is-open');
    menus.forEach(other => setOpen(other, other === menu && next));
  }));
  document.addEventListener('click', e => {
    if (!menus.some(menu => menu.contains(e.target))) menus.forEach(menu => setOpen(menu, false));
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') menus.forEach(menu => setOpen(menu, false));
  });
})();
</script>
