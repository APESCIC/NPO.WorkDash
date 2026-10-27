<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Data backfills that respect tenant isolation: rows are processed one
 * tenant at a time, in id-ordered chunks, so a callback never sees two
 * tenants' rows together and each chunk stays small enough for a short
 * PHP time limit.
 */
final class TenantBackfill
{
    /**
     * @param  callable(Collection<int, stdClass>, int): void  $callback  receives the chunk and its tenant_id
     * @return int rows processed
     */
    public static function each(string $table, callable $callback, int $chunk = 500, ?int $afterTenant = null): int
    {
        $processed = 0;

        $tenants = Tenant::query()->when($afterTenant, fn ($query) => $query->where('id', '>', $afterTenant))->orderBy('id')->pluck('id');

        foreach ($tenants as $tenantId) {
            DB::table($table)->where('tenant_id', $tenantId)->orderBy('id')->chunkById($chunk, function (Collection $rows) use ($callback, $tenantId, &$processed) {
                $callback($rows, (int) $tenantId);
                $processed += $rows->count();
            });
        }

        return $processed;
    }
}
