<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Models\UserFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FunnelApiController extends Controller
{
    /**
     * GET /api/funnels
     *
     * Returns all active funnels with their name and full public URL.
     *
     * Response example:
     * {
     *   "status": true,
     *   "message": "Funnels retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "akmal",
     *       "url": "https://yourdomain.com/funnel/akmal-t6cuId"
     *     }
     *   ]
     * }
     */
    // public function getPatientFunnels(Request $request)
    // {
    //     try{
    //         $userFunnels = UserFunnel::where('user_id', $request->user()->id)->pluck('funnel_id');
    //         $funnels = Funnel::whereIn('id', $userFunnels)
    //             ->where('status', 'active')
    //             ->get(['id', 'name', 'slug']);

    //         $funnels->transform(function ($funnel) {
    //             return [
    //                 'id' => $funnel->id,
    //                 'name' => $funnel->name,
    //                 'url' => url('/funnel/' . $funnel->slug),
    //             ];
    //         });

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Funnels retrieved successfully.',
    //             'data' => $funnels,
    //         ],200);
    //     }catch (\Throwable $e) {
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage(),
    //             'message' => 'Error fetching patient form data'
    //         ], 500);
    //     }
    // }

    public function getPatientFunnels(Request $request)
    {
        try {
            Log::channel('patient_funnel')->info('Fetching patient funnels - Start', [
                'user_id' => $request->user()->id
            ]);

            $userFunnels = UserFunnel::where('user_id', $request->user()->id)
                ->pluck('funnel_id');

            Log::channel('patient_funnel')->info('User funnel IDs fetched', [
                'funnel_ids' => $userFunnels
            ]);

            $funnels = Funnel::whereIn('id', $userFunnels)
                ->where('status', 'active')
                ->get(['id', 'name', 'slug']);

            $funnels->transform(function ($funnel) {
                return [
                    'id' => $funnel->id,
                    'name' => $funnel->name,
                    'url' => url('/funnel/' . $funnel->slug),
                ];
            });

            Log::channel('patient_funnel')->info('Fetching patient funnels - Success', [
                'user_id' => $request->user()->id,
                'total_funnels' => $funnels->count()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Funnels retrieved successfully.',
                'data' => $funnels,
            ], 200);

        } catch (\Throwable $e) {

            Log::channel('patient_funnel')->error('Error fetching patient funnels', [
                'user_id' => $request->user()->id ?? null,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error fetching patient form data'
            ], 500);
        }
    }
}
