<?php

namespace App\Http\Controllers;

use App\Models\Associates;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AchievementService;
use App\Services\Points\PointsClient;
use App\Support\Knowledge\ContributionStats;
use App\Support\Knowledge\ModerationStatus;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Jetstream\Jetstream;

class PagesController extends Controller
{
    public function about(Request $request)
    {
        return view('about');
    }

    public function contact()
    {
        return view('contact');
    }

    public function contactSend(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'message' => 'required',
        ]);

        $details = [
            'name' => $request->name,
            'email' => $request->email,
            'message' => $request->message,
        ];

        // Need to fix this email feature
        // \Mail::to('testmail@gmail.com')->send(new \App\Mail\MailContact($details));
        return redirect()->route('contact')->with('success', 'Message Sent Successfully');
    }

    public function faqs(Request $request)
    {
        return view('faqs');
    }

    public function complains(Request $request)
    {
        return view('complains');
    }

    public function features(Request $request)
    {
        return view('features');
    }

    /**
     * Legal pages read their content from a Markdown source file (resources/markdown/*.md), the
     * same convention Jetstream's own termsAndPrivacyPolicy feature uses (see
     * vendor/laravel/jetstream/src/Http/Controllers/Livewire/TermsOfServiceController.php).
     * Keeping content in Markdown rather than inline Blade HTML is what makes it centrally
     * maintainable: editing the wording is a one-file content change, no markup to touch, and the
     * same file can be copied to another platform's resources/markdown/ directory unchanged.
     */
    public function terms(Request $request)
    {
        return view('terms', [
            'terms' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('terms.md'))),
        ]);
    }

    public function policy(Request $request)
    {
        return view('policy', [
            'policy' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('policy.md'))),
        ]);
    }

    public function cookies(Request $request)
    {
        return view('cookies', [
            'cookies' => Str::markdown(file_get_contents(Jetstream::localizedMarkdownPath('cookies.md'))),
        ]);
    }

    public function solution_search_results()
    {
        $results = [];
        $search_term = request()->search;
        $driver = \DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $solutions = Solutions::where(function ($query) use ($search_term) {
                $query->where('solution_title', 'like', "%{$search_term}%")
                    ->orWhere('solution_description', 'like', "%{$search_term}%")
                    ->orWhere('tags', 'like', "%{$search_term}%");
            })->orderBy('id', 'DESC')->paginate(5);

            $questions = Questions::where(function ($query) use ($search_term) {
                $query->where('question', 'like', "%{$search_term}%")
                    ->orWhere('description', 'like', "%{$search_term}%");
            })->orderBy('id', 'DESC')->paginate(5);
        } elseif ($driver === 'pgsql') {
            // No FULLTEXT index exists on Postgres (the migrations only create one when the driver is
            // mysql — see create_solutions_table.php / create_questions_table.php), and MySQL's
            // MATCH()...AGAINST() syntax used below isn't valid Postgres SQL at all. ILIKE is Postgres's
            // native case-insensitive LIKE, so this mirrors the sqlite branch above.
            $solutions = Solutions::where(function ($query) use ($search_term) {
                $query->where('solution_title', 'ilike', "%{$search_term}%")
                    ->orWhere('solution_description', 'ilike', "%{$search_term}%")
                    ->orWhere('tags', 'ilike', "%{$search_term}%");
            })->orderBy('id', 'DESC')->paginate(5);

            $questions = Questions::where(function ($query) use ($search_term) {
                $query->where('question', 'ilike', "%{$search_term}%")
                    ->orWhere('description', 'ilike', "%{$search_term}%");
            })->orderBy('id', 'DESC')->paginate(5);
        } else {
            // mysql — a real FULLTEXT index backs these columns for this driver only.
            $solutions = Solutions::whereRaw('MATCH (solution_title, solution_description, tags) AGAINST (?)', [$search_term])->orderBy('id', 'DESC')->paginate(5);
            $questions = Questions::whereRaw('MATCH (question, description) AGAINST (?)', [$search_term])->orderBy('id', 'DESC')->paginate(5);
        }

        $results = [
            'questions' => $questions,
            'solutions' => $solutions,
        ];

        return view('search_results', ['results' => $results]);

    }

    public function show($id)
    {
        // Only yourself and people who share a company with you. Anyone else is
        // answered as if the profile did not exist.
        $get_profile = User::where('id', $id)->firstOrFail();
        abort_unless(Auth::id() === $get_profile->id || $this->sharesATeam(Auth::user(), $get_profile), 404);
        $association = Associates::where('user_id', Auth::id())->where('associate_id', $id)->whereNull('deleted_at')->first();

        $isOwner = Auth::id() === $get_profile->id;

        return view('profile.show', [
            'user' => $get_profile,
            'association' => $association,
            'stats' => ContributionStats::for($get_profile, $isOwner),
            // Milestones are not points: their own data, their own section. Others only see them when the owner shares them.
            'achievements' => app(AchievementService::class)->profileFor($get_profile, $isOwner),
            // Points are the owner's own business: nobody else's profile view asks the ledger for them.
            'points' => $isOwner ? app(PointsClient::class)->summary((string) $get_profile->uuid) : null,
            'pointsConfigured' => $isOwner && app(PointsClient::class)->enabled(),
            // Moderation of someone's content is their own business: only the owner's view asks.
            'moderation' => $isOwner ? ModerationStatus::for($get_profile) : collect(),
        ]);
    }

    private function sharesATeam(?User $viewer, User $other): bool
    {
        if (! $viewer) {
            return false;
        }

        $mine = $viewer->allTeams()->pluck('id');

        return $other->allTeams()->pluck('id')->intersect($mine)->isNotEmpty();
    }

    public function edit()
    {
        return view('profile.edit');
    }
}
