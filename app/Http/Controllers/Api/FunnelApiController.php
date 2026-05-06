<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use Illuminate\Http\Request;

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
    public function index(Request $request)
    {
        $funnels = Funnel::select('id', 'name', 'slug')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($funnel) {
                return [
                    'id'   => $funnel->id,
                    'name' => $funnel->name,
                    'url'  => url('/funnel/' . $funnel->slug),
                ];
            });

        return response()->json([
            'status'  => true,
            'message' => 'Funnels retrieved successfully.',
            'data'    => $funnels,
        ]);
    }
}
