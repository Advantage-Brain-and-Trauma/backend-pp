<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\MedhiwaSpeciality;


class MedhiwaSpecialityLocation extends Model
{
    use HasFactory;

    protected $connection = 'medhiwa_ahcs';

    protected $table = 'speciality_location';

    protected $fillable = [
        'city',
        'speciality_id',
        'company_id',
        'status',
        'location_phone_no'
    ];


    public static function getLocationsWithSpecialities()
    {
        // ==============================
        // User Management Related Code
        // Refactored to reuse DB connection variable
        // ==============================
        
        $rows = self::query()
            ->whereNull('speciality_location.deleted_at')
            ->orderBy('speciality_location.city')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $allIds = $rows->flatMap(function ($row) {
            return explode(',', $row->speciality_id);
        })->map(fn($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($allIds)) {
            return collect();
        }

        $specialities = MedhiwaSpeciality::query()
            ->whereIn('id', $allIds)
            ->get()
            ->keyBy('id');

        return $rows->flatMap(function ($row) use ($specialities) {
            $ids = collect(explode(',', $row->speciality_id))
                ->map(fn($id) => (int) trim($id))
                ->filter();

            return $ids->map(function ($id) use ($row, $specialities) {
                if (!isset($specialities[$id])) {
                    return null;
                }

                $spec = $specialities[$id];

                return (object) [
                    'city' => $row->city,
                    'location_status' => $row->status,
                    'location_id' => $row->id,
                    'speciality_id' => $spec->id,
                    'short_name' => $spec->short_name,
                    'name' => $spec->name,
                    'allow_multiple' => $spec->allow_multiple,
                    'multiple_allowed_slots' => $spec->multiple_allowed_slots,
                    'multiple_slot_duration' => $spec->multiple_slot_duration,
                    'parent_id' => $spec->parent_id ?? null,
                ];
            })->filter();
        });
    }


}
