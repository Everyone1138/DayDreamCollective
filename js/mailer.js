document.addEventListener('DOMContentLoaded', function() {
    // Simple Day Dream Collective modal
    function showDDCModal(title, message) {
        const existing = document.getElementById('ddc-modal-overlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'ddc-modal-overlay';
        overlay.className =
            'fixed inset-0 flex items-center justify-center bg-black/60 z-50';

        overlay.innerHTML = `
      <div id="ddc-modal-card"
           class="bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl max-w-md mx-4 p-8 text-center
                  transform transition-all duration-200 scale-95 opacity-0">
        <div class="mx-auto mb-4 w-14 h-14 rounded-full border border-emerald-400 flex items-center justify-center">
          <i class="fa-solid fa-check text-2xl text-emerald-500"></i>
        </div>
        <h3 class="text-2xl font-semibold text-gray-900 mb-2">${title}</h3>
        <p class="text-gray-600 mb-6">${message}</p>
        <button id="ddc-modal-close"
                class="px-6 py-2 rounded-full bg-emerald-500 text-white font-medium hover:bg-emerald-600 transition-colors duration-200">
          Continue
        </button>
      </div>
    `;

        document.body.appendChild(overlay);
        const card = document.getElementById('ddc-modal-card');

        requestAnimationFrame(() => {
            card.classList.remove('scale-95', 'opacity-0');
            card.classList.add('scale-100', 'opacity-100');
        });

        function close() {
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => overlay.remove(), 150);
        }

        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) close();
        });
        document
            .getElementById('ddc-modal-close')
            .addEventListener('click', close);
    }

    function attachFormHandler(options) {
        const form = document.getElementById(options.formId);
        const statusEl = document.getElementById(options.statusId);
        if (!form || !statusEl) return;

        const submitBtn = form.querySelector('[type="submit"]');

        function setStatus(text, type) {
            statusEl.textContent = text || '';
            statusEl.className = 'text-sm mt-2';
            if (type === 'error') statusEl.classList.add('text-red-500');
            else if (type === 'success') statusEl.classList.add('text-green-500');
            else statusEl.classList.add('text-gray-500');
        }

        async function doSubmit() {
            setStatus('Sending...', 'info');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            const formData = new FormData(form);

            try {
                const res = await fetch(options.url, {
                    method: 'POST',
                    body: formData,
                });

                let data;
                try {
                    data = await res.json();
                } catch (e) {
                    data = { ok: false, error: 'Unexpected response from server.' };
                }

                if (data.ok) {
                    setStatus(options.successText, 'success');
                    if (options.resetOnSuccess) form.reset();
                    if (options.modalTitle && options.modalMessage) {
                        showDDCModal(options.modalTitle, options.modalMessage);
                    }
                } else {
                    setStatus(
                        data.error || 'Something went wrong. Please try again.',
                        'error'
                    );
                }
            } catch (e) {
                setStatus('Network error. Please try again.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            }
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            doSubmit();
        });
    }

    // Contact form (matches <form id="contact-form"> in index.html)
    attachFormHandler({
        formId: 'contact-form',
        statusId: 'contact-status',
        url: 'contact.php',
        successText: 'Message sent! Thank you for reaching out.',
        resetOnSuccess: true,
        modalTitle: 'Message sent ✨',
        modalMessage: 'Thank you for contacting Day Dream Collective. Your message was received, and we’ll get back to you as soon as possible.',
    });

    // Newsletter/subscribe form (matches <form id="subscribe-form">)
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