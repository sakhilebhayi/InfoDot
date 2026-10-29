<?php

/*
|--------------------------------------------------------------------------
| Knowledge reputation and entrepreneur tiers (R-13)
|--------------------------------------------------------------------------
|
| OFF by default. When on, each person can see their own progress and nobody else can: there is no leaderboard, no public tier and no
| ranking (Dot.Brain forbids reputation as a trust signal and forbids leaderboards). A tier never grants organisation permissions; roles
| are a separate system (App\Support\TeamRoles).
|
| Every number below is a STARTING PROPOSAL, not a measurement: there is no real usage data yet. Change a number, raise `version`,
| and recalculate; nothing in the code needs to change. Each snapshot stores the version it was computed with.
|
| Score = for each of six dimensions, the sum of its rule points capped at the dimension cap, times the dimension weight.
| Work counts only when it counts for achievements (public, published, not hidden, never help you gave yourself).
| Manipulation controls: caps, nothing for private work, help from the same person decays (accepted answers from one asker count
| 1, 1/2, 1/4, then nothing), nothing for yourself, a gap costs nothing (no streaks), and a moderator hiding your work costs trust.
*/

return [
    'enabled' => (bool) env('REPUTATION_ENABLED', false),

    'version' => 1,

    'tiers' => [
        // key => [name, score needed]. Names and thresholds are configuration.
        'spark' => ['name' => 'InfoDot Spark', 'from' => 0],
        'venture' => ['name' => 'InfoDot Venture', 'from' => 40],
        'growth' => ['name' => 'InfoDot Growth', 'from' => 120],
        'luminary' => ['name' => 'InfoDot Luminary', 'from' => 300],
    ],

    'dimensions' => [
        'learning' => ['label' => 'Learning', 'weight' => 0.8, 'cap' => 40, 'what' => 'Asking public questions, covering different topics and attending seminars.'],
        'contribution' => ['label' => 'Contribution', 'weight' => 1.0, 'cap' => 80, 'what' => 'Answers you gave and solutions you wrote.'],
        'impact' => ['label' => 'Impact', 'weight' => 1.5, 'cap' => 150, 'what' => 'Answers other people accepted, solutions a moderator verified, work added to the library.'],
        'consistency' => ['label' => 'Consistency', 'weight' => 0.4, 'cap' => 24, 'what' => 'Weeks in the last 12 when you asked, answered or wrote something. A gap costs nothing.'],
        'expertise' => ['label' => 'Expertise', 'weight' => 1.2, 'cap' => 75, 'what' => 'Topics where your work was accepted or verified at least twice.'],
        'trust' => ['label' => 'Trust', 'weight' => 1.5, 'cap' => 40, 'what' => 'Work other people vouched for, seminars rated well by attendees, and a clean record with moderators.'],
    ],

    'points' => [
        'question_asked' => ['dimension' => 'learning', 'each' => 2, 'max_count' => 10],
        'topic_explored' => ['dimension' => 'learning', 'each' => 3, 'max_count' => 6],
        'seminar_attended' => ['dimension' => 'learning', 'each' => 5, 'max_count' => 4],
        'answer_given' => ['dimension' => 'contribution', 'each' => 3, 'max_count' => 20],
        'solution_written' => ['dimension' => 'contribution', 'each' => 5, 'max_count' => 10],
        'answer_accepted' => ['dimension' => 'impact', 'each' => 10, 'decay' => [1.0, 0.5, 0.25]], // per asker, in order of acceptance
        'solution_verified' => ['dimension' => 'impact', 'each' => 15, 'max_count' => 10],
        'library_adoption' => ['dimension' => 'impact', 'each' => 20, 'max_count' => 5],
        'active_week' => ['dimension' => 'consistency', 'each' => 2, 'window_weeks' => 12],
        'expertise_area' => ['dimension' => 'expertise', 'each' => 15],
        'vouched_for' => ['dimension' => 'trust', 'each' => 2, 'max_count' => 15],   // accepted answers + verified solutions, any asker
        'seminar_host_rated_well' => ['dimension' => 'trust', 'each' => 10, 'min_average' => 4.0],
        'moderator_hid_work' => ['dimension' => 'trust', 'each' => -10, 'max_count' => 3],
    ],
];
