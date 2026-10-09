# DTR System — Plan 1: Probe and Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove the hospital's biometric device can be read, and stand up the panel HR will use: accounts, offices, and employees loaded from a CSV.

**Architecture:** A `PunchSource` interface hides the ZKTeco library, and `php artisan device:probe` exercises it against the real device. One Filament 5 panel serves `/`, with two roles on `users.role`. Offices and employees are Filament resources guarded by policies; the CSV import is a service (`EmployeeImporter`) behind a Filament action. Every change to accounts, devices, offices and employees is written to the activity log.

**Tech Stack:** Laravel 13.34, PHP 8.4, Filament 5.9 (Livewire 4), spatie/laravel-activitylog 5.1, msaied/zkteco 0.2, PHPUnit on SQLite in memory, MySQL 8 in use.

**Spec:** `docs/superpowers/specs/2026-10-06-dtr-system-design.md` — this is Plan 1 of the five under "Implementation shape".

The final version of every file in this plan was run in a scratch Laravel 13.34 project with the same package versions, and all 61 tests passed there. The interim versions that later tasks replace are subsets of those files. If something here does not compile, the installed version has drifted: check `composer show <package>` before changing the code.

## Global Constraints

- Laravel 13, PHP 8.4, Livewire 4, Filament 5, Tailwind v4, MySQL 8, PHPUnit. Timezone `Asia/Manila` (already set in `config/app.php`).
- One Filament panel at `/`, with Filament's own login. Roles are a `role` column on `users`: `admin`, `hr`. No `spatie/laravel-permission`.
- All attendance logic lives in `app/Services/Attendance/`; Filament pages and actions only call it.
- New packages in this plan, and no others: `msaied/zkteco` `^0.2.2`, `filament/filament` `^5.9`, `spatie/laravel-activitylog` `^5.1`.
- `employment_status` values are exactly `permanent`, `coterminous`, `job_order`, `contract_of_service` — the HRIS's four.
- Every resource has a Policy; hiding a menu is not authorization. Only admin reaches Users.
- The **Security Guidelines** section of `CLAUDE.md` applies to all code.
- Create files with `php artisan make:* --no-interaction`, then replace their contents with the code given here.
- Tests: `php artisan test --compact <path>`; `phpunit.xml` already runs them on SQLite in memory. Before handing off a task, run `vendor/bin/pint --dirty --format agent`.
- **Never run `git commit`.** The user commits. Each task ends with the list of changed files and a suggested commit message.

## Review Focus

1. **An Excel "CSV (Comma delimited)" file is Windows-1252, not UTF-8.** "Peñaflor" must import as "Peñaflor". Pinned by Task 8, `test_a_windows_1252_file_keeps_its_enye`.
2. **Leading zeros in a biometric ID** ("0042") must survive the form and the import as text; to the device "42" is someone else. Pinned by Task 7, `test_hr_adds_an_employee`, and Task 8, `test_it_creates_the_employees_in_the_file`.
3. **Re-importing an older file with blank biometric_id cells** must not erase IDs HR has set since. Pinned by Task 8, `test_a_blank_cell_keeps_a_biometric_id_already_set`.
4. **A device clock read in the wrong zone**: 07:58 on the device is 07:58 in Manila, never eight hours off. Pinned by Task 1, `DeviceTimeTest`.
5. **A deactivated account that is still signed in** is refused on its next click, not when its session expires. Pinned by Task 2, `test_a_deactivated_user_is_refused_even_with_a_live_session`.

---

## Before you start

- Work in `C:\laragon\www\dtr-system`. The commands below run the same in Git Bash and PowerShell.
- `.ai/rules` does not exist; `CLAUDE.md` is the rule set. Read its **Security Guidelines** section.
- The database `dtr_system` exists and Laravel's default migrations have run. `php artisan test --compact` passes (2 tests).
- Task 1, Step 7 needs the device's IP address, port and comm key from HR. If they are not available yet, finish the other steps of Task 1 and carry on with Task 2. **Plan 2 must not start until Task 1, Step 7 has passed.**

## File map

| File | Responsibility | Task |
|---|---|---|
| `app/Services/Attendance/PunchSources/PunchSource.php` | What the system needs from any device | 1 |
| `app/Services/Attendance/PunchSources/RawPunch.php`, `DeviceUser.php` | Values a device returns | 1 |
| `app/Services/Attendance/PunchSources/DeviceTime.php` | Device wall-clock ↔ Manila time | 1 |
| `app/Services/Attendance/PunchSources/DeviceUnreachable.php` | The one exception a `PunchSource` throws | 1 |
| `app/Services/Attendance/PunchSources/ZktecoPunchSource.php` | `PunchSource` over msaied/zkteco | 1 |
| `app/Models/Device.php` + migration + factory | A registered device | 1, 5 |
| `app/Console/Commands/ProbeDevice.php` | `device:probe` | 1 |
| `tests/Fakes/FakePunchSource.php` | An in-memory device for tests | 1 |
| `app/Providers/Filament/AdminPanelProvider.php` | The panel at `/` | 2 |
| `app/Enums/UserRole.php`, `app/Models/User.php` | Roles and panel access | 2, 5 |
| `app/Policies/UserPolicy.php`, `app/Filament/Resources/Users/*` | Accounts screen (admin only) | 3 |
| `app/Console/Commands/CreateAccount.php` | `dtr:user`, the first account | 3 |
| `app/Http/Middleware/SecurityHeaders.php`, `app/Providers/AppServiceProvider.php` | Headers, HTTPS switch, defaults | 4 |
| `app/Models/Office.php`, `app/Policies/OfficePolicy.php`, `app/Filament/Resources/Offices/*` | Offices | 6, 7 |
| `app/Enums/EmploymentStatus.php`, `app/Models/Employee.php`, `app/Policies/EmployeePolicy.php`, `app/Filament/Resources/Employees/*` | Employees | 7 |
| `app/Services/EmployeeImporter.php`, `EmployeeImportResult.php`, `app/Filament/Resources/Employees/Actions/ImportEmployeesAction.php` | CSV import | 8 |

---

### Task 1: Read the device (probe)

**Files:**
- Modify: `composer.json` (`extra.laravel.dont-discover`)
- Create: `database/migrations/*_create_devices_table.php`, `app/Models/Device.php`, `database/factories/DeviceFactory.php`
- Create: `app/Services/Attendance/PunchSources/PunchSource.php`, `RawPunch.php`, `DeviceUser.php`, `DeviceTime.php`, `DeviceUnreachable.php`, `ZktecoPunchSource.php`
- Create: `app/Console/Commands/ProbeDevice.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Fakes/FakePunchSource.php`, `tests/Feature/Devices/DeviceTimeTest.php`, `tests/Feature/Devices/ProbeDeviceTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface PunchSource { fetchPunches(Device $device): iterable<RawPunch>; deviceTime(Device $device): CarbonImmutable; setDeviceTime(Device $device, CarbonImmutable $time): void; deviceUsers(Device $device): iterable<DeviceUser>; }` — every method throws `DeviceUnreachable`.
  - `final readonly class RawPunch(string $biometricId, CarbonImmutable $punchedAt, ?int $rawState = null)`
  - `final readonly class DeviceUser(string $biometricId, string $name)`
  - `DeviceUnreachable::because(Device $device, Throwable $previous): self`
  - `DeviceTime::read(DateTimeInterface): CarbonImmutable`, `DeviceTime::write(CarbonImmutable): DateTimeImmutable`
  - `App\Models\Device` — fillable `name, ip, port, comm_key, is_active`; `comm_key` encrypted and hidden.
  - `Tests\Fakes\FakePunchSource(array $punches = [], array $users = [], ?CarbonImmutable $clock = null, ?string $failure = null)`, `FakePunchSource::unreachable(string $reason)`, public `?CarbonImmutable $timeSet`.
  - Container binding `PunchSource` → `ZktecoPunchSource`.

The library is msaied/zkteco, a PHP port of the widely used pyzk. It talks TCP to port 4370, supports the comm key, needs only PHP's `iconv` (already enabled), and does **not** need the `sockets` extension. Its optional Laravel bridge exists for ADMS push, which this system does not use, so it is kept from loading.

- [ ] **Step 1: Install the library without its Laravel bridge**

In `composer.json`, change:

```json
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
```

to:

```json
    "extra": {
        "laravel": {
            "dont-discover": [
                "msaied/zkteco"
            ]
        }
    },
```

Then run:

```bash
composer require "msaied/zkteco:^0.2.2" --no-interaction
```

Expected: ends with `No security vulnerability advisories found.` and `package:discover` does not list `msaied/zkteco`.

- [ ] **Step 2: Write the failing tests**

Create `tests/Fakes/FakePunchSource.php` (a plain file; `tests/` is autoloaded as `Tests\`):

```php
<?php

namespace Tests\Fakes;

use App\Models\Device;
use App\Services\Attendance\PunchSources\DeviceUnreachable;
use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A device held in memory. It remembers the clock it was told to set, and it
 * can be made unreachable.
 */
class FakePunchSource implements PunchSource
{
    public ?CarbonImmutable $timeSet = null;

    /**
     * @param  list<RawPunch>  $punches
     * @param  list<DeviceUser>  $users
     */
    public function __construct(
        public array $punches = [],
        public array $users = [],
        public ?CarbonImmutable $clock = null,
        public ?string $failure = null,
    ) {}

    public static function unreachable(string $reason = 'Connection timed out'): self
    {
        return new self(failure: $reason);
    }

    public function fetchPunches(Device $device): iterable
    {
        $this->failIfUnreachable($device);

        return $this->punches;
    }

    public function deviceTime(Device $device): CarbonImmutable
    {
        $this->failIfUnreachable($device);

        return $this->clock ?? CarbonImmutable::now();
    }

    public function setDeviceTime(Device $device, CarbonImmutable $time): void
    {
        $this->failIfUnreachable($device);

        $this->timeSet = $time;
        $this->clock = $time;
    }

    public function deviceUsers(Device $device): iterable
    {
        $this->failIfUnreachable($device);

        return $this->users;
    }

    private function failIfUnreachable(Device $device): void
    {
        if ($this->failure !== null) {
            throw DeviceUnreachable::because($device, new RuntimeException($this->failure));
        }
    }
}
```

Run `php artisan make:test Devices/DeviceTimeTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Devices;

use App\Services\Attendance\PunchSources\DeviceTime;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

class DeviceTimeTest extends TestCase
{
    public function test_a_device_reading_is_manila_time_whatever_zone_php_is_in(): void
    {
        // The library may hand back 07:58 tagged UTC. It still means 07:58 on
        // the device's screen, in Manila.
        $reading = new DateTimeImmutable('2026-10-06 07:58:00', new DateTimeZone('UTC'));

        $time = DeviceTime::read($reading);

        $this->assertSame('2026-10-06 07:58:00', $time->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $time->getTimezone()->getName());
    }

    public function test_setting_the_clock_writes_manila_wall_clock_time(): void
    {
        $midnightUtc = CarbonImmutable::parse('2026-10-06 00:00:00', 'UTC');

        $this->assertSame('2026-10-06 08:00:00', DeviceTime::write($midnightUtc)->format('Y-m-d H:i:s'));
    }
}
```

Run `php artisan make:test Devices/ProbeDeviceTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Devices;

use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Carbon\CarbonImmutable;
use Tests\Fakes\FakePunchSource;
use Tests\TestCase;
use ZkTeco\Laravel\ZkTecoServiceProvider;

class ProbeDeviceTest extends TestCase
{
    private function punch(string $biometricId, string $at, int $state = 0): RawPunch
    {
        return new RawPunch($biometricId, CarbonImmutable::parse($at), $state);
    }

    public function test_it_prints_the_clock_the_users_and_the_ten_latest_logs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 08:00:00'));

        $punches = [];
        for ($minute = 0; $minute < 12; $minute++) {
            $punches[] = $this->punch('0042', sprintf('2026-10-06 07:%02d:00', 40 + $minute));
        }

        $this->app->instance(PunchSource::class, new FakePunchSource(
            punches: $punches,
            users: [new DeviceUser('0042', 'JUAN DELA CRUZ'), new DeviceUser('0007', 'ANA REYES')],
            clock: CarbonImmutable::parse('2026-10-06 08:00:30'),
        ));

        $latestTen = [];
        for ($minute = 11; $minute >= 2; $minute--) {
            $latestTen[] = ['0042', 'JUAN DELA CRUZ', sprintf('2026-10-06 07:%02d:00', 40 + $minute), '0'];
        }

        $this->artisan('device:probe', ['ip' => '192.168.1.201'])
            ->expectsOutputToContain('Device clock: 2026-10-06 08:00:30 (30 seconds ahead of this server)')
            ->expectsOutputToContain('Users on device: 2')
            ->expectsOutputToContain('Logs on device: 12')
            ->expectsTable(['Biometric ID', 'Name on device', 'Time', 'State'], $latestTen)
            ->assertSuccessful();
    }

    public function test_a_device_that_cannot_be_reached_fails_with_the_reason(): void
    {
        $this->app->instance(PunchSource::class, FakePunchSource::unreachable('Connection timed out'));

        $this->artisan('device:probe', ['ip' => '192.168.1.201', '--port' => 4370])
            ->expectsOutputToContain('Cannot reach 192.168.1.201:4370: Connection timed out')
            ->assertFailed();
    }

    public function test_the_application_reads_devices_through_the_zkteco_library(): void
    {
        $this->assertInstanceOf(ZktecoPunchSource::class, $this->app->make(PunchSource::class));
    }

    public function test_the_librarys_own_laravel_bridge_is_not_loaded(): void
    {
        // Its routes and commands are for ADMS push, which this system does
        // not use. Not loading it means no endpoint the device could call.
        $this->assertArrayNotHasKey(ZkTecoServiceProvider::class, $this->app->getLoadedProviders());
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Devices`
Expected: FAIL — `Class "App\Services\Attendance\PunchSources\..." not found` (and `Interface ... not found` for the fake).

- [ ] **Step 4: Create the device table and model**

Run `php artisan make:model Device --migration --factory --no-interaction`.

Replace the new `database/migrations/*_create_devices_table.php` with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('ip', 45);
            $table->unsignedSmallInteger('port')->default(4370);
            // Encrypted with APP_KEY, so the column holds ciphertext, not the key.
            $table->text('comm_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->integer('clock_drift_seconds')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
```

Replace `app/Models/Device.php` with:

```php
<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A biometric device on the LAN that the system reads punches from.
 */
#[Fillable(['name', 'ip', 'port', 'comm_key', 'is_active'])]
#[Hidden(['comm_key'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'port' => 4370,
        'is_active' => true,
    ];

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
```

Replace `database/factories/DeviceFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Main entrance',
            'ip' => fake()->localIpv4(),
            'port' => 4370,
            'comm_key' => null,
            'is_active' => true,
        ];
    }
}
```

Run `php artisan migrate --no-interaction`. Expected: `create_devices_table ... DONE`.

- [ ] **Step 5: Write the device interface and its ZKTeco implementation**

Create each file with `php artisan make:interface Services/Attendance/PunchSources/PunchSource --no-interaction` or `php artisan make:class Services/Attendance/PunchSources/<Name> --no-interaction` and replace its contents.

`app/Services/Attendance/PunchSources/PunchSource.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use Carbon\CarbonImmutable;

/**
 * Everything the system needs from a biometric device, and nothing more.
 *
 * Sync, the probe and the Devices screen talk to this, never to a vendor
 * library, so a device that needs another library — or a USB file import —
 * is a new implementation rather than a rewrite.
 *
 * Every method throws DeviceUnreachable when the device cannot be read.
 */
interface PunchSource
{
    /** @return iterable<RawPunch> */
    public function fetchPunches(Device $device): iterable;

    public function deviceTime(Device $device): CarbonImmutable;

    public function setDeviceTime(Device $device, CarbonImmutable $time): void;

    /** @return iterable<DeviceUser> */
    public function deviceUsers(Device $device): iterable;
}
```

`app/Services/Attendance/PunchSources/RawPunch.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

use Carbon\CarbonImmutable;

/**
 * One log exactly as the device recorded it, in Manila wall-clock time.
 */
final readonly class RawPunch
{
    public function __construct(
        public string $biometricId,
        public CarbonImmutable $punchedAt,
        public ?int $rawState = null,
    ) {}
}
```

`app/Services/Attendance/PunchSources/DeviceUser.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

/**
 * A person enrolled on a device: the ID they punch with and the name typed
 * into the device when they were enrolled.
 */
final readonly class DeviceUser
{
    public function __construct(
        public string $biometricId,
        public string $name,
    ) {}
}
```

`app/Services/Attendance/PunchSources/DeviceUnreachable.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use RuntimeException;
use Throwable;

/**
 * The device could not be read: off, unplugged, a wrong IP, port or comm key.
 */
class DeviceUnreachable extends RuntimeException
{
    public static function because(Device $device, Throwable $previous): self
    {
        return new self(
            "Cannot reach {$device->ip}:{$device->port}: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
```

`app/Services/Attendance/PunchSources/DeviceTime.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A biometric device keeps wall-clock time with no zone: 07:58 on its screen
 * is 07:58 in Manila. Reading that through a PHP default zone that happens to
 * be UTC would put every punch, and every late, eight hours off.
 */
final class DeviceTime
{
    /** The device's wall-clock reading, as Manila time. */
    public static function read(DateTimeInterface $deviceTime): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $deviceTime->format('Y-m-d H:i:s'),
            config('app.timezone'),
        );
    }

    /** What the device's clock should show for this moment. */
    public static function write(CarbonImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'));
    }
}
```

`app/Services/Attendance/PunchSources/ZktecoPunchSource.php`:

```php
<?php

namespace App\Services\Attendance\PunchSources;

use App\Models\Device;
use Carbon\CarbonImmutable;
use Closure;
use ZkTeco\Exceptions\ZkException;
use ZkTeco\TCP\Device as ZkDevice;
use ZkTeco\Values\AttendanceRecord;
use ZkTeco\Values\User as ZkUser;

/**
 * Reads a ZKTeco device over TCP through msaied/zkteco.
 *
 * Every call opens its own connection and closes it. None of them disables
 * the device, so people can keep punching while it is read, and nothing here
 * ever clears the device's logs.
 */
class ZktecoPunchSource implements PunchSource
{
    public function fetchPunches(Device $device): iterable
    {
        return $this->connected($device, fn (ZkDevice $zk): array => array_map(
            fn (AttendanceRecord $record): RawPunch => new RawPunch(
                biometricId: $record->userId,
                punchedAt: DeviceTime::read($record->recordedAt),
                rawState: $record->punchState->value,
            ),
            $zk->attendance()->all(),
        ));
    }

    public function deviceTime(Device $device): CarbonImmutable
    {
        return $this->connected(
            $device,
            fn (ZkDevice $zk): CarbonImmutable => DeviceTime::read($zk->info()->time()),
        );
    }

    public function setDeviceTime(Device $device, CarbonImmutable $time): void
    {
        $this->connected($device, function (ZkDevice $zk) use ($time): void {
            $zk->info()->setTime(DeviceTime::write($time));
        });
    }

    public function deviceUsers(Device $device): iterable
    {
        return $this->connected($device, fn (ZkDevice $zk): array => array_map(
            fn (ZkUser $user): DeviceUser => new DeviceUser(
                biometricId: $user->userId,
                name: trim($user->name),
            ),
            $zk->users()->all(),
        ));
    }

    /**
     * @template TResult
     *
     * @param  Closure(ZkDevice): TResult  $work
     * @return TResult
     */
    private function connected(Device $device, Closure $work): mixed
    {
        $zk = new ZkDevice(
            host: $device->ip,
            port: $device->port,
            commKey: (int) ($device->comm_key ?? 0),
            timeout: 10.0,
        );

        try {
            $zk->connect();

            return $work($zk);
        } catch (ZkException $exception) {
            throw DeviceUnreachable::because($device, $exception);
        } finally {
            try {
                $zk->disconnect();
            } catch (ZkException) {
                // The connection is already gone; there is nothing to close.
            }
        }
    }
}
```

Replace `app/Providers/AppServiceProvider.php` with:

```php
<?php

namespace App\Providers;

use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PunchSource::class, ZktecoPunchSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
```

- [ ] **Step 6: Write the probe command and run the tests**

Run `php artisan make:command ProbeDevice --no-interaction` and replace `app/Console/Commands/ProbeDevice.php` with:

```php
<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Attendance\PunchSources\DeviceUnreachable;
use App\Services\Attendance\PunchSources\DeviceUser;
use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\RawPunch;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Proves the device can be read before anything is built on it: its clock,
 * how many people are enrolled, and its ten latest logs. It only reads; it
 * never changes the device.
 */
class ProbeDevice extends Command
{
    protected $signature = 'device:probe
                            {ip : The device\'s IP address}
                            {--port=4370 : The device\'s TCP port}
                            {--key=0 : The comm key set on the device, 0 if none}';

    protected $description = 'Read a biometric device\'s clock, users and latest logs without changing anything';

    public function handle(PunchSource $source): int
    {
        // Not saved: the probe runs before the device is registered.
        $device = new Device([
            'name' => 'Probe',
            'ip' => $this->argument('ip'),
            'port' => (int) $this->option('port'),
            'comm_key' => (string) $this->option('key'),
        ]);

        try {
            $clock = $source->deviceTime($device);
            $users = collect($source->deviceUsers($device));
            $punches = collect($source->fetchPunches($device));
        } catch (DeviceUnreachable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $namesById = $users->mapWithKeys(fn (DeviceUser $user): array => [$user->biometricId => $user->name]);
        $drift = (int) round(CarbonImmutable::now()->diffInSeconds($clock));

        $this->line("Device: {$device->ip}:{$device->port}");
        $this->line("Device clock: {$clock->format('Y-m-d H:i:s')} ({$this->describeDrift($drift)})");
        $this->line("Users on device: {$users->count()}");
        $this->line("Logs on device: {$punches->count()}");

        $this->table(
            ['Biometric ID', 'Name on device', 'Time', 'State'],
            $punches
                ->sortByDesc(fn (RawPunch $punch): int => $punch->punchedAt->getTimestamp())
                ->take(10)
                ->map(fn (RawPunch $punch): array => [
                    $punch->biometricId,
                    $namesById->get($punch->biometricId, ''),
                    $punch->punchedAt->format('Y-m-d H:i:s'),
                    (string) $punch->rawState,
                ])
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }

    private function describeDrift(int $seconds): string
    {
        return match (true) {
            $seconds === 0 => 'same as this server',
            $seconds > 0 => "{$seconds} seconds ahead of this server",
            default => abs($seconds).' seconds behind this server',
        };
    }
}
```

Run: `php artisan test --compact tests/Feature/Devices`
Expected: PASS, 6 tests.

Run: `php artisan test --compact`
Expected: PASS (the two default example tests still pass; the welcome page is removed in Task 2).

- [ ] **Step 7: Probe the real device (needs HR's IP, port and comm key)**

Ask someone at the device to punch once, note the minute, then run within a minute:

```bash
php artisan device:probe <ip> --port=<port> --key=<comm key>
```

Expected:
- `Device clock:` within a few minutes of the server. Write the drift down; Plan 2 adds **Set device time**.
- `Users on device:` close to the number of enrolled staff (130+).
- The newest row of the table is the test punch, at the minute noted, with the name typed on the device. This proves the IDs, the names and the time zone end to end.
- A name with "Ñ" (find one on the device, or enroll a test user such as "PEÑA TEST") prints correctly, not as "PE?A" or garbage. This checks the device's codepage against `DeviceText`.

If it fails, record the exact message and stop this step. Do not switch libraries in this plan:
- `Cannot reach …: …` with a timeout: check the IP and port from the device's own menu (Comm. → Ethernet), that the server can `ping` it, and that no firewall blocks TCP 4370.
- An authentication or protocol error while `ping` works: the comm key is wrong, or the device speaks only UDP (some old models do). Report it to the user; the spec says to revisit Section 5 (Device sync) before Plan 2.

- [ ] **Step 8: Format and hand off**

Run `vendor/bin/pint --dirty --format agent`, then `php artisan test --compact` (expected: PASS).

Report `git status --short` to the user and suggest the commit message:
`feat: read the biometric device through a PunchSource interface (device:probe)`. Include the probe's output from Step 7, or say that Step 7 is still waiting for the device details.

---

### Task 2: The panel at `/`, with sign-in limited to active accounts

**Files:**
- Modify: `composer.json`, `bootstrap/providers.php` (by `filament:install`), `public/` (Filament assets, by `filament:install`)
- Create/Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `routes/web.php`
- Delete: `resources/views/welcome.blade.php`, `tests/Feature/ExampleTest.php`
- Create: `app/Enums/UserRole.php`, `database/migrations/*_add_role_and_is_active_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/Auth/PanelAccessTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces:
  - `enum UserRole: string implements HasLabel { Admin = 'admin'; Hr = 'hr'; getLabel(): string }`
  - `User::canAccessPanel(Panel $panel): bool` (true only when `is_active`), `User::isAdmin(): bool`; `role` cast to `UserRole`, `is_active` cast to bool.
  - `UserFactory` states `admin()`, `inactive()`; default role `hr`, active.
  - The panel `admin` serves `/`; its login is `/login`.

- [ ] **Step 1: Install Filament and its panel**

```bash
composer require "filament/filament:^5.9" -W --no-interaction
php artisan filament:install --panels --no-interaction
```

Expected: `Successfully published assets!` and a new `app/Providers/Filament/AdminPanelProvider.php`, registered in `bootstrap/providers.php`.

- [ ] **Step 2: Write the failing test**

Run `php artisan make:test Auth/PanelAccessTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_an_active_user_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk();
    }

    public function test_a_deactivated_user_is_refused_even_with_a_live_session(): void
    {
        // Deactivating someone who is already signed in has to take effect on
        // their next click, not whenever their session happens to expire.
        $this->actingAs(User::factory()->inactive()->create())
            ->get('/')
            ->assertForbidden();
    }

    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_an_active_user_can_sign_in(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }
}
```

Delete `tests/Feature/ExampleTest.php` (it expects the welcome page at `/`, which the panel replaces) and `resources/views/welcome.blade.php`.

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/PanelAccessTest.php`
Expected: FAIL — the panel is still at `/admin`, so `/` is the welcome route (which now has no view), and `inactive()` does not exist.

- [ ] **Step 4: Move the panel to `/` and give users a role and an active flag**

Replace `app/Providers/Filament/AdminPanelProvider.php` with:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->brandName('DTR System')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

Replace `routes/web.php` with:

```php
<?php

// Every page is served by the Filament panel, starting at `/`.
```

Run `php artisan make:enum UserRole --string --no-interaction` and replace `app/Enums/UserRole.php` with:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an account may do. Both roles run the attendance work; only an admin
 * manages accounts and the biometric devices.
 */
enum UserRole: string implements HasLabel
{
    case Admin = 'admin';
    case Hr = 'hr';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Hr => 'HR',
        };
    }
}
```

Run `php artisan make:migration add_role_and_is_active_to_users_table --table=users --no-interaction` and replace it with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('hr')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
```

Replace `app/Models/User.php` with:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The same defaults as the columns, so a user made in code reads the same
     * before and after it is saved.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'hr',
        'is_active' => true,
    ];

    /**
     * Filament asks this on every request, not only at sign-in, so
     * deactivating an account locks it out on its next click.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
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
        ];
    }
}
```

Replace `database/factories/UserFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Hr,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
```

Run `php artisan migrate --no-interaction`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Auth/PanelAccessTest.php`
Expected: PASS, 5 tests.

Run: `php artisan route:list --path=login`
Expected: `GET|HEAD login ... filament.admin.auth.login`.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` (it tidies `bootstrap/providers.php`, which `filament:install` wrote) and `php artisan test --compact` (expected: PASS).

Report `git status --short` and suggest: `feat: Filament panel at / with admin and hr roles; inactive accounts are refused`. Tell the user that `http://dtr-system.test` now shows the Filament login once Laragon has been reloaded, and that no account exists until Task 3.

---

### Task 3: Accounts screen and the first admin

**Files:**
- Create: `app/Policies/UserPolicy.php`
- Create: `app/Filament/Resources/Users/UserResource.php`, `Schemas/UserForm.php`, `Tables/UsersTable.php`, `Pages/ListUsers.php`, `Pages/CreateUser.php`, `Pages/EditUser.php`
- Create: `app/Console/Commands/CreateAccount.php`
- Test: `tests/Feature/Users/UserResourceTest.php`, `tests/Feature/Console/CreateAccountTest.php`

**Interfaces:**
- Consumes: `UserRole`, `User::isAdmin()`, `UserFactory::admin()` from Task 2.
- Produces: `php artisan dtr:user {--name=} {--email=} {--role=admin}` (asks for the password twice); `UserResource` reachable by admin only.

- [ ] **Step 1: Write the failing tests**

Run `php artisan make:test Users/UserResourceTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_cannot_open_the_accounts_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(UserResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_an_admin_creates_an_hr_account(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Reyes',
                'email' => 'ana@dtrc.test',
                'role' => UserRole::Hr->value,
                'is_active' => true,
                'password' => 'a-long-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'ana@dtrc.test');
        $this->assertSame(UserRole::Hr, $user->role);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
    }

    public function test_a_blank_password_on_edit_keeps_the_old_one(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $before = $user->password;

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Renamed', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_an_admin_cannot_demote_or_deactivate_themselves(): void
    {
        // The last admin doing so would leave nobody able to undo it.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldDisabled('role')
            ->assertFormFieldDisabled('is_active');
    }
}
```

Run `php artisan make:test Console/CreateAccountTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_admin(): void
    {
        $this->artisan('dtr:user', ['--name' => 'Ana Reyes', '--email' => 'ana@dtrc.test'])
            ->expectsQuestion('Password', 'a-long-password')
            ->expectsQuestion('Confirm the password', 'a-long-password')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'ana@dtrc.test');
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-long-password', $user->password));
    }

    public function test_it_creates_an_hr_account(): void
    {
        $this->artisan('dtr:user', ['--name' => 'Ben Cruz', '--email' => 'ben@dtrc.test', '--role' => 'hr'])
            ->expectsQuestion('Password', 'a-long-password')
            ->expectsQuestion('Confirm the password', 'a-long-password')
            ->assertSuccessful();

        $this->assertFalse(User::firstWhere('email', 'ben@dtrc.test')->isAdmin());
    }

    public function test_mismatched_passwords_create_nothing(): void
    {
        $this->artisan('dtr:user', ['--name' => 'Ana Reyes', '--email' => 'ana@dtrc.test'])
            ->expectsQuestion('Password', 'a-long-password')
            ->expectsQuestion('Confirm the password', 'another-password')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_an_unknown_role_is_refused_before_anything_is_asked(): void
    {
        $this->artisan('dtr:user', ['--role' => 'employee'])
            ->expectsOutputToContain('is not a role')
            ->assertFailed();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Users tests/Feature/Console`
Expected: FAIL — `Class "App\Filament\Resources\Users\..." not found` and `The command "dtr:user" does not exist.`

- [ ] **Step 3: Write the policy and the accounts screen**

Run `php artisan make:policy UserPolicy --model=User --no-interaction` and replace it with:

```php
<?php

namespace App\Policies;

use App\Models\User;

/**
 * Accounts are an admin's business only.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Accounts are deactivated, never deleted: the activity log names them as
     * the people who made each change.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
```

Run `php artisan make:filament-resource User --no-interaction`, then replace the generated files.

`app/Filament/Resources/Users/UserResource.php`:

```php
<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 99;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
```

`app/Filament/Resources/Users/Schemas/UserForm.php`:

```php
<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                // An admin cannot demote or deactivate themselves: the last
                // admin doing so would leave nobody able to undo it.
                Select::make('role')
                    ->options(UserRole::class)
                    ->default(UserRole::Hr->value)
                    ->required()
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : null),
            ]);
    }
}
```

`app/Filament/Resources/Users/Tables/UsersTable.php`:

```php
<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

`app/Filament/Resources/Users/Pages/ListUsers.php`:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

`app/Filament/Resources/Users/Pages/CreateUser.php`:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
```

`app/Filament/Resources/Users/Pages/EditUser.php` (no delete action):

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
```

- [ ] **Step 4: Write `dtr:user`**

Run `php artisan make:command CreateAccount --no-interaction` and replace `app/Console/Commands/CreateAccount.php` with:

```php
<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The way the first account is made.
 *
 * Public registration is closed, so a fresh install has no way to produce an
 * account and nobody can sign in. It is a console command rather than a
 * seeder: a seeded account means a password in version control, the same on
 * every install, with nothing to stop it surviving into production.
 */
class CreateAccount extends Command
{
    // The password is not an option. Anything typed on a command line lands in
    // the shell history and the process list, where others can read it.
    protected $signature = 'dtr:user
                            {--name= : The person\'s name}
                            {--email= : The address they sign in with}
                            {--role=admin : admin or hr}';

    protected $description = 'Create an admin or HR account';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error("[{$this->option('role')}] is not a role. Use admin or hr.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm the password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        $this->info("Account [{$email}] created with the {$role->getLabel()} role.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Users tests/Feature/Console`
Expected: PASS, 8 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (expected: PASS).

Report `git status --short` and suggest: `feat: accounts screen for admins and dtr:user for the first account`.

Ask the user to create their own admin account by running, in a terminal they type into themselves:

```bash
php artisan dtr:user --name="Their Name" --email="their@email"
```

It asks for the password twice. Do not run it for them and do not choose the password.

---

### Task 4: Security headers and the HTTPS switch

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php`, `config/app.php`, `config/session.php`, `.env.example`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Security/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: the `PunchSource` binding from Task 1 (kept in `AppServiceProvider::register()`).
- Produces: `config('app.force_https')` (from `APP_FORCE_HTTPS`); `session.secure` follows it; `Date` facade returns `CarbonImmutable`; `Password::default()` is strict in production.

- [ ] **Step 1: Write the failing test**

Run `php artisan make:test Security/SecurityHeadersTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_carries_the_headers(): void
    {
        // The login page above all: it is the one screen a stranger on the
        // LAN can reach without an account.
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_the_policy_forbids_framing_and_pins_forms_and_the_base_url(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_the_policy_does_not_claim_to_restrict_scripts(): void
    {
        // Any script-src this app could run under would need unsafe-eval, so
        // it would block nothing while reading as though it did.
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertStringNotContainsString('default-src', $csp);
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_forcing_https_makes_generated_urls_https(): void
    {
        config(['app.force_https' => true]);
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/login'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Security`
Expected: FAIL — the headers are missing (`Header [X-Content-Type-Options] not present on response.`) and the URL starts with `http://`.

- [ ] **Step 3: Add the middleware, the switch and the defaults**

Run `php artisan make:middleware SecurityHeaders --no-interaction` and replace `app/Http/Middleware/SecurityHeaders.php` with:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers that tell a browser what this application is allowed to do.
 * The same set as the HRIS.
 *
 * They cost nothing and close whole classes of attack that no amount of care
 * in the views can reach: an attendance screen shown inside an invisible frame
 * on somebody else's page, a login form quietly re-pointed at another host, a
 * text file served as script because the browser guessed.
 *
 * **There is no `script-src` here, on purpose.** Filament runs on Livewire and
 * Alpine, and Alpine evaluates its directives at runtime, so the only script
 * policy this application could actually run under is one carrying
 * `unsafe-eval` and `unsafe-inline` — which permits exactly what a script
 * policy exists to forbid, while reading on an audit as though scripts were
 * locked down. A header that claims a protection it does not provide is worse
 * than its absence. See the CSP note in CLAUDE.md before adding one.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            // Nobody may frame this application.
            "frame-ancestors 'none'",
            // An injected <base> tag cannot repoint every relative URL,
            // including the login form's.
            "base-uri 'self'",
            // A form on this site posts to this site.
            "form-action 'self'",
            // No plugin of any kind.
            "object-src 'none'",
        ]));

        // What frame-ancestors says, said again for browsers that predate it.
        $response->headers->set('X-Frame-Options', 'DENY');

        // A file served with the wrong type is not promoted to script because
        // the browser sniffed its contents and decided otherwise.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // A URL here can name an employee. It does not travel to another host
        // in a Referer header.
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Nothing here needs a camera, a microphone or a location, so nothing
        // here — or anything injected into it — may ask.
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
        ]));

        // Only over a connection that is already secure. Over plain http every
        // browser ignores it.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
```

Replace `bootstrap/app.php` with:

```php
<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // On every response, including redirects and errors: an error page is
        // still a page a browser will frame.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
```

In `config/app.php`, directly after the `'url' => env('APP_URL', 'http://localhost'),` line, add:

```php

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | On the server, every URL the application generates is https and the
    | session cookie is marked Secure. Leave it off in development: Laragon
    | serves plain http, and a Secure cookie is never sent back over http,
    | which looks exactly like a login that does not work.
    |
    */

    'force_https' => (bool) env('APP_FORCE_HTTPS', false),
```

In `config/session.php`, replace:

```php
    'secure' => env('SESSION_SECURE_COOKIE'),
```

with:

```php
    // Follows APP_FORCE_HTTPS unless set on its own, so turning HTTPS on for
    // the server cannot leave the session cookie travelling in the clear.
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_FORCE_HTTPS', false)),
```

In `.env.example`, after the `APP_URL=` line, add:

```
APP_FORCE_HTTPS=false
```

Replace `app/Providers/AppServiceProvider.php` with (it keeps Task 1's binding):

```php
<?php

namespace App\Providers;

use App\Services\Attendance\PunchSources\PunchSource;
use App\Services\Attendance\PunchSources\ZktecoPunchSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PunchSource::class, ZktecoPunchSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Attendance arithmetic moves times around constantly. An immutable
        // date cannot be changed underneath the code that is still using it.
        Date::use(CarbonImmutable::class);

        // Without this, a link built by the framework can come back as http
        // even on an https site, and the browser downgrades the request —
        // carrying the session cookie with it.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Security`
Expected: PASS, 5 tests.

Run: `php artisan test --compact`
Expected: PASS (immutable dates do not break the earlier tasks).

- [ ] **Step 5: Format and hand off**

Run `vendor/bin/pint --dirty --format agent`. Report `git status --short` and suggest: `feat: security headers, APP_FORCE_HTTPS switch and production defaults`.

---

### Task 5: Activity log

**Files:**
- Modify: `composer.json`; Create: `database/migrations/*_create_activity_log_table.php` (published)
- Modify: `app/Models/User.php`, `app/Models/Device.php`
- Test: `tests/Feature/AuditTrailTest.php`

**Interfaces:**
- Consumes: `User` (Task 2), `Device` (Task 1).
- Produces: `Spatie\Activitylog\Models\Concerns\LogsActivity` on `User` (logs `name, email, role, is_active` — never `password`) and `Device` (logs `name, ip, port, is_active` — never `comm_key`). Later models use the same trait with `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges()`. Changes are read from `$activity->attribute_changes['old'|'attributes']`.

- [ ] **Step 1: Install the package and its table**

```bash
composer require "spatie/laravel-activitylog:^5.1" --no-interaction
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations" --no-interaction
php artisan migrate --no-interaction
```

Expected: `create_activity_log_table ... DONE`.

- [ ] **Step 2: Write the failing test**

Run `php artisan make:test AuditTrailTest --phpunit --no-interaction` and replace the file with:

```php
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
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/AuditTrailTest.php`
Expected: FAIL — `assertNotNull` fails: no activity is recorded yet.

- [ ] **Step 4: Log users and devices**

Replace `app/Models/User.php` with:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
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
    ];

    /**
     * Filament asks this on every request, not only at sign-in, so
     * deactivating an account locks it out on its next click.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Never the password: even hashed, it does not belong in a log that every
     * HR user can read.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'is_active'])
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
        ];
    }
}
```

Replace `app/Models/Device.php` with:

```php
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
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/AuditTrailTest.php`
Expected: PASS, 3 tests. Then `php artisan test --compact` — PASS.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent`. Report `git status --short` and suggest: `feat: activity log for accounts and devices, without passwords or comm keys`.

---

### Task 6: Offices

**Files:**
- Create: `database/migrations/*_create_offices_table.php`, `app/Models/Office.php`, `database/factories/OfficeFactory.php`, `app/Policies/OfficePolicy.php`
- Create: `app/Filament/Resources/Offices/OfficeResource.php`, `Schemas/OfficeForm.php`, `Tables/OfficesTable.php`, `Pages/ListOffices.php`, `Pages/CreateOffice.php`, `Pages/EditOffice.php` (the three pages as generated)
- Test: `tests/Feature/Offices/OfficeResourceTest.php`

**Interfaces:**
- Consumes: `User` factory (Task 2), activity log (Task 5).
- Produces: `App\Models\Office` — fillable `name, head_name, is_active`; `OfficeFactory`; `OfficeResource` with pages `ListOffices`, `CreateOffice`, `EditOffice` (edit page keeps the generated `DeleteAction`).

- [ ] **Step 1: Generate the files**

```bash
php artisan make:model Office --migration --factory --no-interaction
php artisan make:policy OfficePolicy --model=Office --no-interaction
php artisan make:filament-resource Office --no-interaction
php artisan make:test Offices/OfficeResourceTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing test**

Replace `tests/Feature/Offices/OfficeResourceTest.php` with:

```php
<?php

namespace Tests\Feature\Offices;

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Models\Office;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OfficeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_hr_sees_the_offices(): void
    {
        $offices = Office::factory()->count(3)->create();

        Livewire::test(ListOffices::class)
            ->assertCanSeeTableRecords($offices);
    }

    public function test_hr_adds_an_office_with_the_head_who_signs_form_48(): void
    {
        Livewire::test(CreateOffice::class)
            ->fillForm([
                'name' => 'Nursing Service',
                'head_name' => 'Maria L. Santos',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('offices', [
            'name' => 'Nursing Service',
            'head_name' => 'Maria L. Santos',
            'is_active' => true,
        ]);
    }

    public function test_two_offices_cannot_share_a_name(): void
    {
        Office::factory()->create(['name' => 'Nursing Service']);

        Livewire::test(CreateOffice::class)
            ->fillForm(['name' => 'Nursing Service'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_an_office_keeps_its_own_name_when_edited(): void
    {
        // unique() has to ignore the record being edited, or every save of an
        // existing office would fail.
        $office = Office::factory()->create(['name' => 'Nursing Service']);

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->fillForm(['head_name' => 'Jose P. Rizal'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Jose P. Rizal', $office->fresh()->head_name);
    }

    public function test_an_office_nobody_belongs_to_can_be_deleted(): void
    {
        $office = Office::factory()->create();

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($office);
    }

    public function test_renaming_an_office_is_recorded(): void
    {
        $office = Office::factory()->create(['name' => 'Nursing']);
        $office->update(['name' => 'Nursing Service']);

        $activity = Activity::query()
            ->where('subject_type', $office->getMorphClass())
            ->where('event', 'updated')
            ->first();

        $this->assertSame('Nursing', $activity->attribute_changes['old']['name']);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Offices`
Expected: FAIL — the `offices` table has no `name` column yet (`table offices has no column named name`).

- [ ] **Step 4: Write the table, model, policy and screen**

Replace `database/migrations/*_create_offices_table.php` with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('head_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
```

Replace `app/Models/Office.php` with:

```php
<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A unit employees belong to. Its head signs Form 48 as "In Charge".
 */
#[Fillable(['name', 'head_name', 'is_active'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory, LogsActivity;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

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
            'is_active' => 'boolean',
        ];
    }
}
```

Replace `database/factories/OfficeFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Office',
            'head_name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
```

Replace `app/Policies/OfficePolicy.php` with:

```php
<?php

namespace App\Policies;

use App\Models\Office;
use App\Models\User;

/**
 * Both roles manage offices. The policy exists so that is stated rather than
 * assumed, and so the delete rule has a home.
 */
class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Office $office): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Office $office): bool
    {
        return true;
    }

    public function delete(User $user, Office $office): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
```

Replace `app/Filament/Resources/Offices/OfficeResource.php` with:

```php
<?php

namespace App\Filament\Resources\Offices;

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Filament\Resources\Offices\Schemas\OfficeForm;
use App\Filament\Resources\Offices\Tables\OfficesTable;
use App\Models\Office;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return OfficeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffices::route('/'),
            'create' => CreateOffice::route('/create'),
            'edit' => EditOffice::route('/{record}/edit'),
        ];
    }
}
```

Replace `app/Filament/Resources/Offices/Schemas/OfficeForm.php` with:

```php
<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('head_name')
                    ->label('Head of office')
                    ->helperText('Printed under "In Charge" on Form 48.')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
```

Replace `app/Filament/Resources/Offices/Tables/OfficesTable.php` with:

```php
<?php

namespace App\Filament\Resources\Offices\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OfficesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('head_name')
                    ->label('Head')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

Leave the three generated pages as they are. Run `php artisan migrate --no-interaction`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Offices`
Expected: PASS, 6 tests.

- [ ] **Step 6: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS). Report `git status --short` and suggest: `feat: offices, with the head who signs Form 48`.

---

### Task 7: Employees

**Files:**
- Create: `app/Enums/EmploymentStatus.php`
- Create: `database/migrations/*_create_employees_table.php`, `app/Models/Employee.php`, `database/factories/EmployeeFactory.php`, `app/Policies/EmployeePolicy.php`
- Create: `app/Filament/Resources/Employees/EmployeeResource.php`, `Schemas/EmployeeForm.php`, `Tables/EmployeesTable.php`, `Pages/ListEmployees.php`, `Pages/CreateEmployee.php` (both as generated), `Pages/EditEmployee.php`
- Modify: `app/Models/Office.php` (employees relation), `app/Policies/OfficePolicy.php` (delete rule), `app/Filament/Resources/Offices/Tables/OfficesTable.php` (employee count)
- Test: `tests/Feature/Employees/EmployeeResourceTest.php`

**Interfaces:**
- Consumes: `Office`, `OfficeFactory`, `EditOffice` (Task 6).
- Produces:
  - `enum EmploymentStatus: string implements HasLabel { Permanent='permanent'; JobOrder='job_order'; ContractOfService='contract_of_service'; Coterminous='coterminous'; getLabel(): string; static fromLoose(string $value): ?self }`
  - `App\Models\Employee` (soft deletes) — fillable `employee_number, last_name, first_name, middle_name, suffix, office_id, employment_status, biometric_id, date_hired, date_separated, is_active`; `office(): BelongsTo`; accessor `full_name` ("Dela Cruz, Juan S. Jr.").
  - `EmployeeFactory` with state `withBiometricId(?string $biometricId = null)`.
  - `Office::employees(): HasMany`.
  - Pages `ListEmployees`, `CreateEmployee`, `EditEmployee`.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:enum EmploymentStatus --string --no-interaction
php artisan make:model Employee --migration --factory --no-interaction
php artisan make:policy EmployeePolicy --model=Employee --no-interaction
php artisan make:filament-resource Employee --soft-deletes --no-interaction
php artisan make:test Employees/EmployeeResourceTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing test**

Replace `tests/Feature/Employees/EmployeeResourceTest.php` with:

```php
<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create();
        $this->actingAs($this->hr);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'employee_number' => '2019-0042',
            'office_id' => Office::factory()->create()->id,
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'employment_status' => EmploymentStatus::Permanent->value,
            'biometric_id' => '0042',
            'date_hired' => '2019-03-01',
        ], $overrides);
    }

    public function test_hr_sees_the_employees(): void
    {
        $employees = Employee::factory()->count(3)->create();

        Livewire::test(ListEmployees::class)
            ->assertCanSeeTableRecords($employees);
    }

    public function test_hr_adds_an_employee(): void
    {
        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $employee = Employee::firstWhere('employee_number', '2019-0042');

        // Kept as text: to the device, "0042" and "42" are different people.
        $this->assertSame('0042', $employee->biometric_id);
        $this->assertSame('Dela Cruz, Juan S.', $employee->full_name);
        $this->assertSame('2019-03-01', $employee->date_hired->toDateString());
    }

    public function test_a_biometric_id_belongs_to_one_employee_only(): void
    {
        Employee::factory()->withBiometricId('0042')->create();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasFormErrors(['biometric_id' => 'unique']);
    }

    public function test_a_deleted_employee_still_holds_their_biometric_id(): void
    {
        // Their punches still point at them. The ID has to be cleared on
        // purpose before anyone else may use it.
        Employee::factory()->withBiometricId('0042')->create()->delete();

        Livewire::test(CreateEmployee::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasFormErrors(['biometric_id' => 'unique']);
    }

    public function test_a_separation_date_cannot_come_before_the_hire_date(): void
    {
        $employee = Employee::factory()->create(['date_hired' => '2020-01-15']);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['date_separated' => '2019-12-31'])
            ->call('save')
            ->assertHasFormErrors(['date_separated']);
    }

    public function test_a_separation_date_is_accepted_when_the_hire_date_is_unknown(): void
    {
        $employee = Employee::factory()->create(['date_hired' => null]);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['date_separated' => '2026-09-30'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-09-30', $employee->fresh()->date_separated->toDateString());
    }

    public function test_deleting_an_employee_is_soft_and_can_be_undone(): void
    {
        $employee = Employee::factory()->create();

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($employee);

        Livewire::test(EditEmployee::class, ['record' => $employee->getRouteKey()])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($employee);
    }

    public function test_an_office_with_employees_cannot_be_deleted(): void
    {
        // Deleted employees count too: their rows still point at the office.
        $office = Office::factory()->create();
        Employee::factory()->for($office)->create()->delete();

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_editing_an_employee_records_who_changed_what(): void
    {
        $employee = Employee::factory()->create(['last_name' => 'Dela Cruz']);
        $employee->update(['last_name' => 'Dela Cruz-Reyes']);

        $activity = Activity::query()
            ->where('subject_type', $employee->getMorphClass())
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertSame($this->hr->id, $activity->causer_id);
        $this->assertSame('Dela Cruz', $activity->attribute_changes['old']['last_name']);
        $this->assertSame('Dela Cruz-Reyes', $activity->attribute_changes['attributes']['last_name']);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Employees/EmployeeResourceTest.php`
Expected: FAIL — `Case Permanent` / `Call to undefined method ... withBiometricId()` / missing columns.

- [ ] **Step 4: Write the status, table, model and policy**

Replace `app/Enums/EmploymentStatus.php` with:

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How the hospital engages a person. The same four values as the HRIS, so the
 * two systems agree if they are ever linked.
 *
 * Keep this list to what the hospital actually hires under. The full CSC
 * vocabulary adds Temporary, Casual, Contractual, Substitute and Provisional —
 * every one of them a wrong answer sitting in a dropdown waiting to be picked.
 */
enum EmploymentStatus: string implements HasLabel
{
    case Permanent = 'permanent';
    case JobOrder = 'job_order';
    case ContractOfService = 'contract_of_service';
    case Coterminous = 'coterminous';

    /** Written the way it appears on the appointment paper. */
    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::JobOrder => 'Job Order',
            self::ContractOfService => 'Contract of Service',
            self::Coterminous => 'Co-terminous',
        };
    }

    /**
     * Match however HR wrote it. A spreadsheet says "Contract of Service" or
     * "Co-terminous", not "contract_of_service", and refusing that is a defect
     * in the importer rather than in their file.
     */
    public static function fromLoose(string $value): ?self
    {
        $needle = self::normalize($value);

        foreach (self::cases() as $case) {
            if (self::normalize($case->value) === $needle || self::normalize($case->getLabel()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    /** Strips case and every separator, so "Co-terminous" meets "coterminous". */
    private static function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($value))) ?? '';
    }
}
```

Replace `database/migrations/*_create_employees_table.php` with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 10)->nullable();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('employment_status', 30);
            // A string, not a number: the device's user ID can carry leading
            // zeros, and to the device "0042" and "42" are different people.
            $table->string('biometric_id', 20)->nullable()->unique();
            $table->date('date_hired')->nullable();
            $table->date('date_separated')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

Replace `app/Models/Employee.php` with:

```php
<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'employee_number',
    'last_name',
    'first_name',
    'middle_name',
    'suffix',
    'office_id',
    'employment_status',
    'biometric_id',
    'date_hired',
    'date_separated',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return BelongsTo<Office, $this> */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * "Dela Cruz, Juan S. Jr." — surname first, the way HR sorts and reads a
     * list.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = "{$this->last_name}, {$this->first_name}";

            if (filled($this->middle_name)) {
                $name .= ' '.mb_substr($this->middle_name, 0, 1).'.';
            }

            if (filled($this->suffix)) {
                $name .= ' '.$this->suffix;
            }

            return $name;
        });
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
            'employment_status' => EmploymentStatus::class,
            'date_hired' => 'date',
            'date_separated' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
```

Replace `database/factories/EmployeeFactory.php` with:

```php
<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => fake()->unique()->numerify('####-####'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'suffix' => null,
            'office_id' => Office::factory(),
            'employment_status' => EmploymentStatus::Permanent,
            'biometric_id' => null,
            'date_hired' => null,
            'date_separated' => null,
            'is_active' => true,
        ];
    }

    public function withBiometricId(?string $biometricId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'biometric_id' => $biometricId ?? (string) fake()->unique()->numberBetween(1, 99999),
        ]);
    }
}
```

Replace `app/Policies/EmployeePolicy.php` with:

```php
<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Both roles manage employees. Nobody removes one for good: an employee's
 * punches and computed days point at that row, so a delete is always soft and
 * always reversible.
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Employee $employee): bool
    {
        return true;
    }

    public function delete(User $user, Employee $employee): bool
    {
        return true;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return true;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Write the employee screen, and keep offices with employees**

Replace `app/Filament/Resources/Employees/EmployeeResource.php` with:

```php
<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Employees\Schemas\EmployeeForm;
use App\Filament\Resources\Employees\Tables\EmployeesTable;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'last_name';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var Employee|null $record */
        return $record?->full_name;
    }

    public static function form(Schema $schema): Schema
    {
        return EmployeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }

    /**
     * Lets a deleted employee's page open, so the employee can be restored.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
```

Replace `app/Filament/Resources/Employees/Schemas/EmployeeForm.php` with:

```php
<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmploymentStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('employee_number')
                            ->required()
                            ->maxLength(30)
                            ->unique(),
                        Select::make('office_id')
                            ->label('Office')
                            ->relationship('office', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('last_name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('first_name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('middle_name')
                            ->maxLength(100),
                        TextInput::make('suffix')
                            ->maxLength(10),
                        Select::make('employment_status')
                            ->options(EmploymentStatus::class)
                            ->required(),
                    ]),
                Section::make('Attendance')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('biometric_id')
                            ->label('Biometric ID')
                            ->helperText('The ID this person uses on the biometric device. Leave it blank until it is known.')
                            ->maxLength(20)
                            ->alphaNum()
                            ->unique(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        DatePicker::make('date_hired'),
                        DatePicker::make('date_separated')
                            ->afterOrEqual('date_hired'),
                    ]),
            ]);
    }
}
```

Replace `app/Filament/Resources/Employees/Tables/EmployeesTable.php` with:

```php
<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_number')
                    ->label('Employee No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Employee $record): string => $record->full_name)
                    ->searchable(['last_name', 'first_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('office.name')
                    ->sortable(),
                TextColumn::make('employment_status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('biometric_id')
                    ->label('Biometric ID')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('office')
                    ->relationship('office', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employment_status')
                    ->label('Status')
                    ->options(EmploymentStatus::class),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('biometric_id')
                    ->label('Biometric ID')
                    ->nullable()
                    ->trueLabel('Has an ID')
                    ->falseLabel('No ID yet'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

Replace `app/Filament/Resources/Employees/Pages/EditEmployee.php` with (no force delete):

```php
<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
```

In `app/Models/Office.php`, add the import `use Illuminate\Database\Eloquent\Relations\HasMany;` and this method after the `$attributes` property:

```php
    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
```

In `app/Policies/OfficePolicy.php`, replace the `delete` method with:

```php
    /**
     * Not while anyone belongs to it, deleted employees included: their rows
     * still point here. Deactivate the office instead.
     */
    public function delete(User $user, Office $office): bool
    {
        return ! $office->employees()->withTrashed()->exists();
    }
```

In `app/Filament/Resources/Offices/Tables/OfficesTable.php`, add this column between `head_name` and `is_active`:

```php
                TextColumn::make('employees_count')
                    ->label('Employees')
                    ->counts('employees')
                    ->sortable(),
```

Run `php artisan migrate --no-interaction`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Employees tests/Feature/Offices`
Expected: PASS, 15 tests.

- [ ] **Step 7: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS). Report `git status --short` and suggest: `feat: employees with biometric IDs, hire and separation dates`.

---

### Task 8: Import employees from CSV

**Files:**
- Create: `app/Services/EmployeeImportResult.php`, `app/Services/EmployeeImporter.php`
- Create: `app/Filament/Resources/Employees/Actions/ImportEmployeesAction.php`
- Modify: `app/Filament/Resources/Employees/Pages/ListEmployees.php`
- Test: `tests/Feature/Employees/EmployeeImporterTest.php`, `tests/Feature/Employees/ImportEmployeesActionTest.php`

**Interfaces:**
- Consumes: `Employee`, `Office`, `EmploymentStatus::fromLoose()` (Task 7).
- Produces:
  - `EmployeeImporter::COLUMNS` (the nine column names) and `EmployeeImporter::import(string $path): EmployeeImportResult`.
  - `final readonly class EmployeeImportResult { int $created; int $updated; int $unchanged; list<string> $errors; static succeeded(int, int, int): self; static failed(list<string>): self; isSuccessful(): bool }`. Errors read `Row N: <message>`.
  - Filament action `importEmployees` on the employee list.

The import is all or nothing, matches columns by name in any order and case, takes an existing employee number as an update, and leaves the current value alone when an optional cell is blank. It reads Excel's UTF-8 (with a byte-order mark) and Excel's Windows-1252, and dates as `YYYY-MM-DD`, `MM/DD/YYYY` or `M/D/YYYY`.

- [ ] **Step 1: Write the failing tests**

Run `php artisan make:test Employees/EmployeeImporterTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use App\Services\EmployeeImporter;
use App\Services\EmployeeImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeImporterTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'employee_number,last_name,first_name,middle_name,suffix,office,employment_status,biometric_id,date_hired';

    protected function setUp(): void
    {
        parent::setUp();

        Office::factory()->create(['name' => 'Nursing Service']);
    }

    /** Writes the lines the way Excel does, with CRLF endings. */
    private function csv(string ...$lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'employees');
        file_put_contents($path, implode("\r\n", $lines)."\r\n");

        return $path;
    }

    private function import(string ...$lines): EmployeeImportResult
    {
        return app(EmployeeImporter::class)->import($this->csv(...$lines));
    }

    public function test_it_creates_the_employees_in_the_file(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,Santos,,Nursing Service,Permanent,0042,2019-03-01',
            '2020-0007,Reyes,Ana,,,nursing service,Co-terminous,,10/06/2020',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
        $this->assertSame(2, $result->created);

        $juan = Employee::firstWhere('employee_number', '2019-0042');
        $this->assertSame('0042', $juan->biometric_id);
        $this->assertSame('2019-03-01', $juan->date_hired->toDateString());

        $ana = Employee::firstWhere('employee_number', '2020-0007');
        $this->assertSame(EmploymentStatus::Coterminous, $ana->employment_status);
        $this->assertNull($ana->biometric_id);
        $this->assertSame('2020-10-06', $ana->date_hired->toDateString());
    }

    public function test_one_bad_row_stops_the_whole_file(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '2020-0007,Reyes,Ana,,,Radiology,Permanent,,',
        );

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(
            ['Row 3: office "Radiology" does not exist. Add it under Offices first.'],
            $result->errors,
        );
        $this->assertSame(0, Employee::count());
    }

    public function test_blank_lines_do_not_shift_the_row_numbers(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '',
            '2020-0007,Reyes,Ana,,,Radiology,Permanent,,',
        );

        $this->assertSame(
            ['Row 4: office "Radiology" does not exist. Add it under Offices first.'],
            $result->errors,
        );
    }

    public function test_an_existing_employee_number_updates_that_employee(): void
    {
        $employee = Employee::factory()->create([
            'employee_number' => '2019-0042',
            'office_id' => Office::firstWhere('name', 'Nursing Service')->id,
        ]);

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz-Reyes,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame([0, 1, 0], [$result->created, $result->updated, $result->unchanged]);
        $this->assertSame('Dela Cruz-Reyes', $employee->fresh()->last_name);
        $this->assertSame(1, Employee::count());
    }

    public function test_a_blank_cell_keeps_a_biometric_id_already_set(): void
    {
        // Re-importing last month's file must not undo the IDs HR mapped since.
        $employee = Employee::factory()->withBiometricId('0042')->create(['employee_number' => '2019-0042']);

        $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame('0042', $employee->fresh()->biometric_id);
    }

    public function test_the_same_employee_number_twice_is_refused(): void
    {
        $result = $this->import(
            self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
            '2019-0042,Reyes,Ana,,,Nursing Service,Permanent,,',
        );

        $this->assertSame(['Row 3: employee_number 2019-0042 already appears on row 2.'], $result->errors);
    }

    public function test_a_biometric_id_held_by_another_employee_is_refused(): void
    {
        Employee::factory()->withBiometricId('0042')->create(['employee_number' => '2018-0001']);

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,0042,');

        $this->assertSame(['Row 2: biometric_id 0042 already belongs to another employee.'], $result->errors);
    }

    public function test_a_deleted_employees_number_is_refused(): void
    {
        Employee::factory()->create(['employee_number' => '2019-0042'])->delete();

        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,');

        $this->assertSame(
            ['Row 2: employee_number 2019-0042 belongs to a deleted employee. Restore that employee first.'],
            $result->errors,
        );
    }

    public function test_an_impossible_date_is_refused(): void
    {
        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,2026-02-30');

        $this->assertSame(['Row 2: date_hired "2026-02-30" is not a date. Use YYYY-MM-DD.'], $result->errors);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $result = $this->import(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Casual,,');

        $this->assertSame(
            ['Row 2: employment_status "Casual" is not one of: Permanent, Co-terminous, Job Order, Contract of Service.'],
            $result->errors,
        );
    }

    public function test_columns_may_come_in_any_order_and_any_case(): void
    {
        $result = $this->import(
            'Office,Employee Number,Last Name,First Name,Middle Name,Suffix,Employment Status,Biometric ID,Date Hired',
            'Nursing Service,2019-0042,Dela Cruz,Juan,,,Permanent,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_trailing_empty_columns_from_excel_are_ignored(): void
    {
        $result = $this->import(
            self::HEADER.',,',
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_a_missing_column_is_named(): void
    {
        $result = $this->import('employee_number,last_name,first_name', '2019-0042,Dela Cruz,Juan');

        $this->assertSame(
            ['Row 1: missing column(s): middle_name, suffix, office, employment_status, biometric_id, date_hired.'],
            $result->errors,
        );
    }

    public function test_an_excel_byte_order_mark_is_ignored(): void
    {
        $result = $this->import(
            "\u{FEFF}".self::HEADER,
            '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,',
        );

        $this->assertTrue($result->isSuccessful(), implode("\n", $result->errors));
    }

    public function test_a_windows_1252_file_keeps_its_enye(): void
    {
        // Excel's plain "CSV (Comma delimited)" is not UTF-8.
        $row = mb_convert_encoding('2019-0042,Peñaflor,Niño,,,Nursing Service,Permanent,,', 'Windows-1252', 'UTF-8');

        $this->import(self::HEADER, $row);

        $this->assertSame('Peñaflor', Employee::first()->last_name);
        $this->assertSame('Niño', Employee::first()->first_name);
    }
}
```

Run `php artisan make:test Employees/ImportEmployeesActionTest --phpunit --no-interaction` and replace the file with:

```php
<?php

namespace Tests\Feature\Employees;

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImportEmployeesActionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'employee_number,last_name,first_name,middle_name,suffix,office,employment_status,biometric_id,date_hired';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        Office::factory()->create(['name' => 'Nursing Service']);
    }

    private function upload(string ...$lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.csv', implode("\r\n", $lines)."\r\n");
    }

    public function test_hr_imports_a_csv_from_the_employee_list(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,0042,'),
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Imported: 1 new, 0 updated, 0 unchanged');

        $this->assertDatabaseHas('employees', ['employee_number' => '2019-0042', 'biometric_id' => '0042']);
    }

    public function test_the_uploaded_file_is_not_kept(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Nursing Service,Permanent,,'),
            ]);

        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_a_rejected_file_imports_nothing_and_says_so(): void
    {
        Livewire::test(ListEmployees::class)
            ->callAction('importEmployees', data: [
                'file' => $this->upload(self::HEADER, '2019-0042,Dela Cruz,Juan,,,Radiology,Permanent,,'),
            ])
            ->assertNotified('Nothing was imported');

        $this->assertSame(0, Employee::count());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Employees/EmployeeImporterTest.php tests/Feature/Employees/ImportEmployeesActionTest.php`
Expected: FAIL — `Class "App\Services\EmployeeImporter" not found`, and the list page has no `importEmployees` action yet.

- [ ] **Step 3: Write the importer**

Run `php artisan make:class Services/EmployeeImportResult --no-interaction` and replace it with:

```php
<?php

namespace App\Services;

/**
 * What an import did, or why it did nothing.
 */
final readonly class EmployeeImportResult
{
    /**
     * @param  list<string>  $errors
     */
    private function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public array $errors,
    ) {}

    public static function succeeded(int $created, int $updated, int $unchanged): self
    {
        return new self($created, $updated, $unchanged, []);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self(0, 0, 0, $errors);
    }

    public function isSuccessful(): bool
    {
        return $this->errors === [];
    }
}
```

Run `php artisan make:class Services/EmployeeImporter --no-interaction` and replace it with:

```php
<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\Office;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use SplFileObject;
use SplTempFileObject;

/**
 * Loads HR's employee spreadsheet, all or nothing.
 *
 * Every row is checked before any is saved, and one bad row stops the whole
 * file: a half-imported list is harder to put right than a rejected one,
 * because nobody can tell which half made it in.
 *
 * An employee number already on file updates that employee. A blank optional
 * cell leaves the current value alone, so re-importing an old file cannot
 * erase a biometric ID that HR has set since.
 */
class EmployeeImporter
{
    /** @var list<string> */
    public const COLUMNS = [
        'employee_number',
        'last_name',
        'first_name',
        'middle_name',
        'suffix',
        'office',
        'employment_status',
        'biometric_id',
        'date_hired',
    ];

    /** @var list<string> */
    private const REQUIRED = [
        'employee_number',
        'last_name',
        'first_name',
        'office',
        'employment_status',
    ];

    /** @var list<string> */
    private const OPTIONAL = [
        'middle_name',
        'suffix',
        'biometric_id',
        'date_hired',
    ];

    /** @var array<string, int> */
    private const MAX_LENGTHS = [
        'employee_number' => 30,
        'last_name' => 100,
        'first_name' => 100,
        'middle_name' => 100,
        'suffix' => 10,
        'biometric_id' => 20,
    ];

    /**
     * ISO first, then the month-first forms Excel writes on a Philippine
     * Windows PC.
     *
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', 'm/d/Y', 'n/j/Y'];

    public function import(string $path): EmployeeImportResult
    {
        $records = $this->records($path);

        if ($records === []) {
            return EmployeeImportResult::failed(['The file is empty.']);
        }

        $headerLine = array_key_first($records);
        $header = array_map($this->normalizeHeading(...), $records[$headerLine]);
        unset($records[$headerLine]);

        $headerErrors = $this->headerErrors($header);

        if ($headerErrors !== []) {
            return EmployeeImportResult::failed(array_map(
                fn (string $error): string => "Row {$headerLine}: {$error}",
                $headerErrors,
            ));
        }

        $offices = $this->officeIdsByName();
        $rows = [];
        $errors = [];
        $seenNumbers = [];
        $seenBiometricIds = [];

        foreach ($records as $line => $values) {
            $row = $this->combine($header, $values);

            foreach ($this->rowErrors($row, $offices, $seenNumbers, $seenBiometricIds) as $error) {
                $errors[] = "Row {$line}: {$error}";
            }

            $seenNumbers[$row['employee_number']] ??= $line;

            if ($row['biometric_id'] !== '') {
                $seenBiometricIds[$row['biometric_id']] ??= $line;
            }

            $rows[] = $row;
        }

        if ($errors !== []) {
            return EmployeeImportResult::failed($errors);
        }

        if ($rows === []) {
            return EmployeeImportResult::failed(['The file has no employee rows.']);
        }

        return DB::transaction(fn (): EmployeeImportResult => $this->save($rows, $offices));
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, int>  $offices
     */
    private function save(array $rows, array $offices): EmployeeImportResult
    {
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $employee = Employee::firstOrNew(['employee_number' => $row['employee_number']]);
            $employee->fill($this->attributes($row, $offices, $employee->exists));

            if (! $employee->exists) {
                $employee->save();
                $created++;
            } elseif ($employee->isDirty()) {
                $employee->save();
                $updated++;
            }
        }

        return EmployeeImportResult::succeeded($created, $updated, count($rows) - $created - $updated);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $offices
     * @return array<string, mixed>
     */
    private function attributes(array $row, array $offices, bool $exists): array
    {
        $attributes = [
            'employee_number' => $row['employee_number'],
            'last_name' => $row['last_name'],
            'first_name' => $row['first_name'],
            'middle_name' => $row['middle_name'],
            'suffix' => $row['suffix'],
            'office_id' => $offices[mb_strtolower($row['office'])],
            'employment_status' => EmploymentStatus::fromLoose($row['employment_status']),
            'biometric_id' => $row['biometric_id'],
            'date_hired' => $row['date_hired'] === ''
                ? ''
                : $this->parseDate($row['date_hired'])?->format('Y-m-d'),
        ];

        foreach (self::OPTIONAL as $column) {
            if ($attributes[$column] !== '') {
                continue;
            }

            if ($exists) {
                unset($attributes[$column]);
            } else {
                $attributes[$column] = null;
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $offices
     * @param  array<string, int>  $seenNumbers  the row each employee number first appeared on
     * @param  array<string, int>  $seenBiometricIds  the row each biometric ID first appeared on
     * @return list<string>
     */
    private function rowErrors(array $row, array $offices, array $seenNumbers, array $seenBiometricIds): array
    {
        $errors = [];

        foreach (self::REQUIRED as $column) {
            if ($row[$column] === '') {
                $errors[] = "{$column} is required.";
            }
        }

        foreach (self::MAX_LENGTHS as $column => $max) {
            if (mb_strlen($row[$column]) > $max) {
                $errors[] = "{$column} is longer than {$max} characters.";
            }
        }

        $number = $row['employee_number'];

        if ($number !== '' && isset($seenNumbers[$number])) {
            $errors[] = "employee_number {$number} already appears on row {$seenNumbers[$number]}.";
        } elseif ($number !== '' && Employee::onlyTrashed()->where('employee_number', $number)->exists()) {
            $errors[] = "employee_number {$number} belongs to a deleted employee. Restore that employee first.";
        }

        if ($row['office'] !== '' && ! isset($offices[mb_strtolower($row['office'])])) {
            $errors[] = "office \"{$row['office']}\" does not exist. Add it under Offices first.";
        }

        if ($row['employment_status'] !== '' && EmploymentStatus::fromLoose($row['employment_status']) === null) {
            $errors[] = "employment_status \"{$row['employment_status']}\" is not one of: Permanent, Co-terminous, Job Order, Contract of Service.";
        }

        $biometricId = $row['biometric_id'];

        if ($biometricId !== '' && preg_match('/^[A-Za-z0-9]+$/', $biometricId) !== 1) {
            $errors[] = 'biometric_id may contain only letters and digits.';
        } elseif ($biometricId !== '' && isset($seenBiometricIds[$biometricId])) {
            $errors[] = "biometric_id {$biometricId} already appears on row {$seenBiometricIds[$biometricId]}.";
        } elseif ($biometricId !== '' && Employee::withTrashed()
            ->where('biometric_id', $biometricId)
            ->where('employee_number', '!=', $number)
            ->exists()) {
            $errors[] = "biometric_id {$biometricId} already belongs to another employee.";
        }

        if ($row['date_hired'] !== '' && $this->parseDate($row['date_hired']) === null) {
            $errors[] = "date_hired \"{$row['date_hired']}\" is not a date. Use YYYY-MM-DD.";
        }

        return $errors;
    }

    /**
     * @param  list<string>  $header
     * @return list<string>
     */
    private function headerErrors(array $header): array
    {
        $named = array_values(array_filter($header, fn (string $heading): bool => $heading !== ''));
        $missing = array_diff(self::COLUMNS, $named);
        $unknown = array_diff($named, self::COLUMNS);
        $errors = [];

        if ($missing !== []) {
            $errors[] = 'missing column(s): '.implode(', ', $missing).'.';
        }

        if ($unknown !== []) {
            $errors[] = 'unknown column(s): '.implode(', ', $unknown).'.';
        }

        if (count($named) !== count(array_unique($named))) {
            $errors[] = 'a column appears more than once.';
        }

        return $errors;
    }

    /**
     * "Employee Number", "employee number" and "EMPLOYEE_NUMBER" all mean
     * employee_number.
     */
    private function normalizeHeading(?string $heading): string
    {
        $snake = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim((string) $heading))) ?? '';

        return trim($snake, '_');
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $header, array $values): array
    {
        $row = array_fill_keys(self::COLUMNS, '');

        foreach ($header as $position => $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = trim((string) ($values[$position] ?? ''));
            }
        }

        return $row;
    }

    /**
     * The file's non-blank lines, keyed by line number.
     *
     * @return array<int, list<string|null>>
     */
    private function records(string $path): array
    {
        $file = new SplTempFileObject;
        $file->fwrite($this->utf8Contents($path));
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV);
        // An empty escape turns off PHP's own backslash escaping, which no
        // spreadsheet writes. PHP 8.4 deprecates leaving it unset.
        $file->setCsvControl(',', '"', '');

        $records = [];

        foreach ($file as $index => $values) {
            if (! is_array($values) || implode('', array_map(fn (?string $value): string => trim((string) $value), $values)) === '') {
                continue;
            }

            $records[$index + 1] = $values;
        }

        return $records;
    }

    private function utf8Contents(string $path): string
    {
        $contents = (string) file_get_contents($path);

        // Excel's "CSV UTF-8" begins with a byte-order mark, which would
        // otherwise stick to the first column name.
        if (str_starts_with($contents, "\u{FEFF}")) {
            $contents = substr($contents, 3);
        }

        // Excel's plain "CSV (Comma delimited)" is Windows-1252. Read as
        // UTF-8, every ñ in Peñaflor or Niño would turn into garbage.
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return $contents;
    }

    /** @return array<string, int> office ID by lower-cased name */
    private function officeIdsByName(): array
    {
        return Office::query()
            ->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [mb_strtolower($name) => $id])
            ->all();
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            // The round trip rejects what PHP would otherwise roll over, such
            // as 2026-02-30 quietly becoming 2 March.
            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Run the importer test**

Run: `php artisan test --compact tests/Feature/Employees/EmployeeImporterTest.php`
Expected: PASS, 15 tests.

- [ ] **Step 5: Put the import on the employee list**

Create `app/Filament/Resources/Employees/Actions/ImportEmployeesAction.php`:

```php
<?php

namespace App\Filament\Resources\Employees\Actions;

use App\Models\Employee;
use App\Services\EmployeeImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Uploads HR's CSV and hands it to EmployeeImporter. The upload lands on the
 * private local disk and is deleted as soon as it has been read.
 */
class ImportEmployeesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'importEmployees';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Import CSV')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize('create', Employee::class)
            ->modalHeading('Import employees')
            ->modalDescription('Columns: '.implode(', ', EmployeeImporter::COLUMNS).'. In Excel, format employee_number and biometric_id as Text so leading zeros survive. An employee number already on file updates that employee; a blank cell keeps the current value.')
            ->modalSubmitActionLabel('Import')
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->maxSize(2048)
                    ->required(),
            ])
            ->action(function (array $data, EmployeeImporter $importer): void {
                $disk = Storage::disk('local');

                try {
                    $result = $importer->import($disk->path($data['file']));
                } finally {
                    $disk->delete($data['file']);
                }

                if (! $result->isSuccessful()) {
                    Notification::make()
                        ->danger()
                        ->title('Nothing was imported')
                        ->body($this->listOf($result->errors))
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Imported: {$result->created} new, {$result->updated} updated, {$result->unchanged} unchanged")
                    ->send();
            });
    }

    /**
     * The first ten problems, escaped: the messages quote the file's own text.
     *
     * @param  list<string>  $errors
     */
    private function listOf(array $errors): HtmlString
    {
        $lines = array_map(e(...), array_slice($errors, 0, 10));

        if (count($errors) > 10) {
            $lines[] = '…and '.(count($errors) - 10).' more.';
        }

        return new HtmlString(implode('<br>', $lines));
    }
}
```

Replace `app/Filament/Resources/Employees/Pages/ListEmployees.php` with:

```php
<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\Actions\ImportEmployeesAction;
use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportEmployeesAction::make(),
            CreateAction::make(),
        ];
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Employees`
Expected: PASS, 27 tests.

- [ ] **Step 7: Format and hand off**

Run `vendor/bin/pint --dirty --format agent` and `php artisan test --compact` (PASS). Report `git status --short` and suggest: `feat: import employees from HR's CSV, all or nothing`.

---

## Finish

- [ ] Run `php artisan test --compact`. Expected: PASS, 61 tests, none skipped (60 from this plan plus Laravel's unit example).
- [ ] Run `vendor/bin/pint --dirty --format agent`. Expected: no changes left.
- [ ] There is no front-end build in this plan: Filament ships its compiled assets, and nothing here uses Vite.
- [ ] Smoke test in the browser, after Laragon has been reloaded (Menu → Apache → Reload) so `dtr-system.test` resolves:
  1. Open `http://dtr-system.test` and sign in with the admin account from Task 3.
  2. Add an office, then import a three-row CSV saved from Excel ("CSV (Comma delimited)", with an "ñ" in one name and a biometric ID such as `0042` formatted as Text).
  3. Check the names and IDs in the employee list, then open Accounts and add an HR account.
- [ ] Tell the user:
  - which steps passed, with the test count;
  - whether Task 1, Step 7 (the real device) has passed, and what it printed. Plan 2 waits on it;
  - that `php artisan boost:update` can now be run interactively to add Filament guidance to the Boost section of `CLAUDE.md`;
  - the inputs Plan 2 needs from HR: the schedule templates in use, who is on the roster, and this year's holidays.
