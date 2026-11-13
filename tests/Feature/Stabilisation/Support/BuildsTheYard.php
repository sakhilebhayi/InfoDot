<?php

namespace Tests\Feature\Stabilisation\Support;

use App\Models\Answer;
use App\Models\Attachment;
use App\Models\BusinessAppeal;
use App\Models\BusinessInvestigation;
use App\Models\BusinessReport;
use App\Models\BusinessVerification;
use App\Models\Enquiry;
use App\Models\IdentityConflict;
use App\Models\LibraryEntry;
use App\Models\Questions;
use App\Models\ReputationRingFlag;
use App\Models\Seminar;
use App\Models\SeminarReport;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\AnswerService;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * R-49. One populated copy of the product for the sweeps to walk: a business with everything a business can show
 * (logo, cover, gallery, offerings, a project, a location, an announcement, a brochure, a verification application,
 * an enquiry), knowledge with pictures (a question with an answer, a solution with steps), a seminar, a notice, and
 * something waiting at each moderation desk. Records are made through the app's own forms wherever a form exists,
 * so the sweep walks what a person could really have created.
 *
 * It exists as a trait because the interaction sweep and the image tests need the same world.
 */
trait BuildsTheYard
{
    protected User $owner;

    protected User $member;

    protected User $moderator;

    protected User $colleague;

    protected Team $business;

    protected Questions $question;

    protected Answer $answer;

    protected Solutions $solution;

    protected Seminar $seminar;

    protected Enquiry $enquiry;

    protected BusinessVerification $verification;

    protected BusinessInvestigation $investigation;

    protected BusinessAppeal $appeal;

    protected BusinessReport $openReport;

    protected User $colleague2;

    protected Team $verifiedBusiness;

    protected IdentityConflict $conflict;

    protected Seminar $videoSeminar;

    protected Seminar $smallRoomSeminar;

    protected function buildTheYard(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->owner = User::factory()->withPersonalTeam()->create(['name' => 'Olive Owner']);
        $this->colleague = User::factory()->withPersonalTeam()->create(['name' => 'Adam Admin']);
        $this->member = User::factory()->withPersonalTeam()->create(['name' => 'Mpho Member']);
        $this->colleague2 = User::factory()->withPersonalTeam()->create(['name' => 'Cara Customer']);
        $this->moderator = User::factory()->withPersonalTeam()->create(['name' => 'Rea Reviewer']);
        $this->moderator->forceFill(['is_moderator' => true])->save();

        // The member has a profile picture; the owner has none and is shown by an initial.
        $this->member->updateProfilePhoto(UploadedFile::fake()->image('me.jpg', 320, 320));

        $this->buildBusiness();
        $this->buildKnowledge();
        $this->buildSeminar();
        $this->buildDesks();

        $this->signOut();
    }

    protected function signOut(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    protected function picture(string $name = 'picture.jpg', int $width = 800, int $height = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    protected function pdf(string $name = 'document.pdf'): UploadedFile
    {
        $stream = 'BT /F1 12 Tf 20 700 Td (Document) Tj ET';
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj\n"
            .'4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $pdf);
    }

    protected function businessUrl(string $suffix = ''): string
    {
        return '/business/'.$this->business->uuid.$suffix;
    }

    private function buildBusiness(): void
    {
        $this->business = Team::forceCreate(['user_id' => $this->owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $this->owner->switchTeam($this->business);
        $this->business->users()->attach($this->colleague, ['role' => 'admin']);
        $this->business->profileOrNew()->forceFill([
            'visibility' => 'public', 'description' => 'We drill and equip boreholes across Mpumalanga.', 'location' => 'Nelspruit, Mpumalanga',
            'contact_phone' => '+27 13 555 0100', 'contact_email' => 'hello@acme.example', 'website' => 'https://www.acmeboreholes.example',
            'registration_number' => '2019/123456/07', 'registration_country' => 'South Africa',
        ])->save();

        $as = fn () => $this->actingAs($this->owner);
        foreach (['logo' => [400, 400], 'cover' => [1600, 600], 'gallery' => [1200, 800]] as $role => [$w, $h]) {
            $as()->post($this->businessUrl('/media'), ['role' => $role, 'file' => $this->picture($role.'.jpg', $w, $h), 'caption' => 'The rig on site'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $as()->post($this->businessUrl('/services'), ['name' => 'Solar borehole pump', 'kind' => 'product', 'description' => 'Pumps up to 5 000 litres a day.', 'price_note' => 'From R18 500 installed', 'image' => $this->picture('pump.jpg')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $as()->post($this->businessUrl('/services'), ['name' => 'Borehole drilling', 'kind' => 'service', 'description' => 'Siting, drilling and casing.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $as()->post($this->businessUrl('/projects'), ['title' => 'Farm supply at Hazyview', 'summary' => 'A 150 metre borehole and tank stand.', 'year' => now()->year, 'location' => 'Hazyview', 'image' => $this->picture('farm.jpg'), 'caption' => 'The finished tank stand'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $as()->post($this->businessUrl('/locations'), ['label' => 'Head office', 'address' => '12 Brown Street', 'city' => 'Nelspruit', 'province' => 'Mpumalanga', 'country' => 'South Africa', 'show_address' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $as()->post($this->businessUrl('/announcements'), ['title' => 'New rig in service', 'body' => 'Our second rig is working in the Lowveld from this month.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $as()->post($this->businessUrl('/brochures'), ['title' => 'Price guide', 'file' => $this->pdf('price-guide.pdf')])
            ->assertRedirect()->assertSessionHasNoErrors();

        $as()->post($this->businessUrl('/verification'), [
            'category' => 'registered_company', 'registered_name' => 'Acme Boreholes (Pty) Ltd', 'registration_number' => '2019/123456/07',
            'registration_country' => 'South Africa', 'website' => 'https://www.acmeboreholes.example', 'applicant_role' => 'Director', 'declaration' => '1',
            'evidence' => [['label' => 'registration', 'file' => $this->pdf('cor14-3.pdf')]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->verification = BusinessVerification::where('team_id', $this->business->id)->firstOrFail();

        $as()->post($this->businessUrl('/feature'), ['reason' => 'New rig working in Mpumalanga.'])->assertRedirect()->assertSessionHasNoErrors();

        // A customer writes in, saves the business, and reports it.
        $this->actingAs($this->member)->post($this->businessUrl('/message'), ['subject' => 'Borehole quote', 'category' => 'quote', 'body' => 'We need a borehole drilled on a farm near Hazyview, about 150 metres. When could you come?'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->enquiry = Enquiry::latest('id')->firstOrFail();
        $this->actingAs($this->member)->post($this->businessUrl('/save'), ['new_name' => 'Contractors', 'note' => 'Quoted in March'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->member)->post($this->businessUrl('/report'), ['category' => 'impersonation', 'detail' => 'This page uses the logo and registration number of Acme Water, a different company in Durban.'])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    private function buildKnowledge(): void
    {
        $topic = Topic::first() ?? Topic::create(['slug' => 'water', 'name' => 'Water', 'active' => true, 'sort_order' => 10]);
        $attach = fn (User $by, string $name) => [['uuid' => app(AttachmentService::class)->upload($by, $this->picture($name))->uuid, 'caption' => 'What it looked like']];

        $this->actingAs($this->member)->post('/questions/add', [
            'question' => 'Why does the borehole pump trip after ten minutes?', 'description' => 'It is a 1.1 kW pump on a 60 metre hole. The overload trips every time.',
            'publish' => 1, 'topics' => [$topic->id], 'attachments' => $attach($this->member, 'pump-panel.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->question = Questions::latest('id')->firstOrFail();

        $this->answer = Answer::create(['question_id' => $this->question->id, 'user_id' => $this->owner->id, 'body' => 'Check the run capacitor and the water level first: a pump that runs dry trips on overload within minutes.', 'body_hash' => md5(Str::random(8)), 'status' => 'published']);

        $this->actingAs($this->owner)->post('/solution/add', [
            'solution_title' => 'Testing a borehole pump that keeps tripping', 'solution_description' => 'A way to find out whether the fault is electrical or in the hole.',
            'tags-input' => 'borehole, pump', 'publish' => 1, 'topics' => [$topic->id], 'duration' => 2, 'duration_type' => 'hours',
            'solution_heading' => ['Measure the current', 'Check the water level'], 'solution_body' => ['Clamp each phase and compare with the plate.', 'Lower a dip meter and note the level while pumping.'],
            'attachments' => $attach($this->owner, 'meter.jpg'),
            'step_attachments' => [0 => $attach($this->owner, 'clamp.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->solution = Solutions::latest('id')->firstOrFail();

        $comment = $this->question->comments()->make(['body' => 'Is the pump on a soft starter?']);
        $comment->user()->associate($this->colleague);
        $comment->save();

        LibraryEntry::create(['contribution_ref' => 'infodot:answer:'.$this->answer->id, 'kind' => 'answer', 'answer_id' => $this->answer->id, 'adopted_by' => $this->moderator->id, 'adopted_at' => now()]);

        UserNotice::create(['user_id' => $this->member->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => $this->question->id, 'ref' => 'r49-notice', 'created_at' => now()]);
    }

    private function buildSeminar(): void
    {
        $this->seminar = app(SeminarService::class)->save($this->owner, null, [
            'title' => 'Pricing a drilling job', 'summary' => 'A practical hour on quoting so the job pays.',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room-1',
        ], true);
        app(SeminarService::class)->register($this->seminar, $this->member);
    }

    /** Something waiting at every moderation desk. */
    private function buildDesks(): void
    {
        ReputationRingFlag::create(['kind' => 'cycle', 'member_ids' => [$this->member->id, $this->colleague->id], 'member_hash' => ReputationRingFlag::hashOf([$this->member->id, $this->colleague->id]), 'edge_count' => 2, 'internal_ratio' => 1.0, 'status' => 'open', 'detected_at' => now()]);

        // The content queue: the colleague reports the member's question.
        app(AnswerService::class)->report($this->question, $this->colleague, 'spam');

        // The seminar desk.
        $this->actingAs($this->member)->post('/seminars/'.$this->seminar->id.'/report', ['reason' => array_key_first(SeminarReport::REASONS), 'detail' => 'The description promises something the host cannot deliver.'])->assertRedirect()->assertSessionHasNoErrors();

        // The trust desk: an investigation opened from the member's report (no restriction, so the page stays public), and the owner's appeal.
        $report = BusinessReport::latest('id')->firstOrFail();
        $this->actingAs($this->moderator)->post('/moderation/trust/investigations', [
            'team_id' => $this->business->id, 'concern' => 'potential_impersonation', 'summary' => 'Branding matches Acme Water.',
            'report_ids' => [$report->id], 'restrict' => 1, 'message' => 'Please send proof that you own the Acme name and logo.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->investigation = BusinessInvestigation::latest('id')->firstOrFail();
        $this->actingAs($this->owner)->post($this->businessUrl('/appeals'), ['subject' => 'investigation:'.$this->investigation->id, 'body' => 'We are an Acme Water franchise and use the mark under licence; the agreement is attached.', 'files' => [$this->pdf('licence.pdf')]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->appeal = BusinessAppeal::latest('id')->firstOrFail();
        $this->actingAs($this->moderator)->post('/moderation/trust/investigations/'.$this->investigation->id.'/lift', ['note' => 'Lifted while the appeal is read.']);

        // A second report stays open on the desk.
        $this->actingAs($this->colleague2)->post($this->businessUrl('/report'), ['category' => 'impersonation', 'detail' => 'The phone number on this page belongs to another borehole company in Durban.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->openReport = BusinessReport::latest('id')->firstOrFail();

        // The identity desk: a second business is verified, then a third account copies its name and registration number.
        $verified = Team::forceCreate(['user_id' => $this->colleague2->id, 'name' => 'Lowveld Pumps (Pty) Ltd', 'personal_team' => false]);
        $verified->profileOrNew()->forceFill(['visibility' => 'public', 'description' => 'Pump repairs and spares.', 'registration_number' => '2015/654321/07', 'registration_country' => 'South Africa'])->save();
        $this->actingAs($this->colleague2)->post('/business/'.$verified->uuid.'/verification', $this->application('Lowveld Pumps (Pty) Ltd', '2015/654321/07'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->moderator)->post('/moderation/verifications/'.BusinessVerification::where('team_id', $verified->id)->firstOrFail()->id.'/approve', ['checks' => ['registration_document', 'representative_authority']])
            ->assertSessionHasNoErrors();
        $this->verifiedBusiness = $verified->fresh();
        $copy = Team::forceCreate(['user_id' => $this->colleague->id, 'name' => 'LOWVELD PUMPS PTY LTD', 'personal_team' => false]);
        $copy->profileOrNew()->fill(['registration_number' => '2015 654321 07', 'registration_country' => 'South Africa'])->save();
        $this->conflict = IdentityConflict::where('subject_team_id', $copy->id)->firstOrFail();

        // A pending team invitation on the business's settings page.
        $this->business->teamInvitations()->create(['email' => 'new.hire@acme.example', 'role' => 'editor']);
    }

    /** @return array<string, mixed> */
    private function application(string $name, string $number): array
    {
        return [
            'category' => 'registered_company', 'registered_name' => $name, 'registration_number' => $number, 'registration_country' => 'South Africa',
            'applicant_role' => 'Director', 'declaration' => '1',
            'evidence' => [['label' => 'registration', 'file' => $this->pdf('cor14-3.pdf')], ['label' => 'authority', 'file' => $this->pdf('resolution.pdf')]],
        ];
    }

    /**
     * The parts of the product that are switched off until the owner turns them on: paid seminars and the two kinds
     * of built-in seminar room. Built only for the sweeps that walk the product with everything on.
     */
    protected function switchEverythingOn(): void
    {
        Http::fake();
        config([
            'seminars.paid.enabled' => true, 'seminars.paid.agreement_approved' => true, 'seminars.points.enabled' => true,
            'seminars.p2p.enabled' => true, 'seminars.video.enabled' => true, 'seminars.video.url' => 'wss://live.example.test', 'seminars.video.api_key' => Str::random(12), 'seminars.video.api_secret' => Str::random(32),
            'licensing.finance.url' => 'https://finance.example.test', 'licensing.finance.secret' => Str::random(32), 'licensing.finance.platform' => 'infodot',
            'reputation.enabled' => true,
        ]);
        $this->actingAs($this->member)->post('/progress/appeal', ['message' => 'My accepted answers are missing from the total.'])->assertRedirect();
        DB::table('seminar_commission_invoices')->insert(['host_id' => $this->owner->id, 'period' => now()->format('Y-m'), 'year' => now()->year, 'seq' => 1, 'number' => 'INV-'.now()->year.'-0001', 'kind' => 'invoice', 'fee_cents' => 2000, 'vat_cents' => 261, 'excl_vat_cents' => 1739, 'lines' => '[]', 'issuer' => json_encode(['name' => 'InfoDot (Pty) Ltd', 'address' => '1 Yard Road, Nelspruit', 'vat_number' => '4000000000', 'vat_rate_percent' => 15, 'commission_includes_vat' => true]), 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $seminars = app(SeminarService::class);
        $soon = ['summary' => 'A practical hour.', 'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60];
        $this->videoSeminar = $seminars->save($this->owner, null, $soon + ['title' => 'Reading a pump curve', 'delivery' => 'room'], true);
        $this->smallRoomSeminar = $seminars->save($this->owner, null, $soon + ['title' => 'Siting a borehole', 'delivery' => 'p2p', 'p2p_quality' => 'low', 'p2p_uplink_kbps' => 5000], true);
        $seminars->register($this->videoSeminar, $this->member);
        $seminars->register($this->smallRoomSeminar, $this->member);
        // A seminar cannot be created less than an hour ahead; these two are then brought to five minutes before the start, when the rooms are open.
        $this->videoSeminar->forceFill(['starts_at' => now()->addMinutes(5)])->save();
        $this->smallRoomSeminar->forceFill(['starts_at' => now()->addMinutes(5)])->save();
        $this->signOut();
    }

    /**
     * Pages name built files through Vite. The committed copy of public/build is not what ships (the deploy builds its
     * own), so here every built image is named by its SOURCE path under /build/source/, and the sweeps check that the
     * source file exists and that the build picks it up (builtFromSource). Entry scripts and styles are left out.
     */
    protected function nameBuiltFilesBySource(): void
    {
        ViteFacade::clearResolvedInstance();
        $this->swap(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null)
            {
                return new HtmlString('');
            }

            public function asset($asset, $buildDirectory = null)
            {
                return '/build/source/'.$asset;
            }
        });
    }

    /** Why a file named through Vite would not reach the browser, or null when it will. */
    protected function builtFromSource(string $path): ?string
    {
        $source = Str::after($path, '/build/source/');
        if (! is_file(base_path($source))) {
            return "the source file {$source} does not exist";
        }
        preg_match('/import\.meta\.glob\(\[(.*?)\]\)/s', (string) file_get_contents(resource_path('js/app.js')), $m);
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1] ?? '', $globs);
        foreach ($globs[1] as $glob) {
            $pattern = '#^'.str_replace('\*', '[^/]+', preg_quote('resources/'.Str::after($glob, '../'), '#')).'$#';
            if (preg_match($pattern, $source)) {
                return null;
            }
        }

        return "{$source} is not matched by the import.meta.glob list in resources/js/app.js, so the build leaves it out";
    }

    /** @return list<Attachment> */
    protected function businessPictures(): array
    {
        return Attachment::whereIn('role', ['logo', 'cover', 'gallery', 'offering', 'project'])->whereNull('superseded_at')->get()->all();
    }
}
