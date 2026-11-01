import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface AFKStatus {
  minutes_afk?: number;
  last_seen_afk?: number;
  session_active?: boolean;
  session_started_at?: number;
  javascript_injection?: string;
  user_credits?: number;
  user_credits_formatted?: string;
  credits_per_minute?: number | null;
  minutes_per_credit?: number | null;
  require_captcha?: boolean;
  require_active_tab?: boolean;
  daily_usage?: {
    credits_earned_today: number;
    sessions_today: number;
    time_seconds_today: number;
  };
  daily_limits?: {
    max_credits_per_day: number | null;
    max_sessions_per_day: number | null;
    max_time_per_day_seconds: number | null;
  };
  captcha?: {
    provider: string;
    enabled: boolean;
    [key: string]: unknown;
  };
}

export interface WorkResponse {
  credits_awarded: number;
  total_credits: number;
  total_afk_time: number;
}

export interface ClaimRewardsResponse {
  credits_earned: number;
  credits_formatted: string;
  message: string;
}

export function useAFKAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const handleError = (err: unknown): string => {
    if (axios.isAxiosError(err)) {
      const axiosError = err as AxiosError<{
        message?: string;
        error_message?: string;
        error?: string;
      }>;
      return (
        axiosError.response?.data?.message ||
        axiosError.response?.data?.error_message ||
        axiosError.response?.data?.error ||
        axiosError.message ||
        "An error occurred"
      );
    }
    if (err instanceof Error) {
      return err.message;
    }
    return "An unknown error occurred";
  };

  // Get AFK status
  const getStatus = async (): Promise<AFKStatus> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/user/billingafk/status");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch AFK status");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Start AFK (server session + optional captcha token)
  const startAFK = async (captchaToken?: string | null): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const payload: Record<string, string> = {};
      if (captchaToken) {
        payload.turnstile_token = captchaToken;
        payload.captcha_token = captchaToken;
      }

      const response = await axios.post("/api/user/billingafk/start", payload);
      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to start AFK mode");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Stop AFK
  const stopAFK = async (): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/user/billingafk/stop");
      if (!response.data || !response.data.success) {
        throw new Error(response.data?.message || "Failed to stop AFK mode");
      }
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Claim rewards
  const claimRewards = async (): Promise<ClaimRewardsResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/user/billingafk/claim");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to claim rewards");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Work endpoint - called periodically to award credits (like MythicalDash)
  const work = async (
    creditsEarned: number,
    minutesAfk: number
  ): Promise<WorkResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/user/billingafk/work", {
        credits_earned: creditsEarned,
        minutes_afk: minutesAfk,
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update AFK work");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getStatus,
    startAFK,
    stopAFK,
    claimRewards,
    work,
  };
}
