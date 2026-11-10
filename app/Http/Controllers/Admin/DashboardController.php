<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Eyal;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => Eyal::stats(),
            'registrations' => Eyal::registrations()->take(4)->values(),
            'payments' => Eyal::payments()->take(4)->values(),
            'students' => Eyal::students()->sortByDesc('attendance')->take(4)->values(),
        ]);
    }

    public function reports()
    {
        return view('admin.reports', [
            'stats' => Eyal::stats(),
            'byStage' => Eyal::studentsByStage(),
            'byCourse' => Eyal::studentsByCourse(),
            'students' => Eyal::students(),
            'staff' => Eyal::staffAll(),
        ]);
    }
}
