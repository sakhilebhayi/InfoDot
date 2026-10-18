<?php

namespace App\Http\Controllers\Solutions;

use App\Http\Controllers\Controller;
use App\Models\Solutions;
use App\Models\Steps;
use App\Support\Industry;
use Illuminate\Http\Request;

class SolutionsController extends Controller
{
    public function index()
    {
        $solutions = Solutions::withCount(['likes', 'comments'])
            ->with(['user', 'team'])
            ->latest()
            ->get();

        return view('solutions.index', [
            'solutions' => $solutions,
            'industry' => Industry::for(auth()->user()->currentTeam),
        ]);
    }

    public function create()
    {
        return view('solutions.create', ['industry' => Industry::for(auth()->user()->currentTeam)]);
    }

    public function add_solution(Request $request)
    {
        $request->validate(
            [ // 1st array is field rules
                'solution_title' => 'required|min:3|max:255',
                'solution_description' => 'required|min:3',
                'tags-input' => 'required|min:3',
                'publish' => 'nullable|boolean',
                'duration' => 'required|integer|min:1',
                'duration_type' => 'required|in:hours,days,weeks,months,years,infinite',
                'solution_heading' => 'required|array|min:1',
                'solution_heading.*' => 'required|string|max:255',
                'solution_body' => 'required|array',
                'solution_body.*' => 'required|string',
            ],
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ]);

        $solution = new Solutions;

        $solution->user_id = auth()->user()->id;
        $solution->team_id = auth()->user()->current_team_id;
        $solution->visibility = $request->boolean('publish') ? Solutions::VISIBILITY_PUBLIC : Solutions::VISIBILITY_COMPANY;
        $solution->solution_title = $request->input('solution_title');
        $solution->solution_description = $request->input('solution_description');
        $solution->tags = $request->input('tags-input');
        $solution->duration = $request->input('duration');
        $solution->duration_type = $request->input('duration_type');
        $solution->steps = count($request->input('solution_heading'));
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
        $solution = Solutions::where('id', $id)->firstOrFail();

        return view('solutions.view')->with(['solution' => $solution]);

    }
}
