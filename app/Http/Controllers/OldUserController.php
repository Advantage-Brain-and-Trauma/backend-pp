<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OldUserController extends Controller
{
    public function index()
    {
        return view('user-management.old-users');
    }

    public function list()
    {
        try {
            $allOldUsers = DB::connection('patient_portal')
                ->table('users')
                ->whereNull('deleted_at')
                ->get();

            $newUsersByEmail = User::withTrashed()
                ->select('id', 'email', 'deleted_at')
                ->whereNotNull('email')
                ->get()
                ->keyBy(fn ($u) => Str::lower($u->email));

            $filteredUsers = $allOldUsers->filter(function ($oldUser) use ($newUsersByEmail) {
                if (empty($oldUser->email)) {
                    return false;
                }

                $newUser = $newUsersByEmail->get(Str::lower($oldUser->email));

                if (!$newUser) {
                    return true;
                }

                return !is_null($newUser->deleted_at);
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'Old users retrieved successfully.',
                'data' => $filteredUsers,
            ]);
        } catch (\Throwable $e) {
            Log::channel('stack')->error('Error fetching old users list', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching old users.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sync($id)
    {
        try {
            $oldUser = DB::connection('patient_portal')
                ->table('users')
                ->whereNull('deleted_at')
                ->where('id', $id)
                ->first();

            if (!$oldUser) {
                return response()->json([
                    'status' => false,
                    'message' => 'Old user not found.',
                ], 404);
            }

            if (empty($oldUser->email)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Old user email is empty, cannot sync.',
                ], 422);
            }

            $existingUser = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [Str::lower($oldUser->email)])
                ->first();

            $rawPatientId = $oldUser->patient_id ?? null;
            if (is_string($rawPatientId) && str_starts_with(trim($rawPatientId), '[')) {
                $decoded = json_decode($rawPatientId, true);
                $patientIdArray = is_array($decoded) ? array_values(array_unique(array_filter(array_map('intval', $decoded)))) : null;
            } elseif (is_string($rawPatientId) && str_contains($rawPatientId, ',')) {
                $patientIdArray = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawPatientId)))));
            } elseif (!is_null($rawPatientId) && $rawPatientId !== '') {
                $patientIdArray = [(int) $rawPatientId];
            } else {
                $patientIdArray = null;
            }

            $syncData = [
                'patient_id' => $patientIdArray,
                'name' => $oldUser->name ?? 'Unknown User',
                'email' => $oldUser->email,
                'email_verified_at' => $oldUser->email_verified_at ?? null,
                'password' => $oldUser->password ?? bcrypt(Str::random(16)),
                'remember_token' => $oldUser->remember_token ?? null,
                'role' => $oldUser->role ?? ($oldUser->type ?? 'User'),
                'phone' => $oldUser->phone ?? null,
                'is_active' => isset($oldUser->is_active) ? (int) $oldUser->is_active : (isset($oldUser->active_status) ? (int) $oldUser->active_status : 1),
                'avatar' => $oldUser->avatar ?? 'uploads/avatar/avatar.png',
                'address' => $oldUser->address ?? null,
                'country' => $oldUser->country ?? null,
                'messenger_color' => $oldUser->messenger_color ?? '#2180f3',
                'country_code' => $oldUser->country_code ?? null,
                'phone_verified_at' => $oldUser->phone_verified_at ?? null,
                'UniqueId' => $oldUser->UniqueId ?? null,
                'dark_mode' => isset($oldUser->dark_mode) ? (int) $oldUser->dark_mode : 0,
                'lang' => $oldUser->lang ?? null,
                'social_type' => $oldUser->social_type ?? null,
            ];

            if ($existingUser) {
                if ($existingUser->trashed()) {
                    $existingUser->restore();
                    $existingUser->update($syncData);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'This user already exists in new portal.',
                    ], 409);
                }
            } else {
                User::create($syncData);
            }

            return response()->json([
                'status' => true,
                'message' => 'User synced successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::channel('stack')->error('Error syncing old user', [
                'old_user_id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while syncing the user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
