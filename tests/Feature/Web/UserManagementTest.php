<?php

namespace Tests\Feature\Web;

use App\Livewire\Users\Form;
use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_teacher_who_receives_a_password_link(): void
    {
        Notification::fake();

        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('name', 'New Teacher')
            ->set('email', 'new.teacher@example.com')
            ->set('role', 'teacher')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'new.teacher@example.com')->firstOrFail();
        $this->assertTrue($user->isTeacher());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_user_input_is_validated(): void
    {
        $existing = $this->teacher();

        Livewire::actingAs($this->admin())
            ->test(Form::class)
            ->set('name', '')
            ->set('email', $existing->email)
            ->set('role', 'superuser')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'role']);
    }

    public function test_admin_can_change_roles_but_not_demote_themselves(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();

        Livewire::actingAs($admin)->test(Form::class, ['user' => $teacher])
            ->set('role', 'admin')->call('save')->assertHasNoErrors();
        $this->assertTrue($teacher->fresh()->isAdmin());

        Livewire::actingAs($admin)->test(Form::class, ['user' => $admin])
            ->set('role', 'teacher')->call('save')->assertHasErrors('role');
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_deactivation_revokes_api_tokens_and_cannot_target_self(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $teacher->createToken('phone');

        Livewire::actingAs($admin)->test(Index::class)->call('toggleActive', $teacher->id);
        $this->assertFalse($teacher->fresh()->is_active);
        $this->assertSame(0, $teacher->tokens()->count());

        Livewire::actingAs($admin)->test(Index::class)->call('toggleActive', $admin->id)->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_non_admins_cannot_manage_users(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get(route('users.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('users.create'))->assertForbidden();
        $this->actingAs($teacher)->get(route('users.edit', $this->admin()))->assertForbidden();
    }
}
