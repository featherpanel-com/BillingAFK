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
 * AFK Session chat model for managing AFK sessions.
 */
class AFKSession
{
    private static string $table = 'featherpanel_billingafk_sessions';

    /**
     * Get active session for a user.
     */
    public static function getActiveSession(int $userId): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND is_active = 1 ORDER BY started_at DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Start a new AFK session.
     */
    public static function startSession(int $userId): ?array
    {
        if (!self::assertUserExists($userId)) {
            return null;
        }

        // Check if user already has an active session
        $existing = self::getActiveSession($userId);
        if ($existing !== null) {
            return $existing;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (user_id, started_at, is_active) VALUES (:user_id, NOW(), 1)'
            );
            $stmt->execute(['user_id' => $userId]);

            return self::getActiveSession($userId);
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to start AFK session: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Stop an active session.
     */
    public static function stopSession(int $userId): bool
    {
        if (!self::assertUserExists($userId)) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET stopped_at = NOW(), is_active = 0 WHERE user_id = :user_id AND is_active = 1'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to stop AFK session: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Update session credits and time.
     */
    public static function updateSession(int $sessionId, int $creditsEarned, int $timeElapsed): bool
    {
        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET credits_earned = :credits_earned, time_elapsed = :time_elapsed WHERE id = :id'
            );
            $stmt->execute([
                'id' => $sessionId,
                'credits_earned' => $creditsEarned,
                'time_elapsed' => $timeElapsed,
            ]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to update AFK session: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get the last claim time for a session.
     */
    public static function getLastClaimTime(int $sessionId): ?\DateTime
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT last_claim_at FROM ' . self::$table . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $sessionId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result || !$result['last_claim_at']) {
            return null;
        }

        return new \DateTime($result['last_claim_at']);
    }

    /**
     * Get claimed credits for a session.
     */
    public static function getClaimedCredits(int $sessionId): int
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT credits_claimed FROM ' . self::$table . ' WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $sessionId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) ($result['credits_claimed'] ?? 0) : 0;
    }

    /**
     * Claim rewards from a session.
     */
    public static function claimRewards(int $userId, int $sessionId, int $creditsToClaim): ?array
    {
        $pdo = Database::getPdoConnection();

        try {
            $pdo->beginTransaction();

            // Get session with lock
            $stmt = $pdo->prepare(
                'SELECT * FROM ' . self::$table . ' WHERE id = :id AND user_id = :user_id AND is_active = 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $sessionId, 'user_id' => $userId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                $pdo->rollBack();

                return null;
            }

            $currentCreditsEarned = (int) $session['credits_earned'];
            $alreadyClaimed = (int) ($session['credits_claimed'] ?? 0);
            $availableCredits = $currentCreditsEarned - $alreadyClaimed;

            // Validate credits to claim
            if ($creditsToClaim <= 0 || $creditsToClaim > $availableCredits) {
                $pdo->rollBack();

                return null;
            }

            $newClaimedTotal = $alreadyClaimed + $creditsToClaim;

            // Update session: mark credits as claimed and update last_claim_at
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET credits_claimed = :credits_claimed, last_claim_at = NOW() WHERE id = :id'
            );
            $stmt->execute([
                'id' => $sessionId,
                'credits_claimed' => $newClaimedTotal,
            ]);

            $pdo->commit();

            return [
                'credits_earned' => $creditsToClaim,
                'session_id' => $sessionId,
            ];
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            App::getInstance(true)->getLogger()->error('Failed to claim rewards: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Get user's session history.
     */
    public static function getUserSessions(int $userId, int $limit = 10): array
    {
        if (!self::assertUserExists($userId)) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id ORDER BY started_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
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
