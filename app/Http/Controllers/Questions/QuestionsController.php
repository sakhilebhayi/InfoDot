<?php

namespace App\Http\Controllers\Questions;

use App\Events\Questions\QuestionWasAsked;
use App\Http\Controllers\Controller;
use App\Models\Questions;
use Illuminate\Http\Request;

class QuestionsController extends Controller
{
    public function index()
    {
        $questions = Questions::withCount(['likes', 'comments'])
            ->latest()
            ->get();

        return view('questions.index', [
            'questions' => $questions,
        ]);
    }

    public function seek()
    {
        return view('questions.seek');
    }

    public function view($qid)
    {
        $question = Questions::where('id', $qid)->first();

        return view('questions.view')->with(['question' => $question]);
    }

    public function add_question(Request $request)
    {
        $request->validate(
            [ // 1st array is field rules
                'question' => 'required|min:3|max:255',
                'description' => 'required|min:3',
            ],
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ]);

        $question = new Questions;

        $question->user_id = auth()->user()->id;
        $question->question = $request->input('question');
        $question->description = $request->input('description');
        $question->save();

        $questions = Questions::all();
        // event(new QuestionWasAsked($question));

        return redirect('questions')->with(['questions' => $questions]);
    }
}
