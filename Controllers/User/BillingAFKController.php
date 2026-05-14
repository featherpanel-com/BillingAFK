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

namespace App\Addons\billingafk\Controllers\User;

use App\App;
use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Config\ConfigInterface;
use App\Addons\billingafk\Chat\AFKUserStats;
use App\Addons\billingafk\Helpers\AFKHelper;
use App\Addons\billingafk\Chat\AFKDailyUsage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;

#[OA\Tag(name: 'User - Billing AFK', description: 'AFK rewards management for users')]
class BillingAFKController
{
    #[OA\Get(
        path: '/api/user/billingafk/status',
        summary: 'Get AFK status',
        description: 'Get the current user\'s AFK session status',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Status retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getStatus(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!AFKHelper::isEnabled()) {
            return ApiResponse::error('AFK rewards are currently disabled', 'AFK_DISABLED', 403);
        }

        $settings = AFKHelper::getSettings();
        $userCredits = CreditsHelper::getUserCredits($user['id']);
        $stats = AFKUserStats::getOrCreate($user['id']);
        $dailyUsage = AFKDailyUsage::getTodayUsage($user['id']);

        $captchaConfig = [];
        if ($settings['require_captcha'] ?? false) {
            $config = App::getInstance(true)->getConfig();
            $provider = $config->getSetting(ConfigInterface::CAPTCHA_PROVIDER, 'turnstile');
            $siteKey = '';

            switch ($provider) {
                case 'turnstile':
                    $siteKey = $config->getSetting(ConfigInterface::TURNSTILE_KEY_PUB, '');
                    break;
                case 'hcaptcha':
                    $siteKey = $config->getSetting(ConfigInterface::HCAPTCHA_SITE_KEY, '');
                    break;
                case 'recaptcha':
                    $siteKey = $config->getSetting(ConfigInterface::RECAPTCHA_SITE_KEY, '');
                    break;
                case 'friendlycaptcha':
                    $siteKey = $config->getSetting(ConfigInterface::FRIENDLY_CAPTCHA_SITE_KEY, '');
                    break;
                case 'reforge':
                    $siteKey = $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_SITE_KEY, '');
                    break;
            }

            $captchaConfig = [
                'provider' => $provider,
                'enabled' => $config->getSetting(ConfigInterface::TURNSTILE_ENABLED, 'false') === 'true',
                'theme' => $config->getSetting(ConfigInterface::APP_THEME_DEFAULT, 'dark') === 'dark' ? 'dark' : 'light',
                'turnstile' => [
                    'site_key' => $config->getSetting(ConfigInterface::TURNSTILE_KEY_PUB, ''),
                ],
                'hcaptcha' => [
                    'site_key' => $config->getSetting(ConfigInterface::HCAPTCHA_SITE_KEY, ''),
                ],
                'recaptcha' => [
                    'site_key' => $config->getSetting(ConfigInterface::RECAPTCHA_SITE_KEY, ''),
                    'version' => $config->getSetting(ConfigInterface::RECAPTCHA_VERSION, 'v2'),
                    'v3_action' => $config->getSetting(ConfigInterface::RECAPTCHA_V3_ACTION, 'submit'),
                ],
                'friendlycaptcha' => [
                    'site_key' => $config->getSetting(ConfigInterface::FRIENDLY_CAPTCHA_SITE_KEY, ''),
                ],
                'reforge' => [
                    'site_key' => $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_SITE_KEY, ''),
                    'widget_type' => $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_WIDGET_TYPE, 'checkbox'),
                    'theme' => $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_THEME, 'auto'),
                    'size' => $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_SIZE, 'normal'),
                    'lang' => $config->getSetting(ConfigInterface::REFORGE_CAPTCHA_LANG, 'en'),
                ],
            ];
        }

        return ApiResponse::success(array_merge($settings, [
            'minutes_afk' => (int) ($stats['minutes_afk'] ?? 0),
            'last_seen_afk' => (int) ($stats['last_seen_afk'] ?? 0),
            'user_credits' => $userCredits,
            'user_credits_formatted' => CurrencyHelper::formatAmount($userCredits),
            'daily_usage' => [
                'credits_earned_today' => $dailyUsage ? (int) $dailyUsage['credits_earned'] : 0,
                'sessions_today' => $dailyUsage ? (int) $dailyUsage['sessions_count'] : 0,
                'time_seconds_today' => $dailyUsage ? (int) $dailyUsage['time_seconds'] : 0,
            ],
            'daily_limits' => [
                'max_credits_per_day' => $settings['max_credits_per_day'] ?? null,
                'max_sessions_per_day' => $settings['max_sessions_per_day'] ?? null,
                'max_time_per_day_seconds' => $settings['max_time_per_day_seconds'] ?? null,
            ],
            'captcha' => $captchaConfig,
        ]), 'Status retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/user/billingafk/start',
        summary: 'Start AFK session',
        description: 'Start a new AFK session to earn credits',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'AFK session started successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'AFK disabled or already active'),
        ]
    )]
    public function startAFK(Request $request): Response
    {
        // No-op: Sessions are not used, just return success (like MythicalDash)
        return ApiResponse::success([], 'AFK mode can be started from frontend', 200);
    }

    #[OA\Post(
        path: '/api/user/billingafk/stop',
        summary: 'Stop AFK session',
        description: 'Stop the current AFK session',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'AFK session stopped successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'No active session found'),
        ]
    )]
    public function stopAFK(Request $request): Response
    {
        // No-op: Sessions are not used, just return success (like MythicalDash)
        return ApiResponse::success([], 'AFK mode can be stopped from frontend', 200);
    }

    #[OA\Post(
        path: '/api/user/billingafk/claim',
        summary: 'Claim AFK rewards',
        description: 'Claim credits earned from the current AFK session',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Rewards claimed successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'No active session or no rewards to claim'),
        ]
    )]
    public function claimRewards(Request $request): Response
    {
        // No-op: Credits are awarded automatically via work endpoint (like MythicalDash)
        return ApiResponse::success([], 'Credits are awarded automatically via work endpoint', 200);
    }

    #[OA\Post(
        path: '/api/user/billingafk/work',
        summary: 'Update AFK work (periodic call)',
        description: 'Called every minute to increment AFK time and award credits based on config (like MythicalDash)',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'AFK work updated successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function work(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!AFKHelper::isEnabled()) {
            return ApiResponse::error('AFK rewards are currently disabled', 'AFK_DISABLED', 403);
        }

        // Get settings
        $settings = AFKHelper::getSettings();

        // Abuse prevention: Increment AFK time with rate limiting (atomic operation)
        // This prevents spam by checking and updating in a single transaction
        // Must wait at least 60 seconds (1 minute) between calls
        $updatedStats = AFKUserStats::incrementAFKTime($user['id'], 60);
        if (!$updatedStats) {
            return ApiResponse::error(
                'Please wait at least 60 seconds (1 minute) between work calls. This prevents abuse.',
                'RATE_LIMIT_EXCEEDED',
                429
            );
        }

        // Get current values from updated stats
        $currentMinutesAfk = (int) ($updatedStats['minutes_afk'] ?? 0);
        $newMinutesAfk = $currentMinutesAfk; // Already incremented
        $userCredits = CreditsHelper::getUserCredits($user['id']);

        // Calculate credits to award based on configuration
        $creditsToAward = 0;

        // Priority 1: Use credits_per_minute if set and > 0
        if (isset($settings['credits_per_minute']) && $settings['credits_per_minute'] > 0) {
            // Award credits based on credits_per_minute
            // For fractional credits (e.g., 0.5), we need to track accumulation
            // Simple approach: award floor value each minute, track remainder
            $creditsPerMinute = (float) $settings['credits_per_minute'];

            // Get accumulated fractional credits from stats (we'll store in a separate field or calculate)
            // For now, simple approach: award credits when accumulated value >= 1
            // We'll use a simple calculation: every N minutes, award based on credits_per_minute
            if ($creditsPerMinute >= 1.0) {
                // Award full credits each minute
                $creditsToAward = (int) floor($creditsPerMinute);
            } else {
                // Fractional credits: award 1 credit every (1/credits_per_minute) minutes
                $minutesPerCredit = (int) ceil(1.0 / $creditsPerMinute);
                $creditsToAward = ($newMinutesAfk % $minutesPerCredit === 0) ? 1 : 0;
            }
        }
        // Priority 2: Use minutes_per_credit if set
        elseif (isset($settings['minutes_per_credit']) && $settings['minutes_per_credit'] > 0) {
            // Only award credits when minutes_afk is divisible by minutes_per_credit
            $minutesPerCredit = (float) $settings['minutes_per_credit'];
            if ($minutesPerCredit >= 1.0) {
                $creditsToAward = ($newMinutesAfk % (int) $minutesPerCredit === 0) ? 1 : 0;
            } else {
                // If less than 1 minute per credit, award multiple credits
                $creditsPerMinute = 1.0 / $minutesPerCredit;
                $creditsToAward = (int) floor($creditsPerMinute);
            }
        }
        // Default: 1 credit per minute
        else {
            $creditsToAward = 1;
        }

        // Check daily limits before awarding credits
        if ($creditsToAward > 0) {
            $limitCheck = AFKDailyUsage::checkDailyLimits($user['id'], $settings);
            if (!$limitCheck['allowed']) {
                // Don't award credits if daily limit reached, but still update time
                $creditsToAward = 0;
            } else {
                // Check if awarding would exceed daily credits limit
                $usage = AFKDailyUsage::getTodayUsage($user['id']);
                $creditsToday = $usage ? (int) $usage['credits_earned'] : 0;
                if ($settings['max_credits_per_day'] !== null) {
                    $remainingDailyCredits = $settings['max_credits_per_day'] - $creditsToday;
                    if ($remainingDailyCredits <= 0) {
                        $creditsToAward = 0;
                    } else {
                        $creditsToAward = min($creditsToAward, $remainingDailyCredits);
                    }
                }
            }
        }

        // Add credits atomically if any to award
        $newTotalCredits = $userCredits;
        if ($creditsToAward > 0) {
            $added = CreditsHelper::addUserCredits($user['id'], $creditsToAward);
            if ($added) {
                $newTotalCredits = $userCredits + $creditsToAward;

                // Update daily usage
                AFKDailyUsage::updateUsage($user['id'], $creditsToAward, 60, false);

                // Update total credits earned in stats
                $pdo = \App\Chat\Database::getPdoConnection();
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE featherpanel_billingafk_user_stats SET 
                            total_credits_earned = total_credits_earned + :credits,
                            total_time_seconds = total_time_seconds + 60
                        WHERE user_id = :user_id'
                    );
                    $stmt->execute([
                        'user_id' => $user['id'],
                        'credits' => $creditsToAward,
                    ]);
                } catch (\PDOException $e) {
                    App::getInstance(true)->getLogger()->error('Failed to update AFK stats: ' . $e->getMessage());
                }
            } else {
                App::getInstance(true)->getLogger()->error('Failed to add AFK credits for user: ' . $user['id']);
            }
        } else {
            // Still update total time even if no credits awarded
            $pdo = \App\Chat\Database::getPdoConnection();
            try {
                $stmt = $pdo->prepare(
                    'UPDATE featherpanel_billingafk_user_stats SET 
                        total_time_seconds = total_time_seconds + 60
                    WHERE user_id = :user_id'
                );
                $stmt->execute(['user_id' => $user['id']]);
            } catch (\PDOException $e) {
                App::getInstance(true)->getLogger()->error('Failed to update AFK time: ' . $e->getMessage());
            }
        }

        return ApiResponse::success([
            'credits_awarded' => $creditsToAward,
            'total_credits' => $newTotalCredits,
            'total_afk_time' => $newMinutesAfk,
        ], 'AFK stats updated', 200);
    }

    #[OA\Get(
        path: '/api/user/billingafk/stats',
        summary: 'Get user AFK statistics',
        description: 'Get the current user\'s AFK statistics',
        tags: ['User - Billing AFK'],
        responses: [
            new OA\Response(response: 200, description: 'Statistics retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getStats(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $stats = AFKUserStats::getOrCreate($user['id']);
        if (!$stats) {
            return ApiResponse::error('Failed to retrieve stats', 'STATS_FAILED', 500);
        }

        // Format time
        $totalMinutes = (int) floor($stats['total_time_seconds'] / 60);
        $totalHours = (int) floor($totalMinutes / 60);
        $totalDays = (int) floor($totalHours / 24);

        return ApiResponse::success([
            'total_time_seconds' => (int) $stats['total_time_seconds'],
            'total_time_formatted' => sprintf('%dd %dh %dm', $totalDays, $totalHours % 24, $totalMinutes % 60),
            'total_credits_earned' => (int) $stats['total_credits_earned'],
            'total_credits_formatted' => CurrencyHelper::formatAmount((int) $stats['total_credits_earned']),
            'sessions_count' => (int) $stats['sessions_count'],
            'last_session_at' => $stats['last_session_at'],
        ], 'Statistics retrieved successfully', 200);
    }
}
