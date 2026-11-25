// Feedback while something happens (R-64 phase 10): a button that says it is working and cannot be pressed twice, and one consistent question before
// anything that cannot be undone. Both are enhancements of ordinary forms: without this file every form submits and every action goes ahead exactly as before.
//
//   data-confirm="Question?"       on a <form>: asks first, in a dialog. data-confirm-label names the button that goes ahead, data-cancel-label the one that stops.
//   data-no-busy                   on a <form>: opts out of the working state (for a form whose answer is a file, not a new page)

const BUSY_MS = 12000; // if the page has not changed by then (a download, a failure), the button is given back

let dialog = null;
let pending = null;

function busy(form, button) {
    if (!button || button.getAttribute('aria-busy') === 'true') return;

    const label = button.textContent;
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('aria-disabled', 'true');
    button.classList.add('is-busy');
    button.dataset.label = label;
    form.dataset.sending = '1';

    const release = () => {
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
        button.classList.remove('is-busy');
        delete form.dataset.sending;
    };

    const restored = (event) => {
        if (!event.persisted) return;
        window.removeEventListener('pageshow', restored);
        release();
    };

    window.setTimeout(() => {
        window.removeEventListener('pageshow', restored);
        release();
    }, BUSY_MS);
    window.addEventListener('pageshow', restored);
}

function answer(go) {
    const ask = pending;
    pending = null;
    if (!ask) return;

    dialog.close();

    if (go) {
        ask.form.dataset.confirmed = '1';
        ask.form.requestSubmit(ask.submitter ?? undefined);
    } else {
        ask.opener?.focus?.();
    }
}

function ensureDialog() {
    if (dialog?.isConnected) return dialog;

    dialog = document.createElement('dialog');
    dialog.className = 'ix-confirm';
    dialog.setAttribute('aria-labelledby', 'ix-confirm-text');
    dialog.innerHTML = '<div class="ix-confirm__box"><p id="ix-confirm-text" class="ix-confirm__text"></p><div class="ix-confirm__acts"><button type="button" class="ix-btn" data-cancel>Go back</button><button type="button" class="ix-btn ix-btn--danger" data-go>Yes</button></div></div>';
    document.body.appendChild(dialog);

    dialog.querySelector('[data-cancel]').addEventListener('click', () => answer(false));
    dialog.querySelector('[data-go]').addEventListener('click', () => answer(true));
    // Escape closes the dialog by itself, and that is a "no".
    dialog.addEventListener('cancel', () => {
        pending?.opener?.focus?.();
        pending = null;
    });

    return dialog;
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    // Ask first. The question is a real dialog: focus starts on the safe answer, Escape means no, and a screen reader hears it as a dialog.
    if (form.dataset.confirm && form.dataset.confirmed !== '1') {
        event.preventDefault();
        const box = ensureDialog();
        pending = { form, submitter: event.submitter, opener: document.activeElement };
        box.querySelector('.ix-confirm__text').textContent = form.dataset.confirm;
        box.querySelector('[data-go]').textContent = form.dataset.confirmLabel || 'Yes';
        box.querySelector('[data-cancel]').textContent = form.dataset.cancelLabel || 'Go back';
        box.showModal();
        box.querySelector('[data-cancel]').focus();
        return;
    }
    delete form.dataset.confirmed;
}, true);

// After everything else has had its say (Livewire takes its own forms and prevents the default): a form that is really going to the server shows it.
document.addEventListener('submit', (event) => {
    const form = event.target;
    // A form that does its own working-state (Alpine's x-data on the form itself, as on the sign-in pages), opens a new tab or window, or is marked data-no-busy keeps its own behaviour.
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.hasAttribute('data-no-busy') || form.hasAttribute('x-data') || form.target === '_blank' || event.submitter?.formTarget === '_blank') return;

    if (form.dataset.sending === '1') {
        event.preventDefault(); // a second press while the first is on its way
        return;
    }

    busy(form, event.submitter ?? form.querySelector('button[type="submit"], button:not([type])'));
});

// A button that is busy ignores clicks, so a double press cannot send twice even before the submit event.
document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('button[aria-busy="true"]') : null;
    if (button) event.preventDefault();
}, true);

// If the connection itself fails while a page is open (the laptop went to sleep, the train went into a tunnel), say so once, plainly. Other failures keep
// Livewire's own handling.
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('request', ({ fail }) => {
        fail(({ status }) => {
            if (status === 0) {
                window.dispatchEvent(new CustomEvent('ix-toast', { detail: { key: 'offline', label: 'Connection', text: 'Could not reach InfoDot. Check your connection and try again. Nothing was lost.', tone: 'warn' } }));
            }
        });
    });
});
