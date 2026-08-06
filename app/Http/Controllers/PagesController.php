<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use App\Models\Steps;
use App\Models\Solutions;
use App\Models\Questions;
use App\Models\Associates;
use Illuminate\Http\Request;

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
            'message' => $request->message
        ];
        //Need to fix this email feature
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

    public function terms(Request $request)
    {
        return view('terms');
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
            $solutions = Solutions::whereRaw('MATCH (solution_title, solution_description, tags) AGAINST (?)', array($search_term))->orderBy('id', 'DESC')->paginate(5);
            $questions = Questions::whereRaw('MATCH (question, description) AGAINST (?)', array($search_term))->orderBy('id', 'DESC')->paginate(5);
        }

        $results = [
            'questions' => $questions,
            'solutions' => $solutions
        ];

        return view('search_results', ['results' => $results]);

    }

    public function show($id)
    {
        $get_profile = User::where('id', $id)->first();
        $association = Associates::where('user_id', Auth::id())->where('associate_id', $id)->whereNull('deleted_at')->first();
        return view('profile.show', [
            'user' => $get_profile,
            'association' => $association,
        ]);
    }

    public function edit()
    {
        return view('profile.edit');
    }
}
