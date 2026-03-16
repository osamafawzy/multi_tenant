<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Modules\News\App\Models\News;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant 1: Alpha ──────────────────────────────────
        $alpha = Tenant::create(['id' => 'alpha']);
        $alpha->domains()->create(['domain' => 'alpha.news-tenant.ddev.site']);

        tenancy()->initialize($alpha);

        News::create([
            'title'       => 'Alpha First News',
            'description' => 'This is the first news article for Alpha tenant.',
        ]);

        News::create([
            'title'       => 'Alpha Second News',
            'description' => 'This is the second news article for Alpha tenant.',
        ]);

        tenancy()->end();

        // ── Tenant 2: Beta ───────────────────────────────────
        $beta = Tenant::create(['id' => 'beta']);
        $beta->domains()->create(['domain' => 'beta.news-tenant.ddev.site']);

        tenancy()->initialize($beta);

        News::create([
            'title'       => 'Beta First News',
            'description' => 'This is the first news article for Beta tenant.',
        ]);

        News::create([
            'title'       => 'Beta Second News',
            'description' => 'This is the second news article for Beta tenant.',
        ]);

        tenancy()->end();
    }
}
