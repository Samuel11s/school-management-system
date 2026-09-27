<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_is_displayed(): void
    {
        $this->actingAs($this->teacher())->get('/account')->assertOk()->assertSee('Change password');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->teacher();

        $this->actingAs($user)
            ->put('/user/profile-information', ['name' => 'New Name', 'email' => 'NEW@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_student_email_stays_in_sync_with_account_email(): void
    {
        $student = Student::factory()->withAccount()->create();

        $this->actingAs($student->user)
            ->put('/user/profile-information', ['name' => $student->user->name, 'email' => 'me@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('me@example.com', $student->fresh()->email);
    }

    public function test_password_can_be_updated(): void
    {
        $user = $this->teacher();

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'BetterSecret99',
            'password_confirmation' => 'BetterSecret99',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('BetterSecret99', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = $this->teacher();

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'wrong',
            'password' => 'BetterSecret99',
            'password_confirmation' => 'BetterSecret99',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }
}
