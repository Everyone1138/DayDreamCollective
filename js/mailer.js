document.addEventListener('DOMContentLoaded', function() {
    // Fancy success modal (E)
    function showDDCModal(title, message) {
        const existing = document.getElementById('ddc-modal-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'ddc-modal-overlay';
        overlay.className = 'fixed inset-0 flex items-center justify-center bg-black/60 z-50';

        overlay.innerHTML = `
      <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl max-w-md mx-4 p-8 text-center transform transition-all duration-300 scale-95 opacity-0">
        <div class="mx-auto mb-4 w-14 h-14 rounded-full border border-emerald-400 flex items-center justify-center">
          <i class="fa-solid fa-sparkles text-2xl text-emerald-500"></i>
        </div>
        <h3 class="text-2xl font-semibold text-gray-900 mb-2">${title}</h3>
        <p class="text-gray-600 mb-6">${message}</p>
        <button id="ddc-modal-close" class="px-6 py-2 rounded-full bg-emerald-500 text-white font-medium hover:bg-emerald-600 transition-colors duration-200">
          Continue
        </button>
      </div>
    `;

        document.body.appendChild(overlay);

        const card = overlay.querySelector('div');
        requestAnimationFrame(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });

        function close() {
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => overlay.remove(), 200);
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
        overlay.querySelector('#ddc-modal-close').addEventListener('click', close);
    }

    // Generic handler used by both forms (A, B, G)
    function attachFormHandler(opts) {
        const form = document.getElementById(opts.formId);
        const statusEl = document.getElementById(opts.statusId);
        if (!form || !statusEl) return;

        const submitBtn = form.querySelector('[type="submit"]');

        function setStatus(text, type) {
            statusEl.textContent = text || '';
            statusEl.classList.remove('text-red-500', 'text-green-500', 'text-gray-500');
            if (type === 'error') statusEl.classList.add('text-red-500');
            else if (type === 'success') statusEl.classList.add('text-green-500');
            else if (type === 'info') statusEl.classList.add('text-gray-500');
        }

        function doSubmit() {
            setStatus('Sending...', 'info');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            const formData = new FormData(form);

            fetch(opts.url, {
                    method: 'POST',
                    body: formData,
                })
                .then((res) => res.json().catch(() => ({ ok: false, error: 'Unexpected response from server.' })))
                .then((data) => {
                    if (data.ok) {
                        setStatus(opts.successText, 'success');
                        if (opts.resetOnSuccess) form.reset();
                        if (opts.modalTitle && opts.modalMessage) {
                            showDDCModal(opts.modalTitle, opts.modalMessage);
                        }
                    } else {
                        setStatus(data.error || 'Something went wrong. Please try again.', 'error');
                    }
                })
                .catch(() => {
                    setStatus('Network error. Please try again.', 'error');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                    }
                });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // OPTIONAL reCAPTCHA v3 (G):
            // 1. In index.html, include:
            //    <script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
            // 2. Replace YOUR_RECAPTCHA_SITE_KEY below with your real site key.
            const siteKey = 'YOUR_RECAPTCHA_SITE_KEY';
            if (window.grecaptcha && siteKey && siteKey !== 'YOUR_RECAPTCHA_SITE_KEY') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, { action: opts.formId }).then(function(token) {
                        let tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
                        if (!tokenInput) {
                            tokenInput = document.createElement('input');
                            tokenInput.type = 'hidden';
                            tokenInput.name = 'g-recaptcha-response';
                            form.appendChild(tokenInput);
                        }
                        tokenInput.value = token;
                        doSubmit();
                    });
                });
            } else {
                // No reCAPTCHA configured yet – just submit normally
                doSubmit();
            }
        });
    }

    // Contact form (A, D, E)
    attachFormHandler({
        formId: 'contact-form',
        statusId: 'contact-status',
        url: 'contact.php',
        successText: 'Message sent! Thank you for reaching out.',
        resetOnSuccess: true,
        modalTitle: 'Message sent ✨',
        modalMessage: 'Thank you for contacting Day Dream Collective. Your message was received, and we’ll get back to you as soon as possible.',
    });

    // Newsletter subscribe form (B, D, E)
    attachFormHandler({
        formId: 'subscribe-form',
        statusId: 'subscribe-status',
        url: 'subscribe.php',
        successText: 'You’re subscribed! 🎉',
        resetOnSuccess: true,
        modalTitle: 'Welcome to the dream ✨',
        modalMessage: 'You’re now part of Day Dream Collective’s inner circle. Thank you for joining the journey.',
    });
});