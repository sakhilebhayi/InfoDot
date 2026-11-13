<?php

namespace Tests\Feature\Stabilisation;

use App\Livewire\Answers;
use App\Livewire\Comments;
use App\Models\Solutions;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * R-49. A moderator hides a question or solution; from then on only its author and moderators see it. Three read
 * paths asked only whether the item was public or the viewer's company's, and never whether it had been hidden.
 */
class HiddenContentStaysHiddenTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private const WHY = 'Hidden while a complaint about it is looked at.';

    public function test_a_hidden_solution_is_not_shown_as_a_recommendation_under_an_answer(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $author = $this->person('Author');
        $question = $this->question($asker);
        $solution = $this->solution($author, 'public', 'Price from a cost floor');

        Livewire::actingAs($helper)->test(Answers::class, ['question' => $question])
            ->set('body', 'There is already a good solution for this, linked below with the steps.')
            ->set('recommendationsPayload', json_encode([['id' => $solution->id]]))
            ->call('publish')->assertHasNoErrors();

        Livewire::actingAs($asker)->test(Answers::class, ['question' => $question])->assertSee('Price from a cost floor');

        app(ModerationService::class)->hide($solution, $this->moderator(), self::WHY);

        Livewire::actingAs($asker)->test(Answers::class, ['question' => $question])->assertDontSee('Price from a cost floor');
        // Its author still sees it, as on the solution's own page.
        Livewire::actingAs($author)->test(Answers::class, ['question' => $question])->assertSee('Price from a cost floor');
    }

    public function test_the_answers_and_comments_of_a_hidden_question_stop_loading_for_other_people(): void
    {
        $asker = $this->person('Asker');
        $reader = $this->person('Reader');
        $question = $this->question($asker);
        $this->answer($question, $this->person('Helper'));

        Livewire::actingAs($reader)->test(Answers::class, ['question' => $question])->assertOk();
        Livewire::actingAs($reader)->test(Comments::class, ['model' => $question])->assertOk();

        app(ModerationService::class)->hide($question, $moderator = $this->moderator(), self::WHY);
        $hidden = $question->fresh() ?? $question->newQueryWithoutScopes()->findOrFail($question->id);

        Livewire::actingAs($reader)->test(Answers::class, ['question' => $hidden])->assertNotFound();
        Livewire::actingAs($reader)->test(Comments::class, ['model' => $hidden])->assertNotFound();

        // The author and moderators keep their view.
        Livewire::actingAs($asker)->test(Answers::class, ['question' => $hidden])->assertOk();
        Livewire::actingAs($moderator)->test(Comments::class, ['model' => $hidden])->assertOk();
    }

    public function test_a_hidden_library_entry_is_not_listed_on_the_landing_page(): void
    {
        $official = $this->person('InfoDot');
        $entry = $this->solution($official, 'public', 'Keep cash under control from week one');
        Solutions::withoutGlobalScopes()->whereKey($entry->id)->update(['seed_key' => 'cash-basics']);

        $this->get('/')->assertOk()->assertSee('Keep cash under control from week one');

        app(ModerationService::class)->hide($entry, $this->moderator(), self::WHY);
        auth()->logout();

        $this->get('/')->assertOk()->assertDontSee('Keep cash under control from week one');
    }
}
