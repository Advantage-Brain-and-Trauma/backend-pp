<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        Log::channel('admin_appointments')->info('Appointments index requested');
        // Appointments are fetched from external system
        $appointments = $this->fetchAppointments($request);

        return view('appointments.index', compact('appointments'));
    }

    public function show($id)
    {
        Log::channel('admin_appointments')->info('Appointment show requested', ['appointment_id' => $id]);
        $appointment = $this->fetchAppointment($id);

        return view('appointments.show', compact('appointment'));
    }

    private function fetchAppointments(Request $request): array
    {
        $apiUrl = config('services.appointments.url');
        $apiKey = config('services.appointments.key');

        if (!$apiUrl) {
            // Return mock data when API not configured
            return $this->getMockAppointments();
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->get($apiUrl . '/appointments', $request->only(['date', 'status', 'patient_id']));

            return $response->successful() ? $response->json('data', []) : [];
        } catch (\Exception $e) {
            return $this->getMockAppointments();
        }
    }

    private function fetchAppointment($id): array
    {
        return [
            'id' => $id,
            'patient_name' => 'Sample Patient',
            'doctor' => 'Dr. Sample',
            'date' => now()->format('Y-m-d'),
            'time' => '10:30 AM',
            'type' => 'In-Person',
            'status' => 'confirmed',
            'location' => 'Main Clinic, Suite 300',
        ];
    }

    private function getMockAppointments(): array
    {
        return [
            ['id' => 1, 'patient_name' => 'Sarah Jenkins', 'doctor' => 'Dr. Emily Chen', 'date' => '2024-10-14', 'time' => '10:30 AM', 'type' => 'In-Person', 'status' => 'confirmed', 'location' => 'Main Clinic, Suite 300'],
            ['id' => 2, 'patient_name' => 'Michael Johnson', 'doctor' => 'Mark Johnson, PT', 'date' => '2024-11-02', 'time' => '2:00 PM', 'type' => 'Telehealth', 'status' => 'action_required', 'location' => 'Video Visit'],
            ['id' => 3, 'patient_name' => 'Emily Davis', 'doctor' => 'Dr. Sarah Smith', 'date' => '2024-10-20', 'time' => '9:00 AM', 'type' => 'In-Person', 'status' => 'confirmed', 'location' => 'Main Clinic, Suite 200'],
            ['id' => 4, 'patient_name' => 'Robert Wilson', 'doctor' => 'Dr. Michael Jones', 'date' => '2024-10-25', 'time' => '3:30 PM', 'type' => 'In-Person', 'status' => 'pending', 'location' => 'Branch Office'],
        ];
    }
}



