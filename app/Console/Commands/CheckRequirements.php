<?php

namespace App\Console\Commands;

use App\Install\AppRequirements;
use App\Install\RequirementsChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckRequirements extends Command
{
    protected $signature = 'app:check
        {--json : Print the results as JSON}
        {--no-outbound : Skip the outbound HTTPS check}';

    protected $description = 'Check this server against the NPO.WorkDash requirements (same checks as the install wizard)';

    public function handle(): int
    {
        $checker = AppRequirements::checker(! $this->option('no-outbound'));
        $items = $checker->run();

        try {
            $items = [...$items, ...$checker->checkDatabase(DB::connection()->getPdo())];
        } catch (Throwable $e) {
            $items[] = ['key' => 'db_version', 'label' => 'Database version', 'status' => RequirementsChecker::WARN, 'detail' => 'Not connected: '.$e->getMessage(), 'hints' => []];
        }

        $summary = RequirementsChecker::summary($items);

        if ($this->option('json')) {
            $this->line((string) json_encode(['summary' => $summary, 'items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['', 'Check', 'Result'], array_map(fn (array $item) => [
                strtoupper($item['status']),
                $item['label'],
                $item['detail'],
            ], $items));

            $this->line('Overall: '.strtoupper($summary));
        }

        return $summary === RequirementsChecker::FAIL ? self::FAILURE : self::SUCCESS;
    }
}
