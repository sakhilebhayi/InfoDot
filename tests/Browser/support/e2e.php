<?php

/*
 * Test-only helper for the browser run (tests/Browser). It is never loaded by the application: the Playwright
 * helpers run it with `php artisan tinker --execute="require ..."`, pass one operation as JSON in E2E_OP and read
 * one JSON line back. It refuses to do anything unless the connection is the scratch SQLite file that the browser
 * run owns, so it cannot be pointed at a real database by accident.
 */

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Jetstream\CreateTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Seminars\SeminarService;
use App\Services\Trust\BusinessTrustService;
use App\Services\Verification\VerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$connection = DB::connection();
$database = (string) $connection->getDatabaseName();

if ($connection->getDriverName() !== 'sqlite'
    || ! str_contains(str_replace('\\', '/', $database), '/tests/Browser/.data/')
    || app()->isProduction()) {
    fwrite(STDERR, "e2e.php only runs against the scratch SQLite file in tests/Browser/.data.\n");
    exit(1);
}

/** @var array<string, mixed> $op */
$op = json_decode((string) getenv('E2E_OP'), true, 512, JSON_THROW_ON_ERROR);
$password = (string) getenv('E2E_PASSWORD');

$result = match ($op['op']) {
    // A verified person, made the way the sign-up form makes one (personal team included).
    'make-user' => (function () use ($op, $password) {
        $user = User::where('email', $op['email'])->first()
            ?? app(CreateNewUser::class)->create([
                'name' => $op['name'],
                'email' => $op['email'],
                'password' => $password,
                'password_confirmation' => $password,
                'terms' => 'on',
            ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'is_moderator' => (bool) ($op['moderator'] ?? false),
        ])->save();

        if (! $user->currentTeam) {
            $user->switchTeam($user->personalTeam());
        }

        return ['id' => $user->id, 'email' => $user->email, 'name' => $user->name];
    })(),

    // A business, made the way the "Create a company" form makes one.
    'make-business' => (function () use ($op) {
        $owner = User::where('email', $op['owner'])->firstOrFail();
        $team = app(CreateTeam::class)->create($owner, [
            'name' => $op['name'],
            'industry' => $op['industry'] ?? null,
            'description' => $op['description'] ?? null,
            'visibility' => $op['visibility'],
        ]);

        return ['id' => $team->id, 'uuid' => $team->uuid, 'name' => $team->name];
    })(),

    // Put a person in a business with a Jetstream role, and make it the company they are working in.
    'add-member' => (function () use ($op) {
        $team = Team::findOrFail($op['team_id']);
        $user = User::where('email', $op['email'])->firstOrFail();
        $team->users()->syncWithoutDetaching([$user->id => ['role' => $op['role'] ?? 'editor']]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return ['id' => $user->id];
    })(),

    // The records behind the pages no demonstration content reaches (R-49): a seminar with a registered attendee, a
    // verified business and a second application under the same registration number (an identity conflict), a report
    // about the first business, an investigation that takes it out of the directory, and the owner's appeal. Made
    // through the same services the forms call. The meeting-link check is off for this one call: it would ask the
    // meeting host over the network.
    'make-cases' => (function () use ($op) {
        config(['seminars.link_check.enabled' => false]);
        $person = fn (string $key) => User::where('email', $op[$key])->firstOrFail();
        [$owner, $other, $reporter, $reviewer, $second, $host, $attendee] = array_map($person, ['owner', 'other', 'reporter', 'reviewer', 'second_reviewer', 'host', 'attendee']);
        $acme = Team::findOrFail($op['business_id']);
        $copy = Team::findOrFail($op['other_business_id']);

        $evidence = function (): array {
            $path = tempnam(sys_get_temp_dir(), 'e2e').'.pdf';
            file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");

            return [['file' => new UploadedFile($path, 'registration.pdf', 'application/pdf', null, true), 'label' => 'Registration certificate']];
        };
        $application = fn (Team $team): array => ['category' => 'registered_company', 'registered_name' => $team->name, 'registration_number' => '2019/123456/07', 'registration_country' => 'South Africa', 'applicant_role' => 'Director'];

        $verification = app(VerificationService::class);
        $standing = $verification->apply($owner, $acme, $application($acme), $evidence());
        $verification->approve($second, $standing, ['registration_document', 'representative_authority']);
        $pending = $verification->apply($other, $copy, $application($copy), $evidence());

        $trust = app(BusinessTrustService::class);
        $report = $trust->report($reporter, $acme, ['category' => 'impersonation', 'detail' => 'This page uses the name of another company in our town and quotes its telephone number.']);
        $investigation = $trust->open($reviewer, $acme, 'potential_impersonation', 'The name matches another registered company.', [$report->id], true, 'Please send proof that you own the name you trade under.');
        $appeal = $trust->appeal($owner, $acme, 'investigation', $investigation->id, 'We have traded under this name since 2019 and the registration document we sent shows it clearly.');

        $seminars = app(SeminarService::class);
        $seminar = $seminars->save($host, null, ['title' => 'Pricing your first product', 'summary' => 'A practical hour with worked examples.', 'description' => "What we cover:\n\ncost-plus pricing, value pricing and when to raise a price.",
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'capacity' => 40, 'join_url' => 'https://meet.example.com/pricing'], true);
        $seminars->register($seminar, $attendee);

        return ['seminar' => $seminar->id, 'verification' => $pending->id, 'conflict' => DB::table('identity_conflicts')->orderByDesc('id')->value('id'),
            'report' => $report->id, 'investigation' => $investigation->id, 'appeal' => $appeal->id];
    })(),

    // Mark a person's email verified without the link (used where the link itself is not what is being tested).
    'verify-user' => (function () use ($op) {
        $user = User::where('email', $op['email'])->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        return ['id' => $user->id];
    })(),

    // Give the seeded demonstration accounts the password generated for this run (the seeder's own is a known word).
    'reset-passwords' => (function () use ($op, $password) {
        $count = 0;
        foreach (User::whereIn('email', $op['emails'])->get() as $user) {
            $user->forceFill(['password' => bcrypt($password)])->save();
            $count++;
        }

        return ['count' => $count];
    })(),

    // The sign-in and sign-up limits count per source address, and a browser run is dozens of people at one address.
    // The limits themselves are left as they are; the run empties the counters (the file cache) when it meets one.
    'clear-rate-limits' => (function () {
        // Two tests may empty the cache at the same moment; the loser of that race simply tries again.
        foreach ([1, 2, 3] as $attempt) {
            try {
                return ['flushed' => Cache::flush()];
            } catch (Throwable) {
                usleep(150_000);
            }
        }

        return ['flushed' => false];
    })(),

    // Several browsers write to the one SQLite file at once. Write-ahead logging (a setting kept in the file itself) lets
    // them read while another writes, which the default journal does not.
    'write-ahead-log' => ['mode' => DB::selectOne('PRAGMA journal_mode=WAL')->journal_mode ?? null],

    // Read rows back, to assert what was saved.
    'rows' => DB::table($op['table'])->where($op['where'] ?? [])->orderBy($op['order'] ?? 'id')->limit(50)->get()->all(),

    // Pictures and documents to upload, drawn here so no binary file is kept in the repository.
    'fixtures' => (function () use ($op) {
        $dir = rtrim((string) $op['dir'], '/');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $draw = function (string $file, int $width, int $height, string $type) use ($dir): void {
            $image = imagecreatetruecolor($width, $height);
            imagefill($image, 0, 0, imagecolorallocate($image, 27, 33, 36));
            imagefilledrectangle($image, (int) ($width * 0.1), (int) ($height * 0.1), (int) ($width * 0.9), (int) ($height * 0.9), imagecolorallocate($image, 47, 111, 163));
            imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($height * 0.5), (int) ($height * 0.5), imagecolorallocate($image, 232, 178, 44));
            $type === 'png' ? imagepng($image, $dir.'/'.$file) : imagejpeg($image, $dir.'/'.$file, 82);
            imagedestroy($image);
        };

        $draw('photo-4032x3024.jpg', 4032, 3024, 'jpg');
        $draw('logo-1200x1200.png', 1200, 1200, 'png');
        $draw('cover-3200x1000.jpg', 3200, 1000, 'jpg');
        $draw('evidence-1600x1200.jpg', 1600, 1200, 'jpg');

        // Two files the app must turn away: a type it does not accept, and a picture over the 10 MB limit
        // (an uncompressed 2000 x 2000 PNG is about 12 MB whatever is drawn on it).
        file_put_contents($dir.'/not-a-picture.txt', "This is a text file, not a picture.\n");
        $large = imagecreatetruecolor(2000, 2000);
        imagepng($large, $dir.'/too-large.png', 0);
        imagedestroy($large);

        return collect(scandir($dir))->reject(fn ($f) => str_starts_with($f, '.'))
            ->mapWithKeys(fn ($f) => [$f => filesize($dir.'/'.$f)])->all();
    })(),

    default => throw new InvalidArgumentException('Unknown operation: '.$op['op']),
};

echo "\nE2E_RESULT:".json_encode($result)."\n";
