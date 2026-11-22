<?php

namespace App\Support;

/**
 * Which email domains can never receive mail: the special-use top-level names of RFC 6761 (test, example, invalid,
 * localhost), the reserved example.com / example.net / example.org and everything under them (RFC 2606), and a bare
 * "localhost". Pure and total: it never throws, whatever it is given. `.local` and disposable-mail providers are
 * deliberately not part of this list.
 */
final class ReservedEmailDomains
{
    /** Top-level names reserved for documentation and testing. */
    public const RESERVED_TLDS = ['test', 'example', 'invalid', 'localhost'];

    /** Second-level names reserved for documentation (they and every subdomain). */
    public const RESERVED_NAMES = ['example.com', 'example.net', 'example.org'];

    /** The domain part of an address, lower-cased, in ASCII (punycode), without trailing dots; null when there is none. */
    public static function domainOf(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        return self::normaliseDomain(substr($email, $at + 1));
    }

    public static function normaliseDomain(string $domain): ?string
    {
        $domain = rtrim(trim($domain), ".\u{3002}\u{FF0E}\u{FF61}");
        $domain = trim($domain, "[] \t\r\n");

        if ($domain === '') {
            return null;
        }

        $domain = mb_strtolower($domain);

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $domain) === 1) {
            $ascii = @idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $domain = strtolower($ascii);
            }
        }

        return rtrim($domain, '.') ?: null;
    }

    public static function isReservedDomain(?string $domain): bool
    {
        if ($domain === null || ($domain = self::normaliseDomain($domain)) === null) {
            return false;
        }

        $labels = explode('.', $domain);

        if (in_array(end($labels), self::RESERVED_TLDS, true)) {
            return true;
        }

        foreach (self::RESERVED_NAMES as $name) {
            if ($domain === $name || str_ends_with($domain, '.'.$name)) {
                return true;
            }
        }

        return false;
    }

    public static function isReserved(mixed $email): bool
    {
        return self::isReservedDomain(self::domainOf($email));
    }
}
