<?php if (!defined('APP')) { http_response_code(403); exit('Forbidden'); } ?>
<div class="brand-footer" aria-label="Trading AI Horizon">
  <img src="favicon.png?v=2" width="44" height="44" alt="Trading AI Horizon logo">
</div>
<button class="scroll-top" type="button" aria-label="Back to the top" title="Back to the top">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 14 6-6 6 6-1.4 1.4-3.6-3.6V21h-2v-9.2l-3.6 3.6z"/></svg>
</button>
<script>
(() => {
  const button = document.currentScript.previousElementSibling;
  if (!button || !button.classList.contains('scroll-top')) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const update = () => button.classList.toggle('visible', window.scrollY > 320);
  button.addEventListener('click', () => window.scrollTo({top: 0,
    behavior: reduced.matches ? 'auto' : 'smooth'}));
  window.addEventListener('scroll', update, {passive: true});
  update();
})();
</script>
