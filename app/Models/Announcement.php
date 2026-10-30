<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A notification an admin sends to every agent. Who has read it lives in announcement_reads.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property string $title
 * @property string $body
 */
#[Fillable(['admin_id', 'title', 'body'])]
class Announcement extends Model
{
    /** @return HasMany<AnnouncementRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}
