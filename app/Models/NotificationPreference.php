<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One person's choice for one category of notification (R-64 phase 8).
 *
 * @property int $user_id
 * @property string $category
 * @property bool $popup
 * @property string $email instant | digest | off
 */
class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'category', 'popup', 'email'];

    protected function casts(): array
    {
        return ['popup' => 'boolean'];
    }
}
