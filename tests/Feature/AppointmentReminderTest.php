<?php

namespace Tests\Feature;

use App\Models\AhcsAppointmentSchedulerResponse;
use App\Models\AhcsAttendance;
use App\Models\AhcsCase;
use App\Models\AhcsMedAuth;
use App\Models\AhcsPatient;
use App\Models\AhcsShortUrl;
use App\Models\MedhiwaCareNewOrderType;
use App\Models\MedhiwaSpecialityLocation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    private const TOKEN = 'ab12cd34';

    private string $attendDate;

    protected function setUp(): void
    {
        parent::setUp();

        // The AHCS / Medhiwa databases are external; stand them in with throwaway SQLite.
        foreach (['ahcs', 'medhiwa_ahcs'] as $connection) {
            config(["database.connections.$connection" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::purge($connection);
        }

        config(['services.app_server.api_url' => 'http://app-server.test/api']);

        $ahcs = Schema::connection('ahcs');
        $ahcs->create((new AhcsShortUrl)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('token');
            $table->text('long_url');
        });
        $ahcs->create((new AhcsAppointmentSchedulerResponse)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->integer('patient_id');
            $table->integer('case_id');
            $table->integer('appointment_id');
            $table->integer('patient_apt_msg_status');
            $table->text('patient_apt_msg_reason')->nullable();
        });
        $ahcs->create((new AhcsAttendance)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->integer('ma_id');
            $table->string('department');
            $table->string('service');
            $table->string('attend_type');
            $table->integer('provider_id');
            $table->string('provider_name');
            $table->string('attend_date');
            $table->string('time');
            $table->string('end_time');
            $table->string('attend_status');
            $table->integer('is_virtual')->default(0);
        });
        $ahcs->create((new AhcsCase)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->integer('patient_id');
        });
        $ahcs->create((new AhcsMedAuth)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->integer('case_id');
            $table->string('status');
            $table->integer('no_sessions');
            $table->integer('sessions_completed');
        });
        $ahcs->create((new AhcsPatient)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
        });

        $medhiwa = Schema::connection('medhiwa_ahcs');
        $medhiwa->create((new MedhiwaSpecialityLocation)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('location_phone_no')->nullable();
            $table->timestamps();
        });
        $medhiwa->create((new MedhiwaCareNewOrderType)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

        $this->attendDate = now('America/Chicago')->addDays(3)->format('Y-m-d');

        AhcsPatient::create(['id' => 7, 'first_name' => 'Jamie', 'last_name' => 'Doe']);
        AhcsCase::create(['id' => 20, 'patient_id' => 7]);
        AhcsMedAuth::create(['id' => 30, 'case_id' => 20, 'status' => 'Approved', 'no_sessions' => 5, 'sessions_completed' => 1]);
        AhcsAttendance::create([
            'id' => 100, 'ma_id' => 30, 'department' => 'Dallas', 'service' => 'PT', 'attend_type' => 'FU',
            'provider_id' => 9, 'provider_name' => 'Dr. Maya Patel', 'attend_date' => $this->attendDate,
            'time' => '10:30:00', 'end_time' => '11:00:00', 'attend_status' => 'S',
        ]);
        MedhiwaSpecialityLocation::create(['city' => 'Dallas', 'location_phone_no' => '877-487-8289']);
        MedhiwaCareNewOrderType::create(['code' => 'FU', 'name' => 'Follow-up visit']);

        AhcsShortUrl::create([
            'token'    => self::TOKEN,
            'long_url' => base64_encode(json_encode([
                'appointment_id' => 100, 'patient_id' => 7, 'case_id' => 20, 'cell_no' => '555-000-0000',
            ])),
        ]);
    }

    public function test_show_returns_appointment_details_for_a_valid_token(): void
    {
        $this->getJson('/api/appointment-reminder/' . self::TOKEN)
            ->assertOk()
            ->assertJsonPath('data.patient_first_name', 'Jamie')
            ->assertJsonPath('data.provider_name', 'Dr. Maya Patel')
            ->assertJsonPath('data.visit_type_name', 'Follow-up visit')
            ->assertJsonPath('data.facility_phone_no', '877-487-8289')
            ->assertJsonPath('data.can_respond', true)
            ->assertJsonPath('data.response.status', null);
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->getJson('/api/appointment-reminder/zzzzzzzz')->assertNotFound();
    }

    public function test_token_for_another_patients_case_is_rejected(): void
    {
        AhcsCase::where('id', 20)->update(['patient_id' => 8]);

        $this->getJson('/api/appointment-reminder/' . self::TOKEN)->assertNotFound();
    }

    public function test_past_appointment_is_gone(): void
    {
        AhcsAttendance::where('id', 100)->update(['attend_date' => now('America/Chicago')->subDay()->format('Y-m-d')]);

        $this->getJson('/api/appointment-reminder/' . self::TOKEN)->assertStatus(410);
    }

    public function test_confirm_saves_one_approved_response(): void
    {
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')
            ->assertOk()
            ->assertJsonPath('data.response.status', 'confirmed');

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();

        $rows = AhcsAppointmentSchedulerResponse::all();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->patient_apt_msg_status);
        $this->assertSame(100, (int) $rows[0]->appointment_id);
    }

    public function test_confirm_is_refused_when_appointment_is_not_scheduled(): void
    {
        AhcsAttendance::where('id', 100)->update(['attend_status' => 'Cancelled']);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertStatus(422);
        $this->assertSame(0, AhcsAppointmentSchedulerResponse::count());
    }

    public function test_help_request_saves_category_option_and_note(): void
    {
        $body = ['category' => 'transportation', 'option' => 'ride', 'note' => 'Wheelchair van'];

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', $body)
            ->assertOk()
            ->assertJsonPath('data.request', 'I need help finding a ride')
            ->assertJsonPath('data.response.status', 'help_requested');

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', $body)->assertOk();

        $rows = AhcsAppointmentSchedulerResponse::all();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) $rows[0]->patient_apt_msg_status);
        $this->assertSame('Transportation help: I need help finding a ride - Wheelchair van', $rows[0]->patient_apt_msg_reason);
    }

    public function test_help_request_validates_option_and_required_note(): void
    {
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', ['category' => 'location', 'option' => 'ride'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['option']);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', ['category' => 'other', 'option' => 'insurance'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $this->assertSame(0, AhcsAppointmentSchedulerResponse::count());
    }

    public function test_time_slots_returns_only_open_times_later_that_day(): void
    {
        $this->fakeSlots();

        $this->getJson('/api/appointment-reminder/' . self::TOKEN . '/time-slots')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data'    => [
                    'attend_date'  => $this->attendDate,
                    'current_time' => '10:30:00',
                    'slots'        => [
                        ['time' => '13:00', 'label' => '1:00 PM'],
                        ['time' => '15:30', 'label' => '3:30 PM'],
                    ],
                    'message'      => null,
                ],
            ]);

        // Reminder flow asks the scheduling service to drop its 24-hour notice.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/patient-portal/available-time-slots')
            && str_contains($request->url(), 'min_lead_hours=0'));
    }

    public function test_time_slots_explains_why_a_day_has_no_slots(): void
    {
        Http::fake([
            '*/available-time-slots*' => Http::response(['status' => false, 'message' => 'Not Available', 'start_times' => []]),
        ]);

        $this->getJson('/api/appointment-reminder/' . self::TOKEN . '/time-slots')
            ->assertOk()
            ->assertJsonPath('data.slots', [])
            ->assertJsonPath('data.message', 'Not Available');
    }

    public function test_reschedule_modifies_the_same_appointment_and_confirms_it(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])
            ->assertOk()
            ->assertJsonPath('data.time', '13:00:00')
            ->assertJsonPath('data.end_time', '13:30:00');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/modify-appointment-schedule/Jamie%20Doe/20/100')
            && $request['time'] === '13:00'
            && $request['end_time'] === '13:30'
            && $request['provider_id'] === 9
            && $request['provider_name'] === 'Dr. Maya Patel'
            && $request['attend_type'] === 'FU'
            && $request['attend_date'] === $this->attendDate
            && $request['attend_status'] === 'S');

        $rows = AhcsAppointmentSchedulerResponse::all();
        $this->assertCount(1, $rows);
        $this->assertSame(100, (int) $rows[0]->appointment_id);
        $this->assertSame(1, (int) $rows[0]->patient_apt_msg_status);

        // Same appointment, so the link still works afterwards.
        $this->getJson('/api/appointment-reminder/' . self::TOKEN)
            ->assertOk()
            ->assertJsonPath('data.time', '13:00:00')
            ->assertJsonPath('data.can_respond', true)
            ->assertJsonPath('data.response.status', 'confirmed');
    }

    public function test_reschedule_rejects_a_time_that_is_not_open(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '14:00'])
            ->assertStatus(422);

        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_reschedule_surfaces_the_scheduling_service_reason(): void
    {
        $this->fakeSlots(['status' => false, 'action' => 'modified', 'attend_id' => 100, 'error' => 'AMD: slot is blocked']);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'AMD: slot is blocked');

        $this->assertSame(0, AhcsAppointmentSchedulerResponse::count());
    }

    public function test_reschedule_replaces_the_staff_only_missing_amd_message(): void
    {
        $this->fakeSlots(['status' => false, 'error' => 'Appointment status is "Checked In", which does not allow provider changes.']);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This appointment cannot be changed online. Please call us to reschedule.');
    }

    private function fakeSlots(?array $modifyFailure = null): void
    {
        Http::fake([
            '*/available-time-slots*' => Http::response(['status' => true, 'start_times' => [
                ['value' => '09:00', 'label' => '09:00 am', 'disabled' => false],
                ['value' => '10:30', 'label' => '10:30 am (Booked)', 'disabled' => true],
                ['value' => '13:00', 'label' => '01:00 pm', 'disabled' => false],
                ['value' => '14:00', 'label' => '02:00 pm (Booked)', 'disabled' => true],
                ['value' => '15:30', 'label' => '03:30 pm', 'disabled' => false],
            ]]),
            '*/modify-appointment-schedule/*' => function ($request) use ($modifyFailure) {
                if ($modifyFailure) {
                    return Http::response($modifyFailure);
                }

                // Stand in for the scheduling service editing the row in place.
                AhcsAttendance::where('id', 100)->update([
                    'time'     => $request['time'] . ':00',
                    'end_time' => $request['end_time'] . ':00',
                ]);

                return Http::response(['status' => 'success', 'action' => 'modified', 'attend_id' => 100]);
            },
        ]);
    }
}
