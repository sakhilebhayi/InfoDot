<?php

/*
|--------------------------------------------------------------------------
| Platform recommendations (R-50 section 8)
|--------------------------------------------------------------------------
|
| Each rule suggests one platform to a company and says why, in a sentence the company can check for itself.
| A rule may use only facts InfoDot itself holds about the company (App\Services\Platforms\PlatformRecommendations
| lists them): the industry the company chose, how many people it has on InfoDot, the platforms it already holds,
| and counts of what it has really done here. Nothing is estimated, scored or inferred, and there is no sequence
| or milestone: when no rule is true for a company, it is shown no recommendation at all.
|
| A rule:
|   platform  the catalogue key that is suggested
|   when      every condition must hold:
|               industry => [keys of config/industries.php]   the industry the company chose
|               min      => [fact => number]                  facts: members, enquiries, services, products,
|                                                              showcase_projects, questions, solutions
|               holds    => [platform keys]                   platforms the company already holds
|   reason    the sentence shown. :company, :industry and any fact name (:members, :enquiries ...) are filled in
|             from the same facts the conditions read.
|
| A platform the company already holds, cannot get yet, or has dismissed is never suggested.
|
*/

return [

    // How many suggestions a company sees at once, at most.
    'limit' => 3,

    'rules' => [
        [
            'platform' => 'hr',
            'when' => ['min' => ['members' => 5]],
            'reason' => ':company has :members people on InfoDot. Dot.HR keeps their records, leave and timesheets in one place.',
        ],
        [
            'platform' => 'engage',
            'when' => ['min' => ['enquiries' => 3]],
            'reason' => ':company has received :enquiries enquiries in its InfoDot Inbox. Dot.Engage is where an agreement with a customer is signed and verified.',
        ],
        [
            'platform' => 'emall',
            'when' => ['min' => ['products' => 1]],
            'reason' => ':company lists products for sale on its business page (:products so far). Dot.Emall is a marketplace where a business opens a store and sells them.',
        ],
        [
            'platform' => 'projects',
            'when' => ['min' => ['showcase_projects' => 1, 'members' => 2]],
            'reason' => ':company shows completed work on its business page (:showcase_projects so far) and has :members people. Dot.Projects plans and tracks the next job.',
        ],
        [
            'platform' => 'central',
            'when' => ['industry' => ['mining']],
            'reason' => ':company is registered on InfoDot as a :industry business. Dot.Central is the control room for a mining shift.',
        ],
    ],

];
