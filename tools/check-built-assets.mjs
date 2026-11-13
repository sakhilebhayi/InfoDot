// Run after `npm run build` (CI, "frontend build and advisories"). The PHP tests cannot see a real build, so this
// is where the build itself is held to what R-49 promised: it serves its own fonts, it names no other host, and the
// charting library is a separate file that only the Analytics page fetches.
import { existsSync, readFileSync } from 'node:fs';

const fail = (message) => {
    console.error(`check-built-assets: ${message}`);
    process.exit(1);
};

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const entry = (key) => manifest[key] ?? fail(`${key} is not in the build manifest`);

const css = readFileSync(`public/build/${entry('resources/css/app.css').file}`, 'utf8');
const fonts = [...new Set([...css.matchAll(/url\(([^)]+\.woff2)\)/g)].map((m) => m[1]))];
if (fonts.length < 13) fail(`the stylesheet names ${fonts.length} font files; 13 are expected`);
for (const font of fonts) {
    if (!font.startsWith('/build/assets/')) fail(`a font is not served from the build: ${font}`);
    if (!existsSync(`public${font}`)) fail(`the stylesheet names a font the build does not hold: ${font}`);
}
if (/url\(["']?(https?:)?\/\//.test(css)) fail('the stylesheet loads a file from another host');
if (!/font-display:\s*block/.test(css)) fail('the icon font no longer blocks, so icons could show as their names');

// The fonts a layout preloads must be in the manifest under the names OptionalVite::PRELOADABLE_FONTS uses.
const php = readFileSync('app/Support/OptionalVite.php', 'utf8');
const preloaded = [...php.matchAll(/=> '((?:node_modules|resources)\/[^']+\.woff2)'/g)].map((m) => m[1]);
if (preloaded.length !== 4) fail(`expected 4 preloadable fonts in OptionalVite, found ${preloaded.length}`);
preloaded.forEach(entry);
entry('resources/img/brand/logo_white.png');
entry('resources/img/landing/hero-1920.jpg');

const chart = entry('resources/js/analytics-chart.js');
if (!chart.isDynamicEntry) fail('the chart module is not a separate, dynamically imported file');
const app = readFileSync(`public/build/${entry('resources/js/app.js').file}`, 'utf8');
if (app.includes('apexcharts-canvas')) fail('the charting library is inside app.js, so every page downloads it');
if (!readFileSync(`public/build/${chart.file}`, 'utf8').includes('apexcharts-canvas')) fail('the chart file does not hold the charting library');

console.log(`check-built-assets: ${fonts.length} fonts served from the build, no other host, chart library split out.`);
