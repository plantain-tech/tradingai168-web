<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }
$NAV_ACTIVE = $NAV_ACTIVE ?? ''; ?>
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
      <a href="monitor.php" class="<?= $NAV_ACTIVE === 'mon' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18v-2H5V3H3zm18 4-6 6-4-4-5 5 1.4 1.4L11 12l4 4 7.4-7.4L21 7z"/></svg>
        Monitor</a>
      <a href="markets.php" class="<?= $NAV_ACTIVE === 'mkt' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M3 5v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2zm2 0h14v3H5V5zm0 5h6v9H5v-9zm8 0h6v9h-6v-9z"/></svg>
        Markets</a>
      <a href="settings.php" class="<?= $NAV_ACTIVE === 'set' ? 'on' : '' ?>">
        <svg viewBox="0 0 24 24"><path d="M19.4 13a7.9 7.9 0 0 0 .1-1 7.9 7.9 0 0 0-.1-1l2.1-1.7a.5.5 0 0 0 .1-.6l-2-3.5a.5.5 0 0 0-.6-.2l-2.5 1a7.7 7.7 0 0 0-1.7-1l-.4-2.6a.5.5 0 0 0-.5-.4h-4a.5.5 0 0 0-.5.4l-.4 2.6c-.6.3-1.2.6-1.7 1l-2.5-1a.5.5 0 0 0-.6.2l-2 3.5a.5.5 0 0 0 .1.6L4.5 11a7.9 7.9 0 0 0 0 2l-2.1 1.7a.5.5 0 0 0-.1.6l2 3.5c.1.2.4.3.6.2l2.5-1c.5.4 1.1.8 1.7 1l.4 2.6c0 .2.2.4.5.4h4c.2 0 .5-.2.5-.4l.4-2.6c.6-.3 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5a.5.5 0 0 0-.1-.6L19.4 13zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z"/></svg>
        Settings</a>
    </nav>
    <a class="tb-out" href="logout.php" title="Log out">
      <svg viewBox="0 0 24 24"><path d="M10 17l1.4-1.4L8.8 13H18v-2H8.8l2.6-2.6L10 7l-5 5 5 5zm9-14H5a2 2 0 0 0-2 2v4h2V5h14v14H5v-4H3v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/></svg>
    </a>
  </div>
</header>
