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
 * AFK User Stats chat model for managing user statistics.
 */
class AFKUserStats
{
    private static string $table = 'featherpanel_billingafk_user_stats';

    /**
     * Get or create stats for a user.
     */
    public static function getOrCreate(int $userId): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        $pdo = Database::getPdoConnection();

        // Try to get existing stats
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($stats !== false) {
            return $stats;
        }

        // Create new stats record
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (user_id, minutes_afk, last_seen_afk) VALUES (:user_id, 0, 0)'
            );
            $stmt->execute(['user_id' => $userId]);

            return self::getOrCreate($userId);
        } catch (\PDOException $e) {
            // Handle duplicate key (race condition)
            if ($e->getCode() !== '23000') {
                App::getInstance(true)->getLogger()->error('Failed to create AFK stats: ' . $e->getMessage());

                return null;
            }

            // Retry fetch
            $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
    }

    /**
     * Increment minutes_afk and update last_seen_afk (like MythicalDash).
     * Also performs rate limiting to prevent spam.
     *
     * @return array|null Returns updated stats on success, null on rate limit or error
     */
    public static function incrementAFKTime(int $userId, int $minSecondsBetweenCalls = 60): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            // Lock row for update and check rate limit atomically
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id FOR UPDATE'
            );
            $stmt->execute(['user_id' => $userId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $now = time();

            // Rate limiting: Check if last call was too recent
            // Only rate limit if last_seen_afk is a valid recent timestamp (not 0, not null, and reasonable)
            if ($stats !== false && isset($stats['last_seen_afk'])) {
                $lastSeenAfk = (int) ($stats['last_seen_afk'] ?? 0);
                // Only check rate limit if last_seen_afk is a valid timestamp (greater than year 2020)
                // This allows first-time calls (last_seen_afk = 0) and old data to pass through
                if ($lastSeenAfk > 1577836800) { // Unix timestamp for 2020-01-01 (reasonable minimum)
                    $timeSinceLastCall = $now - $lastSeenAfk;
                    if ($timeSinceLastCall < $minSecondsBetweenCalls) {
                        $pdo->rollBack();

                        return null; // Rate limited
                    }
                }
            }

            if (!$stats) {
                // Create if doesn't exist
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . self::$table . ' (user_id, minutes_afk, last_seen_afk) VALUES (:user_id, 1, :timestamp)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'timestamp' => $now,
                ]);

                // Get the newly created record
                $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
                $stmt->execute(['user_id' => $userId]);
                $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            } else {
                // Update existing - increment minutes_afk and update last_seen_afk
                $stmt = $pdo->prepare(
                    'UPDATE ' . self::$table . ' SET 
                        minutes_afk = minutes_afk + 1,
                        last_seen_afk = :timestamp
                    WHERE user_id = :user_id'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'timestamp' => $now,
                ]);

                // Get updated record
                $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id LIMIT 1');
                $stmt->execute(['user_id' => $userId]);
                $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            $pdo->commit();

            return $stats ?: null;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            App::getInstance(true)->getLogger()->error('Failed to increment AFK time: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update stats after claiming rewards.
     */
    public static function updateStats(int $userId, int $timeSeconds, int $creditsEarned): bool
    {
        if (!self::assertUserExists($userId)) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            // Lock row for update
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id FOR UPDATE'
            );
            $stmt->execute(['user_id' => $userId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stats) {
                // Create if doesn't exist
                $stmt = $pdo->prepare(
                    'INSERT INTO ' . self::$table . ' (user_id, total_time_seconds, total_credits_earned, sessions_count) VALUES (:user_id, :time, :credits, 1)'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'time' => $timeSeconds,
                    'credits' => $creditsEarned,
                ]);
            } else {
                // Update existing
                $stmt = $pdo->prepare(
                    'UPDATE ' . self::$table . ' SET 
                        total_time_seconds = total_time_seconds + :time,
                        total_credits_earned = total_credits_earned + :credits,
                        sessions_count = sessions_count + 1,
                        last_session_at = NOW()
                    WHERE user_id = :user_id'
                );
                $stmt->execute([
                    'user_id' => $userId,
                    'time' => $timeSeconds,
                    'credits' => $creditsEarned,
                ]);
            }

            $pdo->commit();

            return true;
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            App::getInstance(true)->getLogger()->error('Failed to update AFK stats: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get all user stats (for admin).
     */
    public static function getAllStats(int $limit = 100, int $offset = 0): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT s.*, u.username, u.email 
            FROM ' . self::$table . ' s
            LEFT JOIN featherpanel_users u ON s.user_id = u.id
            ORDER BY s.total_credits_earned DESC
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
