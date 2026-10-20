<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * InfoDot's older tables (users, questions, solutions ...) have INT UNSIGNED ids (`increments`). A new foreign key
 * built with foreignId() is BIGINT UNSIGNED, which MySQL refuses ("Foreign key constraint is incorrectly formed")
 * while SQLite, which the tests use, accepts it. This broke the first deploy of the answers table on 2026-10-20.
 */
class LegacyKeyTypesTest extends TestCase
{
    public function test_new_migrations_do_not_use_bigint_foreign_keys_to_the_legacy_tables(): void
    {
        $offenders = [];

        foreach (glob(dirname(__DIR__, 2).'/database/migrations/2026_10_2*.php') as $file) {
            if (preg_match('/foreignId\(.*(users|questions|solutions|teams)/s', file_get_contents($file)) || preg_match('/foreignId\([^)]*\)->constrained\(/', file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Use unsignedInteger() + foreign()->references() for keys to INT UNSIGNED tables.');
    }
}
