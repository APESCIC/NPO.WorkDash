<?php

namespace App\Http\Controllers;

use App\Support\HealthCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * 200 only when every check passes, 503 otherwise. Used by the updater,
     * Cloudron deploys and uptime monitoring.
     */
    public function __invoke(HealthCheck $health): JsonResponse
    {
        $result = $health->run();

        return response()->json([
            'status' => $result['ok'] ? 'ok' : 'failing',
            'version' => $result['version'],
            'checks' => $result['checks'],
        ], $result['ok'] ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
