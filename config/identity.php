<?php

/*
| Identity matching (R-29 Part C, §18 "configurable risk-assessment model"). Each signal adds its
| weight; the total decides the level. A level is a reason to look, never a verdict.
*/

return [

    'weights' => [
        'registration_number' => 60,   // unique in its registry: the strongest single signal
        'name_exact' => 30,            // same name once case, spacing, punctuation and legal suffixes are ignored
        'name_lookalike' => 30,        // same after look-alike characters merge (0/o, rn/m…) but not the same text
        'name_similar' => 25,          // a minor spelling variation
        'website' => 30,               // the same website domain
        'contact_domain' => 15,        // contact or official email at the verified business's domain
        'logo' => 25,                  // near-identical logo
        'cover' => 15,                 // near-identical cover photograph
        'same_country' => 5,           // only added when something else already matched
    ],

    // Score thresholds per level (inclusive lower bounds). Below "low", nothing is recorded.
    'levels' => [
        'critical' => 80,
        'high' => 60,
        'medium' => 40,
        'low' => 25,
    ],

    // Name similarity (0..1) that counts as a "minor spelling variation".
    'name_similarity' => 0.85,

    // Fingerprint bits (of 64) within which two images count as near-identical.
    'image_distance' => 8,
];
