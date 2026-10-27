<?php

namespace App\Updates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class FeedClient
{
    public function __construct(
        private readonly string $feedUrl,
        private readonly ?string $licenceKey = null,
        private readonly ?string $installationId = null,
    ) {}

    public static function fromConfig(?string $installationId = null): self
    {
        return new self(
            (string) config('updates.feed_url'),
            config('updates.licence.key'),
            $installationId,
        );
    }

    /**
     * Fetch and validate a channel feed.
     *
     * @return array<string, mixed>
     */
    public function fetch(string $channel): array
    {
        $headers = array_filter([
            'X-Licence-Key' => $this->licenceKey,
            'X-Installation-Id' => $this->installationId,
        ]);

        try {
            $response = Http::timeout(15)->acceptJson()->withHeaders($headers)
                ->get(rtrim($this->feedUrl, '/')."/{$channel}.json");
        } catch (ConnectionException $e) {
            throw new InvalidRelease('The update feed could not be reached: '.$e->getMessage());
        }

        $feed = $response->json();

        if (! $response->successful() || ! is_array($feed)) {
            throw new InvalidRelease("The update feed returned HTTP {$response->status()}.");
        }

        $errors = UpdateFeed::errors($feed);

        if ($errors !== []) {
            throw new InvalidRelease('The update feed is not valid: '.$errors[0]);
        }

        return $feed;
    }
}
