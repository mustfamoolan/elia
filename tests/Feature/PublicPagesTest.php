<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Setting;
use App\Models\Stage;
use App\Models\User;
use Database\Seeders\EyalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EyalSeeder::class);
    }

    public function test_home_page_uses_database_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('معهد آيــــــــلا التعليمي')
            ->assertViewHas('stats', fn (array $stats) => $stats['students'] === 7
                && $stats['courses'] === 6
                && $stats['stages'] === 5);
    }

    public function test_courses_page_filters_by_stage_from_database(): void
    {
        $stage = Stage::where('name', 'سادس إعدادي')->firstOrFail();

        $this->get('/courses?stage='.$stage->id)
            ->assertOk()
            ->assertSee('دورة الرياضيات')
            ->assertSee('دورة الفيزياء')
            ->assertDontSee('دورة العلوم')
            ->assertViewHas('courses', fn ($courses) => $courses->count() === 2
                && $courses->every(fn (array $course) => $course['stage'] === (string) $stage->id));
    }

    public function test_results_search_reads_student_grades_from_database(): void
    {
        $this->get('/results?q=101')
            ->assertOk()
            ->assertSee('أحمد محمد علي')
            ->assertSee('الامتحان الشهري الأول')
            ->assertSee('الرياضيات')
            ->assertSee('92');
    }

    public function test_homework_page_lists_tasks_from_database(): void
    {
        $this->get('/homework')
            ->assertOk()
            ->assertSee('حل تمارين الفصل الثالث')
            ->assertSee('تقرير عن قوانين نيوتن')
            ->assertSee('أ. حسين الكعبي');
    }

    public function test_guest_notifications_only_show_public_announcements(): void
    {
        $this->get('/notifications')
            ->assertOk()
            ->assertSee('إعلان عام')
            ->assertDontSee('تذكير بموعد الامتحان');
    }

    public function test_staff_page_only_shows_public_staff(): void
    {
        $this->get('/staff')
            ->assertOk()
            ->assertSee('أ. حسين الكعبي')
            ->assertSee('أ. سارة الحسن')
            ->assertDontSee('أ. محمد الجابري');
    }

    public function test_registration_request_is_stored_and_returns_whatsapp_url(): void
    {
        $stage = Stage::firstOrFail();
        $course = Course::firstOrFail();

        $response = $this->postJson('/register', [
            'student_name' => 'طالب اختبار',
            'parent_name' => 'ولي أمر اختبار',
            'phone' => '07700000000',
            'stage' => $stage->id,
            'course' => $course->id,
            'notes' => 'ملاحظة اختبار',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['message', 'whatsapp_url', 'registration_id'])
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'تم استلام طلب التسجيل'));

        $this->assertStringContainsString('https://wa.me/', $response->json('whatsapp_url'));

        $this->assertDatabaseHas('registration_requests', [
            'student_name' => 'طالب اختبار',
            'parent_name' => 'ولي أمر اختبار',
            'phone' => '07700000000',
            'stage_id' => $stage->id,
            'course_id' => $course->id,
            'status' => 'new',
        ]);
    }

    public function test_registration_request_requires_the_expected_fields(): void
    {
        $this->postJson('/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_name', 'parent_name', 'phone', 'stage']);
    }

    public function test_parent_portal_shows_linked_child_data(): void
    {
        $parent = User::where('username', 'parent')->firstOrFail();

        $this->actingAs($parent)
            ->get('/parent')
            ->assertOk()
            ->assertSee('أحمد محمد علي')
            ->assertSee('REC-1001')
            ->assertSee('حساب ولي الأمر');
    }

    public function test_student_portal_shows_own_data(): void
    {
        $student = User::where('username', '101')->firstOrFail();

        $this->actingAs($student)
            ->get('/student')
            ->assertOk()
            ->assertSee('أحمد محمد علي')
            ->assertSee('الرياضيات')
            ->assertSee('الفيزياء');
    }

    public function test_institute_settings_override_config_values(): void
    {
        Setting::set('institute.name', 'معهد الاختبار');

        $this->get('/')
            ->assertOk()
            ->assertSee('معهد الاختبار');
    }
}
