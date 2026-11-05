document.addEventListener('click', (event) => {
  if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
  const link = event.target.closest?.('a[data-maotk-r2-public]');
  if (!link || link.origin !== window.location.origin) return;

  event.preventDefault();
  const form = document.createElement('form');
  form.method = 'post';
  form.action = link.href;
  form.hidden = true;
  document.body.append(form);
  form.submit();
});
