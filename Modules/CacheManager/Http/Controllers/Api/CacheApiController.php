<?php

namespace Modules\CacheManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CacheApiController extends Controller
{
    /**
     * Handle cache status request with validation
     */
    public function getCacheStatus(Request $request): JsonResponse
    {
        $status = $request->status ?? false;
        $type = $request->type ?? false;
        Artisan::call('cache:clear');

        $cache_status = Cache::remember('optimize_cache_status', 10080, function () use ($status, $type) {
            return [
                'status' => $status,
                'type' => $type,
            ];
        });

        // Return the posted data with cache status context
        return response()->json([
            'success' => true,
            'message' => 'Cache status request received successfully',
            'status' => $cache_status,
        ]);
    }
}
