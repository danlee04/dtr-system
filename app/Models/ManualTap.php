<?php

namespace App\Models;

use App\Enums\DtrSlot;
use App\Enums\ManualTapReason;
use Database\Factories\ManualTapFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A time HR copied into a slot the DTR printed blank: what the employee wrote
 * on the paper and the supervisor signed. It belongs to the employee and the
 * date, not to an upload, so it outlives a reprint of the month. A time the
 * DTR prints in the same slot wins over it.
 */
#[Fillable(['employee_id', 'date', 'slot', 'time', 'reason', 'remarks', 'filled_by_user_id'])]
class ManualTap extends Model
{
    /** @use HasFactory<ManualTapFactory> */
    use HasFactory, LogsActivity;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'slot' => DtrSlot::class,
            'reason' => ManualTapReason::class,
        ];
    }
}
