<?php

namespace App\Addons\billingafk;

use App\Plugins\AppPlugin;

class BillingAFK implements AppPlugin
{
    /**
     * @inheritDoc
     */
    public static function processEvents(\App\Plugins\PluginEvents $event): void
    {
        // Process plugin events here
        // Routes and Controllers are automatically registered from Routes/ and Controllers/ directories
        
        // Add your event listeners here as needed
    }

    /**
     * @inheritDoc
     */
    public static function pluginInstall(): void
    {
        // Plugin installation logic
        // Create tables, directories, etc.
    }

    /**
     * @inheritDoc
     */
    public static function pluginUpdate(?string $oldVersion, ?string $newVersion): void
    {
        // Plugin update logic
        // Migrate data, update configurations, etc.
        // $oldVersion contains the previous version (e.g., '1.0.0')
        // $newVersion contains the new version being installed (e.g., '1.0.1')
    }

    /**
     * @inheritDoc
     */
    public static function pluginUninstall(): void
    {
        // Plugin uninstallation logic
        // Clean up tables, files, etc.
    }
}