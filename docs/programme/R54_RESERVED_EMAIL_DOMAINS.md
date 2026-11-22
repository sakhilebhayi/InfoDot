# R-54: reserved email domains are blocked

Found 2026-11: a fake account `runtime-check@example.test` in a platform database. InfoDot is the only place accounts are
created, so InfoDot now refuses new addresses that can never receive mail.

## The rule

`App\Rules\DeliverableEmailDomain` (logic in `App\Support\ReservedEmailDomains`) rejects an address whose domain is:

- on a special-use top-level name (RFC 6761): `test`, `example`, `invalid`, `localhost`, including a bare `localhost`;
- `example.com`, `example.net`, `example.org` or any subdomain of them (RFC 2606).

It is case-insensitive, trims, ignores trailing dots, converts IDN to punycode and never throws on odd input.
`example.com.au` and `notexample.com` are accepted. `.local` and real disposable-mail providers are out of scope.

The message shown on the field is: "Please use a real email address that can receive mail: addresses on example, test,
invalid and localhost domains are not accepted." It uses the normal field error, so forms keep their mobile and
accessibility behaviour. The rule sits before `email` (with `bail`) so this message wins over the generic one.

## Where it applies (every new address)

Registration (`CreateNewUser`), profile email change (`UpdateUserProfileInformation`, only when the address changes),
team invitations (`InviteTeamMember`, which also serves the team screen), the contact form (`PagesController@contactSend`),
the business contact email (`BusinessProfileController@update`, only when it changes) and the business official-email
verification code (`BusinessVerificationController@sendCode`). `AddTeamMember` only accepts existing users, and
enquiries and the admin commands take no new address.

Existing users are never touched and are never locked out: sign-in does not use the rule.

## The flag

`config('auth.block_reserved_email_domains')`, env `BLOCK_RESERVED_EMAIL_DOMAINS`, default **true**. `phpunit.xml` sets it
to false so the existing suite, factories and the DemoSeeder keep using `example` addresses; the tests in
`tests/Feature/ReservedEmailDomainsTest.php` turn it on. Production needs no env change.

## Finding existing fake accounts (read-only)

```
php artisan users:reserved-emails          # id, masked email, created_at
php artisan users:reserved-emails --count  # just the number
```

It changes and deletes nothing. What to do with the accounts it lists is the owner's decision.
