// R-52: the type scale and the font families (font-sans, font-mono, text-dot-*) come from the shared dot-ui preset, one place for
// every Dot platform. The two display and text entries this file used to carry named fonts that were never loaded.
const dotPreset = require('./dot-ui/tailwind/tailwind-preset.cjs');

module.exports = {
    presets: [dotPreset],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('daisyui'),
    ],

    daisyui: {
        themes: ['dark'],
    },
};
