<?php

/*
|--------------------------------------------------------------------------
| Contribution achievements
|--------------------------------------------------------------------------
|
| Milestones for knowledge the community can use. They are not points: nothing here can be redeemed, they carry
| no score, and they are never ranked or compared. A milestone is a plain count of CURRENT qualifying work, so it is
| taken back if that work is hidden or its acceptance or verification is removed, and it comes back if the work does.
|
| Qualifying work, in every metric: published, not hidden by a moderator, on a public question or solution (company-only
| work is never counted, so a milestone cannot hint at private work), and never help you gave yourself.
|
|   questions  public questions you asked
|   answers    published answers you gave to other people's public questions
|   accepted   of those, answers somebody other than you accepted as the solution
|   verified   public solutions you wrote that a moderator other than you verified
|
| `version` is stored on every award. Change a threshold or add a milestone by editing this file and raising the
| version; nothing in the code needs to change.
*/

return [
    'version' => 1,

    'metrics' => [
        'questions' => ['label' => 'questions asked'],
        'answers' => ['label' => 'answers given'],
        'accepted' => ['label' => 'accepted answers'],
        'verified' => ['label' => 'verified solutions'],
    ],

    'achievements' => [
        'first_question' => [
            'name' => 'First question',
            'meaning' => 'You asked a question the whole community can read.',
            'metric' => 'questions', 'threshold' => 1,
        ],
        'questions_10' => [
            'name' => 'Ten questions',
            'meaning' => 'You have asked ten public questions.',
            'metric' => 'questions', 'threshold' => 10,
        ],
        'first_answer' => [
            'name' => 'First answer',
            'meaning' => 'You answered a question that someone else asked.',
            'metric' => 'answers', 'threshold' => 1,
        ],
        'answers_10' => [
            'name' => 'Ten answers',
            'meaning' => 'You have answered ten questions that other people asked.',
            'metric' => 'answers', 'threshold' => 10,
        ],
        'answers_50' => [
            'name' => 'Fifty answers',
            'meaning' => 'You have answered fifty questions that other people asked.',
            'metric' => 'answers', 'threshold' => 50,
        ],
        'first_accepted' => [
            'name' => 'First accepted answer',
            'meaning' => 'Someone else accepted one of your answers as the solution to their problem.',
            'metric' => 'accepted', 'threshold' => 1,
        ],
        'accepted_5' => [
            'name' => 'Five accepted answers',
            'meaning' => 'Five of your answers were accepted as the solution by other people.',
            'metric' => 'accepted', 'threshold' => 5,
        ],
        'accepted_25' => [
            'name' => 'Twenty-five accepted answers',
            'meaning' => 'Twenty-five of your answers were accepted as the solution by other people.',
            'metric' => 'accepted', 'threshold' => 25,
        ],
        'first_verified' => [
            'name' => 'First verified solution',
            'meaning' => 'A moderator checked one of your public solutions and confirmed that it works.',
            'metric' => 'verified', 'threshold' => 1,
        ],
        'verified_5' => [
            'name' => 'Five verified solutions',
            'meaning' => 'A moderator confirmed that five of your public solutions work.',
            'metric' => 'verified', 'threshold' => 5,
        ],
    ],
];
