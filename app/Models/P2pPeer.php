<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One person's connection point in a peer-to-peer seminar room: who they are fed by, and whether they can pass the stream on. */
class P2pPeer extends Model
{
    protected $fillable = ['seminar_id', 'user_id', 'peer_id', 'role', 'parent_peer_id', 'depth', 'can_relay', 'status', 'last_seen'];

    protected function casts(): array
    {
        return ['can_relay' => 'boolean', 'last_seen' => 'datetime', 'depth' => 'integer'];
    }
}
