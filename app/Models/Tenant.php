<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\DatabaseManager;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return ['id', 'tenancy_db_name'];
    }

    public function createDatabase(DatabaseManager $databaseManager): bool
    {
        $database = $this->database()->getName();

        // Skip creation if DB already exists (shared DB scenario)
        if ($databaseManager->databaseExists($database)) {
            return false;
        }

        return $databaseManager->createDatabase($database);
    }
}
