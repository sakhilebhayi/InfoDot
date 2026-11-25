<?php

namespace App\Support;

/**
 * Text that other people wrote (a company name, an account name) on its way into a Markdown email. The vendor mail layout reads Markdown more than once, so
 * escaping is not enough: the characters that mean something in Markdown are taken out, and a bare address is kept from turning into a link.
 */
final class MailText
{
    public static function safe(string $text, string $fallback = '', int $limit = 80): string
    {
        $text = (string) preg_replace('/[\\\\`*_{}\[\]()#|<>~&"\']+/', '', strip_tags($text));
        $text = (string) preg_replace(['~:(?=//)~', '~(?<=www)\.~i', '~@~'], [":\u{200B}", ".\u{200B}", "@\u{200B}"], $text);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return $text === '' ? $fallback : mb_substr($text, 0, $limit);
    }
}
