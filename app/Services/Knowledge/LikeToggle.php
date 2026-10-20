<?php

namespace App\Services\Knowledge;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One like per person per item, whatever the browser sends: a second click removes it, a third restores the same
 * row (the table has a unique index, so a double submit cannot add a second one).
 */
class LikeToggle
{
    public function toggle(Model $item, User $user): bool
    {
        $like = $item->likes()->withTrashed()->where('user_id', $user->id)->first();

        if ($like === null) {
            $like = $item->likes()->make();
            $like->user()->associate($user);
            $like->save();

            return true;
        }

        if ($like->trashed()) {
            $like->restore();

            return true;
        }

        $like->delete();

        return false;
    }
}
