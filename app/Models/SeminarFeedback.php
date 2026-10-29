<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One attendee's rating of a seminar. The comment is for the host only. */
class SeminarFeedback extends Model
{
    protected $table = 'seminar_feedback';

    protected $fillable = ['seminar_id', 'user_id', 'rating', 'comment'];
}
