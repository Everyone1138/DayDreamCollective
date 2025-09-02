document.addEventListener('DOMContentLoaded', function() {
    // ... keep your existing code above

    // CONTACT FORM AJAX
    const contactForm = document.getElementById('contact-form');
    const contactStatus = document.getElementById('contact-status');

    contactForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        contactStatus.textContent = 'Sending...';

        const formData = new FormData(contactForm);

        try {
            const res = await fetch(contactForm.action, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                contactStatus.className = 'text-green-600 text-sm mt-2';
                contactStatus.textContent = 'Thanks! Your message has been sent.';
                contactForm.reset();
            } else {
                contactStatus.className = 'text-red-600 text-sm mt-2';
                contactStatus.textContent = data.error || 'Sorry, something went wrong.';
            }
        } catch (err) {
            contactStatus.className = 'text-red-600 text-sm mt-2';
            contactStatus.textContent = 'Network error. Please try again.';
        }
    });

    // SUBSCRIBE FORM AJAX
    const subscribeForm = document.getElementById('subscribe-form');
    const subscribeStatus = document.getElementById('subscribe-status');

    subscribeForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        subscribeStatus.textContent = 'Subscribing...';

        const formData = new FormData(subscribeForm);

        try {
            const res = await fetch(subscribeForm.action, { method: 'POST', body: formData });
            const data = await res.json();
            if (data.ok) {
                subscribeStatus.className = 'text-green-200 text-sm mt-2';
                subscribeStatus.textContent = 'You’re subscribed!';
                subscribeForm.reset();
            } else {
                subscribeStatus.className = 'text-red-200 text-sm mt-2';
                subscribeStatus.textContent = data.error || 'Could not subscribe.';
            }
        } catch (err) {
            subscribeStatus.className = 'text-red-200 text-sm mt-2';
            subscribeStatus.textContent = 'Network error. Please try again.';
        }
    });
});