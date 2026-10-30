<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * R-21 (R-15 phase): a repeatable local-development baseline, so `php artisan migrate:fresh --seed`
 * rebuilds a complete working environment in one command. Idempotent: rows are keyed on fixed
 * emails/titles and re-running updates instead of duplicating. DEV ONLY — it refuses to run in
 * production, because demo accounts with a known password must never exist on the live site.
 *
 * Deliberately NOT seeded: billing subscriptions (Cashier/Paystack state belongs in the provider's
 * test mode, not fabricated rows), binary media (uploading through the UI exercises the real path),
 * and notifications (they arrive naturally the moment the demo accounts interact). The official
 * knowledge library IS seeded, through the same `knowledge:seed` command production used.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder is for local development only.');

            return;
        }

        $owner = $this->user('Demo Owner', 'demo-owner@infodot.test');
        $editor = $this->user('Demo Editor', 'demo-editor@infodot.test');
        $member = $this->user('Demo Member', 'demo-member@infodot.test');

        // A business (Jetstream team) with members in the Jetstream roles.
        $company = Team::firstOrCreate(
            ['name' => 'Demo Traders (Pty) Ltd', 'user_id' => $owner->id],
            ['personal_team' => false],
        );
        $company->users()->syncWithoutDetaching([
            $editor->id => ['role' => 'admin'],
            $member->id => ['role' => 'editor'],
        ]);

        foreach ([$owner, $editor, $member] as $user) {
            $user->forceFill(['current_team_id' => $company->id])->save();
        }

        // A public question with a published answer (the same shapes the controllers write).
        $question = Questions::updateOrCreate(
            ['question' => 'How do I register a small business for VAT?', 'user_id' => $member->id],
            [
                'team_id' => $company->id,
                'visibility' => 'public',
                'description' => 'Turnover is approaching the threshold and I want to know when registration becomes compulsory and what SARS needs from me.',
                'status' => 0,
            ],
        );

        $answerBody = 'Registration is compulsory once taxable supplies pass R1 million in any rolling 12-month period; voluntary registration opens at R50 000. Register on eFiling under SARS Registered Details, and keep your invoices from day one.';
        Answer::firstOrCreate(
            ['question_id' => $question->id, 'user_id' => $editor->id, 'body_hash' => Answer::hashBody($answerBody)],
            ['body' => $answerBody, 'status' => Answer::PUBLISHED, 'is_self_answer' => false],
        );

        // A public solution with steps, written the way KnowledgeSeeder writes them.
        $solution = Solutions::withoutGlobalScopes()
            ->firstOrNew(['solution_title' => 'Demo: month-end reconciliation routine']);
        $solution->user_id = $owner->id;
        $solution->team_id = $company->id;
        $solution->visibility = Solutions::VISIBILITY_PUBLIC;
        $solution->solution_description = 'The routine the demo business follows to close each month: bank, debtors, creditors, then a one-page summary for the owner.';
        $solution->tags = 'finance,admin,month-end';
        $solution->duration = 2;
        $solution->duration_type = 'hours';

        $steps = [
            ['Match the bank statement', 'Export the month\'s bank statement and match every line to an invoice or receipt.'],
            ['Chase late debtors', 'Follow up every customer invoice older than 30 days.'],
            ['Confirm supplier statements', 'Check each supplier statement against the bills you captured.'],
            ['Write the summary', 'One page: cash in, cash out, and what changed since last month.'],
        ];

        $solution->steps = count($steps);
        $solution->save();

        Steps::where('solution_id', $solution->id)->delete();
        foreach ($steps as [$heading, $body]) {
            $row = new Steps;
            $row->user_id = $owner->id;
            $row->solution_id = $solution->id;
            $row->solution_heading = $heading;
            $row->solution_body = $body;
            $row->save();
        }

        // The official knowledge library — identical command to the production seeding run.
        Artisan::call('knowledge:seed', ['--force' => true]);

        $this->command?->info('Demo baseline ready. Accounts: demo-owner@infodot.test, demo-editor@infodot.test, demo-member@infodot.test (password: "password").');
    }

    private function user(string $name, string $email): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
