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

namespace App\Addons\billingafk\Helpers;

use App\Plugins\PluginSettings;

/**
 * Helper for working with AFK settings using PluginSettings.
 */
class AFKHelper
{
    private const PLUGIN_IDENTIFIER = 'billingafk';

    /**
     * Get all AFK settings.
     */
    public static function getSettings(): array
    {
        return [
            'credits_per_minute' => (float) (self::getSetting('credits_per_minute') ?? 1.0),
            'minutes_per_credit' => self::getSetting('minutes_per_credit') ? (float) self::getSetting('minutes_per_credit') : null,
            'reward_interval_seconds' => (int) (self::getSetting('reward_interval_seconds') ?? 60),
            'max_credits_per_session' => self::getSetting('max_credits_per_session') ? (int) self::getSetting('max_credits_per_session') : null,
            'max_session_duration_seconds' => self::getSetting('max_session_duration_seconds') ? (int) self::getSetting('max_session_duration_seconds') : null,
            'javascript_injection' => self::getSetting('javascript_injection') ?? '',
            'is_enabled' => self::getSetting('is_enabled') === '1' || self::getSetting('is_enabled') === 'true',
            'require_claim' => self::getSetting('require_claim') !== '0' && self::getSetting('require_claim') !== 'false',
            'auto_claim_interval_seconds' => self::getSetting('auto_claim_interval_seconds') ? (int) self::getSetting('auto_claim_interval_seconds') : null,
            // Daily limits
            'max_credits_per_day' => self::getSetting('max_credits_per_day') ? (int) self::getSetting('max_credits_per_day') : null,
            'max_sessions_per_day' => self::getSetting('max_sessions_per_day') ? (int) self::getSetting('max_sessions_per_day') : null,
            'max_time_per_day_seconds' => self::getSetting('max_time_per_day_seconds') ? (int) self::getSetting('max_time_per_day_seconds') : null,
        ];
    }

    /**
     * Update AFK settings.
     */
    public static function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($value === null) {
                continue;
            }

            // Convert boolean to string
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = (string) $value;
            }

            PluginSettings::setSetting(self::PLUGIN_IDENTIFIER, $key, $value);
        }
    }

    /**
     * Get a single setting.
     */
    public static function getSetting(string $key): ?string
    {
        return PluginSettings::getSetting(self::PLUGIN_IDENTIFIER, $key);
    }

    /**
     * Set a single setting.
     */
    public static function setSetting(string $key, string $value): void
    {
        PluginSettings::setSetting(self::PLUGIN_IDENTIFIER, $key, $value);
    }

    /**
     * Check if AFK is enabled.
     */
    public static function isEnabled(): bool
    {
        $enabled = self::getSetting('is_enabled');

        return $enabled === '1' || $enabled === 'true' || $enabled === null; // Default to enabled
    }

    /**
     * Calculate credits earned based on time elapsed.
     */
    public static function calculateCredits(int $timeElapsedSeconds): int
    {
        $settings = self::getSettings();

        // Use credits_per_minute if set, otherwise use minutes_per_credit
        if ($settings['credits_per_minute'] > 0) {
            $credits = ($timeElapsedSeconds / 60) * $settings['credits_per_minute'];
        } elseif ($settings['minutes_per_credit'] > 0) {
            $credits = ($timeElapsedSeconds / 60) / $settings['minutes_per_credit'];
        } else {
            // Default: 1 credit per minute
            $credits = $timeElapsedSeconds / 60;
        }

        $credits = (int) floor($credits);

        // Apply max credits per session limit
        if ($settings['max_credits_per_session'] !== null && $credits > $settings['max_credits_per_session']) {
            $credits = $settings['max_credits_per_session'];
        }

        return max(0, $credits);
    }

    /**
     * Get next reward time in seconds.
     */
    public static function getNextRewardIn(int $timeElapsedSeconds): ?int
    {
        $settings = self::getSettings();
        $interval = $settings['reward_interval_seconds'];

        if ($interval <= 0) {
            return null;
        }

        $nextReward = $interval - ($timeElapsedSeconds % $interval);

        return $nextReward > 0 ? $nextReward : $interval;
    }
}
