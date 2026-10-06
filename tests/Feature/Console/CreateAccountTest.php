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
