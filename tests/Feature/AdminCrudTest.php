<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Payment;
use App\Models\RegistrationRequest;
use App\Models\Setting;
use App\Models\Stage;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use Database\Seeders\EyalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EyalSeeder::class);

        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $pages = [
            '/admin',
            '/admin/reports',
            '/admin/students',
            '/admin/registrations',
            '/admin/stages',
            '/admin/courses',
            '/admin/staff',
            '/admin/attendance',
            '/admin/grades',
            '/admin/homework',
            '/admin/payments',
            '/admin/announcements',
            '/admin/offers',
            '/admin/schedule',
            '/admin/notifications',
            '/admin/settings',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
    }

    public function test_admin_can_create_update_and_delete_a_stage(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/stages', [
                'name' => 'مرحلة تجريبية',
                'description' => 'وصف تجريبي',
                'order' => 9,
                'visible' => 1,
            ])
            ->assertRedirect();

        $stage = Stage::where('name', 'مرحلة تجريبية')->firstOrFail();
        $this->assertTrue($stage->is_visible);

        $this->actingAs($this->admin)
            ->put('/admin/stages/'.$stage->id, [
                'name' => 'مرحلة معدلة',
                'order' => 8,
            ])
            ->assertRedirect();

        $this->assertSame('مرحلة معدلة', $stage->fresh()->name);
        $this->assertFalse($stage->fresh()->is_visible);

        $this->actingAs($this->admin)
            ->post('/admin/stages/'.$stage->id.'/toggle')
            ->assertOk()
            ->assertJson(['active' => true]);

        $this->actingAs($this->admin)
            ->delete('/admin/stages/'.$stage->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('stages', ['id' => $stage->id]);
    }

    public function test_admin_can_create_a_student_with_account_and_charge(): void
    {
        $stage = Stage::firstOrFail();
        $parent = User::where('username', 'parent')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/students', [
                'student_name' => 'طالب اختبار',
                'parent_name' => 'ولي أمر اختبار',
                'parent_id' => $parent->id,
                'phone' => '07700000000',
                'stage' => $stage->id,
                'subjects' => 'رياضيات',
                'study_type' => 'دروس تقوية',
                'shift' => 'مسائي',
                'fee' => 120000,
                'has_account' => 1,
                'password' => 'secret123',
            ])
            ->assertRedirect();

        $student = Student::where('name', 'طالب اختبار')->firstOrFail();

        $this->assertNotNull($student->user_id);
        $this->assertSame('student', $student->user->role);
        $this->assertSame(120000, (int) $student->charges()->sum('amount'));
        $this->assertTrue($student->courses()->where('subject', 'رياضيات')->exists());
        $this->assertTrue($student->parents()->where('users.id', $parent->id)->exists());

        $this->actingAs($this->admin)
            ->put('/admin/students/'.$student->id, [
                'student_name' => 'طالب اختبار معدل',
                'stage' => $stage->id,
                'fee' => 90000,
            ])
            ->assertRedirect();

        $this->assertSame('طالب اختبار معدل', $student->fresh()->name);
        $this->assertSame(90000, (int) $student->fresh()->charges()->sum('amount'));
    }

    public function test_admin_can_accept_a_registration_and_create_student(): void
    {
        $registration = RegistrationRequest::where('status', 'new')->firstOrFail();
        $before = Student::count();

        $this->actingAs($this->admin)
            ->patch('/admin/registrations/'.$registration->id.'/accepted')
            ->assertRedirect();

        $registration->refresh();

        $this->assertSame('accepted', $registration->status);
        $this->assertSame($before + 1, Student::count());
        $this->assertDatabaseHas('students', ['name' => $registration->student_name]);
    }

    public function test_admin_can_record_a_payment_and_print_the_receipt(): void
    {
        $student = Student::where('student_no', '101')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/payments', [
                'student' => $student->student_no,
                'amount' => 50000,
                'method' => 'cash',
                'date' => now()->toDateString(),
                'note' => 'دفعة اختبار',
            ])
            ->assertRedirect();

        $payment = Payment::where('note', 'دفعة اختبار')->firstOrFail();

        $this->assertStringStartsWith('REC-', $payment->receipt_no);
        $this->assertSame($student->id, $payment->student_id);

        $this->actingAs($this->admin)
            ->get('/admin/payments/'.$payment->id.'/receipt')
            ->assertOk()
            ->assertSee($payment->receipt_no)
            ->assertSee('طالب');
    }

    public function test_admin_can_send_a_notification_to_all_users(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/notifications', [
                'title' => 'إشعار اختبار',
                'audience' => 'all',
                'body' => 'نص إشعار الاختبار',
            ])
            ->assertRedirect();

        $notification = SystemNotification::where('title', 'إشعار اختبار')->firstOrFail();

        $this->assertSame('all', $notification->audience_type);
        $this->assertGreaterThan(0, $notification->recipients()->count());
    }

    public function test_admin_can_update_settings_theme_and_toggles(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/settings/institute', [
                'name' => 'معهد اختبار',
                'address' => 'عنوان اختبار',
                'phone' => '07700000001',
                'whatsapp' => '07700000002',
                'work_hours' => '9 - 5',
                'announcement' => 'إعلان اختبار',
            ])
            ->assertRedirect();

        $this->assertSame('معهد اختبار', Setting::get('institute.name'));

        $this->actingAs($this->admin)
            ->put('/admin/settings/theme', [
                'primary_color' => '#123456',
                'secondary_color' => '#654321',
            ])
            ->assertRedirect();

        $this->assertSame('#123456', Setting::get('theme.primary_color'));

        $this->actingAs($this->admin)
            ->post('/admin/settings/toggle?key=features.registrations_open')
            ->assertOk()
            ->assertJson(['active' => false]);

        $this->assertFalse(Setting::get('features.registrations_open'));
    }

    public function test_admin_can_manage_user_accounts(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'ولي أمر اختبار',
                'username' => 'parent-test',
                'password' => 'secret123',
                'role' => 'parent',
                'phone' => '07700000003',
                'active' => 1,
            ])
            ->assertRedirect();

        $user = User::where('username', 'parent-test')->firstOrFail();

        $this->assertSame('parent', $user->role);
        $this->assertTrue($user->is_active);

        $this->actingAs($this->admin)
            ->put('/admin/users/'.$user->id, [
                'name' => 'ولي أمر معدل',
                'username' => 'parent-test',
                'phone' => '07700000004',
            ])
            ->assertRedirect();

        $this->assertSame('ولي أمر معدل', $user->fresh()->name);
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($this->admin)
            ->delete('/admin/users/'.$user->id)
            ->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_can_create_staff_and_course(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/staff', [
                'name' => 'مدرس اختبار',
                'username' => 'teacher-test',
                'password' => 'secret123',
                'subject' => 'كيمياء',
                'stage' => 'سادس إعدادي',
                'phone' => '07700000005',
            ])
            ->assertRedirect();

        $teacher = User::where('username', 'teacher-test')->firstOrFail();
        $this->assertSame('staff', $teacher->role);
        $this->assertDatabaseHas('staff', ['user_id' => $teacher->id]);

        $stage = Stage::firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/courses', [
                'name' => 'دورة اختبار',
                'stage' => $stage->id,
                'teacher_id' => $teacher->id,
                'subject' => 'كيمياء',
                'price' => 100000,
                'seats' => 20,
                'schedule' => 'الأحد 4:00 م',
                'active' => 1,
            ])
            ->assertRedirect();

        $course = Course::where('name', 'دورة اختبار')->firstOrFail();
        $this->assertSame($teacher->id, $course->teacher_id);
        $this->assertSame($stage->id, $course->stage_id);
        $this->assertTrue($course->is_active);
    }

    public function test_non_admin_cannot_perform_admin_actions(): void
    {
        $student = User::where('role', 'student')->firstOrFail();

        $this->actingAs($student)
            ->post('/admin/stages', ['name' => 'مرحلة ممنوعة'])
            ->assertForbidden();

        $this->actingAs($student)
            ->delete('/admin/students/1')
            ->assertForbidden();

        $this->assertDatabaseMissing('stages', ['name' => 'مرحلة ممنوعة']);
    }

    public function test_admin_student_validation_rejects_empty_name(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/students', ['student_name' => ''])
            ->assertSessionHasErrors('student_name');
    }
}
