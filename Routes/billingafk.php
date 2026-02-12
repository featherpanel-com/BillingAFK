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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingafk\Controllers\User\BillingAFKController as UserController;
use App\Addons\billingafk\Controllers\Admin\BillingAFKController as AdminController;

return function (RouteCollection $routes): void {
    // User Routes (require authentication)
    // Get AFK status
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-status',
        '/api/user/billingafk/status',
        function (Request $request) {
            return (new UserController())->getStatus($request);
        },
        ['GET']
    );

    // Start AFK session
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-start',
        '/api/user/billingafk/start',
        function (Request $request) {
            return (new UserController())->startAFK($request);
        },
        ['POST']
    );

    // Stop AFK session
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-stop',
        '/api/user/billingafk/stop',
        function (Request $request) {
            return (new UserController())->stopAFK($request);
        },
        ['POST']
    );

    // Claim rewards
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-claim',
        '/api/user/billingafk/claim',
        function (Request $request) {
            return (new UserController())->claimRewards($request);
        },
        ['POST']
    );

    // Get user stats
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-stats',
        '/api/user/billingafk/stats',
        function (Request $request) {
            return (new UserController())->getStats($request);
        },
        ['GET']
    );

    // Work endpoint - called periodically to award credits
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingafk-user-work',
        '/api/user/billingafk/work',
        function (Request $request) {
            return (new UserController())->work($request);
        },
        ['POST']
    );

    // Admin Routes
    // Get settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingafk-admin-settings',
        '/api/admin/billingafk/settings',
        function (Request $request) {
            return (new AdminController())->getSettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingafk-admin-settings-update',
        '/api/admin/billingafk/settings',
        function (Request $request) {
            return (new AdminController())->updateSettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Get all user stats
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingafk-admin-stats',
        '/api/admin/billingafk/stats',
        function (Request $request) {
            return (new AdminController())->getAllStats($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Get user stats
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingafk-admin-user-stats',
        '/api/admin/billingafk/user/{userId}/stats',
        function (Request $request, array $args) {
            $userId = (int) ($args['userId'] ?? 0);
            if (!$userId) {
                return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getUserStats($request, $userId);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );
};
