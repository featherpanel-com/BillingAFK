import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface AFKStatus {
  is_afk: boolean;
  started_at: string | null;
  credits_earned: number;
  credits_formatted: string;
  time_elapsed: number;
  next_reward_in: number | null;
  javascript_injection?: string;
  user_credits?: number;
  user_credits_formatted?: string;
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

  // Start AFK
  const startAFK = async (): Promise<void> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post("/api/user/billingafk/start");
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

  return {
    loading,
    error,
    getStatus,
    startAFK,
    stopAFK,
    claimRewards,
  };
}
