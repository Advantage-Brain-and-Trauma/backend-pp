<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PatientAppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ClinicalController;
use App\Http\Controllers\Api\ClinicalNoteController;
use App\Http\Controllers\Api\Auth\AuthApiController;
use App\Http\Controllers\Api\Auth\PasswordResetApiController;
use App\Http\Controllers\Api\FunnelApiController;
use App\Http\Controllers\Api\FormSubmissionNoteController;
use App\Http\Controllers\Api\RecentActivityController;
use App\Http\Controllers\Api\ProxyAccessController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\DirectEmailLoginController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login',[AuthApiController::class, 'login']);
Route::post('magic-link/verify',[AuthApiController::class, 'magicLinkVerify']);

// Internal: login directly via email only (no password) — issues the same JWT as normal login
// Requires a shared secret header (X-API-KEY) since it bypasses password verification.
Route::middleware('internal.api.key')->post('login-by-email', [DirectEmailLoginController::class, 'loginByEmail']);

// ── Password Reset (public – no auth required) ────────────────────────────
Route::middleware('throttle:10,1')->group(function () {
    Route::post('password/forgot',          [PasswordResetApiController::class, 'forgotPassword']);
    Route::post('password/forgot-phone',    [PasswordResetApiController::class, 'forgotPasswordByPhone']);
    Route::post('password/reset',           [PasswordResetApiController::class, 'resetPassword']);
    Route::get('password/validate-token',   [PasswordResetApiController::class, 'validateToken']);
});

// Public: proxy accepts invitation via emailed token
Route::post('proxy/accept/{token}', [ProxyAccessController::class, 'accept']);

// Public: lookup basic user/patient details by email
Route::middleware('throttle:30,1')->group(function () {
    Route::get('get-user-details-by-email', [PatientController::class, 'getUserDetailsByEmail']);
});

Route::middleware('auth:api')->group(function () {
    Route::post('logout',[AuthApiController::class, 'logout']);
    Route::post('refresh-token',[AuthApiController::class, 'refreshToken']);
});

Route::middleware(['auth:api', 'role.api:User', 'patient.active'])->group(function (){
    Route::get('get-patient-appointments',[PatientAppointmentController::class,'getPatientAppointments'])->middleware('proxy.log');
    Route::get('get-appointment',[PatientAppointmentController::class, 'getAppointment']);
    Route::get('get-appointment-reasons',[PatientAppointmentController::class, 'getAppointmentReasons']);
    Route::get('get-appointment-departments',[PatientAppointmentController::class, 'getAppointmentDepartments']);
    Route::get('get-department-speciality-with-physician',[PatientAppointmentController::class, 'getDepartmentSpecialityWithPhysician']);
    Route::get('get-company-by-department-and-provider',[PatientAppointmentController::class, 'getCompanyByDepartmentAndProvider']);
    Route::get('available-time-slots',[PatientAppointmentController::class, 'getAvailableTimeSlots']);
    Route::get('check-sessions-completed',[PatientAppointmentController::class, 'checkSessionsCompleted']);
    Route::get('get-approved-preauth',[PatientAppointmentController::class, 'getApprovedPreauth']);
    Route::get('get-time-slots-date-range',[PatientAppointmentController::class, 'getTimeSlotDateRange']);
    Route::post('schedule-patient-appointment/{userName}/{caseId}',[PatientAppointmentController::class, 'schedulePatientAppointment']);
    Route::post('appointment-reschedule',[PatientAppointmentController::class, 'appointmentReschedule']);
    Route::post('notify-patient-preauth-missing-details',[PatientAppointmentController::class, 'notifyPatientPreauthMissingDetails']);
    Route::post('appointment-schedule/{userName}/{caseId}/{maId}/{patientId}',[PatientAppointmentController::class, 'appointmentSchedule']);

    Route::get('get-patient-submited-form-data',[ClinicalController::class, 'getPatientSubmitedFormData'])->middleware('cors'); // old platform form data
    Route::post('download-patient-submited-form-pdf',[ClinicalController::class, 'downloadPatientSubmitedFormPdf']); // old platform form pdf download
    Route::get('get-patient-form-data',[ClinicalController::class, 'getPatientFormData'])->middleware('proxy.log'); // new platform form data
    Route::post('download-patient-form-pdf',[ClinicalController::class, 'downloadPatientFormPdf']); // new platform form pdf download
    Route::get('clinical-note', [ClinicalNoteController::class, 'show'])->middleware('proxy.log');
    Route::get('get-clinical', [ClinicalNoteController::class, 'getClinicalDocuments']);
    Route::get('clinical-note/view/{noteId}', [ClinicalNoteController::class, 'viewNote']);
    Route::get('clinical-note/download/{noteId}', [ClinicalNoteController::class, 'downloadNote']);
    Route::get('clinical-note-preview', [ClinicalNoteController::class, 'preview']);
    Route::get('clinical-note-preview-url', [ClinicalNoteController::class, 'generatePreviewUrl']);
    Route::get('attach/preview/{caseId}/{folder}/{subFolder}/{filename}', [ClinicalNoteController::class, 'previewAttachmentPath']);

    Route::get('get-patient-details',[PatientController::class, 'getPatientDetails'])->middleware('proxy.log');
    Route::get('get-case-ids-by-patient-id', [PatientController::class, 'getCaseIdsByPatientId']);
    Route::get('get-case-ids-by-email', [PatientController::class, 'getCaseIdsByEmail']);
    Route::post('change-patient-case', [PatientController::class, 'changePatientCase']);

    // Funnels API
    Route::get('get-patient-funnels', [FunnelApiController::class, 'getPatientFunnels'])->middleware('proxy.log');
    // Funnel submission details API
    Route::get('get-patient-funnel-submission-details/{funnelId}', [FunnelApiController::class, 'getPatientFunnelSubmissionDetails']);
    // Form Submissions API
    Route::post('patient-forms/{formId}/submit', [FunnelApiController::class, 'patientSubmitForm']);

    // Recent Activity
    Route::get('recent-activity', [RecentActivityController::class, 'index'])->middleware('proxy.log');

    // ── Proxy Access ──────────────────────────────────────────────────────────
    // Patient-facing: manage who can view their records
    Route::post('proxy/invite',           [ProxyAccessController::class, 'invite']);
    Route::get('proxy/list',              [ProxyAccessController::class, 'list']);
    Route::delete('proxy/{id}/revoke',    [ProxyAccessController::class, 'revoke']);
    Route::get('proxy/{id}/history',      [ProxyAccessController::class, 'history']);

    // Proxy-facing: see and switch into granted patient accounts
    Route::get('proxy/my-access',         [ProxyAccessController::class, 'myAccess']);
    Route::post('proxy/switch-patient',   [ProxyAccessController::class, 'switchPatient']);

    // Administrator
    Route::get('get-administrator-notes', [ClinicalNoteController::class, 'getAdministratorNotes']);

    // Form Submission Notes API
    Route::get('form-submissions/{submissionId}/notes',             [FormSubmissionNoteController::class, 'index']);
    Route::post('form-submissions/{submissionId}/notes',            [FormSubmissionNoteController::class, 'store']);
    Route::put('form-submissions/{submissionId}/notes/{noteId}',    [FormSubmissionNoteController::class, 'update']);
    Route::delete('form-submissions/{submissionId}/notes/{noteId}', [FormSubmissionNoteController::class, 'destroy']);
});

    Route::get('get-all-old-forms', [FunnelApiController::class, 'getAllOldForms']); // get all olds form data
    Route::get('get-all-funnel-list', [FunnelApiController::class, 'getAllFunnelList']); // get all funnel list
    Route::post('assign-funnel', [FunnelApiController::class, 'assignFunnel']);
    Route::post('check-assign-funnel', [FunnelApiController::class, 'CheckAssignFunnel']);
    Route::post('assign-funnel-sms', [FunnelApiController::class, 'assignFunnelSms']);
    Route::post('add-patient-to-funnel', [FunnelApiController::class, 'addPatientToFunnel']);
    Route::post('direct-login', [LoginController::class, 'directLogin']);
