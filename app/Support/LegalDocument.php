<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Takes the HTML of a legal page (rendered from its markdown) and prepares it for reading: the "Last updated" line is lifted out,
 * every section heading gets an address so it can be linked and jumped to, a contents list is built from them, and tables are
 * wrapped so they scroll inside themselves on a phone. The words are never changed.
 */
class LegalDocument
{
    /** @return array{updated: ?string, html: string, toc: list<array{id: string, text: string}>} */
    public static function prepare(string $html): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div id="legal-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $root = $doc->getElementById('legal-root');
        $updated = null;

        // "Last updated: 6 August 2026" is set in italics as the first paragraph: lift it out and show it under the title.
        $first = $xpath->query('//div[@id="legal-root"]/p[1][em]')->item(0);
        if ($first instanceof DOMElement && preg_match('/^\s*Last updated:\s*(.+?)\s*$/i', $first->textContent, $m)) {
            $updated = $m[1];
            $first->parentNode->removeChild($first);
        }

        $toc = [];
        $used = [];
        foreach ($xpath->query('//div[@id="legal-root"]/h2') as $h2) {
            $text = trim($h2->textContent);
            $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-') ?: 'section';
            $id = $base;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base.'-'.$n;
            }
            $used[$id] = true;
            $h2->setAttribute('id', $id);
            $toc[] = ['id' => $id, 'text' => $text];
        }

        foreach (iterator_to_array($xpath->query('//div[@id="legal-root"]//table')) as $table) {
            $wrap = $doc->createElement('div');
            $wrap->setAttribute('class', 'ix-tablewrap');
            $table->parentNode->replaceChild($wrap, $table);
            $wrap->appendChild($table);
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return ['updated' => $updated, 'html' => $out, 'toc' => $toc];
    }
}
