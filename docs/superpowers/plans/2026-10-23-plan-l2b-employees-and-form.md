# Leave Module — Plan L2b: Employees and the Form Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Employees sign in with their employee number, see their own balances and history, file and cancel their own leave, and anyone entitled prints the filled HRM-07 leave form.

**Architecture:** A second Filament panel at `/employee` (`EmployeePanelProvider`) holds only the signed-in employee's leave: a "My leave" dashboard, History, Applications and Leave types. Accounts are `users` rows with role `employee` linked by `users.employee_id`; HR creates them in bulk and hands out one-time password slips. The HR panel and every existing policy become staff-only. The form is a plain Laravel route rendering an A4 print view of HR's HRM-07 layout.

**Tech Stack:** Laravel 13, PHP 8.4 (bcmath, zip), Filament 5.9 (Livewire 4), spatie/laravel-activitylog 5.1, PHPUnit 12 on SQLite in memory, MySQL 8 in use.

**Spec:** `docs/superpowers/specs/2026-10-23-leave-module-design.md`, sections **Accounts**, **Screens**, **Security**, **Leave types**, **The leave form**, **Data model**. Plans L1 and L2a are built (commits up to `0807f6d`).

## Changes to the spec

To be made in the spec together with this plan, for the user to review with it:

- `leave_applications.credits_at_filing` (json, nullable): VL and SL as they stood just before the application was charged, so 7.A prints the same figures every time. Applications filed before L2b have none and print 7.A blank.
- The form is a plain route, `GET /leave-applications/{leaveApplication}/print`, not a Filament page, so it prints without the panel around it. A guest is sent to `/login`.
- Temporary passwords reach HR once, on a slips page fed by an encrypted one-time session flash; they are stored nowhere readable.
- "My leave" is the employee panel's dashboard, with two widgets: the balances and the pending applications.
- The leave type descriptions are Claude's wording from the CSC rules named on the form; HR reads them before go-live.
- The Users screen offers only Admin and HR as roles and can link an account to its owner's employee record.

## Global Constraints

- Laravel 13, PHP 8.4, Livewire 4, Filament 5, PHPUnit. Timezone `Asia/Manila`.
- **No new packages and no npm build.** The print view and the slips page carry their own inline CSS.
- **Two panels.** `admin` at `/` (staff: roles `admin`, `hr`); `employee` at `/employee`. `User::canAccessPanel()` decides per panel id, on every request.
- **Staff-only HR panel:** every policy ability that returned `true` returns `$user->isStaff()`. The leave application policy also lets an employee act on their own rows as the spec's **Who does what** table says.
- **An employee sees only their own leave.** Every employee-panel query is scoped to `auth()->user()->employee_id`, never to a value from the request. Record IDs in Livewire components are `#[Locked]`.
- **Passwords are never stored readable or logged.** Temporary passwords: `Str::password(10, symbols: false)`; shown once; `must_change_password` set; the activity log keeps excluding `password`.
- Days are three-place strings with bcmath, as in L1 and L2a.
- Write through models so the activity log sees changes; `users` logs `employee_id` and `must_change_password` too.
- The **Security Guidelines** in `CLAUDE.md` apply. Names, positions, reasons and the typed Division Head are user text: `{{ }}` only, never `{!! !!}`.
- Create files with `php artisan make:* --no-interaction`. Test helpers must not be named after `TestCase` methods.
- Tests: `php artisan test --compact <path>`; Pint before handing off. **Never run `git commit`.** Each task ends with its changed files and a suggested commit message.

## Review Focus

1. **An employee number typed with spaces around it** (" 2020-0001 "), as people paste it. It still signs in. Pinned by Task 2, `test_an_employee_signs_in_with_their_employee_number`.
2. **An employee changing the ID in a print or view URL** to another employee's application. Refused (403), never shown. Pinned by Task 5, `test_an_employee_cannot_print_someone_elses_form`, and Task 7, `test_an_employee_never_sees_someone_elses_application`.
3. **"Create logins" run again over employees who already have accounts** (a double click, or two HR users). They are skipped; one account per employee. Pinned by Task 3, `test_employees_with_an_account_are_skipped`.
4. **The slips page reloaded or bookmarked.** The passwords are gone after the first view. Pinned by Task 3, `test_the_slips_show_once`.
5. **An employee deactivated or deleted while signed in.** The next request is refused. Pinned by Task 2, `test_an_employee_account_stops_with_the_employee`.

---

## Before you start

- Work in `C:\laragon\www\dtr-system` on `main`. `php artisan test --compact` passes (221 tests).
- HR's form is `storage/app/samples/HRM-07 - Leave Application.xlsx` (git-ignored). Its layout is summarized in Task 5; open the workbook in Excel to compare the printout.

## File map

| File | Responsibility | Task |
|---|---|---|
| `database/migrations/*_add_employee_link_to_users_table.php`, `app/Enums/UserRole.php`, `app/Models/User.php`, `database/factories/UserFactory.php` | Employee accounts and per-panel access | 1 |
| `app/Policies/*.php` (8 files) | Staff-only abilities; own-row rules for leave applications | 1 |
| `app/Filament/Resources/Users/*`, `app/Console/Commands/CreateAccount.php` | Staff accounts only; link to an employee | 1 |
| `app/Providers/Filament/EmployeePanelProvider.php`, `app/Filament/Employee/Pages/Auth/Login.php`, `app/Filament/Employee/Pages/Auth/ChangePassword.php`, `app/Http/Middleware/RequirePasswordChange.php`, `app/Filament/Employee/Pages/MyLeave.php` | The employee panel, sign-in by employee number, password change | 2 |
| `app/Filament/Resources/Employees/Actions/CreateLoginsBulkAction.php`, `.../Actions/ResetLoginAction.php`, `app/Http/Controllers/LoginSlipsController.php`, `resources/views/leave/login-slips.blade.php`, `bootstrap/app.php`, `routes/web.php` | Accounts for employees and their slips | 3 |
| `database/migrations/*_add_form_fields_to_leave_applications_and_employees.php`, `app/Models/Employee.php`, `app/Models/LeaveApplication.php`, `app/Services/Leave/LeaveApplications.php`, `app/Filament/Resources/LeaveApplications/Schemas/LeaveApplicationForm.php`, `app/Filament/Concerns/FilesLeave.php`, `app/Filament/Resources/Employees/Schemas/EmployeeForm.php` | Position, Division Head, credits at filing | 4 |
| `config/leave.php`, `public/images/trc-caraga-logo.png`, `app/Http/Controllers/PrintLeaveApplicationController.php`, `resources/views/leave/print.blade.php`, HR print actions | The HRM-07 print view | 5 |
| `app/Filament/Employee/Widgets/*`, `app/Filament/Employee/Pages/History.php`, `app/Filament/Employee/Pages/LeaveTypes.php`, views, `app/Services/Leave/LeaveLedger.php`, `app/Enums/LeaveType.php` | My leave, History, Leave types | 6 |
| `app/Filament/Employee/Resources/MyLeaveApplications/*` | The employee's applications | 7 |

---

### Task 1: Employee accounts, per-panel access, staff-only policies

**Files:**
- Create: `database/migrations/*_add_employee_link_to_users_table.php`
- Modify: `app/Enums/UserRole.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Models/Employee.php` (`user(): HasOne`)
- Modify: `app/Policies/EmployeePolicy.php`, `HolidayPolicy.php`, `OfficePolicy.php`, `RosterEntryPolicy.php`, `ScheduleAssignmentPolicy.php`, `SchedulePolicy.php`, `LeaveLedgerEntryPolicy.php`, `LeaveApplicationPolicy.php`
- Modify: `app/Filament/Resources/Users/UserResource.php`, `Schemas/UserForm.php`, `Tables/UsersTable.php`, `app/Console/Commands/CreateAccount.php`
- Test: `tests/Feature/Auth/EmployeeAccessTest.php`; extend `tests/Feature/Users/*` and `tests/Feature/Console/CreateAccountTest.php`

**Interfaces:**
- Produces:
  - Migration: `users.employee_id` `foreignId()->nullable()->unique()->constrained()->nullOnDelete()`; `users.must_change_password` boolean default false; `users.email` made nullable (`->nullable()->change()`).
  - `UserRole::Employee = 'employee'`, label "Employee".
  - `User`: fillable gains `employee_id`, `must_change_password`; cast `must_change_password` → boolean; `employee(): BelongsTo` (`withTrashed()`); `isStaff(): bool` (admin or hr); `canAccessPanel(Panel $panel): bool`: `admin` → `is_active && isStaff()`; `employee` → `is_active` and a linked employee that is active, not deleted, and not separated before today.
  - `UserFactory` states `employee(?Employee $employee = null)` (role employee, linked, email null) and `mustChangePassword()`.
  - `Employee::user(): HasOne<User>`.
  - Policies: every former `return true` becomes `return $user->isStaff()`. `LeaveApplicationPolicy`: `viewAny` and `create` → staff or linked employee; `view` → staff or own; `approve` → staff and pending; `cancel` → not cancelled, and staff, or own and pending; `print(User, LeaveApplication)` → same as `view`; the rest false.
  - `LeaveApplicationResource::canAccess()` → staff only (the HR list must never open for an employee).
  - Users screen: lists only admin and hr accounts; role options Admin and HR only; a searchable `employee_id` select ("Linked employee — opens their own leave") over employees, unique among users.
  - `dtr:user` refuses `--role=employee`.

- [ ] **Step 1: Generate the migration**

```bash
php artisan make:migration add_employee_link_to_users_table --no-interaction
php artisan make:test Auth/EmployeeAccessTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Auth/EmployeeAccessTest.php` (`RefreshDatabase`):

```php
public function test_staff_reach_the_hr_panel_and_employees_do_not(): void
{
    $this->actingAs(User::factory()->create())->get('/')->assertOk();
    $this->actingAs(User::factory()->employee()->create())->get('/')->assertForbidden();
}

public function test_only_linked_active_accounts_may_use_the_employee_panel(): void
{
    $panel = Panel::make()->id('employee');   // the panel itself arrives in Task 2
    $employeeAccount = User::factory()->employee()->create();

    $this->assertTrue($employeeAccount->canAccessPanel($panel));
    $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
    $this->assertTrue(User::factory()->create(['employee_id' => Employee::factory()->create()->id])->canAccessPanel($panel));

    $employeeAccount->employee->update(['date_separated' => today()->subDay()]);
    $this->assertFalse($employeeAccount->fresh()->canAccessPanel($panel));
}

public function test_employees_have_no_staff_abilities(): void
{
    $user = User::factory()->employee()->create();

    foreach ([Employee::class, Office::class, Schedule::class, Holiday::class, RosterEntry::class, ScheduleAssignment::class, LeaveLedgerEntry::class] as $model) {
        $this->assertFalse($user->can('viewAny', $model), $model);
        $this->assertFalse($user->can('create', $model), $model);
    }
}

public function test_an_employee_acts_only_on_their_own_applications(): void
{
    $user = User::factory()->employee()->create();
    $own = LeaveApplication::factory()->for($user->employee)->create();
    $theirs = LeaveApplication::factory()->create();

    $this->assertTrue($user->can('view', $own));
    $this->assertTrue($user->can('print', $own));
    $this->assertTrue($user->can('cancel', $own));
    $this->assertFalse($user->can('approve', $own));
    $this->assertFalse($user->can('view', $theirs));
    $this->assertFalse($user->can('cancel', $theirs));

    $own->update(['status' => LeaveApplicationStatus::Approved]);
    $this->assertFalse($user->can('cancel', $own->fresh()));
}
```

Add to the Users resource tests: `test_the_users_screen_lists_staff_only` (an employee account is not listed), `test_an_admin_links_an_account_to_an_employee` (edit sets `employee_id`), `test_a_role_of_employee_is_not_offered`. Add to `CreateAccountTest`: `test_the_command_refuses_the_employee_role`.

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Auth/ tests/Feature/Users/ tests/Feature/Console/CreateAccountTest.php`
Expected: FAIL (no `employee()` state, no `isStaff()`).

- [ ] **Step 4: Implement the migration, role, model, factory states, policies and the Users screen**

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact`
Expected: PASS, the existing suite still green (221 + Task 1's).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: employee accounts and staff-only HR panel`

---

### Task 2: The employee panel, sign-in by employee number, password change

**Files:**
- Create: `app/Providers/Filament/EmployeePanelProvider.php` (via `php artisan make:filament-panel employee`), `app/Filament/Employee/Pages/Auth/Login.php`, `app/Filament/Employee/Pages/Auth/ChangePassword.php`, `app/Filament/Employee/Pages/MyLeave.php`, `app/Http/Middleware/RequirePasswordChange.php`
- Test: `tests/Feature/Employee/EmployeeLoginTest.php`

**Interfaces:**
- Consumes: Task 1's `User::canAccessPanel()`, `must_change_password`, factory states.
- Produces:
  - Panel `employee`: path `employee`, `->login(Login::class)`, `->profile(ChangePassword::class, isSimple: false)`, brand "DTR System", primary Amber, `->sidebarWidth('16rem')`, `->maxContentWidth(Width::Full)`, the same `STYLES_AFTER` render hook as the HR panel (`filament.sidebar`), pages discovered in `app/Filament/Employee/Pages`, resources in `app/Filament/Employee/Resources`, widgets in `app/Filament/Employee/Widgets`, `->pages([MyLeave::class])`, the HR panel's middleware list, `authMiddleware([Authenticate::class, RequirePasswordChange::class])`.
  - `Login extends Filament\Auth\Pages\Login`: form `employee_number` (label "Employee number", required, autofocus), password, remember; `getCredentialsFromFormData()` returns `['employee_id' => <id of the employee whose number equals the trimmed input, or 0>, 'password' => ...]`; failures are keyed `data.employee_number`. Rate limiting and the timebox stay Filament's.
  - `ChangePassword extends Filament\Auth\Pages\EditProfile`: form = password (required), confirmation, current password; `mutateFormDataBeforeSave()` adds `must_change_password = false`.
  - `RequirePasswordChange`: on a GET request by a user with `must_change_password`, anything but the profile page redirects to it.
  - `MyLeave extends Filament\Pages\Dashboard`: title and navigation label "My leave"; its widgets come in Task 6 (empty until then).

- [ ] **Step 1: Generate the files**

```bash
php artisan make:filament-panel employee --no-interaction
php artisan make:class Filament/Employee/Pages/Auth/Login --no-interaction
php artisan make:class Filament/Employee/Pages/Auth/ChangePassword --no-interaction
php artisan make:class Filament/Employee/Pages/MyLeave --no-interaction
php artisan make:middleware RequirePasswordChange --no-interaction
php artisan make:test Employee/EmployeeLoginTest --phpunit --no-interaction
```

Check `bootstrap/providers.php` lists `EmployeePanelProvider`.

- [ ] **Step 2: Write the failing tests**

`setUp`: `Filament::setCurrentPanel(Filament::getPanel('employee'))`; an employee `2020-0001` with an account, password `'secret-pass-1'`.

```php
public function test_an_employee_signs_in_with_their_employee_number(): void
{
    Livewire::test(Login::class)
        ->fillForm(['employee_number' => ' 2020-0001 ', 'password' => 'secret-pass-1'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($this->user);
}

public function test_a_wrong_number_or_password_is_refused_on_the_number_field(): void
{
    Livewire::test(Login::class)->fillForm(['employee_number' => '2020-0001', 'password' => 'wrong'])->call('authenticate')->assertHasFormErrors(['employee_number']);
    Livewire::test(Login::class)->fillForm(['employee_number' => '9999-9999', 'password' => 'secret-pass-1'])->call('authenticate')->assertHasFormErrors(['employee_number']);
    $this->assertGuest();
}

public function test_a_deactivated_employee_cannot_sign_in(): void
{
    $this->user->employee->update(['is_active' => false]);

    Livewire::test(Login::class)->fillForm(['employee_number' => '2020-0001', 'password' => 'secret-pass-1'])->call('authenticate')->assertHasFormErrors(['employee_number']);
    $this->assertGuest();
}

public function test_a_temporary_password_must_be_changed_first(): void
{
    $this->user->update(['must_change_password' => true]);

    $this->actingAs($this->user)->get('/employee')->assertRedirect(Filament::getPanel('employee')->getProfileUrl());
    $this->get(Filament::getPanel('employee')->getProfileUrl())->assertOk();
}

public function test_only_linked_accounts_reach_the_employee_panel(): void
{
    $this->actingAs($this->user)->get('/employee')->assertOk();
    $this->actingAs(User::factory()->create())->get('/employee')->assertForbidden();
}

public function test_an_employee_account_stops_with_the_employee(): void
{
    $this->actingAs($this->user)->get('/employee')->assertOk();

    $this->user->employee->update(['is_active' => false]);
    $this->get('/employee')->assertForbidden();

    $this->user->employee->update(['is_active' => true]);
    $this->user->employee->delete();
    $this->get('/employee')->assertForbidden();
}

public function test_changing_the_password_clears_the_flag(): void
{
    $this->user->update(['must_change_password' => true]);
    $this->actingAs($this->user);

    Livewire::test(ChangePassword::class)
        ->fillForm(['password' => 'a-new-pass-123', 'passwordConfirmation' => 'a-new-pass-123', 'currentPassword' => 'secret-pass-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertFalse($this->user->fresh()->must_change_password);
    $this->assertTrue(Hash::check('a-new-pass-123', $this->user->fresh()->password));
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Employee/EmployeeLoginTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the panel, the login, the password page, the middleware and `MyLeave`**

- [ ] **Step 5: Run the tests, including Task 1's panel group**

Run: `php artisan test --compact tests/Feature/Employee/ tests/Feature/Auth/`
Expected: PASS.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: employee panel with sign-in by employee number`

---

### Task 3: Logins for employees, and their slips

**Files:**
- Create: `app/Filament/Resources/Employees/Actions/CreateLoginsBulkAction.php`, `app/Filament/Resources/Employees/Actions/ResetLoginAction.php`, `app/Http/Controllers/LoginSlipsController.php`, `resources/views/leave/login-slips.blade.php`
- Modify: `app/Filament/Resources/Employees/Tables/EmployeesTable.php` (toolbar), `app/Filament/Resources/Employees/Pages/EditEmployee.php` (header), `routes/web.php`, `bootstrap/app.php` (`$middleware->redirectGuestsTo('/login')`), `app/Policies/EmployeePolicy.php` (`manageLogin`)
- Test: `tests/Feature/Employees/EmployeeLoginsTest.php`

**Interfaces:**
- Consumes: Task 1's `User` role and link.
- Produces:
  - `EmployeePolicy::manageLogin(User $user, Employee $employee): bool` → staff.
  - `CreateLoginsBulkAction` (name `createLogins`, label "Create logins"): for each selected employee who earns credits, is active and has no account, creates a user (role employee, `name` = `full_name`, `email` null, a temporary password, `must_change_password` true); skips the rest; flashes the slips; redirects to the slips page. Notification: "N logins created, M skipped (no credits, inactive, or already has one)".
  - `ResetLoginAction` (name `resetLogin`, label "Reset login password"), on the employee edit page: visible when the employee's account has role employee; gives a new temporary password, sets the flag, flashes one slip, redirects.
  - Slips: `session()->flash('login_slips', encrypt($slips))`, `$slips` a list of `['name' => ..., 'employee_number' => ..., 'password' => ...]`; route `GET /employee-logins/slips` named `employee-logins.slips`, `auth`, staff only; the view reads and decrypts the flash; with none it says "No new logins to show. Passwords are shown once."; each slip shows the name, employee number, temporary password, the employee panel URL, and "Change this password when you first sign in."; A4, cut lines, a Print button hidden in print.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:class Filament/Resources/Employees/Actions/CreateLoginsBulkAction --no-interaction
php artisan make:class Filament/Resources/Employees/Actions/ResetLoginAction --no-interaction
php artisan make:controller LoginSlipsController --invokable --no-interaction
php artisan make:test Employees/EmployeeLoginsTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

```php
public function test_hr_creates_logins_for_the_selected_employees(): void
{
    $employees = Employee::factory()->count(2)->create();

    Livewire::test(ListEmployees::class)
        ->selectTableRecords($employees->modelKeys())
        ->callAction(TestAction::make('createLogins')->table()->bulk())
        ->assertRedirect(route('employee-logins.slips'));

    foreach ($employees as $employee) {
        $user = $employee->fresh()->user;
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->email);
    }
}

public function test_employees_with_an_account_are_skipped(): void
{
    $withAccount = User::factory()->employee()->create()->employee;
    $jobOrder = Employee::factory()->create(['employment_status' => EmploymentStatus::JobOrder]);

    Livewire::test(ListEmployees::class)
        ->selectTableRecords([$withAccount->id, $jobOrder->id])
        ->callAction(TestAction::make('createLogins')->table()->bulk());

    $this->assertSame(1, User::where('employee_id', $withAccount->id)->count());
    $this->assertNull($jobOrder->fresh()->user);
}

public function test_the_slips_show_once(): void
{
    $employee = Employee::factory()->create(['employee_number' => '2020-0001']);
    Livewire::test(ListEmployees::class)
        ->selectTableRecords([$employee->id])
        ->callAction(TestAction::make('createLogins')->table()->bulk());

    $first = $this->get(route('employee-logins.slips'))->assertOk()->assertSee('2020-0001');
    preg_match('/data-password="([^"]+)"/', $first->getContent(), $match);
    $this->assertTrue(Hash::check($match[1], $employee->fresh()->user->password));

    $this->get(route('employee-logins.slips'))->assertOk()->assertDontSee('2020-0001')->assertSee('Passwords are shown once');
}

public function test_hr_resets_a_forgotten_password(): void
{
    $user = User::factory()->employee()->create(['must_change_password' => false]);
    $old = $user->password;

    Livewire::test(EditEmployee::class, ['record' => $user->employee->getRouteKey()])
        ->callAction('resetLogin')
        ->assertRedirect(route('employee-logins.slips'));

    $this->assertNotSame($old, $user->fresh()->password);
    $this->assertTrue($user->fresh()->must_change_password);
}

public function test_the_slips_page_is_for_staff(): void
{
    $this->actingAs(User::factory()->employee()->create())->get(route('employee-logins.slips'))->assertForbidden();
    auth()->logout();
    $this->get(route('employee-logins.slips'))->assertRedirect('/login');
}
```

`setUp` acts as an hr user. The slips view puts each password in a `data-password` attribute as well as the text, for the test.

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Employees/EmployeeLoginsTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the policy ability, the two actions, the route, the controller, the view and the guest redirect**

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact tests/Feature/Employees/`
Expected: PASS.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: logins for employees with one-time password slips`

---

### Task 4: Position, Division Head, and the credits at filing

**Files:**
- Create: `database/migrations/*_add_form_fields_to_leave_applications_and_employees.php`, `app/Filament/Concerns/FilesLeave.php`
- Modify: `app/Models/Employee.php` (fillable `position`), `app/Filament/Resources/Employees/Schemas/EmployeeForm.php`, `app/Models/LeaveApplication.php`, `app/Services/Leave/LeaveApplications.php`, `app/Filament/Resources/LeaveApplications/Schemas/LeaveApplicationForm.php`, `app/Filament/Resources/LeaveApplications/Pages/CreateLeaveApplication.php`
- Test: `tests/Feature/Leave/FormFieldsTest.php`

**Interfaces:**
- Consumes: L2a's `LeaveApplications::file()`, `LeaveLedger::balances()`.
- Produces:
  - `employees.position` string(100) nullable; a "Position" text field in the employee form's Employee section.
  - `leave_applications.division_head` string(255) nullable; `leave_applications.credits_at_filing` json nullable (cast `array`).
  - `file()` accepts `division_head` (trimmed, at most 255, blank → null) and stores `credits_at_filing` = `['VL' => …, 'SL' => …]` from the balances read just before charging.
  - `LeaveApplication::certification(): ?array` → null without `credits_at_filing`; else `['VL' => ['earned' => s, 'less' => s, 'balance' => s], 'SL' => [...]]`, where `less` is the negated sum of the application's `Application` rows of that type and `balance` = `earned − less`.
  - `LeaveApplicationForm::fields(Closure $employee): array` — every field but the employee select, plus `division_head` ("Division Head (signs 7.B)", max 255); `$employee` is `fn (Get $get): ?Employee`. `configure()` = the employee select + `fields(fn (Get $get) => Employee::find($get('employee_id')))`.
  - `FilesLeave` trait with `fileLeaveFor(Employee $employee, array $data): LeaveApplication`: calls the service and re-throws its `ValidationException` with keys prefixed `data.`; `CreateLeaveApplication` uses it.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:migration add_form_fields_to_leave_applications_and_employees --no-interaction
php artisan make:test Leave/FormFieldsTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`setUp` as in `LeaveApplicationsTest` (start 2026-11-01, travel 2026-11-02, REG, VL 5.000 and SL 2.000 openings).

```php
public function test_filing_keeps_the_credits_it_was_charged_against(): void
{
    $application = $this->fileLeave(['leave_type' => 'SL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-05']);

    $this->assertSame([
        'VL' => ['earned' => '5.000', 'less' => '1.000', 'balance' => '4.000'],
        'SL' => ['earned' => '2.000', 'less' => '2.000', 'balance' => '0.000'],
    ], $application->certification());
}

public function test_the_division_head_is_typed_on_the_application(): void
{
    $this->assertSame('Dr. Juan Dela Cruz', $this->fileLeave(['division_head' => '  Dr. Juan Dela Cruz '])->division_head);
    $this->assertNull($this->fileLeave(['date_from' => '2026-11-09', 'date_to' => '2026-11-09', 'division_head' => ' '])->division_head);
}

public function test_an_application_without_credits_at_filing_certifies_nothing(): void
{
    $this->assertNull(LeaveApplication::factory()->create(['credits_at_filing' => null])->certification());
}

public function test_hr_types_an_employees_position(): void
{
    Livewire::test(EditEmployee::class, ['record' => $this->employee->getRouteKey()])
        ->fillForm(['position' => 'Nurse II'])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertSame('Nurse II', $this->employee->fresh()->position);
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/FormFieldsTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the migration, the model changes, `certification()`, the service changes, the form split and the trait**

- [ ] **Step 5: Run the leave and employee tests**

Run: `php artisan test --compact tests/Feature/Leave/ tests/Feature/Employees/`
Expected: PASS.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: position, division head and credits at filing`

---

### Task 5: The printed HRM-07 form

**Files:**
- Create: `public/images/trc-caraga-logo.png`, `app/Http/Controllers/PrintLeaveApplicationController.php`, `resources/views/leave/print.blade.php`
- Modify: `config/leave.php`, `routes/web.php`, `app/Filament/Resources/LeaveApplications/Tables/LeaveApplicationsTable.php`, `app/Filament/Resources/LeaveApplications/Pages/ViewLeaveApplication.php`
- Test: `tests/Feature/Leave/PrintLeaveApplicationTest.php`

**Interfaces:**
- Consumes: Task 1's `print` ability; Task 4's `position`, `division_head`, `certification()`; L2a's `LeaveApplication`, `LeaveType`, `LeaveDetail`.
- Produces:
  - `config('leave.form')`: `doc_code` "HRM-07", `effectivity` "June 3, 2026", `revision` "1", `certifying_officer` `['name' => 'Mary Jane Lao Guico', 'title' => 'Human Resource Development Officer']`, `approving_officer` `['name' => 'Edhel S. Miro, MD, DPCAM, MM-PA', 'title' => 'Chief of Hospital III']`, `recommending_title` "Division Head". Names print in capitals (CSS), as on HR's form.
  - Route `GET /leave-applications/{leaveApplication}/print` named `leave-applications.print`, `auth`; the controller authorizes `print` and returns the view.
  - Table action and view-page header action `print` ("Print form", printer icon, opens the route in a new tab), in the HR panel.

The layout, from HR's workbook (A4 portrait, page 1 only): the logo and "Civil Service Form No. 6 / Revised 2020" at the top left; "HUMAN RESOURCE MANAGEMENT SECTION" and "LEAVE APPLICATION" centred; the box Doc. Code / Effectivity / Revision No. / Page No. 1 of 1 at the right. Then 1. Office/Department, 2. Name (Last, First, Middle), 3. Date of filing, 4. Position, 5. Salary (blank). "6. DETAILS OF APPLICATION": 6.A the 13 types with a box each, ticked for the application's type, and "Others: ____" carrying "Wellness Leave" when that is the type; 6.B the details with boxes and the specified text; 6.C the number of working days and the inclusive dates (`dates()`); 6.D Not requested / Requested; the applicant's signature line. "7. DETAILS OF ACTION ON APPLICATION": 7.A "As of {filing date}" and the VL/SL table from `certification()` (blank when null) over the certifying officer; 7.B For approval / For disapproval due to over the typed Division Head and the title; 7.C days with pay / without pay; 7.D Disapproved due to; the approving officer at the foot. A Print button, hidden in print. `@page { size: A4 portrait; margin: 0.5in 0.35in; }`.

- [ ] **Step 1: Copy the logo out of HR's workbook and generate the files**

```bash
php -r '$z = new ZipArchive; $z->open("storage/app/samples/HRM-07 - Leave Application.xlsx"); @mkdir("public/images"); file_put_contents("public/images/trc-caraga-logo.png", $z->getFromName("xl/media/image1.png"));'
php artisan make:controller PrintLeaveApplicationController --invokable --no-interaction
php artisan make:test Leave/PrintLeaveApplicationTest --phpunit --no-interaction
```

- [ ] **Step 2: Write the failing tests**

`setUp` as in Task 4; the employee has office "Nursing Service", position "Nurse II", and an SL application Nov 3–5 with `division_head` "Dr. Ana Reyes".

```php
public function test_hr_prints_a_filled_form(): void
{
    $this->get(route('leave-applications.print', $this->application))
        ->assertOk()
        ->assertSeeInOrder(['HRM-07', 'Nursing Service', $this->employee->last_name, 'Nurse II', '3.000', 'Nov 3–5, 2026'], escape: false)
        ->assertSee('data-checked="SL"', escape: false)
        ->assertSeeInOrder(['2.000', '2.000', '0.000'])
        ->assertSee('Mary Jane Lao Guico')
        ->assertSee('Dr. Ana Reyes')
        ->assertSee('Edhel S. Miro, MD, DPCAM, MM-PA');
}

public function test_wellness_leave_prints_under_others(): void
{
    $wellness = LeaveApplication::factory()->for($this->employee)->create(['leave_type' => LeaveType::Wellness]);

    $this->get(route('leave-applications.print', $wellness))->assertSee('data-checked="others"', escape: false)->assertSee('Wellness Leave');
}

public function test_the_employee_prints_their_own_form(): void
{
    $this->actingAs(User::factory()->employee($this->employee)->create())
        ->get(route('leave-applications.print', $this->application))
        ->assertOk();
}

public function test_an_employee_cannot_print_someone_elses_form(): void
{
    $this->actingAs(User::factory()->employee()->create())
        ->get(route('leave-applications.print', $this->application))
        ->assertForbidden();
}

public function test_the_hr_list_offers_the_print_action(): void
{
    Livewire::test(ListLeaveApplications::class)
        ->assertActionVisible(TestAction::make('print')->table($this->application));
}
```

The view marks the ticked box with `data-checked="{code}"` (or `"others"`) for the tests.

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Leave/PrintLeaveApplicationTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the config, the route, the controller, the view and the two HR actions**

- [ ] **Step 5: Run the tests, then compare a printout with HR's workbook**

Run: `php artisan test --compact tests/Feature/Leave/`
Expected: PASS. Open the print view of a test application in a browser, print to PDF on A4, and check it against the workbook side by side.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: print the HRM-07 leave form`

---

### Task 6: My leave, History, Leave types

**Files:**
- Create: `app/Filament/Employee/Widgets/MyLeaveBalances.php`, `app/Filament/Employee/Widgets/MyPendingApplications.php`, `app/Filament/Employee/Pages/History.php`, `app/Filament/Employee/Pages/LeaveTypes.php`, `resources/views/filament/employee/pages/history.blade.php`, `resources/views/filament/employee/pages/leave-types.blade.php`
- Modify: `app/Filament/Employee/Pages/MyLeave.php` (`getWidgets()`), `app/Services/Leave/LeaveLedger.php`, `app/Enums/LeaveType.php`
- Test: `tests/Feature/Employee/MyLeaveTest.php`

**Interfaces:**
- Consumes: Task 2's panel; L1's ledger.
- Produces:
  - `LeaveLedger::history(Employee $employee, LeaveType $type): list<array{posted_on: CarbonImmutable, entry: string, days: string, balance: string, remarks: ?string}>`: the type's rows, newest first, each with the running balance after it (worked oldest first with bcmath, then reversed).
  - `LeaveType::description(): string`, the copy below.
  - `MyLeaveBalances` (stats overview): VL, SL, SPL, Wellness for the signed-in employee. `MyPendingApplications` (table widget): the signed-in employee's pending applications (type, dates, days, without pay) with a print link.
  - `History` page (navigation sort 2): a tab per tracked type (`public string $type = 'VL'`, ignored unless `LeaveType::tryFrom($type)?->isTracked()`), a table of date, entry, days (signed), balance, remarks.
  - `LeaveTypes` page (navigation sort 3): every type with its label, code, description, and the balance where tracked.

Descriptions (HR reads them before go-live):

| Code | Description |
|---|---|
| VL | Time off for personal reasons, rest or travel. File it ahead of time. Taken from your VL credits. |
| FL | The five VL days a year everyone with 10 or more VL credits must take. Days not taken by December 31 are deducted from VL. |
| SL | For your own illness, injury or check-up. File it when you return; more than five days needs a medical certificate. Taken from SL, then VL. |
| ML | 105 days with pay for childbirth or miscarriage (R.A. No. 11210). Ask HR about the requirements. |
| PL | 7 days for a married male employee for each of his wife's first four deliveries (R.A. No. 8187). |
| SPL | 3 days a year for personal milestones and family obligations, such as a birthday, an enrollment or a family member's hospitalization. Unused days do not carry over. |
| SOLO | 7 days a year for a solo parent with a valid Solo Parent ID (R.A. No. 8972). |
| STUDY | Up to six months to finish a master's degree or review for the bar or a board examination, under an agreement with the hospital. |
| VAWC | Up to 10 days for an employee who is a victim of violence under R.A. No. 9262, to attend to medical and legal concerns. |
| RP | Up to six months for an employee injured in the performance of duty, to recover. |
| SLBW | Up to two months for a woman employee after surgery for a gynecological disorder (R.A. No. 9710). |
| SEL | Up to 5 days a year for an employee directly affected by a calamity in a declared area. |
| ADOPT | For an employee who legally adopts a child (R.A. No. 8552). Ask HR about the days and requirements. |
| WL | 5 days a year for rest and wellness. Unused days do not carry over. |

- [ ] **Step 1: Generate the files**

```bash
php artisan make:filament-widget MyLeaveBalances --panel=employee --no-interaction
php artisan make:filament-widget MyPendingApplications --panel=employee --no-interaction
php artisan make:filament-page History --panel=employee --no-interaction
php artisan make:filament-page LeaveTypes --panel=employee --no-interaction
php artisan make:test Employee/MyLeaveTest --phpunit --no-interaction
```

Make `MyLeaveBalances` extend `Filament\Widgets\StatsOverviewWidget` and `MyPendingApplications` extend `Filament\Widgets\TableWidget`, whatever the generator wrote.

- [ ] **Step 2: Write the failing tests**

`setUp`: employee panel current; an employee account with openings VL 5.000, SL 2.000 dated 2026-10-31 and a VL adjustment −1.500 dated 2026-11-10; acting as that account; a second employee with VL 9.000.

```php
public function test_the_history_runs_a_balance_newest_first(): void
{
    $rows = app(LeaveLedger::class)->history($this->employee, LeaveType::Vacation);

    $this->assertSame(['3.500', '5.000'], array_column($rows, 'balance'));
    $this->assertSame(['-1.500', '5.000'], array_column($rows, 'days'));
}

public function test_my_leave_shows_only_my_balances(): void
{
    Livewire::test(MyLeaveBalances::class)->assertSee('3.500')->assertSee('2.000')->assertDontSee('9.000');
}

public function test_my_pending_applications_are_mine_only(): void
{
    $mine = LeaveApplication::factory()->for($this->employee)->create();
    $theirs = LeaveApplication::factory()->create();

    Livewire::test(MyPendingApplications::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
}

public function test_the_history_page_switches_type(): void
{
    Livewire::test(History::class)->assertSee('3.500')->set('type', 'SL')->assertSee('2.000')->set('type', 'FL')->assertSee('3.500');
}

public function test_every_leave_type_is_explained(): void
{
    foreach (LeaveType::cases() as $type) {
        $this->assertNotSame('', $type->description());
    }

    $this->get(LeaveTypes::getUrl(panel: 'employee'))->assertOk()->assertSee('Wellness Leave')->assertSee('3.500');
}
```

(`set('type', 'FL')` falls back to VL: FL is not tracked.)

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Employee/MyLeaveTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement `history()`, the descriptions, the widgets and the two pages**

- [ ] **Step 5: Run the employee tests**

Run: `php artisan test --compact tests/Feature/Employee/`
Expected: PASS.

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: my leave, history and leave types for employees`

---

### Task 7: The employee's applications

**Files:**
- Create: `app/Filament/Employee/Resources/MyLeaveApplications/MyLeaveApplicationResource.php`, `Pages/ListMyLeaveApplications.php`, `Pages/CreateMyLeaveApplication.php`, `Pages/ViewMyLeaveApplication.php`, `Tables/MyLeaveApplicationsTable.php`
- Test: `tests/Feature/Employee/MyLeaveApplicationsTest.php`

**Interfaces:**
- Consumes: Task 4's `LeaveApplicationForm::fields()` and `FilesLeave`; Task 5's print route; L2a's `CancelLeaveApplicationAction` and `LeaveApplicationInfolist`.
- Produces: `MyLeaveApplicationResource` (model `LeaveApplication`, navigation "Applications", sort 1, slug `applications`): `getEloquentQuery()` = `parent::getEloquentQuery()->where('employee_id', auth()->user()->employee_id)`; form = `LeaveApplicationForm::fields(fn (): ?Employee => auth()->user()->employee)`; create page files for `auth()->user()->employee` through `FilesLeave` (title "File leave", redirect to view); table: filed, type, dates, days, without pay, status; record actions view, print (new tab), `CancelLeaveApplicationAction` (its policy now limits an employee to their own pending ones); no approve, edit or delete.

- [ ] **Step 1: Generate the files**

```bash
php artisan make:filament-resource LeaveApplication --view --panel=employee --no-interaction
php artisan make:test Employee/MyLeaveApplicationsTest --phpunit --no-interaction
```

The generator names it after the model; rename the folder to `app/Filament/Employee/Resources/MyLeaveApplications` and the classes to `MyLeaveApplicationResource`, `ListMyLeaveApplications`, `CreateMyLeaveApplication`, `ViewMyLeaveApplication`, `MyLeaveApplicationsTable`, so they never read as HR's. Delete the generated edit page and its route.

- [ ] **Step 2: Write the failing tests**

`setUp` as `LeaveApplicationsTest` plus an employee account linked to that employee, acting as it, employee panel current.

```php
public function test_an_employee_files_their_own_leave(): void
{
    Livewire::test(CreateMyLeaveApplication::class)
        ->fillForm(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-04', 'division_head' => 'Dr. Ana Reyes'])
        ->call('create')
        ->assertHasNoFormErrors();

    $application = LeaveApplication::sole();
    $this->assertTrue($application->employee->is($this->employee));
    $this->assertSame($this->user->id, $application->filed_by_user_id);
}

public function test_the_form_previews_the_employees_own_days(): void
{
    Livewire::test(CreateMyLeaveApplication::class)
        ->fillForm(['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-04'])
        ->assertSee('2.000 days: 2.000 with pay (VL 2.000), 0.000 without pay');
}

public function test_an_employee_never_sees_someone_elses_application(): void
{
    $theirs = LeaveApplication::factory()->create();

    Livewire::test(ListMyLeaveApplications::class)->assertCanNotSeeTableRecords([$theirs]);
    $this->get(MyLeaveApplicationResource::getUrl('view', ['record' => $theirs], panel: 'employee'))->assertNotFound();
}

public function test_an_employee_cancels_their_pending_leave(): void
{
    $application = app(LeaveApplications::class)->file($this->employee, ['leave_type' => 'VL', 'date_from' => '2026-11-03', 'date_to' => '2026-11-03', 'coverage' => 'whole_day']);

    Livewire::test(ListMyLeaveApplications::class)
        ->callAction(TestAction::make('cancel')->table($application), ['cancel_reason' => 'Plans changed'])
        ->assertHasNoFormErrors();

    $this->assertSame(LeaveApplicationStatus::Cancelled, $application->fresh()->status);
}

public function test_an_approved_application_cannot_be_cancelled_or_approved_by_the_employee(): void
{
    $application = LeaveApplication::factory()->for($this->employee)->create(['status' => LeaveApplicationStatus::Approved]);

    Livewire::test(ListMyLeaveApplications::class)
        ->assertActionHidden(TestAction::make('cancel')->table($application))
        ->assertActionDoesNotExist(TestAction::make('approve')->table($application));
}
```

- [ ] **Step 3: Run them to verify they fail**

Run: `php artisan test --compact tests/Feature/Employee/MyLeaveApplicationsTest.php`
Expected: FAIL.

- [ ] **Step 4: Implement the resource, its pages and table**

- [ ] **Step 5: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS (221 + about 45).

- [ ] **Step 6: Hand off**

Pint. Suggested message: `feat: employees file and cancel their own leave`

---

## Finish

- [ ] `php artisan test --compact`: all pass. `vendor/bin/pint --dirty --format agent`: clean.
- [ ] `php artisan migrate` on Laragon's MySQL.
- [ ] Smoke test: create a login for one test employee, read the slip, sign in at `/employee` with the employee number, change the password, file a leave, print the form; as HR, approve it.
- [ ] HR reads the leave type descriptions (Task 6) before go-live.
