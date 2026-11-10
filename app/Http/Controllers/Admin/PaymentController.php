<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
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
        $validated = $request->validate([
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

    public function destroy(Payment $payment)
    {
        $payment->delete();

        return back()->with('success', 'تم حذف الدفعة.');
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
