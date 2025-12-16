<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
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

