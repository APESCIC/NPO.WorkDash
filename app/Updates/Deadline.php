<?php

namespace App\Updates;

/**
 * The time budget for one step's work in a single request.
 */
final class Deadline
{
    private readonly float $endsAt;

    public function __construct(float $seconds)
    {
        $this->endsAt = microtime(true) + $seconds;
    }

    public static function fromConfig(): self
    {
        return new self((float) config('updates.step_budget_seconds'));
    }

    public function passed(): bool
    {
        return microtime(true) >= $this->endsAt;
    }
}
