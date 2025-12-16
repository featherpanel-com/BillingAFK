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

namespace App\Addons\billingafk\Controllers\Admin;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingafk\Chat\AFKUserStats;
use App\Addons\billingafk\Helpers\AFKHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CurrencyHelper;

#[OA\Tag(name: 'Admin - Billing AFK', description: 'AFK rewards administration')]
class BillingAFKController
{
    #[OA\Get(
        path: '/api/admin/billingafk/settings',
        summary: 'Get AFK settings',
        description: 'Get all AFK configuration settings',
        tags: ['Admin - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        $settings = AFKHelper::getSettings();

        return ApiResponse::success($settings, 'Settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingafk/settings',
        summary: 'Update AFK settings',
        description: 'Update AFK configuration settings',
        tags: ['Admin - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        try {
            AFKHelper::updateSettings($data);

            return ApiResponse::success(AFKHelper::getSettings(), 'Settings updated successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update settings: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingafk/stats',
        summary: 'Get all user AFK statistics',
        description: 'Get AFK statistics for all users',
        tags: ['Admin - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Statistics retrieved successfully'),
        ]
    )]
    public function getAllStats(Request $request): Response
    {
        $page = max(1, (int) ($request->query->get('page') ?? 1));
        $limit = max(1, min(100, (int) ($request->query->get('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $stats = AFKUserStats::getAllStats($limit, $offset);

        // Format the stats
        $formattedStats = array_map(function ($stat) {
            $totalMinutes = (int) floor($stat['total_time_seconds'] / 60);
            $totalHours = (int) floor($totalMinutes / 60);
            $totalDays = (int) floor($totalHours / 24);

            // Get minutes_afk and last_seen_afk (handle case where columns might not exist)
            $minutesAfk = isset($stat['minutes_afk']) ? (int) $stat['minutes_afk'] : 0;
            $lastSeenAfk = isset($stat['last_seen_afk']) ? (int) $stat['last_seen_afk'] : 0;

            // If minutes_afk is 0 but total_time_seconds > 0, use total_time_seconds as fallback
            if ($minutesAfk === 0 && $totalMinutes > 0) {
                $minutesAfk = $totalMinutes;
            }

            $lastSeenFormatted = $lastSeenAfk > 0
                ? date('Y-m-d H:i:s', $lastSeenAfk)
                : 'Never';

            return [
                'user_id' => (int) $stat['user_id'],
                'username' => $stat['username'] ?? 'Unknown',
                'email' => $stat['email'] ?? null,
                'total_time_seconds' => (int) $stat['total_time_seconds'],
                'total_time_formatted' => sprintf('%dd %dh %dm', $totalDays, $totalHours % 24, $totalMinutes % 60),
                'total_credits_earned' => (int) $stat['total_credits_earned'],
                'total_credits_formatted' => CurrencyHelper::formatAmount((int) $stat['total_credits_earned']),
                'minutes_afk' => $minutesAfk,
                'last_seen_afk' => $lastSeenFormatted,
            ];
        }, $stats);

        // Get total count
        $pdo = \App\Chat\Database::getPdoConnection();
        $countStmt = $pdo->query('SELECT COUNT(*) as count FROM featherpanel_billingafk_user_stats');
        $total = (int) $countStmt->fetch(\PDO::FETCH_ASSOC)['count'];

        return ApiResponse::success([
            'data' => $formattedStats,
            'meta' => [
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit),
                ],
            ],
        ], 'Statistics retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingafk/user/{userId}/stats',
        summary: 'Get user AFK statistics',
        description: 'Get AFK statistics for a specific user',
        tags: ['Admin - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Statistics retrieved successfully'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function getUserStats(Request $request, int $userId): Response
    {
        if ($userId <= 0) {
            return ApiResponse::error('Invalid user ID', 'INVALID_ID', 400);
        }

        $stats = AFKUserStats::getOrCreate($userId);
        if (!$stats) {
            return ApiResponse::error('User not found', 'USER_NOT_FOUND', 404);
        }

        $totalMinutes = (int) floor($stats['total_time_seconds'] / 60);
        $totalHours = (int) floor($totalMinutes / 60);
        $totalDays = (int) floor($totalHours / 24);

        $minutesAfk = (int) ($stats['minutes_afk'] ?? 0);
        $lastSeenAfk = (int) ($stats['last_seen_afk'] ?? 0);
        $lastSeenFormatted = $lastSeenAfk > 0
            ? date('Y-m-d H:i:s', $lastSeenAfk)
            : 'Never';

        return ApiResponse::success([
            'user_id' => (int) $stats['user_id'],
            'total_time_seconds' => (int) $stats['total_time_seconds'],
            'total_time_formatted' => sprintf('%dd %dh %dm', $totalDays, $totalHours % 24, $totalMinutes % 60),
            'total_credits_earned' => (int) $stats['total_credits_earned'],
            'total_credits_formatted' => CurrencyHelper::formatAmount((int) $stats['total_credits_earned']),
            'minutes_afk' => $minutesAfk,
            'last_seen_afk' => $lastSeenFormatted,
        ], 'Statistics retrieved successfully', 200);
    }
}
