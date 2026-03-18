<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveTenantByFile
{
    public function handle(Request $request, Closure $next)
    {
        DB::enableQueryLog();

        $domain = $request->getHost();
        $map    = config('tenant_map');

        // Central domain — skip tenant resolution
        if (in_array($domain, config('tenancy.central_domains', []))) {
            return $next($request);
        }

        // Unknown domain — abort
        if (! isset($map[$domain])) {
            throw new NotFoundHttpException("Tenant not found for domain: {$domain}");
        }

        $tenantData = $map[$domain];
        // 👇 Switch DB connection dynamically — NO central DB hit
        Config::set('database.connections.tenant.database', $tenantData['db']);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // 👇 Store current tenant in app container
        app()->instance('currentTenant', (object)[
            'id'     => $tenantData['tenant_id'],
            'db'     => $tenantData['db'],
            'domain' => $domain,
        ]);

        // 👇 Override default DB connection for models
        Config::set('database.default', 'tenant');

        DB::connection('mysql')->enableQueryLog();   // central
        DB::connection('tenant')->enableQueryLog();  // tenant

        $response = $next($request);

        // 👇 Dump all queries that ran during this request
        $queries = DB::getQueryLog();
        \Log::info('CENTRAL DB queries:', DB::connection('mysql')->getQueryLog());
        \Log::info('TENANT DB queries:', DB::connection('tenant')->getQueryLog());
        return $response;
    }
}
