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
| 1, 1/2, 1/4, then nothing, and half again when that asker's own answers were accepted by you), nothing for yourself, a gap costs nothing (no streaks), and a moderator hiding your work costs trust.
*/

return [
    'enabled' => (bool) env('REPUTATION_ENABLED', false),

    'version' => 1,

    /*
     | Ring detection (advisory). Over the last `window_days`, acceptances are a directed graph (the person who accepted an answer ->
     | its author; solutions a non-moderator verified count the same way). Moderators are never members: what they accept or verify
     | is their job, not vouching. A flag is raised for
     |   - a cycle of 3 or 4 accounts (A accepted B, B accepted C, C accepted A), every link seen at least `min_edges_per_link` times;
     |   - a tight cluster: accounts joined by MUTUAL acceptance, 3 to 8 of them, where each member got at least `min_internal_ratio`
     |     of the acceptances they received from inside the group, the group has at least `min_density` of all possible directed
     |     links and at least `min_internal_edges` acceptances between its members.
     | A pair that accepts each other is not a ring: the reciprocal control in `points.answer_accepted` already halves it.
     | A flag is only a question for a moderator. It bans nobody and nobody in it is told. While it is open or confirmed, an
     | acceptance between two of its members counts `discount_factor` times as much (never stacked with the reciprocal factor: the
     | lower of the two applies). A dismissed flag has no effect. Edit a number, raise `version`; nothing in the code changes.
     */
    'rings' => [
        'enabled' => (bool) env('REPUTATION_RINGS_ENABLED', true),
        'window_days' => 180,
        'min_edges_per_link' => 1,
        'cluster' => ['min_members' => 3, 'max_members' => 8, 'min_internal_ratio' => 0.8, 'min_density' => 0.5, 'min_internal_edges' => 6],
        'discount_factor' => 0.25,
    ],

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
        'answer_accepted' => ['dimension' => 'impact', 'each' => 10, 'decay' => [1.0, 0.5, 0.25], 'reciprocal_factor' => 0.5], // decay per asker, in order of acceptance; reciprocal: that asker also accepted this person's answers
        'solution_verified' => ['dimension' => 'impact', 'each' => 15, 'max_count' => 10],
        'library_adoption' => ['dimension' => 'impact', 'each' => 20, 'max_count' => 5],
        'active_week' => ['dimension' => 'consistency', 'each' => 2, 'window_weeks' => 12],
        'expertise_area' => ['dimension' => 'expertise', 'each' => 15],
        'vouched_for' => ['dimension' => 'trust', 'each' => 2, 'max_count' => 15],   // accepted answers + verified solutions, any asker
        'seminar_host_rated_well' => ['dimension' => 'trust', 'each' => 10, 'min_average' => 4.0],
        'moderator_hid_work' => ['dimension' => 'trust', 'each' => -10, 'max_count' => 3],
    ],
];
