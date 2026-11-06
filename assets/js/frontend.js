/**
 * NZ Lead Capture — AJAX submission with a plain-POST fallback.
 * If JavaScript is off, the form simply posts normally (see class-nzlc-form.php).
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    var forms = document.querySelectorAll('.nzlc-form');
    if (!forms.length || typeof NZLC === 'undefined') {
      return;
    }

    forms.forEach(function (form) {
      var notice = form.querySelector('.nzlc-notice');
      var button = form.querySelector('.nzlc-submit');

      function show(message, ok) {
        notice.hidden = false;
        notice.textContent = message;
        notice.classList.toggle('ok', !!ok);
        notice.classList.toggle('err', !ok);
        notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (button) {
          button.disabled = true;
        }
        var data = new FormData(form);
        data.append('action', 'nzlc_submit');
        data.append('nonce', NZLC.nonce);

        fetch(NZLC.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
          .then(function (res) { return res.json(); })
          .then(function (json) {
            if (json && json.success) {
              show(json.data.message || 'Thanks! We will be in touch.', true);
              form.reset();
            } else {
              var errs = (json && json.data && json.data.errors) || ['Something went wrong.'];
              show(errs.join(' '), false);
            }
          })
          .catch(function () {
            // AJAX failed (e.g. blocked endpoint): fall back to a plain POST.
            form.submit();
          })
          .finally(function () {
            if (button) {
              button.disabled = false;
            }
          });
      });
    });
  });
})();
