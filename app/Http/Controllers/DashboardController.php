<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Funnel;
use App\Models\FormSubmission;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        Log::channel('admin_dashboard')->info('Dashboard requested');
        $stats = [
            'total_forms' => Form::count(),
            'active_forms' => Form::where('is_active', 1)->count(),
            'total_funnels' => Funnel::count(),
            'active_funnels' => Funnel::where('status', 'active')->count(),
            'total_submissions' => FormSubmission::count(),
            'pending_submissions' => FormSubmission::where('status', 'pending')->count(),
            'unread_messages' => Message::where('status', 'unread')->count(),
        ];

        $recentSubmissions = FormSubmission::with(['form'])->latest()->take(5)->get();
        $recentMessages = Message::where('status', 'unread')->latest()->take(5)->get();

        return view('dashboard', compact('stats', 'recentSubmissions', 'recentMessages'));
    }
}




