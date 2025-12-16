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

namespace App\Addons\billingafk\Chat;

use App\App;
use App\Chat\User;
use App\Chat\Database;

/**
 * AFK Daily Usage chat model for tracking daily limits.
 */
class AFKDailyUsage
{
    private static string $table = 'featherpanel_billingafk_daily_usage';

    /**
     * Get or create daily usage record for a user and date.
     */
    public static function getOrCreate(int $userId, string $date = 'today'): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        if ($date === 'today') {
            $date = date('Y-m-d');
        }

        $pdo = Database::getPdoConnection();

        // Try to get existing record
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND date = :date LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'date' => $date]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($record !== false) {
            return $record;
        }

        // Create new record
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (user_id, date) VALUES (:user_id, :date)'
            );
            $stmt->execute(['user_id' => $userId, 'date' => $date]);

            return self::getOrCreate($userId, $date);
        } catch (\PDOException $e) {
            // Handle duplicate key (race condition)
            if ($e->getCode() !== '23000') {
                App::getInstance(true)->getLogger()->error('Failed to create daily usage: ' . $e->getMessage());

                return null;
            }

            // Retry fetch
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND date = :date LIMIT 1'
            );
            $stmt->execute(['user_id' => $userId, 'date' => $date]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
    }

    /**
     * Update daily usage after claiming rewards.
     */
    public static function updateUsage(int $userId, int $creditsEarned, int $timeSeconds, bool $incrementSession = false): bool
    {
        if (!self::assertUserExists($userId)) {
            return false;
        }

        $date = date('Y-m-d');
        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            // Lock row for update
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND date = :date FOR UPDATE'
            );
            $stmt->execute(['user_id' => $userId, 'date' => $date]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$record) {
                // Create if doesn't exist
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . self::$table . ' (user_id, date, credits_earned, time_seconds, sessions_count) VALUES (:user_id, :date, :credits, :time, :sessions)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'date' => $date,
                    'credits' => $creditsEarned,
                    'time' => $timeSeconds,
                    'sessions' => $incrementSession ? 1 : 0,
                ]);
            } else {
                // Update existing
                $newCredits = (int) $record['credits_earned'] + $creditsEarned;
                $newTime = (int) $record['time_seconds'] + $timeSeconds;
                $newSessions = (int) $record['sessions_count'] + ($incrementSession ? 1 : 0);

                $stmt = $pdo->prepare(
                    'UPDATE ' . self::$table . ' SET 
                        credits_earned = :credits,
                        time_seconds = :time,
                        sessions_count = :sessions
                    WHERE user_id = :user_id AND date = :date'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'date' => $date,
                    'credits' => $newCredits,
                    'time' => $newTime,
                    'sessions' => $newSessions,
                ]);
            }

            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            App::getInstance(true)->getLogger()->error('Failed to update daily usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get today's usage for a user.
     */
    public static function getTodayUsage(int $userId): ?array
    {
        return self::getOrCreate($userId, 'today');
    }

    /**
     * Check if user has reached daily limits.
     */
    public static function checkDailyLimits(int $userId, array $settings): array
    {
        $usage = self::getTodayUsage($userId);
        if (!$usage) {
            return ['allowed' => true, 'reason' => null];
        }

        $creditsToday = (int) $usage['credits_earned'];
        $sessionsToday = (int) $usage['sessions_count'];
        $timeToday = (int) $usage['time_seconds'];

        // Check credits limit
        if ($settings['max_credits_per_day'] !== null && $creditsToday >= $settings['max_credits_per_day']) {
            return [
                'allowed' => false,
                'reason' => 'DAILY_CREDITS_LIMIT',
                'message' => sprintf('Daily credits limit reached (%d/%d)', $creditsToday, $settings['max_credits_per_day']),
            ];
        }

        // Check sessions limit
        if ($settings['max_sessions_per_day'] !== null && $sessionsToday >= $settings['max_sessions_per_day']) {
            return [
                'allowed' => false,
                'reason' => 'DAILY_SESSIONS_LIMIT',
                'message' => sprintf('Daily sessions limit reached (%d/%d)', $sessionsToday, $settings['max_sessions_per_day']),
            ];
        }

        // Check time limit
        if ($settings['max_time_per_day_seconds'] !== null && $timeToday >= $settings['max_time_per_day_seconds']) {
            return [
                'allowed' => false,
                'reason' => 'DAILY_TIME_LIMIT',
                'message' => sprintf('Daily time limit reached (%d/%d seconds)', $timeToday, $settings['max_time_per_day_seconds']),
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    private static function assertUserExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::getUserById($userId);

        return $user !== null;
    }
}
