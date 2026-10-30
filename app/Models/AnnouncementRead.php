<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $announcement_id
 * @property int $agent_id
 * @property Carbon $read_at
 */
#[Fillable(['announcement_id', 'agent_id', 'read_at'])]
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
