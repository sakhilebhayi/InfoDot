<?php

namespace Tests\Unit;

use App\Rules\DeliverableEmailDomain;
use App\Support\ReservedEmailDomains;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservedEmailDomainsTest extends TestCase
{
    public static function reserved(): array
    {
        return [
            ['x@example.test'], ['x@foo.invalid'], ['x@sub.example.com'], ['x@localhost'], ['x@EXAMPLE.ORG'],
            ['x@example.test.'], ['  x@Example.NET  '], ['x@a.b.example.net'], ['x@my.example'], ['x@LOCALHOST.'],
            ['x@host.localhost'], ['x@example.com'], ['"odd@name"@example.test'], ['x@EXAMPLE.TEST..'],
            ['x@xn--example-.test'], ['x@bücher.example'], ['x@例え.test'],
        ];
    }

    public static function acceptable(): array
    {
        return [
            ['x@example.com.au'], ['x@notexample.com'], ['x@gmail.com'], ['x@testing.com'], ['x@example.co.za'],
            ['x@mail.local'], ['x@mailinator.com'], ['x@invalid.com'], ['x@localhost.com'], ['x@myexample.org'],
            ['x@bücher.de'], ['no-at-sign'], [''], ['x@'],
        ];
    }

    #[DataProvider('reserved')]
    public function test_reserved_addresses_are_recognised(string $email): void
    {
        $this->assertTrue(ReservedEmailDomains::isReserved($email), $email);
    }

    #[DataProvider('acceptable')]
    public function test_real_and_lookalike_addresses_are_not_reserved(string $email): void
    {
        $this->assertFalse(ReservedEmailDomains::isReserved($email), $email);
    }

    public function test_odd_input_never_throws(): void
    {
        foreach ([null, 12, 1.5, true, [], ['a@example.test'], new \stdClass, "\0@\0", '@', '@@', str_repeat('a', 5000).'@'.str_repeat('é', 400).'.test', "x@\xff\xfe.test"] as $odd) {
            $this->assertIsBool(ReservedEmailDomains::isReserved($odd));
        }
        $this->assertFalse(ReservedEmailDomains::isReserved(null));
        $this->assertFalse(ReservedEmailDomains::isReservedDomain(null));
        $this->assertTrue(ReservedEmailDomains::isReservedDomain('Example.ORG.'));
    }

    public function test_the_rule_fails_with_the_clear_message_when_enabled(): void
    {
        config(['auth.block_reserved_email_domains' => true]);

        $v = Validator::make(['email' => 'x@example.test.'], ['email' => ['bail', new DeliverableEmailDomain, 'email']]);
        $this->assertTrue($v->fails());
        $this->assertSame(DeliverableEmailDomain::MESSAGE, $v->errors()->first('email'));
        $this->assertTrue(Validator::make(['email' => 'x@example.com.au'], ['email' => [new DeliverableEmailDomain]])->passes());
        $this->assertTrue(Validator::make(['email' => ['x']], ['email' => [new DeliverableEmailDomain]])->passes());
    }

    public function test_the_rule_is_skipped_when_the_flag_is_off(): void
    {
        config(['auth.block_reserved_email_domains' => false]);

        $this->assertTrue(Validator::make(['email' => 'x@example.test'], ['email' => [new DeliverableEmailDomain]])->passes());
    }
}
