<?php

namespace App\Services\Onboarding;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * R-62 phase 7: company details from a PDF or plain-text document, by rules, with no AI and no outside service.
 *
 * The file is read from the upload's temporary path and is never stored; only the values found (and the file's name) are kept, in the import record.
 * What counts as a document is decided by looking at the bytes (a PDF starts with `%PDF-`, text must be valid UTF-8 with no control bytes), never by the
 * name or the type the browser claims. A PDF is parsed with tight limits and any failure is a plain refusal. A scanned PDF with no text layer gives nothing:
 * reading pictures of text needs OCR, which this does not have, and the person is told so.
 *
 * Only what the document says in a recognisable way is taken: labelled lines ("Company name: ...") and values with an exact shape (an email, a phone number,
 * a web address, a CIPC registration number). An industry is never guessed. Every value carries a confidence and is checked by the person before anything is saved.
 */
class DocumentExtractor
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_CHARS = 100000;

    /**
     * @return array{fields: array<string, array{value: string, confidence: float}>, kind: string, name: string}
     *
     * @throws DocumentRejected
     */
    public function extract(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            throw new DocumentRejected('We could not read that file. Try uploading it again.');
        }

        $size = (int) filesize($path);
        if ($size === 0) {
            throw new DocumentRejected('That file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new DocumentRejected('That file is too large. The most we can read is 5 MB.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 1024);
        $kind = $this->kind($head, $path);
        $text = $kind === 'pdf' ? $this->pdfText($path) : $this->plainText($path);
        $text = $this->clean($text);

        if ($text === '') {
            throw new DocumentRejected($kind === 'pdf'
                ? 'That PDF has no text we can read. It may be a scan or a photo of a page, and we cannot read pictures of text yet. You can type the details in instead.'
                : 'That file has no text in it.');
        }

        return ['fields' => $this->fields($text), 'kind' => $kind, 'name' => $this->safeName((string) $file->getClientOriginalName())];
    }

    /** The kind of document the bytes say it is. */
    private function kind(string $head, string $path): string
    {
        // A PDF may have a few stray bytes before its header; the specification allows the header within the first 1024.
        if (str_contains($head, '%PDF-')) {
            return 'pdf';
        }

        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($sniffed === 'text/plain' && ! str_contains($head, "\0")) {
            return 'text';
        }

        throw new DocumentRejected('We can read PDF and plain text files. Word, Excel and image files are not supported yet.');
    }

    private function pdfText(string $path): string
    {
        try {
            $config = new Config;
            $config->setDecodeMemoryLimit(16 * 1024 * 1024);
            $config->setMaxNestingDepth(20);
            $config->setRetainImageContent(false);

            return (new Parser([], $config))->parseFile($path)->getText();
        } catch (Throwable $e) {
            // Encrypted, damaged, or built to exhaust the parser: all the same to the person.
            throw new DocumentRejected('We could not read that PDF. It may be protected or damaged. You can type the details in instead.');
        }
    }

    private function plainText(string $path): string
    {
        $text = (string) file_get_contents($path, false, null, 0, self::MAX_BYTES);

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return $text;
    }

    /** Text with control characters and runs of blanks removed, lines kept, capped in length. */
    private function clean(string $text): string
    {
        $text = (string) preg_replace('/[^\P{C}\n\t]+/u', ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim(Str::limit($text, self::MAX_CHARS, ''));
    }

    /** @return array<string, array{value: string, confidence: float}> */
    private function fields(string $text): array
    {
        $fields = [];
        $put = function (string $field, ?string $value, float $confidence) use (&$fields) {
            $value = $value === null ? null : trim(strip_tags($value), " \t\n\r\0\x0B.,;:");
            if ($value !== null && $value !== '' && ! isset($fields[$field])) {
                $fields[$field] = ['value' => $value, 'confidence' => $confidence];
            }
        };

        $put('name', $this->labelled($text, ['registered name', 'company name', 'enterprise name', 'business name', 'name of company', 'name of business', 'trading as', 'trade name'], 120), 0.75);
        $put('name', $this->companyLine($text), 0.5);
        $put('registration_number', $this->match('/\b(\d{4}\/\d{6}\/\d{2})\b/', $text), 0.75);
        $put('description', $this->labelled($text, ['business description', 'nature of business', 'about us', 'what we do', 'description'], 500), 0.5);
        $put('location', $this->labelled($text, ['physical address', 'business address', 'registered address', 'address'], 200), 0.5);
        $put('contact_email', $this->email($text), 0.6);
        $put('contact_phone', $this->phone($text), 0.55);
        $put('website', $this->website($text), 0.55);

        return $fields;
    }

    /** The value after a label such as "Company name:" on the same line, for the first label found. @param list<string> $labels */
    private function labelled(string $text, array $labels, int $max): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('/^\s*'.preg_quote($label, '/').'\s*[:\-–]\s*(.{2,'.$max.'}?)\s*$/imu', $text, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** A line in the first few that ends the way company names do (Pty Ltd, NPC, Limited...), which is how a certificate or letterhead states it. */
    private function companyLine(string $text): ?string
    {
        foreach (array_slice(preg_split('/\n/', $text) ?: [], 0, 15) as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) <= 120 && preg_match('/\b(\(Pty\)\s*Ltd|Pty\s*Ltd|Proprietary Limited|NPC|Limited|Ltd|CC|Inc)\.?$/iu', $line)) {
                return $line;
            }
        }

        return null;
    }

    private function match(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) === 1 ? $m[1] : null;
    }

    private function email(string $text): ?string
    {
        if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $all)) {
            foreach ($all[0] as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL) && ! preg_match('/^(no-?reply|donotreply)@/i', $candidate)) {
                    return Str::lower($candidate);
                }
            }
        }

        return null;
    }

    private function phone(string $text): ?string
    {
        if (preg_match('/(?<![\d\/])(\+27|0)[\s\-().]*\d{2}[\s\-.]*\d{3}[\s\-.]*\d{4}(?!\d)/', $text, $m)) {
            return preg_replace('/\s+/', ' ', trim($m[0]));
        }

        return null;
    }

    private function website(string $text): ?string
    {
        if (preg_match_all('~(?<![@\w.])((?:https?://)?(?:www\.)?[A-Za-z0-9][A-Za-z0-9\-]*(?:\.[A-Za-z0-9\-]+)*\.(?:co\.za|org\.za|com|org|net|co|za|io|africa)(?:/[^\s<>"\']*)?)~i', $text, $all)) {
            foreach ($all[1] as $candidate) {
                $url = Str::startsWith(Str::lower($candidate), ['http://', 'https://']) ? $candidate : 'https://'.$candidate;
                $host = parse_url($url, PHP_URL_HOST);
                if (is_string($host) && filter_var($url, FILTER_VALIDATE_URL)) {
                    return 'https://'.Str::lower(preg_replace('/^www\./i', '', $host) ?? $host);
                }
            }
        }

        return null;
    }

    /** The file's name as a short, plain label for the import record: letters, digits and a few marks only. */
    private function safeName(string $name): string
    {
        return Str::limit(trim((string) preg_replace('/[^\p{L}\p{N} ._\-]+/u', '', $name)), 120, '') ?: 'document';
    }
}
