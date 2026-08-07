<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Steps extends Model
{
    use HasFactory;
    use Search;

    protected $table = 'solutions_step';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'solution_heading' => 'array',
        'solution_body' => 'array',
    ];

    protected $fillable = [
        'user_id', 'solution_id', 'solution_heading', 'solution_body',
    ];

    protected $searchable = [
        'solution_heading',
        'solution_body',
    ];

    public function solution()
    {
        return $this->belongsTo(Solutions::class);
    }
}
