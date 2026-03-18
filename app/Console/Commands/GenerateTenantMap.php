<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;

class GenerateTenantMap extends Command
{
    protected $signature   = 'tenant:generate-map';
    protected $description = 'Regenerate config/tenant_map.php from database';

    public function handle(): void
    {
        $tenants = Tenant::with('domains')->get();

        $map = [];
        foreach ($tenants as $tenant) {
            foreach ($tenant->domains as $domain) {
                $map[$domain->domain] = [
                    'db'        => $tenant->tenancy_db_name,
                    'tenant_id' => $tenant->id,
                ];
            }
        }

        $export = "<?php\n\nreturn " . var_export($map, true) . ";\n";
        file_put_contents(config_path('tenant_map.php'), $export);

        $this->info('tenant_map.php regenerated with ' . count($map) . ' domains ✅');
    }
}
