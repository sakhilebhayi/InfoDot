<?php

namespace Tests\Feature;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Production runs Laravel Scout with the TNTSearch driver (SCOUT_DRIVER=tntsearch);
 * the test suite switches search off. TNTSearch reads the primary key out of each
 * model's searchable array, so a model whose array has no `id` throws
 * "Undefined array key id" the moment it is saved. For User that made every
 * registration fail with a 500 and roll back (2026-10-17, first sign-up after the
 * site came back). These tests run the REAL driver against a temporary index.
 */
class SearchIndexingTest extends TestCase
{
    use RefreshDatabase;

    private string $indexDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->indexDir = sys_get_temp_dir().'/infodot-tnt-'.uniqid();
        File::ensureDirectoryExists($this->indexDir);

        config(['scout.driver' => 'tntsearch', 'scout.tntsearch.storage' => $this->indexDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->indexDir);

        parent::tearDown();
    }

    public function test_saving_a_user_indexes_it_instead_of_throwing(): void
    {
        $user = User::factory()->create(['name' => 'Searchable Person', 'email' => 'searchable@example.test']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertFileExists($this->indexDir.'/users.index');
    }

    public function test_updating_a_user_reindexes_it_too(): void
    {
        $user = User::factory()->create();
        $user->update(['name' => 'Renamed Person']);

        $this->assertSame('Renamed Person', $user->fresh()->name);
    }

    public function test_every_searchable_model_puts_its_key_in_the_searchable_array(): void
    {
        foreach ([new User, new Questions, new Solutions] as $model) {
            $model->id = 42;

            $this->assertArrayHasKey('id', $model->toSearchableArray(), $model::class.' must include its key for TNTSearch');
            $this->assertSame(42, $model->toSearchableArray()['id']);
        }
    }
}
