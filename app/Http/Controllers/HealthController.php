<?php

namespace App\Http\Controllers;

use App\Support\HealthCheck;
use App\Support\LocalRevision;
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

        $body = [
            'status' => $result['ok'] ? 'ok' : 'failing',
            'version' => $result['version'],
            'checks' => $result['checks'],
        ];

        // The live preview gate checks the Laragon preview serves this checkout through FastCGI.
        if (app()->environment('local')) {
            $body['local'] = [
                'php' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'git_revision' => (new LocalRevision(base_path()))->current(),
            ];
        }

        return response()->json($body, $result['ok'] ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
