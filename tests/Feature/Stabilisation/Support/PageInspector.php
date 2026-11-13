<?php

namespace Tests\Feature\Stabilisation\Support;

use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;

/**
 * R-49. Reads one rendered page the way a browser would and reports every control on it that cannot work: a link
 * with nowhere to go, a button nothing listens to, a form no route answers, a Livewire action naming a method the
 * component does not have, a label or in-page link pointing at an id that is not there, an id used twice. It also
 * lists what the page loads (links to follow, pictures, scripts, styles) so the sweep can request each one.
 *
 * It exists as its own class so the interaction sweep and the image tests judge pages by one set of rules.
 */
final class PageInspector
{
    private const LIVEWIRE_EVENTS = ['click', 'submit', 'change', 'keydown', 'keyup', 'input', 'blur', 'focus', 'mouseenter', 'mouseleave', 'poll', 'init'];

    private const LIVEWIRE_MAGIC = ['$refresh', '$set', '$toggle', '$dispatch', '$dispatchSelf', '$dispatchTo', '$parent', '$commit', '$js'];

    private const WIRE_HELPERS = ['set', 'get', 'call', 'entangle', 'dispatch', 'dispatchSelf', 'dispatchTo', 'on', 'upload', 'uploadMultiple', 'removeUpload', 'cancelUpload', 'watch', 'toggle', 'el', 'id', 'parent', 'commit', 'refresh'];

    /** @var array<string, class-string|null> */
    private static array $components = [];

    private static ?string $scripts = null;

    /**
     * @return array{
     *     links: list<string>, external_links: list<string>, forms: list<array{method: string, action: string}>,
     *     resources: list<array{kind: string, url: string}>, problems: list<string>, counts: array<string, int>,
     *     signatures: array<string, list<string>>
     * }
     */
    public function inspect(string $html, string $pagePath): array
    {
        // Alpine keeps conditional and repeated markup inside <template>, which a parser holds apart from the page.
        // Renamed, the controls inside are read like the rest.
        $html = preg_replace(['/<template\b/i', '#</template>#i'], ['<ix-template', '</ix-template>'], $html) ?? $html;
        $dom = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $out = ['links' => [], 'external_links' => [], 'forms' => [], 'resources' => [], 'problems' => [], 'counts' => [], 'signatures' => []];
        $count = function (string $type, string $signature) use (&$out): void {
            $out['counts'][$type] = ($out['counts'][$type] ?? 0) + 1;
            $out['signatures'][$type][] = $signature;
        };

        // ---- ids -------------------------------------------------------------------------------------------------
        $ids = [];
        foreach ($dom->querySelectorAll('[id]') as $el) {
            $id = $el->getAttribute('id');
            if ($id === '' || $this->isBound($el, 'id')) {
                continue;
            }
            $ids[$id] = ($ids[$id] ?? 0) + 1;
        }
        foreach ($ids as $id => $n) {
            if ($n > 1) {
                $out['problems'][] = "the id \"{$id}\" is used {$n} times";
            }
        }
        $names = [];
        foreach ($dom->querySelectorAll('a[name]') as $el) {
            $names[$el->getAttribute('name')] = true;
        }

        // ---- links -----------------------------------------------------------------------------------------------
        foreach ($dom->querySelectorAll('a') as $a) {
            $href = $a->hasAttribute('href') ? trim($a->getAttribute('href')) : null;
            $handled = $this->hasHandler($a, 'click') || $this->isBound($a, 'href');
            $label = $this->describe($a);

            if ($href === null) {
                if ($handled) {
                    $count('link:scripted', $label);
                } elseif (! $a->hasAttribute('id') && ! $a->hasAttribute('name')) {
                    $out['problems'][] = "{$label} has no href and nothing handles a click";
                }

                continue;
            }
            if ($href === '' || $href === '#' || preg_match('/^javascript:/i', $href)) {
                if ($handled) {
                    $count('link:scripted', $label);
                } else {
                    $out['problems'][] = "{$label} has the placeholder href \"{$href}\" and nothing handles a click";
                }

                continue;
            }
            if ($href[0] === '#') {
                $target = substr($href, 1);
                if (! isset($ids[$target]) && ! isset($names[$target]) && ! $handled) {
                    $out['problems'][] = "{$label} points at #{$target}, which is not on the page";
                }
                $count('link:in-page', $href);

                continue;
            }
            if (preg_match('/^(mailto:|tel:|sms:)/i', $href)) {
                $count('link:contact', $href);

                continue;
            }
            $local = $this->localPath($href);
            if ($local === null) {
                $count('link:external', $href);
                $out['external_links'][] = $href;

                continue;
            }
            $count('link:internal', $local);
            $out['links'][] = $local;
            if (($fragment = parse_url($href, PHP_URL_FRAGMENT)) && $this->samePage($local, $pagePath) && ! isset($ids[$fragment]) && ! isset($names[$fragment])) {
                $out['problems'][] = "{$label} points at #{$fragment} on this page, which is not there";
            }
        }

        // ---- forms -----------------------------------------------------------------------------------------------
        $formsOk = [];
        foreach ($dom->querySelectorAll('form') as $form) {
            $label = $this->describe($form);
            $method = strtoupper($form->getAttribute('method') ?: 'GET');
            $spoof = $form->querySelector('input[name="_method"]');
            if ($method === 'POST' && $spoof) {
                $method = strtoupper($spoof->getAttribute('value') ?: 'POST');
            }
            $action = trim($form->getAttribute('action') ?? '');
            $scripted = $this->hasHandler($form, 'submit');
            $bound = $this->isBound($form, 'action');

            // A form with its own address is still sent there once its handler agrees, so the address is checked below.
            if (($scripted && $action === '') || $bound) {
                $count($scripted ? 'form:scripted' : 'form:bound', $label);
                foreach ($this->livewireProblems($form) as $problem) {
                    $out['problems'][] = $problem;
                }

                continue;
            }

            $target = $action === '' ? $pagePath : $action;
            $local = $this->localPath($target);
            if ($local === null) {
                $count('form:external', $target);
                if (! str_starts_with($target, 'https://')) {
                    $out['problems'][] = "{$label} posts to {$target}, which the Content-Security-Policy form-action does not allow";
                }

                continue;
            }
            foreach ($this->livewireProblems($form) as $problem) {
                $out['problems'][] = $problem;
            }
            $path = parse_url($local, PHP_URL_PATH) ?: '/';
            $count('form:route', $method.' '.$this->shape($path));
            $out['forms'][] = ['method' => $method, 'action' => $path];
            try {
                Route::getRoutes()->match(Request::create($path, $method));
            } catch (\Throwable) {
                $out['problems'][] = "{$label} sends {$method} {$path}, which no route answers";
            }
            if ($method !== 'GET' && ! $form->querySelector('input[name="_token"]')) {
                $out['problems'][] = "{$label} sends {$method} {$path} without a CSRF token field";
            }
        }

        // ---- buttons ---------------------------------------------------------------------------------------------
        foreach ($dom->querySelectorAll('button, input[type="submit"], input[type="button"], input[type="image"], input[type="reset"]') as $button) {
            $label = $this->describe($button);
            $type = strtolower($button->getAttribute('type') ?: 'submit');
            if ($button->localName === 'button' && ! in_array($type, ['submit', 'button', 'reset'], true)) {
                $type = 'submit';
            }
            $form = $button->hasAttribute('form') ? $dom->getElementById($button->getAttribute('form')) : $button->closest('form');
            $handled = $this->hasAnyHandler($button) || $this->wrappedInClickHandler($button);

            if ($button->hasAttribute('popovertarget')) {
                $target = $button->getAttribute('popovertarget');
                isset($ids[$target]) ? $count('button:popover', $label) : $out['problems'][] = "{$label} opens the popover #{$target}, which is not on the page";

                continue;
            }
            if ($handled) {
                $count('button:scripted', $label);

                continue;
            }
            if (in_array($type, ['submit', 'image', 'reset'], true) && $form) {
                $count('button:form', $label);

                continue;
            }
            if ($button->hasAttribute('form')) {
                $out['problems'][] = "{$label} names the form #{$button->getAttribute('form')}, which is not on the page";

                continue;
            }
            $out['problems'][] = $type === 'button'
                ? "{$label} is type=\"button\" and nothing handles a click"
                : "{$label} is outside any form and nothing handles a click";
        }

        // ---- Livewire actions and Alpine calls into Livewire -----------------------------------------------------
        foreach ($dom->querySelectorAll('*') as $el) {
            $carries = false;
            foreach ($el->attributes as $attribute) {
                $name = $attribute->name;
                if (str_starts_with($name, 'on') && ! str_contains($name, ':') && ! str_contains($name, '-') && $this->isNativeEvent($name)) {
                    $out['problems'][] = $this->describe($el)." uses the inline handler {$name}=, which the Content-Security-Policy blocks (script-src-attr 'none')";
                }
                if (str_starts_with($name, 'wire:') || str_contains($attribute->value, '$wire.')) {
                    $carries = true;
                }
            }
            if ($carries && $el->localName !== 'form') {
                foreach ($this->livewireProblems($el) as $problem) {
                    $out['problems'][] = $problem;
                }
                foreach ($el->attributes as $attribute) {
                    if (preg_match('/^wire:('.implode('|', self::LIVEWIRE_EVENTS).')\b/', $attribute->name)) {
                        $count('livewire:action', $attribute->name.'='.$attribute->value);
                    }
                }
            }
        }
        foreach ($dom->querySelectorAll('form') as $form) {
            foreach ($form->attributes as $attribute) {
                if (str_starts_with($attribute->name, 'wire:submit')) {
                    $count('livewire:action', $attribute->name.'='.$attribute->value);
                }
            }
        }

        // ---- Alpine components -------------------------------------------------------------------------------------
        // x-data="name(...)" needs a component of that name: registered in resources/js, or defined by a script on the
        // page. Without it every control inside is dead.
        $pageScripts = '';
        foreach ($dom->querySelectorAll('script') as $script) {
            $pageScripts .= "\n".$script->textContent;
        }
        foreach ($dom->querySelectorAll('[x-data]') as $el) {
            if (! preg_match('/^([A-Za-z_$][\w$]*)\s*(?:\(|$)/', trim($el->getAttribute('x-data') ?? ''), $m)) {
                continue;
            }
            $name = preg_quote($m[1], '/');
            $defined = '/Alpine\.data\(\s*[\'"]'.$name.'[\'"]|function\s+'.$name.'\b|window\.'.$name.'\s*=|\b'.$name.'\s*[:=]\s*(?:function|\(|async)/';
            if (preg_match($defined, $this->scripts().$pageScripts)) {
                $count('alpine:component', $m[1]);
            } else {
                $out['problems'][] = $this->describe($el)." uses the Alpine component {$m[1]}, which no script defines";
            }
        }

        // ---- labels, described-by and controls ---------------------------------------------------------------------
        foreach ($dom->querySelectorAll('label[for]') as $labelEl) {
            $for = $labelEl->getAttribute('for');
            if ($this->isBound($labelEl, 'for')) {
                continue;
            }
            $count('label', $for);
            if (! isset($ids[$for])) {
                $out['problems'][] = 'the label "'.$this->text($labelEl)."\" is for #{$for}, which is not on the page";
            }
        }

        // ---- inline scripts ---------------------------------------------------------------------------------------
        foreach ($dom->querySelectorAll('script') as $script) {
            $type = strtolower($script->getAttribute('type') ?? '');
            if ($script->hasAttribute('src') || ($type !== '' && ! in_array($type, ['module', 'text/javascript', 'application/javascript'], true))) {
                continue;
            }
            if (! $script->hasAttribute('nonce')) {
                $out['problems'][] = 'an inline <script> has no nonce, so the Content-Security-Policy blocks it: '.mb_substr(trim(preg_replace('/\s+/', ' ', $script->textContent) ?? ''), 0, 70);
            }
        }

        // ---- what the page loads ----------------------------------------------------------------------------------
        $resource = function (string $kind, ?string $url) use (&$out, $count): void {
            $url = trim((string) $url);
            if ($url === '') {
                return;
            }
            if (preg_match('/^(data:|blob:|about:)/i', $url)) {
                $count('loads:inline '.$kind, substr($url, 0, 40));

                return;
            }
            $count('loads:'.$kind, $this->localPath($url) !== null ? $this->shape((string) $this->localPath($url)) : $url);
            $out['resources'][] = ['kind' => $kind, 'url' => html_entity_decode($url)];
        };
        foreach ($dom->querySelectorAll('img') as $img) {
            $label = $this->describe($img);
            if (! $img->hasAttribute('alt') && ! $this->isBound($img, 'alt')) {
                $out['problems'][] = "{$label} has no alt attribute";
            }
            if ($this->isBound($img, 'src')) {
                $count('image:bound', $label);

                continue;
            }
            // No src at all and a script that fills it in (the crop preview): nothing is requested until then.
            if (! $img->hasAttribute('src') && ! $img->hasAttribute('srcset') && $this->hasAnyHandler($img)) {
                $count('image:scripted', $label);

                continue;
            }
            if (trim($img->getAttribute('src') ?? '') === '' && ! $img->hasAttribute('srcset')) {
                $out['problems'][] = "{$label} has an empty src";

                continue;
            }
            $resource('img', $img->getAttribute('src'));
            foreach ($this->srcset($img->getAttribute('srcset')) as $url) {
                $resource('srcset', $url);
            }
        }
        foreach ($dom->querySelectorAll('source') as $source) {
            $media = $source->parentElement && in_array($source->parentElement->localName, ['video', 'audio'], true) ? 'media' : 'source';
            $resource($media, $source->getAttribute('src'));
            foreach ($this->srcset($source->getAttribute('srcset')) as $url) {
                $resource('srcset', $url);
            }
        }
        foreach ($dom->querySelectorAll('video, audio') as $media) {
            $resource('media', $media->getAttribute('src'));
            $resource('poster', $media->getAttribute('poster'));
        }
        foreach ($dom->querySelectorAll('[style]') as $styled) {
            foreach ($this->cssUrls($styled->getAttribute('style') ?? '') as $url) {
                $resource('css-url', $url);
            }
        }
        foreach ($dom->querySelectorAll('style') as $style) {
            foreach ($this->cssUrls($style->textContent ?? '') as $url) {
                $resource('css-url', $url);
            }
        }
        foreach ($dom->querySelectorAll('link[href]') as $link) {
            $rel = strtolower($link->getAttribute('rel') ?? '');
            if (str_contains($rel, 'icon')) {
                $resource('icon', $link->getAttribute('href'));
            } elseif (str_contains($rel, 'stylesheet')) {
                $resource('style', $link->getAttribute('href'));
            } elseif (str_contains($rel, 'preload') && $link->getAttribute('as') === 'font') {
                $resource('font', $link->getAttribute('href'));
            } elseif (str_contains($rel, 'preload') && $link->getAttribute('as') === 'image') {
                $resource('img', $link->getAttribute('href'));
                foreach ($this->srcset($link->getAttribute('imagesrcset')) as $url) {
                    $resource('srcset', $url);
                }
            } elseif (str_contains($rel, 'modulepreload')) {
                $resource('script', $link->getAttribute('href'));
            }
        }
        foreach ($dom->querySelectorAll('script[src]') as $script) {
            $resource('script', $script->getAttribute('src'));
        }
        foreach ($dom->querySelectorAll('iframe[src], embed[src]') as $frame) {
            $resource('frame', $frame->getAttribute('src'));
        }
        foreach ($dom->querySelectorAll('summary') as $summary) {
            $count('disclosure', $this->text($summary));
        }

        $out['links'] = array_values(array_unique($out['links']));

        return $out;
    }

    /** The path and query of a URL on this site, or null when it leads to another host. */
    public function localPath(string $url): ?string
    {
        $url = html_entity_decode(trim($url));
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }
        $own = [parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost', 'localhost', '127.0.0.1'];
        if (isset($parts['host']) && ! in_array($parts['host'], $own, true)) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    /** A path with its ids and uuids replaced, so the same control on two records counts once. */
    public function shape(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i', '/{uuid}', $path) ?? $path;

        return preg_replace('#/\d+(?=/|$)#', '/{id}', $path) ?? $path;
    }

    private function samePage(string $local, string $pagePath): bool
    {
        return (parse_url($local, PHP_URL_PATH) ?: '/') === (parse_url($pagePath, PHP_URL_PATH) ?: '/');
    }

    private function isBound(Element $el, string $attribute): bool
    {
        return $el->hasAttribute(':'.$attribute) || $el->hasAttribute('x-bind:'.$attribute) || $el->hasAttribute('wire:'.$attribute);
    }

    private function hasHandler(Element $el, string $event): bool
    {
        foreach ($el->attributes as $attribute) {
            foreach (['@'.$event, 'x-on:'.$event, 'wire:'.$event] as $prefix) {
                if ($attribute->name === $prefix || str_starts_with($attribute->name, $prefix.'.')) {
                    return trim($attribute->value) !== '';
                }
            }
        }

        return false;
    }

    /** A click, key or pointer handler from Alpine or Livewire, or an attribute a script in resources/js listens for. */
    private function hasAnyHandler(Element $el): bool
    {
        foreach ($el->attributes as $attribute) {
            $name = $attribute->name;
            if ((str_starts_with($name, '@') || str_starts_with($name, 'x-on:') || preg_match('/^wire:(click|mousedown|keydown)/', $name)) && trim($attribute->value) !== '') {
                return true;
            }
            // A script in resources/js that names this control's id or data attribute is listening for it.
            if ($name === 'id' && $attribute->value !== '' && preg_match('/[\'"#]'.preg_quote($attribute->value, '/').'[\'"\]\s,.:)]/', $this->scripts())) {
                return true;
            }
            if (str_starts_with($name, 'data-') && str_contains($this->scripts(), '['.$name)) {
                return true;
            }
        }

        return false;
    }

    /** Jetstream's confirm-password wrapper listens on a span around the button it guards. */
    private function wrappedInClickHandler(Element $el): bool
    {
        for ($parent = $el->parentElement; $parent && ! in_array($parent->localName, ['form', 'body'], true); $parent = $parent->parentElement) {
            foreach (['@click', 'x-on:click', 'wire:click'] as $name) {
                if ($parent->hasAttribute($name) && trim($parent->getAttribute($name) ?? '') !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    private function scripts(): string
    {
        return self::$scripts ??= implode("\n", array_map(fn (string $file): string => (string) file_get_contents($file), glob(resource_path('js/*.js')) ?: []));
    }

    private function isNativeEvent(string $name): bool
    {
        return in_array($name, ['onclick', 'onchange', 'onsubmit', 'oninput', 'onload', 'onerror', 'onkeydown', 'onkeyup', 'onkeypress', 'onfocus', 'onblur', 'onmouseover', 'onmouseout', 'onmousedown', 'onmouseup', 'ondblclick', 'onreset', 'onselect', 'ontoggle'], true);
    }

    /** @return list<string> */
    private function livewireProblems(Element $el): array
    {
        $problems = [];
        $class = null;
        $resolved = false;
        $resolve = function () use ($el, &$class, &$resolved): ?string {
            if (! $resolved) {
                $resolved = true;
                $class = $this->componentClass($el);
            }

            return $class;
        };

        foreach ($el->attributes as $attribute) {
            $name = $attribute->name;
            $value = trim($attribute->value);

            if (preg_match('/^wire:('.implode('|', self::LIVEWIRE_EVENTS).')\b/', $name) && $value !== '') {
                $class = $resolve();
                if ($class === null) {
                    $problems[] = $this->describe($el)." has {$name}=\"{$value}\" outside any Livewire component";

                    continue;
                }
                if (! preg_match('/^(\$?[A-Za-z_][A-Za-z0-9_]*)/', $value, $m)) {
                    continue;
                }
                if (in_array($m[1], self::LIVEWIRE_MAGIC, true)) {
                    if (in_array($m[1], ['$set', '$toggle'], true) && preg_match('/^\$\w+\(\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)/', $value, $p) && ! $this->publicProperty($class, $p[1])) {
                        $problems[] = $this->describe($el)." has {$name}=\"{$value}\", but ".class_basename($class)." has no public property \${$p[1]}";
                    }

                    continue;
                }
                if (! $this->publicMethod($class, $m[1])) {
                    $problems[] = $this->describe($el)." has {$name}=\"{$value}\", but ".class_basename($class)." has no public method {$m[1]}()";
                }

                continue;
            }

            if (preg_match('/^wire:model\b/', $name) && $value !== '') {
                $class = $resolve();
                $root = explode('.', $value)[0];
                if ($class !== null && ! $this->publicProperty($class, $root)) {
                    $problems[] = $this->describe($el)." has {$name}=\"{$value}\", but ".class_basename($class)." has no public property \${$root}";
                }

                continue;
            }

            if (str_contains($value, '$wire.') && preg_match_all('/\$wire\.([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $value, $calls)) {
                $class = $resolve();
                foreach (array_unique($calls[1]) as $method) {
                    if (in_array($method, self::WIRE_HELPERS, true)) {
                        continue;
                    }
                    if ($class === null) {
                        $problems[] = $this->describe($el)." calls \$wire.{$method}() outside any Livewire component";
                    } elseif (! $this->publicMethod($class, $method)) {
                        $problems[] = $this->describe($el)." calls \$wire.{$method}(), but ".class_basename($class).' has no such public method';
                    }
                }
            }
        }

        return $problems;
    }

    /** @return class-string|null */
    private function componentClass(Element $el): ?string
    {
        $root = $el->closest('[wire\\:id]');
        if (! $root) {
            return null;
        }
        $snapshot = json_decode($root->getAttribute('wire:snapshot') ?? '', true);
        $name = $snapshot['memo']['name'] ?? null;
        if (! is_string($name)) {
            return null;
        }
        if (! array_key_exists($name, self::$components)) {
            try {
                self::$components[$name] = get_class(Livewire::new($name));
            } catch (\Throwable) {
                self::$components[$name] = null;
            }
        }

        return self::$components[$name];
    }

    private function publicMethod(string $class, string $method): bool
    {
        return method_exists($class, $method) && (new ReflectionMethod($class, $method))->isPublic();
    }

    private function publicProperty(string $class, string $property): bool
    {
        return property_exists($class, $property) && (new ReflectionProperty($class, $property))->isPublic();
    }

    /** @return list<string> */
    private function srcset(?string $srcset): array
    {
        $urls = [];
        foreach (explode(',', (string) $srcset) as $candidate) {
            $url = trim(explode(' ', trim($candidate))[0] ?? '');
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** @return list<string> */
    private function cssUrls(string $css): array
    {
        preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $css, $m);

        return array_values(array_filter(array_map('trim', $m[1]), fn (string $u): bool => ! str_starts_with($u, '#')));
    }

    private function text(Element $el): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $el->textContent ?? '') ?? ''), 0, 50);
    }

    private function describe(Element $el): string
    {
        $bits = '<'.$el->localName;
        foreach (['id', 'name', 'href', 'action', 'src', 'type', 'class', 'aria-label'] as $attribute) {
            if ($el->hasAttribute($attribute) && $el->getAttribute($attribute) !== '') {
                $bits .= ' '.$attribute.'="'.mb_substr($this->shapeValue($attribute, $el->getAttribute($attribute)), 0, 60).'"';
            }
        }
        $text = $el->localName === 'form' ? '' : $this->text($el);

        return $bits.'>'.($text !== '' ? " \"{$text}\"" : '');
    }

    private function shapeValue(string $attribute, string $value): string
    {
        return in_array($attribute, ['href', 'action', 'src'], true) && $this->localPath($value) !== null ? $this->shape($this->localPath($value)) : $value;
    }
}
