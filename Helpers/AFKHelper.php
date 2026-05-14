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
        $defaults = [
            'credits_per_minute' => 1.0,
            'minutes_per_credit' => null,
            'reward_interval_seconds' => 60,
            'max_credits_per_session' => null,
            'max_session_duration_seconds' => null,
            'javascript_injection' => '',
            'is_enabled' => true,
            'require_claim' => true,
            'auto_claim_interval_seconds' => null,
            'max_credits_per_day' => null,
            'max_sessions_per_day' => null,
            'max_time_per_day_seconds' => null,
            'require_active_tab' => true,
            'require_captcha' => false,
        ];

        $settings = [];
        foreach ($defaults as $key => $defaultValue) {
            $value = self::getSetting($key);

            if ($value === null) {
                $settings[$key] = $defaultValue;
            } else {
                if (is_bool($defaultValue)) {
                    $settings[$key] = ($value === '1' || $value === 'true');
                } elseif (is_int($defaultValue)) {
                    $settings[$key] = (int) $value;
                } elseif (is_float($defaultValue)) {
                    $settings[$key] = (float) $value;
                } else {
                    $settings[$key] = $value;
                }
            }
        }

        // Special handling for javascript_injection
        if (isset($settings['javascript_injection']) && is_string($settings['javascript_injection'])) {
            $settings['javascript_injection'] = html_entity_decode(
                $settings['javascript_injection'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        return $settings;
    }

    /**
     * Update AFK settings.
     */
    public static function updateSettings(array $settings): void
    {
        $validKeys = [
            'credits_per_minute', 'minutes_per_credit', 'reward_interval_seconds',
            'max_credits_per_session', 'max_session_duration_seconds', 'javascript_injection',
            'is_enabled', 'require_claim', 'auto_claim_interval_seconds',
            'max_credits_per_day', 'max_sessions_per_day', 'max_time_per_day_seconds',
            'require_active_tab', 'require_captcha',
        ];

        foreach ($settings as $key => $value) {
            if (!in_array($key, $validKeys)) {
                continue;
            }

            if ($value === null) {
                PluginSettings::deleteSettings(self::PLUGIN_IDENTIFIER, $key);
                continue;
            }

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
        $settings = self::getSettings();

        return (bool) ($settings['is_enabled'] ?? true);
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
