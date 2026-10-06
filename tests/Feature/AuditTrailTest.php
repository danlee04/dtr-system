<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_an_account_records_who_did_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $user->update(['is_active' => false]);

        $activity = Activity::query()
            ->where('subject_type', $user->getMorphClass())
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertFalse($activity->attribute_changes['attributes']['is_active']);
    }

    public function test_a_password_never_reaches_the_log(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $user = User::factory()->create();
        $user->update(['password' => 'a-brand-new-password']);

        $logged = Activity::query()->where('subject_type', $user->getMorphClass())->get();

        $this->assertFalse($logged->contains('event', 'updated'));

        foreach ($logged as $activity) {
            $this->assertArrayNotHasKey('password', $activity->attribute_changes['attributes'] ?? []);
        }
    }

    public function test_a_device_comm_key_never_reaches_the_log(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $device = Device::factory()->create(['comm_key' => '123456']);
        $device->update(['comm_key' => '654321', 'name' => 'Lobby']);

        $logged = Activity::query()->where('subject_type', $device->getMorphClass())->get();

        $this->assertCount(2, $logged);

        foreach ($logged as $activity) {
            $this->assertArrayNotHasKey('comm_key', $activity->attribute_changes['attributes'] ?? []);
        }

        $this->assertSame('654321', $device->fresh()->comm_key);
    }
}
