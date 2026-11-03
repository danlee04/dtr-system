<?php

namespace App\Models;

use Database\Factories\DtrDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day's row on an uploaded DTR, in the form's own columns. The times are as
 * printed; nothing here is late or undertime yet.
 */
#[Fillable(['date', 'am_arrival', 'am_departure', 'pm_arrival', 'pm_departure', 'ot_arrival', 'ot_departure', 'remarks'])]
class DtrDay extends Model
{
    /** @use HasFactory<DtrDayFactory> */
    use HasFactory;

    // Written once with their upload, whose timestamps they share.
    public $timestamps = false;

    /** @return BelongsTo<DtrUpload, $this> */
    public function dtrUpload(): BelongsTo
    {
        return $this->belongsTo(DtrUpload::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
