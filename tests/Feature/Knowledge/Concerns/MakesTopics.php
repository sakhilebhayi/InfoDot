<?php

namespace Tests\Feature\Knowledge\Concerns;

use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;

/** Topics for the topic tests. The starting list is already in the database: the migration put it there. */
trait MakesTopics
{
    private function topic(string $slug): Topic
    {
        return Topic::where('slug', $slug)->firstOrFail();
    }

    /** File an item under the given topic slugs, the way the application does. */
    private function file(Model $item, string ...$slugs): Model
    {
        $item->topics()->sync(Topic::whereIn('slug', $slugs)->pluck('id')->all());

        return $item;
    }
}
