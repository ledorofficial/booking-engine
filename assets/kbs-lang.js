(function () {
  // Close the language menu on an outside click or Escape.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details[data-kbs-lang-menu][open]').forEach(function (d) {
      if (!d.contains(e.target)) d.open = false;
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details[data-kbs-lang-menu][open]').forEach(function (d) {
      d.open = false;
      var s = d.querySelector('summary'); if (s) s.focus();
    });
  });
})();
