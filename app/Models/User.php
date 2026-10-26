<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'employee_id', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * The same defaults as the columns, so a user made in code reads the same
     * before and after it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'hr',
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Even when the employee is deleted, so the account can be refused for
     * it rather than mistaken for one with no employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * Filament asks this on every request, not only at sign-in, so
     * deactivating an account, or its employee, locks it out on its next
     * click. The HR panel is for staff; the employee panel for an account
     * linked to an employee who still works here.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->isStaff(),
            'employee' => $this->hasCurrentEmployee(),
            default => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Admin or HR: the people who run the HR panel. */
    public function isStaff(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Hr], true);
    }

    private function hasCurrentEmployee(): bool
    {
        $employee = $this->employee;

        return $employee !== null
            && $employee->is_active
            && ! $employee->trashed()
            && ($employee->date_separated === null || $employee->date_separated->greaterThanOrEqualTo(today()));
    }

    /**
     * Never the password: even hashed, it does not belong in a log that every
     * HR user can read.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'is_active', 'employee_id', 'must_change_password'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }
}
