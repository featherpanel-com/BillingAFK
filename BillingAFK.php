<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingafk;

use App\Plugins\AppPlugin;
use App\Addons\billingafk\Helpers\AFKHelper;

class BillingAFK implements AppPlugin
{
    public static function processEvents(\App\Plugins\PluginEvents $event): void
    {
        // Process plugin events here
        // Routes and Controllers are automatically registered from Routes/ and Controllers/ directories

        // Add your event listeners here as needed
    }

    public static function pluginInstall(): void
    {
        // Plugin installation logic
        // Create tables, directories, etc.
        AFKHelper::setSetting('require_active_tab', '1');
        if (AFKHelper::getSetting('is_enabled') === null) {
            AFKHelper::setSetting('is_enabled', '1');
        }
    }

    public static function pluginUpdate(?string $oldVersion, ?string $newVersion): void
    {
        // Ensure sidebar visibility has a concrete is_enabled value (null hides the page).
        if (AFKHelper::getSetting('is_enabled') === null) {
            AFKHelper::setSetting('is_enabled', '1');
        }
    }

    public static function pluginUninstall(): void
    {
        // Plugin uninstallation logic
        // Clean up tables, files, etc.
    }
}
