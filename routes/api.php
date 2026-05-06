<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PatientAppointmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ClinicalController;
use App\Http\Controllers\Api\Auth\AuthApiController;
use App\Http\Controllers\Api\FunnelApiController;

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

Route::middleware('auth:api')->group(function () {
    Route::post('logout',[AuthApiController::class, 'logout']);
});

Route::middleware(['auth:api', 'role.api:User'])->group(function (){
    Route::get('get-patient-appointments',[PatientAppointmentController::class,'getPatientAppointments']);
    Route::get('get-appointment-departments',[PatientAppointmentController::class, 'getAppointmentDepartments']);
    Route::get('get-department-speciality-with-physician',[PatientAppointmentController::class, 'getDepartmentSpecialityWithPhysician']);

    Route::get('get-company-by-department-and-provider',[PatientAppointmentController::class, 'getCompanyByDepartmentAndProvider']);
    Route::post('schedule-patient-appointment/{userName}/{caseId}',[PatientAppointmentController::class, 'schedulePatientAppointment']);
    Route::get('get-patient-submited-form-data',[ClinicalController::class, 'getPatientSubmitedFormData'])->middleware('cors'); // old platform form data
    Route::post('download-patient-submited-form-pdf',[ClinicalController::class, 'downloadPatientSubmitedFormPdf']);
    Route::get('view-patient-submited-form/{formValueId}',[ClinicalController::class, 'viewPatientSubmitedFormPdf']);
    Route::get('get-patient-details',[PatientController::class, 'getPatientDetails']);
    Route::get('get-patient-form-data',[ClinicalController::class, 'getPatientFormData']); // new platform form data

    // Funnels API
    Route::get('get-patient-funnels', [FunnelApiController::class, 'getPatientFunnels']);
});

