<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Support\Eyal;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        return view('admin.payments', [
            'payments' => Eyal::payments(),
            'students' => Eyal::students(),
            'stats' => Eyal::stats(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayment($request);

        $student = Student::query()->where('student_no', $validated['student'])->firstOrFail();

        $charge = $student->charges()->oldest('id')->first();

        Payment::create([
            'student_id' => $student->id,
            'charge_id' => $charge?->id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'receipt_no' => $this->nextReceiptNumber(),
            'paid_at' => $validated['date'] ?? now()->toDateString(),
            'note' => $validated['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'تم تسجيل الدفعة.');
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $this->validatePayment($request);

        $student = Student::query()->where('student_no', $validated['student'])->firstOrFail();
        $charge = $student->charges()->oldest('id')->first();

        $payment->update([
            'student_id' => $student->id,
            'charge_id' => $charge?->id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'paid_at' => $validated['date'] ?? ($payment->paid_at?->toDateString() ?? now()->toDateString()),
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', 'تم تعديل بيانات الدفعة والمبلغ بنجاح.');
    }

    public function updateFee(Request $request, Student $student)
    {
        $validated = $request->validate([
            'fee' => ['required', 'numeric', 'min:0'],
        ], [
            'fee.required' => 'يرجى إدخال قيمة الاشتراك.',
            'fee.min' => 'قيمة الاشتراك يجب أن تكون صفراً أو أكثر.',
        ]);

        $fee = (int) $validated['fee'];
        $charge = $student->charges()->oldest('id')->first();

        if ($charge) {
            $charge->update(['amount' => $fee]);
        } elseif ($fee > 0) {
            StudentCharge::create([
                'student_id' => $student->id,
                'title' => 'اشتراك دراسي',
                'amount' => $fee,
                'due_date' => now()->addDays(30)->toDateString(),
                'created_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'تم تحديث قيمة اشتراك الطالب '.$student->name.' بنجاح.');
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return back()->with('success', 'تم حذف الدفعة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayment(Request $request): array
    {
        return $request->validate([
            'student' => ['required', 'string', 'exists:students,student_no'],
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'in:cash,zaincash,transfer'],
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'student.required' => 'يرجى اختيار الطالب.',
            'student.exists' => 'الطالب المختار غير موجود.',
            'amount.required' => 'يرجى إدخال المبلغ.',
            'amount.min' => 'المبلغ يجب أن يكون أكبر من صفر.',
            'method.required' => 'يرجى اختيار طريقة الدفع.',
        ]);
    }

    public function receipt(Payment $payment)
    {
        $payment->load('student.stage');

        return view('admin.payment-receipt', [
            'payment' => $payment,
            'student' => $payment->student,
        ]);
    }

    private function nextReceiptNumber(): string
    {
        $max = Payment::query()
            ->pluck('receipt_no')
            ->map(fn ($value) => (int) preg_replace('/\D/', '', (string) $value))
            ->max();

        $next = max(1000, (int) $max) + 1;

        while (Payment::where('receipt_no', 'REC-'.$next)->exists()) {
            $next++;
        }

        return 'REC-'.$next;
    }
}
