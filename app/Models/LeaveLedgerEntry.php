<?php

namespace App\Models;

use App\Enums\LeaveType;
use App\Enums\LedgerKind;
use Database\Factories\LeaveLedgerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One credit or deduction of leave. A balance is the sum of an employee's
 * rows for a type, and a row is never edited: a mistake is corrected by
 * another row, so every number can be explained.
 */
#[Fillable([
    'employee_id',
    'leave_type',
    'posted_on',
    'days',
    'kind',
    'period',
    'posting_key',
    'remarks',
    'created_by_user_id',
])]
class LeaveLedgerEntry extends Model
{
    /** @use HasFactory<LeaveLedgerEntryFactory> */
    use HasFactory, LogsActivity;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Rows posted by the system are inserted without the model and so never
     * reach the log; what is logged is what HR typed.
     */
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
            'leave_type' => LeaveType::class,
            'kind' => LedgerKind::class,
            'posted_on' => 'date',
            'days' => 'decimal:3',
        ];
    }
}
