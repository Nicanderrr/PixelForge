<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_login_logout_and_protected_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'a-secure-passphrase', 'password_confirmation' => 'a-secure-passphrase'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'ada@example.test', 'password' => 'a-secure-passphrase'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_registration_validates_unique_email_and_password(): void
    {
        $user = User::factory()->create();
        $this->post('/register', ['name' => 'Ada', 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'mismatch'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_login_is_throttled_and_never_accepts_bad_credentials(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'bad'])->assertSessionHasErrors('email');
        }
        $this->assertGuest();
    }

    public function test_password_changes_require_current_password(): void
    {
        $this->actingAs(User::factory()->create())->patch('/settings', ['name' => 'Ada', 'email' => 'new@example.test', 'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password'])->assertSessionHasErrors('current_password');
    }
}
