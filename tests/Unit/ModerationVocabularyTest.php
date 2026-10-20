<?php

namespace Tests\Unit;

use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\Solutions;
use App\Services\Knowledge\ModerationService;
use Tests\TestCase;

/** The words moderation uses must stay in step: every action and reason has a label, and the kinds map to real models. */
class ModerationVocabularyTest extends TestCase
{
    public function test_every_audit_action_has_a_label_and_the_list_matches_the_design(): void
    {
        $this->assertEqualsCanonicalizing(
            ['dismissed', 'hidden', 'restored', 'acceptance_revoked', 'appeal_upheld', 'appeal_denied', 'verified', 'unverified'],
            ModerationAction::ACTIONS,
        );
        $this->assertEqualsCanonicalizing(ModerationAction::ACTIONS, array_keys(ModerationAction::LABELS));
    }

    public function test_every_report_reason_and_status_is_named(): void
    {
        $this->assertEqualsCanonicalizing(ContentReport::REASONS, array_keys(ContentReport::REASON_LABELS));
        $this->assertSame(['open', 'actioned', 'dismissed'], ContentReport::STATUSES);
        $this->assertSame(['open', 'upheld', 'denied'], [ModerationAppeal::OPEN, ModerationAppeal::UPHELD, ModerationAppeal::DENIED]);
    }

    public function test_the_three_kinds_of_moderated_content_map_to_their_models_both_ways(): void
    {
        $this->assertSame(
            ['question' => Questions::class, 'answer' => Answer::class, 'solution' => Solutions::class],
            ModerationService::KINDS,
        );
        $this->assertSame('answer', ModerationService::kindOf(new Answer));
        $this->assertSame('solution', ModerationService::kindOf(new Solutions));
        $this->expectException(\InvalidArgumentException::class);
        ModerationService::kindOf(new ContentReport);
    }

    public function test_an_audit_row_has_no_updated_timestamp(): void
    {
        $this->assertNull(ModerationAction::UPDATED_AT);
        $this->assertSame('Hidden', (new ModerationAction(['action' => 'hidden']))->label());
    }
}
