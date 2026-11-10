<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Stage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $username, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'مستخدم '.$username,
            'username' => $username,
            'password' => '123456',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login_from_protected_pages(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/teacher')->assertRedirect('/login');
    }

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $admin = $this->makeUser('admin', 'admin');

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => '123456',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->assertSame('admin', session('eyal_user.role'));
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_staff_is_redirected_to_teacher_portal(): void
    {
        $this->makeUser('staff', 'teacher');

        $this->post('/login', [
            'username' => 'teacher',
            'password' => '123456',
        ])->assertRedirect('/teacher');
    }

    public function test_student_is_redirected_to_student_page(): void
    {
        $this->makeUser('student', '101');

        $this->post('/login', [
            'username' => '101',
            'password' => '123456',
        ])->assertRedirect('/student');
    }

    public function test_parent_is_redirected_to_parent_page(): void
    {
        $this->makeUser('parent', 'parent');

        $this->post('/login', [
            'username' => 'parent',
            'password' => '123456',
        ])->assertRedirect('/parent');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser('student', 'inactive', ['is_active' => false]);

        $this->post('/login', [
            'username' => 'inactive',
            'password' => '123456',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_wrong_role_gets_forbidden(): void
    {
        $student = $this->makeUser('student', '101');

        $this->actingAs($student)->get('/admin')->assertForbidden();
        $this->actingAs($student)->get('/teacher')->assertForbidden();
    }

    public function test_teacher_sees_only_their_courses(): void
    {
        $teacher = $this->makeUser('staff', 'teacher');
        $otherTeacher = $this->makeUser('staff', 'other');

        $stage = Stage::create(['name' => 'سادس إعدادي', 'sort_order' => 1]);

        $ownCourse = Course::create([
            'stage_id' => $stage->id,
            'teacher_id' => $teacher->id,
            'name' => 'دورة الرياضيات',
            'subject' => 'رياضيات',
        ]);

        Course::create([
            'stage_id' => $stage->id,
            'teacher_id' => $otherTeacher->id,
            'name' => 'دورة الفيزياء',
            'subject' => 'فيزياء',
        ]);

        Student::create([
            'stage_id' => $stage->id,
            'student_no' => '101',
            'name' => 'أحمد محمد علي',
        ])->courses()->attach($ownCourse->id, ['fee' => 150000, 'status' => 'active']);

        $this->actingAs($teacher)
            ->get('/teacher')
            ->assertOk()
            ->assertSee('دورة الرياضيات')
            ->assertDontSee('دورة الفيزياء');
    }

    public function test_logout_clears_authentication(): void
    {
        $admin = $this->makeUser('admin', 'admin');

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(session('eyal_user'));
    }
}
