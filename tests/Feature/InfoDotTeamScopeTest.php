<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves HasTeamScope itself is load-bearing, independent of any Policy
 * or explicit where('team_id', ...) call: querying File directly as a
 * user on a different team, with no manual scoping anywhere in the
 * path, still cannot see the row. This is the property that makes the
 * scope "defense in depth" rather than decorative — it holds even if a
 * future controller or Livewire component forgets to scope a query
 * explicitly.
 */
class InfoDotTeamScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_alone_blocks_cross_team_access_even_without_an_explicit_where(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $outsider = User::factory()->withPersonalTeam()->create();

        $file = new File(['name' => 'Owner File', 'size' => 1024, 'path' => 'owner/file.txt']);
        $file->team_id = $owner->currentTeam->id;
        $file->save();

        $this->actingAs($outsider);

        $this->assertNull(File::find($file->id));
        $this->assertSame(0, File::query()->count());

        $this->actingAs($owner);

        $this->assertNotNull(File::find($file->id));
        $this->assertSame(1, File::query()->count());
    }
}
