<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of the demonstration register (R-49): a record that exists only to show InfoDot working. The register is
 * what makes demonstration content labelled wherever it shows and removable exactly; see App\Services\Demo.
 *
 * @property int $id
 * @property class-string<Model> $record_type
 * @property string $record_id
 */
class DemoRecord extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
