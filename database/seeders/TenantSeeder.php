<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Modules\News\App\Models\News;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    protected array $tenantDbs = ['shared_db_1', 'shared_db_2', 'shared_db_3'];

    public function run(): void
    {
        // Step 1: Drop all tenant DBs cleanly before starting
        foreach ($this->tenantDbs as $db) {
            DB::statement("DROP DATABASE IF EXISTS `{$db}`");
        }

        $groups = [
            'shared_db_1' => ['alpha', 'beta'],
            'shared_db_2' => ['gamma', 'delta'],
            'shared_db_3' => ['omega', 'sigma'],
        ];

        foreach ($groups as $dbName => $tenantIds) {
            $isFirstTenant = true;

            foreach ($tenantIds as $id) {
                $tenant = Tenant::create([
                    'id'              => $id,
                    'tenancy_db_name' => $dbName,
                ]);

                $tenant->domains()->create([
                    'domain' => "{$id}.news-tenant.ddev.site",
                ]);

                if ($isFirstTenant) {
                    // Create DB and migrate ONCE per group
                    $tenant->database()->manager()->createDatabase($tenant);
                    \Artisan::call('tenants:migrate', ['--tenants' => [$id]]);
                    $isFirstTenant = false;
                }
                // Second tenant skips DB creation & migration ✅

                // Seed news scoped to this tenant
                tenancy()->initialize($tenant);

                News::create([
                    'tenant_id' => $tenant->id,
                    'title'       => ucfirst($id) . ' First News',
                    'description' => 'First news article for ' . ucfirst($id),
                ]);

                News::create([
                    'tenant_id' => $tenant->id,
                    'title'       => ucfirst($id) . ' Second News',
                    'description' => 'Second news article for ' . ucfirst($id),
                ]);

                tenancy()->end();
            }
        }
    }
}
