<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\Eyal;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements', [
            'announcements' => Eyal::announcementsAll(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Announcement::create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? 'عام',
            'audience' => 'all',
            'is_pinned' => (bool) ($validated['pinned'] ?? false),
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $request->user()->id,
            'color' => $this->colorFor($validated['category'] ?? 'عام'),
        ]);

        return back()->with('success', 'تم نشر الإعلان.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $this->validated($request);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'] ?? 'عام',
            'is_pinned' => (bool) ($validated['pinned'] ?? false),
            'color' => $this->colorFor($validated['category'] ?? 'عام'),
        ]);

        return back()->with('success', 'تم تحديث الإعلان.');
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->update([
            'is_published' => ! $announcement->is_published,
            'published_at' => $announcement->published_at ?? now(),
        ]);

        return response()->json(['active' => $announcement->is_published]);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'تم حذف الإعلان.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'body' => ['required', 'string', 'max:5000'],
            'pinned' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'يرجى إدخال عنوان الإعلان.',
            'body.required' => 'يرجى إدخال نص الإعلان.',
        ]);
    }

    private function colorFor(string $category): string
    {
        return match ($category) {
            'تسجيل' => 'blue',
            'امتحانات' => 'amber',
            'عطلة' => 'slate',
            default => 'emerald',
        };
    }
}
