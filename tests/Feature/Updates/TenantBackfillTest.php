<?php

use App\Models\Tenant;
use App\Support\TenantBackfill;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Temporary tables do not commit the RefreshDatabase transaction.
    DB::statement('CREATE TEMPORARY TABLE backfill_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, value INT NOT NULL, INDEX (tenant_id))');

    $this->tenants = Tenant::factory()->count(3)->create();

    foreach ($this->tenants as $index => $tenant) {
        foreach (range(1, 5 + $index) as $value) {
            DB::table('backfill_items')->insert(['tenant_id' => $tenant->id, 'value' => $value]);
        }
    }
});

test('backfills process one tenant at a time in small chunks', function () {
    $chunks = [];

    $processed = TenantBackfill::each('backfill_items', function ($rows, int $tenantId) use (&$chunks) {
        $chunks[] = [$tenantId, $rows->pluck('tenant_id')->unique()->values()->all(), $rows->count()];
    }, chunk: 2);

    expect($processed)->toBe(18);

    foreach ($chunks as [$tenantId, $tenantIds, $count]) {
        expect($tenantIds)->toBe([$tenantId])->and($count)->toBeLessThanOrEqual(2);
    }

    expect(collect($chunks)->pluck(0)->unique()->values()->all())->toBe($this->tenants->pluck('id')->all());
});

test('a backfill can continue after the last finished tenant', function () {
    $seen = [];

    TenantBackfill::each('backfill_items', function ($rows, int $tenantId) use (&$seen) {
        $seen[] = $tenantId;
    }, afterTenant: $this->tenants[0]->id);

    expect(array_values(array_unique($seen)))->toBe([$this->tenants[1]->id, $this->tenants[2]->id]);
});
