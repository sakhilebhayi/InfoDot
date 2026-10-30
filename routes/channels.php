<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// The `question` channel used to authorise every signed-in person. Its event is not dispatched today; if it ever is, it must be scoped to the
// company and the item's visibility first. Until then nobody is authorised, so turning the event on cannot leak a private question.
Broadcast::channel('question', function () {
    return false;
});
