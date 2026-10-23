<?php

namespace App\Models;

use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveCoverage;
use App\Enums\LeaveDetail;
use App\Enums\LeaveType;
use Database\Factories\LeaveApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One leave filed on CS Form No. 6. It is never edited: a wrong one is
 * cancelled, which gives its days back, and filed again. Its ledger rows are
 * what it drew; its breakdown is how each date was counted at filing.
 */
#[Fillable([
    'employee_id',
    'leave_type',
    'date_from',
    'date_to',
    'coverage',
    'detail',
    'detail_text',
    'commutation_requested',
    'days',
    'days_with_pay',
    'days_without_pay',
    'breakdown',
    'status',
    'filed_by_user_id',
    'approved_at',
    'approved_by_user_id',
    'cancelled_at',
    'cancelled_by_user_id',
    'cancel_reason',
])]
class LeaveApplication extends Model
{
    /** @use HasFactory<LeaveApplicationFactory> */
    use HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'coverage' => 'whole_day',
        'commutation_requested' => false,
        'status' => 'pending',
    ];

    /**
     * With deleted employees too: an employee is only ever soft-deleted, and
     * their leave must still show and still cancel.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<LeaveLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LeaveLedgerEntry::class);
    }

    /** "Nov 3–4, 2026", "Nov 3, 2026 (first half)", "Dec 28, 2026 – Jan 2, 2027". */
    public function dates(): string
    {
        $from = $this->date_from;
        $to = $this->date_to;

        return match (true) {
            $from->equalTo($to) => $from->format('M j, Y').match ($this->coverage) {
                LeaveCoverage::FirstHalf => ' (first half)',
                LeaveCoverage::SecondHalf => ' (second half)',
                default => '',
            },
            $from->format('Y-m') === $to->format('Y-m') => "{$from->format('M j')}–{$to->format('j, Y')}",
            $from->year === $to->year => "{$from->format('M j')} – {$to->format('M j, Y')}",
            default => "{$from->format('M j, Y')} – {$to->format('M j, Y')}",
        };
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
            'leave_type' => LeaveType::class,
            'date_from' => 'date',
            'date_to' => 'date',
            'coverage' => LeaveCoverage::class,
            'detail' => LeaveDetail::class,
            'commutation_requested' => 'boolean',
            'days' => 'decimal:3',
            'days_with_pay' => 'decimal:3',
            'days_without_pay' => 'decimal:3',
            'breakdown' => 'array',
            'status' => LeaveApplicationStatus::class,
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
