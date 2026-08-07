<?php

namespace App\Http\Controllers\Solutions;

use App\Http\Controllers\Controller;
use App\Models\Solutions;
use App\Models\Steps;
use Illuminate\Http\Request;

class SolutionsController extends Controller
{
    public function index()
    {
        $solutions = Solutions::withCount(['likes', 'comments'])
            ->latest()
            ->get();

        return view('solutions.index', [
            'solutions' => $solutions,
        ]);
    }

    public function create()
    {
        return view('solutions.create');
    }

    public function add_solution(Request $request)
    {
        $request->validate(
            [ // 1st array is field rules
                'solution_title' => 'required|min:3|max:255',
                'solution_description' => 'required|min:3',
                'tags-input' => 'required|min:3',
                'duration' => 'required',
                'duration_type' => 'required',
            ],
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ]);

        $solution = new Solutions;

        $solution->user_id = auth()->user()->id;
        $solution->solution_title = $request->input('solution_title');
        $solution->solution_description = $request->input('solution_description');
        $solution->tags = $request->input('tags-input');
        $solution->duration = $request->input('duration');
        $solution->duration_type = $request->input('duration_type');
        $solution->steps = $request->input('steps');
        $solution->save();

        $steps = $request->input('solution_heading');

        foreach ($steps as $key => $n) {
            $solution_step = new Steps;

            $solution_step->user_id = auth()->user()->id;
            $solution_step->solution_id = $solution->id;
            $solution_step->solution_heading = $request->input('solution_heading')[$key];
            $solution_step->solution_body = $request->input('solution_body')[$key];
            $solution_step->save();
        }

        return redirect()->route('solutions');
    }

    public function view_solution($id)
    {
        $solution = Solutions::where('id', $id)->first();

        return view('solutions.view')->with(['solution' => $solution]);

    }
}
