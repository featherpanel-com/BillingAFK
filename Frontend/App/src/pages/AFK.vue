<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from "vue";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Alert, AlertDescription } from "@/components/ui/alert";
import {
  Clock,
  Play,
  Pause,
  Wallet,
  Loader2,
  Timer,
  AlertCircle,
} from "lucide-vue-next";
import { useAFKAPI } from "@/composables/useAFKAPI";
import { useToast } from "vue-toastification";
import axios from "axios";

const toast = useToast();
const { loading, error, getStatus } = useAFKAPI();

// AFK State (like MythicalDash)
const afkStatus = ref<{
  minutes_afk?: number;
  last_seen_afk?: number;
  javascript_injection?: string;
  user_credits?: number;
  user_credits_formatted?: string;
  credits_per_minute?: number | null;
  minutes_per_credit?: number | null;
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
} | null>(null);

const isActive = ref(false);
const seconds = ref(0);
const totalCoins = ref(0);
const sessionCoins = ref(0);
const currentSessionTime = ref(0); // in minutes
const totalAFKTime = ref(0); // in minutes
let timerInterval: number | null = null;

// Calculate expected credits per minute from settings (for display only)
const expectedCreditsPerMinute = computed(() => {
  if (!afkStatus.value) return 0;

  // Priority 1: Use credits_per_minute if set
  if (
    afkStatus.value.credits_per_minute &&
    afkStatus.value.credits_per_minute > 0
  ) {
    return afkStatus.value.credits_per_minute;
  }

  // Priority 2: Use minutes_per_credit if set
  if (
    afkStatus.value.minutes_per_credit &&
    afkStatus.value.minutes_per_credit > 0
  ) {
    return 1.0 / afkStatus.value.minutes_per_credit;
  }

  // Default: 1 credit per minute
  return 1;
});

// Format time helper
const formatTime = (
  totalSeconds: number
): { hours: string; minutes: string; seconds: string } => {
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const secs = totalSeconds % 60;
  return {
    hours: hours.toString().padStart(2, "0"),
    minutes: minutes.toString().padStart(2, "0"),
    seconds: secs.toString().padStart(2, "0"),
  };
};

// Format time into readable string (e.g. "12h 34m")
const formatTimeString = (totalMinutes: number): string => {
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;

  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}m`;
};

// Computed for display
const displayTime = computed(() => {
  const time = formatTime(seconds.value);
  return {
    hours: time.hours,
    minutes: time.minutes,
    seconds: time.seconds,
  };
});

// Check if user is actively on the page
const isUserActive = (): boolean => {
  return document.visibilityState === "visible";
};

// Handle visibility change
const handleVisibilityChange = () => {
  if (!isUserActive() && isActive.value) {
    // User switched to another tab or minimized the window
    stopTimer();
    toast.warning(
      "AFK session paused. Please keep this tab active to continue earning rewards."
    );
  }
};

// Load AFK status
const loadStatus = async () => {
  try {
    const status = await getStatus();
    afkStatus.value = status;

    // Update user credits
    if (status.user_credits !== undefined) {
      totalCoins.value = status.user_credits;
    }

    // Update total AFK time
    if (status.minutes_afk !== undefined) {
      totalAFKTime.value = status.minutes_afk;
    }

    // Inject JavaScript if provided
    if (status.javascript_injection && status.javascript_injection.trim()) {
      try {
        const script = document.createElement("script");
        script.textContent = status.javascript_injection;
        document.head.appendChild(script);
        setTimeout(() => {
          if (script.parentNode) {
            script.parentNode.removeChild(script);
          }
        }, 100);
      } catch (err) {
        console.error("Failed to inject JavaScript:", err);
      }
    }
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load AFK status"
    );
  }
};

// Toggle AFK mode (like MythicalDash - no backend session, just local state)
const toggleAFK = () => {
  if (isActive.value) {
    stopTimer();
  } else {
    startTimer();
  }
};

// Start the timer
const startTimer = () => {
  if (!isUserActive()) {
    toast.warning("Please keep this tab active to start earning AFK rewards.");
    return;
  }

  if (timerInterval) {
    stopTimer();
  }

  isActive.value = true;

  // Start the main timer
  timerInterval = window.setInterval(() => {
    if (!isUserActive()) {
      stopTimer();
      return;
    }

    seconds.value++;

    // Every minute, update AFK stats (like MythicalDash)
    if (seconds.value % 60 === 0) {
      updateAFKStats();
    }

    // Refetch credits every 30 seconds to show real-time updates
    if (seconds.value % 30 === 0 && seconds.value > 0) {
      loadStatus();
    }
  }, 1000);

  // Add visibility change listener
  document.addEventListener("visibilitychange", handleVisibilityChange);
};

// Update AFK stats - called every minute (like MythicalDash)
const updateAFKStats = async () => {
  // Increase AFK time by 1 minute
  currentSessionTime.value += 1;
  totalAFKTime.value += 1;

  // Don't calculate credits on frontend - let server handle it
  // Just send minutes_afk: 1 and server will calculate credits based on settings

  // Make an API call to update the server (like MythicalDash)
  try {
    const response = await axios.post("/api/user/billingafk/work", {
      minutes_afk: 1,
    });

    const data = response.data;

    // Check for successful API response
    if (response.status === 200 && data.success) {
      // Update total credits from server response (server is source of truth)
      if (data.data?.total_credits !== undefined) {
        totalCoins.value = data.data.total_credits;
      }
      if (data.data?.total_afk_time !== undefined) {
        totalAFKTime.value = data.data.total_afk_time;
      }

      // Update session credits based on what server actually awarded
      if (
        data.data?.credits_awarded !== undefined &&
        data.data.credits_awarded > 0
      ) {
        sessionCoins.value += data.data.credits_awarded;
      }

      // Refresh status to get updated user credits and daily limits
      await loadStatus();
    } else {
      console.warn("API responded with non-success status:", data);
    }
  } catch (err) {
    console.error("Failed to update AFK stats:", err);
    if (axios.isAxiosError(err)) {
      const errorMsg = err.response?.data?.message || err.message;
      // Don't show error for rate limiting - it's expected
      if (!errorMsg.includes("RATE_LIMIT") && !errorMsg.includes("wait")) {
        toast.error(`Failed to update AFK stats: ${errorMsg}`);
      }
    }
  }
};

// Stop the timer
const stopTimer = () => {
  isActive.value = false;
  if (timerInterval !== null) {
    clearInterval(timerInterval);
    timerInterval = null;

    // Remove visibility change listener
    document.removeEventListener("visibilitychange", handleVisibilityChange);
  }
};

onMounted(() => {
  loadStatus();
});

onUnmounted(() => {
  stopTimer();
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-4xl">
      <div class="mb-6">
        <div class="flex items-center gap-1.5 mb-0.5">
          <Timer class="h-4 w-4 text-primary" />
          <h1 class="text-lg font-semibold">AFK Rewards</h1>
        </div>
        <p class="text-xs text-muted-foreground ml-5.5">
          Earn credits by staying AFK. Start the timer and keep this tab active!
        </p>
      </div>

      <!-- User Credits Card -->
      <Card
        v-if="afkStatus?.user_credits !== undefined"
        class="mb-4 border-primary/20"
      >
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg bg-primary/10">
              <Wallet class="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">Your Credits</h2>
              <p class="text-sm text-muted-foreground">
                Current credit balance
              </p>
            </div>
          </div>
          <div class="flex items-baseline gap-2">
            <div class="text-3xl font-bold">
              {{ afkStatus.user_credits_formatted || "0" }}
            </div>
          </div>
        </div>
      </Card>

      <!-- Daily Limits Card -->
      <Card
        v-if="
          afkStatus?.daily_limits &&
          (afkStatus.daily_limits.max_credits_per_day ||
            afkStatus.daily_limits.max_sessions_per_day ||
            afkStatus.daily_limits.max_time_per_day_seconds)
        "
        class="mb-4 border-blue-500/20"
      >
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg bg-blue-500/10">
              <AlertCircle class="h-5 w-5 text-blue-500" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">Daily Limits</h2>
              <p class="text-sm text-muted-foreground">
                Your daily usage and limits
              </p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-if="afkStatus.daily_limits.max_credits_per_day"
              class="p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="text-xs text-muted-foreground mb-1 font-medium">
                Credits Today
              </div>
              <div class="text-lg font-semibold">
                {{ afkStatus.daily_usage?.credits_earned_today || 0 }} /
                {{ afkStatus.daily_limits.max_credits_per_day }}
              </div>
            </div>
            <div
              v-if="afkStatus.daily_limits.max_sessions_per_day"
              class="p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="text-xs text-muted-foreground mb-1 font-medium">
                Sessions Today
              </div>
              <div class="text-lg font-semibold">
                {{ afkStatus.daily_usage?.sessions_today || 0 }} /
                {{ afkStatus.daily_limits.max_sessions_per_day }}
              </div>
            </div>
            <div
              v-if="afkStatus.daily_limits.max_time_per_day_seconds"
              class="p-3 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="text-xs text-muted-foreground mb-1 font-medium">
                Time Today
              </div>
              <div class="text-lg font-semibold">
                {{
                  formatTimeString(
                    Math.floor(
                      (afkStatus.daily_usage?.time_seconds_today || 0) / 60
                    )
                  )
                }}
                /
                {{
                  formatTimeString(
                    Math.floor(
                      afkStatus.daily_limits.max_time_per_day_seconds / 60
                    )
                  )
                }}
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Main AFK Timer Card -->
      <Card class="mb-4 border-primary/20">
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg bg-primary/10">
              <Clock class="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">AFK Timer</h2>
              <p class="text-sm text-muted-foreground">
                Earn credits by staying AFK
              </p>
            </div>
          </div>

          <div
            v-if="loading && !afkStatus"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin text-primary" />
          </div>

          <div v-else-if="afkStatus" class="space-y-6">
            <!-- Status Badge and Toggle -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Badge :variant="isActive ? 'default' : 'secondary'">
                  {{ isActive ? "Active" : "Inactive" }}
                </Badge>
                <span class="text-sm text-muted-foreground">
                  {{
                    isActive
                      ? "You're currently AFK and earning credits"
                      : "AFK mode is not active"
                  }}
                </span>
              </div>
              <Button
                @click="toggleAFK"
                :disabled="loading"
                :variant="isActive ? 'destructive' : 'default'"
              >
                <Play v-if="!isActive" class="h-4 w-4 mr-2" />
                <Pause v-else class="h-4 w-4 mr-2" />
                {{ isActive ? "Stop AFK" : "Start AFK" }}
              </Button>
            </div>

            <!-- Large Timer Display -->
            <div
              v-if="isActive"
              class="bg-muted/30 rounded-xl p-8 text-center border border-border/50"
            >
              <div class="grid grid-cols-3 gap-4">
                <div class="timer-unit">
                  <div class="text-5xl font-bold text-white">
                    {{ displayTime.hours }}
                  </div>
                  <div
                    class="text-xs text-muted-foreground uppercase tracking-wide mt-2"
                  >
                    Hours
                  </div>
                </div>
                <div class="timer-unit">
                  <div class="text-5xl font-bold text-white">
                    {{ displayTime.minutes }}
                  </div>
                  <div
                    class="text-xs text-muted-foreground uppercase tracking-wide mt-2"
                  >
                    Minutes
                  </div>
                </div>
                <div class="timer-unit">
                  <div class="text-5xl font-bold text-white">
                    {{ displayTime.seconds }}
                  </div>
                  <div
                    class="text-xs text-muted-foreground uppercase tracking-wide mt-2"
                  >
                    Seconds
                  </div>
                </div>
              </div>
            </div>

            <!-- Credits Counter -->
            <div
              v-if="isActive"
              class="bg-muted/30 rounded-xl p-6 border border-border/50"
            >
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <div
                    class="w-10 h-10 rounded-full bg-yellow-500/20 flex items-center justify-center mr-3"
                  >
                    <Wallet class="h-5 w-5 text-yellow-500" />
                  </div>
                  <div>
                    <div class="text-sm text-muted-foreground">
                      Total Credits
                    </div>
                    <div class="text-2xl font-bold text-yellow-500">
                      {{ totalCoins }}
                    </div>
                  </div>
                </div>
                <div>
                  <div class="text-sm text-muted-foreground text-right">
                    Current Session
                  </div>
                  <div class="text-xl font-medium text-yellow-400">
                    +{{ sessionCoins }} credits
                  </div>
                  <div class="text-xs text-muted-foreground text-right mt-1">
                    ~{{ expectedCreditsPerMinute.toFixed(2) }} credits/min
                  </div>
                </div>
              </div>
            </div>

            <!-- AFK Stats -->
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-muted/30 rounded-lg p-4 border border-border/50">
                <div class="text-sm text-muted-foreground mb-1">
                  Current Session
                </div>
                <div class="text-lg font-semibold text-white">
                  {{ formatTimeString(currentSessionTime) }}
                </div>
              </div>
              <div class="bg-muted/30 rounded-lg p-4 border border-border/50">
                <div class="text-sm text-muted-foreground mb-1">
                  Total AFK Time
                </div>
                <div class="text-lg font-semibold text-white">
                  {{ formatTimeString(totalAFKTime) }}
                </div>
              </div>
            </div>
          </div>

          <Alert v-else-if="error" class="mt-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription class="text-sm">{{ error }}</AlertDescription>
          </Alert>
        </div>
      </Card>

      <Alert v-if="isActive" class="mt-4">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription class="text-sm">
          AFK mode is active. You're earning credits! Keep this tab active to
          continue earning rewards.
        </AlertDescription>
      </Alert>
    </div>
  </div>
</template>

<style scoped>
.timer-unit {
  background-color: rgba(31, 41, 55, 0.5);
  border-radius: 0.75rem;
  padding: 1.25rem 0.5rem;
  position: relative;
  overflow: hidden;
}

.timer-unit::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background: linear-gradient(
    to right,
    rgba(99, 102, 241, 0.2),
    rgba(139, 92, 246, 0.2)
  );
}
</style>
