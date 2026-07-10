<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<!-- Shared trade-confirmation modal + one-click wiring -->
<div class="modal-back" id="modalBack" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-icon" id="modalIcon">⚡</div>
    <h3 id="modalTitle">Confirm</h3>
    <div class="modal-rows" id="modalRows"></div>
    <p class="muted small" id="modalNote"></p>
    <div class="modal-btns">
      <button class="btn ghost" id="modalCancel" type="button">Cancel</button>
      <button class="btn" id="modalOk" type="button">Confirm</button>
    </div>
  </div>
</div>
<script>
const _mb = document.getElementById('modalBack');
function tradeModal({title, icon, rows, note, okLabel, danger, onConfirm}) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalIcon').textContent = icon || '⚡';
  document.getElementById('modalNote').textContent = note || '';
  const ok = document.getElementById('modalOk');
  ok.textContent = okLabel || 'Confirm';
  ok.classList.toggle('danger', !!danger);
  document.getElementById('modalRows').innerHTML =
    rows.map(([k, v]) => `<div class="mrow"><span>${k}</span><b>${v}</b></div>`).join('');
  _mb.classList.add('open');
  const close = () => _mb.classList.remove('open');
  document.getElementById('modalCancel').onclick = close;
  _mb.onclick = e => { if (e.target === _mb) close(); };
  ok.onclick = async () => { ok.disabled = true; ok.textContent = 'Working…';
    try { await onConfirm(); } finally { ok.disabled = false; close(); } };
}
function lockButton(btn, hint) {
  btn.disabled = true;
  btn.classList.add('locked');
  btn.innerHTML = '<span class="lockdot"></span>' + hint;
}
async function queueCommand(action, ticker, csrf) {
  const r = await fetch('api/command.php', {method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action, ticker, csrf})});
  return (await r.json()).ok === true;
}
</script>
