// R-71: saves the cookie banner's answer without reloading the page. Without this file the form simply posts and the page comes back;
// if the request fails for any reason the form is submitted the ordinary way, so a choice is never lost.
function start() {
    const banner = document.querySelector('[data-cookie-banner]');
    const form = banner?.querySelector('[data-cookie-form]');
    if (!banner || !form || !window.fetch) return;

    form.addEventListener('submit', async (event) => {
        const button = event.submitter;
        if (!button || !button.name) return;
        event.preventDefault();

        const body = new FormData(form);
        body.set(button.name, button.value);
        form.querySelectorAll('button').forEach((b) => { b.disabled = true; });

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            banner.remove();
        } catch (error) {
            form.querySelectorAll('button').forEach((b) => { b.disabled = false; });
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = button.name;
            field.value = button.value;
            form.appendChild(field);
            form.submit();
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();
