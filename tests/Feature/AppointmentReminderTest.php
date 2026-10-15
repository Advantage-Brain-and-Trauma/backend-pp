<?php

namespace Tests\Feature;

use App\Models\AhcsAppointmentSchedulerResponse;
use App\Models\AhcsAttendance;
use App\Models\AhcsCase;
use App\Models\AhcsMedAuth;
use App\Models\AhcsPatient;
use App\Models\AhcsShortUrl;
use App\Models\ChatMessage;
use App\Models\ChatUser;
use App\Models\Conversation;
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
            $table->text('patient_apt_help_reason')->nullable();
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
            $table->text('attend_notes')->nullable();
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
            $table->softDeletes();
            $table->timestamps();
        });
        $medhiwa->create((new MedhiwaCareNewOrderType)->getTable(), function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

        // Support chat tables (default connection). Only the columns the chat code reads.
        Schema::create('chat_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('external_type');
            $table->unsignedBigInteger('external_id');
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('conversation_key')->unique();
            $table->string('type')->default('direct');
            $table->unsignedBigInteger('assigned_chat_user_id')->nullable();
            $table->unsignedBigInteger('department_chat_user_id')->nullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->integer('session')->default(1);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_chat_user_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('chat_user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('sender_chat_user_id');
            $table->unsignedBigInteger('sent_by_chat_user_id')->nullable();
            $table->text('message')->nullable();
            $table->string('message_type')->default('text');
            $table->string('attachment')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        config(['chat.shared_secret' => 'test-secret', 'chat.staff_ip_allowlist' => []]);

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

    public function test_help_request_after_confirm_updates_the_confirmed_row(): void
    {
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();

        $ride = ['category' => 'transportation', 'option' => 'ride'];

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', $ride)
            ->assertOk()
            ->assertJsonPath('data.response.status', 'confirmed')
            ->assertJsonPath('data.response.help_reason', 'Transportation help: I need help finding a ride');

        // A repeat is a no-op; a different help request replaces the earlier one on the same row.
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', $ride)->assertOk();
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', ['category' => 'other', 'option' => 'other', 'note' => 'Running late'])
            ->assertOk()
            ->assertJsonPath('data.response.status', 'confirmed');

        $rows = AhcsAppointmentSchedulerResponse::all();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->patient_apt_msg_status);
        $this->assertSame('', (string) $rows[0]->patient_apt_msg_reason);
        $this->assertSame('Another concern: Something else - Running late', $rows[0]->patient_apt_help_reason);

        // Each distinct help request still reaches staff.
        $this->assertSame(3, DB::table('chat_messages')->count());
    }

    public function test_help_request_after_reschedule_keeps_the_appointment_confirmed(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])->assertOk();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', ['category' => 'transportation', 'option' => 'ride'])
            ->assertOk()
            ->assertJsonPath('data.response.status', 'confirmed');

        $rows = AhcsAppointmentSchedulerResponse::all();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->patient_apt_msg_status);
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
                        ['time' => '16:00', 'label' => '4:00 PM'],
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

    public function test_reschedule_sends_existing_staff_notes_back_so_they_are_kept(): void
    {
        $this->fakeSlots();
        AhcsAttendance::where('id', 100)->update(['attend_notes' => 'Needs interpreter']);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])->assertOk();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['attend_notes'] === 'Needs interpreter');
    }

    public function test_reschedule_is_refused_once_the_appointment_is_confirmed(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This appointment is already confirmed. Please call us to change the time.');

        // A second online reschedule is refused the same way.
        AhcsAppointmentSchedulerResponse::query()->delete();
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])->assertOk();
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '15:30'])->assertStatus(422);

        Http::assertSentCount(2); // one slots lookup + one modify, nothing for the refused calls
        $this->assertSame('13:00:00', AhcsAttendance::find(100)->time);
    }

    public function test_reschedule_is_allowed_after_a_help_request_without_confirmation(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', ['category' => 'transportation', 'option' => 'ride'])->assertOk();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])->assertOk();
    }

    public function test_empty_reasons_are_returned_as_null(): void
    {
        $this->getJson('/api/appointment-reminder/' . self::TOKEN)
            ->assertJsonPath('data.response.reason', null)
            ->assertJsonPath('data.response.help_reason', null);

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')
            ->assertJsonPath('data.response.status', 'confirmed')
            ->assertJsonPath('data.response.reason', null)
            ->assertJsonPath('data.response.help_reason', null);
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

    public function test_replies_are_copied_to_a_flagged_staff_only_chat_thread(): void
    {
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/help-request', [
            'category' => 'transportation', 'option' => 'ride',
        ])->assertOk();

        $thread = Conversation::sole();
        $this->assertSame(Conversation::TYPE_APPOINTMENT_REMINDER, $thread->type);
        $this->assertSame(20, (int) $thread->case_id);

        $department = ChatUser::where('external_type', 'department')->sole();
        $patient = ChatUser::where('external_type', 'patient')->sole();
        $this->assertSame(7, (int) $patient->external_id);
        $this->assertSame((int) $department->id, (int) $thread->department_chat_user_id);

        // Staff only: the patient wrote the messages but is NOT a participant.
        $this->assertSame(
            [(int) $department->id],
            $thread->participants->pluck('chat_user_id')->map(fn ($id) => (int) $id)->all()
        );

        $messages = $thread->messages()->orderBy('id')->get();
        $this->assertSame(['reminder_confirmed', 'reminder_help_requested'], $messages->pluck('message_type')->all());
        $this->assertSame((int) $patient->id, (int) $messages[0]->sender_chat_user_id);
        $this->assertStringContainsString('Confirmed appointment on', $messages[0]->message);
        $this->assertStringContainsString('Transportation help: I need help finding a ride', $messages[1]->message);
    }

    public function test_time_change_is_copied_to_chat(): void
    {
        $this->fakeSlots();

        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/reschedule', ['time' => '13:00'])->assertOk();

        $message = ChatMessage::sole();
        $this->assertSame('reminder_time_changed', $message->message_type);
        $this->assertStringContainsString('from 10:30 AM to 1:00 PM', $message->message);
    }

    public function test_reminder_threads_have_their_own_staff_list_and_stay_out_of_the_queue(): void
    {
        $this->postJson('/api/appointment-reminder/' . self::TOKEN . '/confirm')->assertOk();

        $staff = ['staff_external_id' => 5, 'staff_name' => 'Staff One', 'departments' => ['Dallas']];
        $headers = ['X-CHAT-SECRET' => 'test-secret'];

        $this->postJson('/api/chat/staff/conversations', $staff, $headers)
            ->assertOk()
            ->assertJsonCount(0, 'conversations');

        $uuid = $this->postJson('/api/chat/staff/conversations', $staff + ['scope' => 'appointment_reminder'], $headers)
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.is_appointment_reminder', true)
            ->assertJsonPath('conversations.0.case_id', 20)
            ->assertJsonPath('conversations.0.patient.patient_id', 7)
            ->assertJsonPath('conversations.0.patient.name', 'Jamie Doe')
            ->assertJsonPath('conversations.0.can_reply', false)
            ->assertJsonPath('conversations.0.unread_count', 1)
            ->assertJsonPath('conversations.0.last_message.reminder_response', 'reminder_confirmed')
            ->json('conversations.0.uuid');

        $this->postJson("/api/chat/staff/conversations/$uuid/messages", $staff, $headers)
            ->assertOk()
            ->assertJsonPath('messages.data.0.reminder_response', 'reminder_confirmed')
            ->assertJsonPath('messages.data.0.from', 'patient');

        // Read-only: no reply, no claim, no ending.
        $this->postJson("/api/chat/staff/conversations/$uuid/send", $staff + ['message' => 'Hello'], $headers)->assertStatus(422);
        $this->postJson("/api/chat/staff/conversations/$uuid/assign", $staff, $headers)->assertStatus(422);
        $this->postJson("/api/chat/staff/conversations/$uuid/close", $staff, $headers)->assertStatus(422);

        $this->assertSame(1, ChatMessage::count());
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
                ['value' => '16:00', 'label' => '04:00 pm', 'disabled' => false],
                ['value' => '16:30', 'label' => '04:30 pm', 'disabled' => false],
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
