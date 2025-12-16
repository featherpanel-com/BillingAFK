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

namespace App\Addons\billingafk\Controllers\User;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingafk\Chat\AFKSession;
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

        $session = AFKSession::getActiveSession($user['id']);
        $settings = AFKHelper::getSettings();
        $javascriptInjection = $settings['javascript_injection'] ?? '';

        // Get user's current credits balance
        $userCredits = CreditsHelper::getUserCredits($user['id']);

        // Get daily usage and limits
        $dailyUsage = AFKDailyUsage::getTodayUsage($user['id']);
        $dailyUsageData = [
            'credits_earned_today' => $dailyUsage ? (int) $dailyUsage['credits_earned'] : 0,
            'sessions_today' => $dailyUsage ? (int) $dailyUsage['sessions_count'] : 0,
            'time_seconds_today' => $dailyUsage ? (int) $dailyUsage['time_seconds'] : 0,
        ];

        // Get daily limits
        $dailyLimits = [
            'max_credits_per_day' => $settings['max_credits_per_day'],
            'max_sessions_per_day' => $settings['max_sessions_per_day'],
            'max_time_per_day_seconds' => $settings['max_time_per_day_seconds'],
        ];

        if (!$session) {
            return ApiResponse::success([
                'is_afk' => false,
                'started_at' => null,
                'credits_earned' => 0,
                'credits_formatted' => CurrencyHelper::formatAmount(0),
                'time_elapsed' => 0,
                'next_reward_in' => null,
                'javascript_injection' => $javascriptInjection,
                'user_credits' => $userCredits,
                'user_credits_formatted' => CurrencyHelper::formatAmount($userCredits),
                'daily_usage' => $dailyUsageData,
                'daily_limits' => $dailyLimits,
            ], 'Status retrieved successfully', 200);
        }

        // Calculate current time elapsed
        $startedAt = new \DateTime($session['started_at']);
        $now = new \DateTime();
        $timeElapsed = (int) ($now->getTimestamp() - $startedAt->getTimestamp());

        // Apply max session duration limit
        if ($settings['max_session_duration_seconds'] !== null && $timeElapsed > $settings['max_session_duration_seconds']) {
            $timeElapsed = $settings['max_session_duration_seconds'];
        }

        // Calculate credits earned (only unclaimed credits)
        $totalCreditsEarned = AFKHelper::calculateCredits($timeElapsed);
        $alreadyClaimed = AFKSession::getClaimedCredits((int) $session['id']);
        $creditsEarned = max(0, $totalCreditsEarned - $alreadyClaimed);

        // Calculate next reward time based on reward interval
        $nextRewardIn = null;
        if ($settings['reward_interval_seconds'] > 0) {
            $lastClaimTime = AFKSession::getLastClaimTime((int) $session['id']);
            $interval = $settings['reward_interval_seconds'];

            if ($lastClaimTime !== null) {
                // Calculate time since last claim
                $timeSinceLastClaim = (int) ($now->getTimestamp() - $lastClaimTime->getTimestamp());
                // Calculate when next reward will be available
                $nextRewardIn = max(0, $interval - ($timeSinceLastClaim % $interval));
            } else {
                // No claim yet, calculate from session start
                $nextRewardIn = max(0, $interval - ($timeElapsed % $interval));
            }
        }

        // Update session if credits changed
        if ($totalCreditsEarned !== (int) $session['credits_earned'] || $timeElapsed !== (int) $session['time_elapsed']) {
            AFKSession::updateSession((int) $session['id'], $totalCreditsEarned, $timeElapsed);
        }

        // Get user's current credits balance
        $userCredits = CreditsHelper::getUserCredits($user['id']);

        // Get daily usage and limits
        $dailyUsage = AFKDailyUsage::getTodayUsage($user['id']);
        $dailyUsageData = [
            'credits_earned_today' => $dailyUsage ? (int) $dailyUsage['credits_earned'] : 0,
            'sessions_today' => $dailyUsage ? (int) $dailyUsage['sessions_count'] : 0,
            'time_seconds_today' => $dailyUsage ? (int) $dailyUsage['time_seconds'] : 0,
        ];

        // Get daily limits
        $dailyLimits = [
            'max_credits_per_day' => $settings['max_credits_per_day'],
            'max_sessions_per_day' => $settings['max_sessions_per_day'],
            'max_time_per_day_seconds' => $settings['max_time_per_day_seconds'],
        ];

        // Get JavaScript injection code from settings
        $javascriptInjection = $settings['javascript_injection'] ?? '';

        return ApiResponse::success([
            'is_afk' => true,
            'started_at' => $session['started_at'],
            'credits_earned' => $creditsEarned,
            'credits_formatted' => CurrencyHelper::formatAmount($creditsEarned),
            'time_elapsed' => max(0, $timeElapsed), // Ensure non-negative
            'next_reward_in' => $nextRewardIn,
            'javascript_injection' => $javascriptInjection,
            'user_credits' => $userCredits,
            'user_credits_formatted' => CurrencyHelper::formatAmount($userCredits),
            'daily_usage' => $dailyUsageData,
            'daily_limits' => $dailyLimits,
        ], 'Status retrieved successfully', 200);
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
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!AFKHelper::isEnabled()) {
            return ApiResponse::error('AFK rewards are currently disabled', 'AFK_DISABLED', 403);
        }

        // Check daily limits before starting session
        $settings = AFKHelper::getSettings();
        $limitCheck = AFKDailyUsage::checkDailyLimits($user['id'], $settings);
        if (!$limitCheck['allowed']) {
            return ApiResponse::error($limitCheck['message'], $limitCheck['reason'], 429);
        }

        // Check if already has active session
        $existing = AFKSession::getActiveSession($user['id']);
        if ($existing !== null) {
            return ApiResponse::error('AFK session already active', 'SESSION_ALREADY_ACTIVE', 400);
        }

        $session = AFKSession::startSession($user['id']);
        if (!$session) {
            return ApiResponse::error('Failed to start AFK session', 'START_FAILED', 500);
        }

        return ApiResponse::success([
            'session_id' => $session['id'],
            'started_at' => $session['started_at'],
        ], 'AFK session started successfully', 200);
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
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $session = AFKSession::getActiveSession($user['id']);
        if (!$session) {
            return ApiResponse::error('No active AFK session found', 'NO_ACTIVE_SESSION', 404);
        }

        $stopped = AFKSession::stopSession($user['id']);
        if (!$stopped) {
            return ApiResponse::error('Failed to stop AFK session', 'STOP_FAILED', 500);
        }

        return ApiResponse::success([
            'session_id' => $session['id'],
        ], 'AFK session stopped successfully', 200);
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
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $session = AFKSession::getActiveSession($user['id']);
        if (!$session) {
            return ApiResponse::error('No active AFK session found', 'NO_ACTIVE_SESSION', 404);
        }

        // Recalculate credits before claiming
        $startedAt = new \DateTime($session['started_at']);
        $now = new \DateTime();
        $timeElapsed = (int) ($now->getTimestamp() - $startedAt->getTimestamp());
        $settings = AFKHelper::getSettings();

        if ($settings['max_session_duration_seconds'] !== null && $timeElapsed > $settings['max_session_duration_seconds']) {
            $timeElapsed = $settings['max_session_duration_seconds'];
        }

        // Calculate total credits earned so far
        $totalCreditsEarned = AFKHelper::calculateCredits($timeElapsed);

        // Get already claimed credits
        $alreadyClaimed = AFKSession::getClaimedCredits((int) $session['id']);
        $availableCredits = $totalCreditsEarned - $alreadyClaimed;

        if ($availableCredits <= 0) {
            return ApiResponse::error('No credits available to claim. Please wait for more time to accumulate.', 'NO_CREDITS', 400);
        }

        // Check daily limits before claiming
        $limitCheck = AFKDailyUsage::checkDailyLimits($user['id'], $settings);
        if (!$limitCheck['allowed']) {
            return ApiResponse::error($limitCheck['message'], $limitCheck['reason'], 429);
        }

        // Calculate how many credits can be claimed based on time since last claim (or session start)
        $lastClaimTime = AFKSession::getLastClaimTime((int) $session['id']);
        $timeForNewCredits = $lastClaimTime !== null
            ? (int) ($now->getTimestamp() - $lastClaimTime->getTimestamp())
            : $timeElapsed;

        // Only allow claiming credits earned since last claim
        $creditsSinceLastClaim = AFKHelper::calculateCredits($timeForNewCredits);

        // Cap at available credits
        $creditsToClaim = min($creditsSinceLastClaim, $availableCredits);

        // Check if claiming would exceed daily credits limit
        $usage = AFKDailyUsage::getTodayUsage($user['id']);
        $creditsToday = $usage ? (int) $usage['credits_earned'] : 0;
        if ($settings['max_credits_per_day'] !== null) {
            $remainingDailyCredits = $settings['max_credits_per_day'] - $creditsToday;
            if ($remainingDailyCredits <= 0) {
                return ApiResponse::error(
                    sprintf('Daily credits limit reached (%d/%d). Please try again tomorrow.', $creditsToday, $settings['max_credits_per_day']),
                    'DAILY_CREDITS_LIMIT',
                    429
                );
            }
            // Cap credits to claim at remaining daily limit
            $creditsToClaim = min($creditsToClaim, $remainingDailyCredits);
        }

        if ($creditsToClaim <= 0) {
            return ApiResponse::error('No credits available to claim. Please wait for more time to accumulate.', 'NO_CREDITS', 400);
        }

        // Abuse prevention: Check if enough time has passed since last claim
        $lastClaimTime = AFKSession::getLastClaimTime((int) $session['id']);
        if ($lastClaimTime !== null) {
            $timeSinceLastClaim = (int) ($now->getTimestamp() - $lastClaimTime->getTimestamp());
            $minTimeBetweenClaims = $settings['reward_interval_seconds'] ?? 60;

            if ($timeSinceLastClaim < $minTimeBetweenClaims) {
                $remainingTime = $minTimeBetweenClaims - $timeSinceLastClaim;

                return ApiResponse::error(
                    sprintf('Please wait %d more seconds before claiming again. This prevents abuse.', $remainingTime),
                    'CLAIM_TOO_SOON',
                    429
                );
            }
        }

        // Calculate how many credits can be claimed based on time since last claim (or session start)
        $timeForNewCredits = $lastClaimTime !== null
            ? (int) ($now->getTimestamp() - $lastClaimTime->getTimestamp())
            : $timeElapsed;

        // Only allow claiming credits earned since last claim
        $creditsSinceLastClaim = AFKHelper::calculateCredits($timeForNewCredits);

        // Cap at available credits
        $creditsToClaim = min($creditsSinceLastClaim, $availableCredits);

        if ($creditsToClaim <= 0) {
            return ApiResponse::error('No credits available to claim. Please wait for more time to accumulate.', 'NO_CREDITS', 400);
        }

        // Update session with current totals
        AFKSession::updateSession((int) $session['id'], $totalCreditsEarned, $timeElapsed);

        // Claim rewards (with abuse prevention)
        $claimResult = AFKSession::claimRewards($user['id'], (int) $session['id'], $creditsToClaim);
        if (!$claimResult) {
            return ApiResponse::error('Failed to claim rewards', 'CLAIM_FAILED', 500);
        }

        // Add credits to user account using billingcore
        $added = CreditsHelper::addUserCredits($user['id'], $claimResult['credits_earned']);
        if (!$added) {
            return ApiResponse::error('Failed to add credits to account', 'CREDITS_ADD_FAILED', 500);
        }

        // Update user stats
        AFKUserStats::updateStats($user['id'], $timeElapsed, $claimResult['credits_earned']);

        return ApiResponse::success([
            'credits_earned' => $claimResult['credits_earned'],
            'credits_formatted' => CurrencyHelper::formatAmount($claimResult['credits_earned']),
            'message' => 'Rewards claimed successfully',
        ], 'Rewards claimed successfully', 200);
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
