<?php

namespace Tests\Feature\Onboarding;

use App\Models\CompanyProfileImport;
use App\Models\Team;
use App\Models\User;
use App\Services\Onboarding\DocumentExtractor;
use App\Services\Onboarding\DocumentRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-62 phase 7: company details read from a PDF or text document by rules, never stored, never guessed, always checked by the person. */
class DocumentImportTest extends TestCase
{
    use RefreshDatabase;

    private const CERTIFICATE = <<<'TXT'
DLAMINI FREIGHT (PTY) LTD
Registration Certificate

Registration number: 2009/123456/07
Business description: We move freight across KwaZulu-Natal.
Physical address: 12 Dock Road, Durban, 4001
Contact: hello@dlaminifreight.co.za, +27 31 555 0100
Website: www.dlaminifreight.co.za
TXT;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('company-import:1');
    }

    private function person(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function text(string $body, string $name = 'profile.txt'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $body);
    }

    /** A real, small PDF with one line of text per entry, built by hand so the test needs no PDF library to write it. */
    private function pdf(array $lines): UploadedFile
    {
        $stream = "BT /F1 12 Tf 50 750 Td 14 TL\n";
        foreach ($lines as $line) {
            $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
        }
        $stream .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return UploadedFile::fake()->createWithContent('certificate.pdf', $pdf);
    }

    public function test_labelled_lines_and_exact_patterns_are_read_from_text(): void
    {
        $out = app(DocumentExtractor::class)->extract($this->text(self::CERTIFICATE));
        $f = collect($out['fields'])->map(fn ($x) => $x['value'])->all();

        $this->assertSame('text', $out['kind']);
        $this->assertSame('DLAMINI FREIGHT (PTY) LTD', $f['name']);
        $this->assertSame('2009/123456/07', $f['registration_number']);
        $this->assertSame('We move freight across KwaZulu-Natal', $f['description']);
        $this->assertSame('12 Dock Road, Durban, 4001', $f['location']);
        $this->assertSame('hello@dlaminifreight.co.za', $f['contact_email']);
        $this->assertSame('+27 31 555 0100', $f['contact_phone']);
        $this->assertSame('https://dlaminifreight.co.za', $f['website']);
        $this->assertArrayNotHasKey('industry', $f, 'an industry is never guessed');
        foreach ($out['fields'] as $field) {
            $this->assertGreaterThan(0, $field['confidence']);
            $this->assertLessThan(1, $field['confidence']);
        }
    }

    public function test_a_pdf_with_a_text_layer_is_read(): void
    {
        $out = app(DocumentExtractor::class)->extract($this->pdf(['Company name: Kestrel Mining (Pty) Ltd', 'Email: info@kestrel.co.za']));

        $this->assertSame('pdf', $out['kind']);
        $this->assertSame('Kestrel Mining (Pty) Ltd', $out['fields']['name']['value']);
        $this->assertSame('info@kestrel.co.za', $out['fields']['contact_email']['value']);
    }

    public function test_what_is_not_a_pdf_or_text_is_refused_whatever_it_is_called(): void
    {
        $this->expectException(DocumentRejected::class);
        // A program with a .pdf name: the bytes decide, not the name.
        app(DocumentExtractor::class)->extract(UploadedFile::fake()->createWithContent('invoice.pdf', "MZ\x90\x00\x03\x00\x00\x00binary"));
    }

    public function test_a_broken_pdf_empty_file_and_blank_text_are_refused_plainly(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4 this is not really a pdf'),
            UploadedFile::fake()->createWithContent('b.txt', ''),
            $this->text("   \n\n  "),
        ] as $file) {
            try {
                app(DocumentExtractor::class)->extract($file);
                $this->fail('expected a refusal');
            } catch (DocumentRejected $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_an_oversized_file_is_refused(): void
    {
        $this->expectException(DocumentRejected::class);
        app(DocumentExtractor::class)->extract($this->text(str_repeat('a', DocumentExtractor::MAX_BYTES + 10)));
    }

    public function test_the_flow_fills_the_form_stores_no_file_and_creates_nothing(): void
    {
        Storage::fake();
        $user = $this->person();

        $this->actingAs($user)->post(route('onboarding.business.document'), ['document' => $this->text(self::CERTIFICATE, 'My Certificate (1).txt')])
            ->assertRedirect(route('onboarding.business', ['import' => 1]));

        $import = CompanyProfileImport::sole();
        $this->assertSame(['document', 'draft', 'My Certificate 1.txt'], [$import->source, $import->status, $import->source_ref]);
        $this->assertSame(0, Team::where('personal_team', false)->count());
        $this->assertSame([], Storage::allFiles(), 'the file itself is never kept');

        $this->actingAs($user)->get(route('onboarding.business', ['import' => $import->id]))->assertOk()
            ->assertSee('We read the document My Certificate 1.txt')->assertSee('Read from your document')->assertSee('value="2009/123456/07"', false)
            ->assertSee('We never guess an industry');
    }

    public function test_confirming_records_the_document_as_the_source_of_what_was_kept(): void
    {
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.document'), ['document' => $this->text(self::CERTIFICATE)])->assertRedirect();
        $import = CompanyProfileImport::sole();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'import' => $import->id, 'name' => 'Dlamini Freight (Pty) Ltd', 'industry' => 'logistics', 'description' => 'Our own words.', 'visibility' => 'public',
            'registration_number' => '2009/123456/07', 'contact_email' => 'hello@dlaminifreight.co.za',
        ])->assertRedirect(route('onboarding.recommendations'));

        $team = Team::where('personal_team', false)->sole();
        $sources = $team->profile()->first()->field_sources;
        $this->assertSame('2009/123456/07', $team->profile()->first()->registration_number);
        $this->assertSame('document', $sources['registration_number']['source']);
        $this->assertSame('manual', $sources['description']['source'], 'the person rewrote it');
        $this->assertSame('confirmed', $import->fresh()->status);
    }

    public function test_a_file_with_no_company_details_is_abandoned_and_says_so(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post(route('onboarding.business.document'), ['document' => $this->text('Just some notes about lunch.')])
            ->assertRedirect(route('onboarding.business'))->assertSessionHasErrors('document');

        $this->assertSame('abandoned', CompanyProfileImport::sole()->status);
    }

    public function test_a_file_that_is_not_a_document_is_refused_with_a_plain_message(): void
    {
        $this->actingAs($this->person())->post(route('onboarding.business.document'), ['document' => UploadedFile::fake()->createWithContent('x.pdf', "MZ\x90\x00\x00")])
            ->assertSessionHasErrors('document');
    }

    public function test_no_file_a_guest_and_another_persons_import_are_all_handled(): void
    {
        $this->post(route('onboarding.business.document'), [])->assertRedirect();
        $this->actingAs($this->person())->post(route('onboarding.business.document'), [])->assertSessionHasErrors('document');

        $other = $this->person();
        $this->actingAs($other)->post(route('onboarding.business.document'), ['document' => $this->text(self::CERTIFICATE)]);
        $mine = $this->person();
        $this->actingAs($mine)->get(route('onboarding.business', ['import' => CompanyProfileImport::sole()->id]))->assertOk()->assertDontSee('We read the document');
    }

    public function test_reading_is_limited_per_person_per_day_together_with_websites(): void
    {
        $user = $this->person();
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('company-import:'.$user->id, 86400);
        }

        $this->actingAs($user)->post(route('onboarding.business.document'), ['document' => $this->text(self::CERTIFICATE)])->assertSessionHasErrors('document');
        $this->assertSame(0, CompanyProfileImport::count());
    }

    public function test_text_found_in_a_document_is_escaped_when_shown(): void
    {
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.document'), ['document' => $this->text("Company name: <script>alert(1)</script> Traders (Pty) Ltd\nEmail: a@b.co.za")])->assertRedirect();

        $html = $this->actingAs($user)->get(route('onboarding.business', ['import' => CompanyProfileImport::sole()->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
