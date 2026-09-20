'use strict';
document.addEventListener('submit', event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') return;
  if (form.dataset.submitting) { event.preventDefault(); return; }
  form.dataset.submitting = 'true';
  form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-submitting]').forEach(form => {
    delete form.dataset.submitting;
    form.querySelectorAll('button').forEach(button => { button.disabled = false; });
  });
});
