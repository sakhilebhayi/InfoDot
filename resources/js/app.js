import './bootstrap';
import Alpine from 'alpinejs';

// Platform logos are emitted into the build (public/build is the one folder every host serves from),
// so Blade can reach them with Vite::asset().
import.meta.glob('../img/platforms/*.png');

// Livewire 3 bundles and starts its own Alpine on every page that loads it. Starting a second one
// makes them fight, so this bundle starts Alpine only on pages that do not have Livewire
// (the public home page and the error pages).
if (!window.Livewire) {
    window.Alpine = Alpine;
    Alpine.start();
}
