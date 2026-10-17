import './bootstrap';
import Alpine from 'alpinejs';

// Livewire 3 bundles and starts its own Alpine on every page that loads it. Starting a second one
// makes them fight, so this bundle starts Alpine only on pages that do not have Livewire
// (the public home page and the error pages).
if (!window.Livewire) {
    window.Alpine = Alpine;
    Alpine.start();
}
