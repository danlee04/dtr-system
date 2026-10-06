<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A biometric device on the LAN that the system reads punches from.
 */
#[Fillable(['name', 'ip', 'port', 'comm_key', 'is_active'])]
#[Hidden(['comm_key'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'port' => 4370,
        'is_active' => true,
    ];

    /**
     * Never the comm key: it guards the device, and the log is readable by
     * every HR user.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'ip', 'port', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'comm_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'clock_drift_seconds' => 'integer',
        ];
    }
}
