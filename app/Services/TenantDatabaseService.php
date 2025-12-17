<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class TenantDatabaseService
{
    /**
     * Set up a dynamic database connection for a tenant
     * 
     * @param string|null $tenantId The tenant database name (e.g., 'tenant_rafi1')
     * @return string The connection name to use
     */
    public static function setTenantConnection(?string $tenantId): string
    {
        // If no tenant ID provided, use default 'crm' connection (perfexcrm)
        if (empty($tenantId)) {
            return 'crm';
        }

        // If tenant ID doesn't start with 'tenant_', it's likely the main database
        if (!str_starts_with($tenantId, 'tenant_')) {
            // Check if it's the main perfexcrm database
            if ($tenantId === 'perfexcrm' || $tenantId === 'crm') {
                return 'crm';
            }
            // Otherwise, treat it as a tenant database
            $tenantId = 'tenant_' . $tenantId;
        }

        // Create a unique connection name for this tenant
        $connectionName = 'tenant_' . md5($tenantId);

        // Check if connection already exists
        if (!config("database.connections.{$connectionName}")) {
            // Get the base CRM connection config
            $crmConfig = config('database.connections.crm');

            // Create new connection config for this tenant
            $tenantConfig = array_merge($crmConfig, [
                'database' => $tenantId,
            ]);

            // Set the new connection in config
            Config::set("database.connections.{$connectionName}", $tenantConfig);

            // Purge any existing connection
            DB::purge($connectionName);
        }

        return $connectionName;
    }

    /**
     * Get the appropriate database connection based on tenant ID
     * 
     * @param string|null $tenantId
     * @return \Illuminate\Database\Connection
     */
    public static function getTenantConnection(?string $tenantId)
    {
        $connectionName = self::setTenantConnection($tenantId);
        return DB::connection($connectionName);
    }

    /**
     * Check if a tenant database exists
     * 
     * @param string $tenantId
     * @return bool
     */
    public static function tenantDatabaseExists(string $tenantId): bool
    {
        try {
            $databases = DB::connection('crm')
                ->select('SHOW DATABASES LIKE ?', [$tenantId]);
            
            return !empty($databases);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of all tenant databases
     * 
     * @return array
     */
    public static function getAllTenantDatabases(): array
    {
        try {
            $databases = DB::connection('crm')
                ->select("SHOW DATABASES LIKE 'tenant_%'");
            
            return array_map(function($db) {
                return reset($db);
            }, $databases);
        } catch (\Exception $e) {
            return [];
        }
    }
}
