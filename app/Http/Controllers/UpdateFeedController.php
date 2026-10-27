<?php

namespace App\Http\Controllers;

use App\Models\Licence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateFeedController extends Controller
{
    /**
     * Serve the published channel feed. With licence checks switched on,
     * download links are withheld unless a valid X-Licence-Key is sent; the
     * requesting install keeps working either way.
     */
    public function __invoke(Request $request, string $channel): JsonResponse
    {
        abort_unless(in_array($channel, config('updates.channels'), true), 404);

        $feed = Cache::remember("updates.feed.{$channel}", now()->addMinutes(10), function () use ($channel) {
            $response = Http::timeout(10)->get(rtrim((string) config('updates.feed_url'), '/')."/{$channel}.json");

            return $response->successful() ? $response->json() : null;
        });

        abort_unless(is_array($feed), 503, 'The update feed is unavailable.');

        if (config('updates.licence.required')) {
            $status = Licence::statusFor($request->header('X-Licence-Key'));
            $feed['licence'] = ['status' => $status];

            if ($status !== 'valid') {
                $feed['releases'] = array_map(
                    fn (array $release) => collect($release)->except(['download_url', 'signature_url'])->all(),
                    $feed['releases'] ?? [],
                );
            }
        }

        return response()->json($feed);
    }
}
