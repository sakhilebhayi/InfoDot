import './bootstrap';
import './attachments';
import './mentions';
import './business-media';
import Alpine from 'alpinejs';

// Platform logos are emitted into the build (public/build is the one folder every host serves from),
// so Blade can reach them with Vite::asset().
import.meta.glob(['../img/platforms/*.png', '../img/apps/*.png', '../img/landing/*.jpg', '../img/landing/platforms/*.png', '../img/arrow-mask.png']);

// Livewire 3 bundles and starts its own Alpine on every page that loads it. Starting a second one
// makes them fight, so this bundle starts Alpine only on pages that do not have Livewire
// (the public home page and the error pages).
if (!window.Livewire) {
    window.Alpine = Alpine;
    Alpine.start();
}

// Livewire switches a form's submit button off while the request runs, and the browser then drops focus to the page.
// A keyboard user who pressed Enter on "Send report" would land back at the top of the page. Once the answer has
// been drawn, put focus back on the same button, or on the status message when the form went away.
document.addEventListener('livewire:init', () => {
    let sent = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        const root = form instanceof HTMLFormElement ? form.closest('[wire\\:id]') : null;

        if (!root || ![...form.attributes].some((attribute) => attribute.name.startsWith('wire:submit'))) {
            return;
        }

        const buttons = [...form.querySelectorAll('button')].filter((button) => button.type === 'submit');

        sent = {
            id: root.getAttribute('wire:id'),
            form: [...root.querySelectorAll('form')].indexOf(form),
            button: Math.max(buttons.indexOf(event.submitter), 0),
            at: Date.now(),
        };
    }, true);

    window.Livewire.hook('morphed', ({ component }) => {
        if (!sent || sent.id !== component.id) {
            return;
        }

        const pending = sent;
        sent = null;

        if (Date.now() - pending.at > 15000 || (document.activeElement && document.activeElement !== document.body)) {
            return;
        }

        const form = component.el.querySelectorAll('form')[pending.form];
        const button = form ? [...form.querySelectorAll('button')].filter((candidate) => candidate.type === 'submit')[pending.button] : null;

        button?.focus();

        // The form may be gone (a row that left the list) or closed, so the button cannot take focus: use the status line.
        if (document.activeElement === button) {
            return;
        }

        const status = component.el.querySelector('[role="status"]');

        if (status) {
            status.setAttribute('tabindex', '-1');
            status.focus();
        }
    });
});
