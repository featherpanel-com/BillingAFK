import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface AFKSettings {
  credits_per_minute: number;
  minutes_per_credit: number | null;
  reward_interval_seconds: number;
  max_credits_per_session: number | null;
  max_session_duration_seconds: number | null;
  javascript_injection: string;
  is_enabled: boolean;
  require_claim: boolean;
  auto_claim_interval_seconds: number | null;
  max_credits_per_day: number | null;
  max_sessions_per_day: number | null;
  max_time_per_day_seconds: number | null;
}

export interface UserAFKStats {
  user_id: number;
  username: string;
  email: string | null;
  total_time_seconds: number;
  total_time_formatted: string;
  total_credits_earned: number;
  total_credits_formatted: string;
  sessions_count: number;
  last_session_at: string | null;
}

export interface UserStatsResponse {
  data: UserAFKStats[];
  meta: {
    pagination: {
      current_page: number;
      per_page: number;
      total: number;
      total_pages: number;
    };
  };
}

export function useAFKAdminAPI() {
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

  // Get settings
  const getSettings = async (): Promise<AFKSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingafk/settings");
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Update settings
  const updateSettings = async (
    settings: Partial<AFKSettings>
  ): Promise<AFKSettings> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.patch(
        "/api/admin/billingafk/settings",
        settings
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error(response.data?.message || "Failed to update settings");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get all user stats
  const getAllStats = async (
    page: number = 1,
    limit: number = 20
  ): Promise<UserStatsResponse> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get("/api/admin/billingafk/stats", {
        params: {
          page,
          limit,
        },
      });
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch statistics");
    } catch (err) {
      const errorMsg = handleError(err);
      error.value = errorMsg;
      throw new Error(errorMsg);
    } finally {
      loading.value = false;
    }
  };

  // Get user stats
  const getUserStats = async (userId: number): Promise<UserAFKStats> => {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(
        `/api/admin/billingafk/user/${userId}/stats`
      );
      if (response.data && response.data.success) {
        return response.data.data;
      }
      throw new Error("Failed to fetch user statistics");
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
    getSettings,
    updateSettings,
    getAllStats,
    getUserStats,
  };
}
