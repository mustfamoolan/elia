<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Stage;
use App\Support\Eyal;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    public function index()
    {
        return view('admin.offers', [
            'offers' => Eyal::offersAll(),
            'stages' => Eyal::stages(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Offer::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'stage_id' => $this->stageId($validated['stage'] ?? null),
            'discount_percent' => $this->discount($validated['discount'] ?? null),
            'old_price' => $validated['old_price'] ?? 0,
            'price' => $validated['price'] ?? 0,
            'starts_at' => now()->toDateString(),
            'ends_at' => $validated['expires'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'color' => 'rose',
        ]);

        return back()->with('success', 'تمت إضافة العرض.');
    }

    public function update(Request $request, Offer $offer)
    {
        $validated = $this->validated($request);

        $offer->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'stage_id' => $this->stageId($validated['stage'] ?? null),
            'discount_percent' => $this->discount($validated['discount'] ?? null),
            'old_price' => $validated['old_price'] ?? 0,
            'price' => $validated['price'] ?? 0,
            'ends_at' => $validated['expires'] ?? null,
        ]);

        return back()->with('success', 'تم تحديث العرض.');
    }

    public function toggle(Offer $offer)
    {
        $offer->update(['is_active' => ! $offer->is_active]);

        return response()->json(['active' => $offer->is_active]);
    }

    public function destroy(Offer $offer)
    {
        $offer->delete();

        return back()->with('success', 'تم حذف العرض.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'string', 'max:20'],
            'old_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'expires' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'يرجى إدخال عنوان العرض.',
        ]);
    }

    private function stageId(?string $stage): ?int
    {
        if (! $stage) {
            return null;
        }

        if (ctype_digit($stage)) {
            return (int) $stage;
        }

        return Stage::query()->where('name', $stage)->value('id');
    }

    private function discount(?string $discount): float
    {
        if (! $discount) {
            return 0;
        }

        return (float) preg_replace('/[^0-9.]/', '', $discount);
    }
}
