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
  CheckCircle2,
  AlertCircle,
  Wallet,
  Loader2,
  Timer,
} from "lucide-vue-next";
import { useAFKAPI } from "@/composables/useAFKAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { loading, error, getStatus, startAFK, stopAFK, claimRewards } =
  useAFKAPI();

// AFK State
const afkStatus = ref<{
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
} | null>(null);

const isAFKActive = ref(false);
const timerInterval = ref<number | null>(null);
const elapsedTime = ref(0);
const nextRewardIn = ref<number | null>(null);
const sessionStartTime = ref<number | null>(null);

// Format time helper
const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m ${secs}s`;
  }
  if (minutes > 0) {
    return `${minutes}m ${secs}s`;
  }
  return `${secs}s`;
};

// Computed properties
const canClaimRewards = computed(() => {
  return (
    afkStatus.value &&
    afkStatus.value.is_afk &&
    afkStatus.value.credits_earned > 0
  );
});

const displayTime = computed(() => {
  return formatTime(elapsedTime.value);
});

const displayNextReward = computed(() => {
  if (nextRewardIn.value === null) return "N/A";
  return formatTime(nextRewardIn.value);
});

// Load AFK status
const loadStatus = async () => {
  try {
    const status = await getStatus();
    afkStatus.value = status;
    isAFKActive.value = status.is_afk;

    // Set session start time from server if available
    if (status.started_at) {
      sessionStartTime.value = new Date(status.started_at).getTime();
      // Calculate elapsed time from session start
      const serverElapsed = Math.max(0, status.time_elapsed || 0);
      elapsedTime.value = serverElapsed;
    } else {
      elapsedTime.value = 0;
      sessionStartTime.value = null;
    }

    // Set next reward from server
    if (status.next_reward_in !== null && status.next_reward_in >= 0) {
      nextRewardIn.value = status.next_reward_in;
    } else {
      nextRewardIn.value = null;
    }

    // Inject JavaScript if provided
    if (status.javascript_injection && status.javascript_injection.trim()) {
      try {
        // Create a script element and execute the code
        const script = document.createElement("script");
        script.textContent = status.javascript_injection;
        document.head.appendChild(script);
        // Remove after execution to keep DOM clean
        setTimeout(() => {
          if (script.parentNode) {
            script.parentNode.removeChild(script);
          }
        }, 100);
      } catch (err) {
        console.error("Failed to inject JavaScript:", err);
      }
    }

    // Start/restart timer if AFK is active
    if (status.is_afk) {
      if (timerInterval.value) {
        stopTimer();
      }
      startTimer();
    } else if (!status.is_afk && timerInterval.value) {
      stopTimer();
      sessionStartTime.value = null;
    }
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load AFK status"
    );
  }
};

// Start AFK
const handleStartAFK = async () => {
  try {
    await startAFK();
    toast.success("AFK mode started! You'll earn credits while away.");
    await loadStatus();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to start AFK");
  }
};

// Stop AFK
const handleStopAFK = async () => {
  try {
    await stopAFK();
    toast.success("AFK mode stopped.");
    await loadStatus();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to stop AFK");
  }
};

// Claim rewards
const handleClaimRewards = async () => {
  try {
    const result = await claimRewards();
    toast.success(`Successfully claimed ${result.credits_formatted} credits!`);
    await loadStatus();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to claim rewards");
  }
};

// Timer functions
const startTimer = () => {
  if (timerInterval.value) {
    stopTimer();
  }

  // If we have a session start time from the server, use it
  if (afkStatus.value?.started_at) {
    const startedAt = new Date(afkStatus.value.started_at).getTime();
    sessionStartTime.value = startedAt;
  } else {
    // Otherwise use current time
    sessionStartTime.value = Date.now();
  }

  timerInterval.value = window.setInterval(() => {
    if (!sessionStartTime.value) return;

    // Calculate elapsed time from session start
    const currentElapsed = Math.floor(
      (Date.now() - sessionStartTime.value) / 1000
    );
    elapsedTime.value = Math.max(0, currentElapsed);

    // Update next reward countdown based on reward interval
    if (
      afkStatus.value &&
      afkStatus.value.next_reward_in !== null &&
      afkStatus.value.next_reward_in > 0
    ) {
      // Calculate next reward based on elapsed time and interval
      const interval = afkStatus.value.next_reward_in || 60;
      const timeSinceLastReward = elapsedTime.value % interval;
      nextRewardIn.value = Math.max(0, interval - timeSinceLastReward);
    } else {
      nextRewardIn.value = null;
    }

    // Refresh status every 30 seconds to sync with server
    if (currentElapsed > 0 && currentElapsed % 30 === 0) {
      loadStatus();
    }
  }, 1000);
};

const stopTimer = () => {
  if (timerInterval.value) {
    clearInterval(timerInterval.value);
    timerInterval.value = null;
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
          Earn credits by staying AFK. Start the timer and claim your rewards!
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
                {{ formatTime(afkStatus.daily_usage?.time_seconds_today || 0) }}
                /
                {{
                  formatTime(afkStatus.daily_limits.max_time_per_day_seconds)
                }}
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- Status Card -->
      <Card class="mb-4 border-primary/20">
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg bg-primary/10">
              <Clock class="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">AFK Status</h2>
              <p class="text-sm text-muted-foreground">
                Current AFK session information
              </p>
            </div>
          </div>

          <div
            v-if="loading && !afkStatus"
            class="flex items-center justify-center py-8"
          >
            <Loader2 class="h-6 w-6 animate-spin text-primary" />
          </div>

          <div v-else-if="afkStatus" class="space-y-4">
            <!-- Status Badge -->
            <div class="flex items-center gap-2">
              <Badge :variant="isAFKActive ? 'default' : 'secondary'">
                {{ isAFKActive ? "Active" : "Inactive" }}
              </Badge>
              <span class="text-sm text-muted-foreground">
                {{
                  isAFKActive
                    ? "You're currently AFK and earning credits"
                    : "AFK mode is not active"
                }}
              </span>
            </div>

            <!-- Time Elapsed -->
            <div
              v-if="isAFKActive"
              class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-md bg-background">
                  <Timer class="h-4 w-4 text-muted-foreground" />
                </div>
                <div>
                  <div class="text-xs text-muted-foreground mb-1 font-medium">
                    Time Elapsed
                  </div>
                  <div class="text-2xl font-bold">{{ displayTime }}</div>
                </div>
              </div>
            </div>

            <!-- Credits Earned -->
            <div
              v-if="afkStatus.credits_earned > 0"
              class="flex items-center justify-between p-4 rounded-lg bg-green-500/10 border border-green-500/20"
            >
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-md bg-green-500/20">
                  <Wallet class="h-4 w-4 text-green-500" />
                </div>
                <div>
                  <div class="text-xs text-muted-foreground mb-1 font-medium">
                    Credits Earned
                  </div>
                  <div class="text-2xl font-bold text-green-500">
                    {{ afkStatus.credits_formatted }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Next Reward -->
            <div
              v-if="isAFKActive && nextRewardIn !== null && nextRewardIn > 0"
              class="flex items-center justify-between p-4 rounded-lg bg-muted/30 border border-border/50"
            >
              <div class="flex items-center gap-3">
                <div class="p-2 rounded-md bg-background">
                  <Clock class="h-4 w-4 text-muted-foreground" />
                </div>
                <div>
                  <div class="text-xs text-muted-foreground mb-1 font-medium">
                    Next Reward In
                  </div>
                  <div class="text-lg font-semibold">
                    {{ displayNextReward }}
                  </div>
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

      <!-- Actions Card -->
      <Card>
        <div class="p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="p-2.5 rounded-lg bg-primary/10">
              <Play class="h-5 w-5 text-primary" />
            </div>
            <div>
              <h2 class="text-lg font-semibold">Actions</h2>
              <p class="text-sm text-muted-foreground">
                Start or stop AFK mode, and claim your rewards
              </p>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-3">
            <Button
              v-if="!isAFKActive"
              @click="handleStartAFK"
              :disabled="loading"
              class="flex-1"
              size="lg"
            >
              <Play class="h-4 w-4 mr-2" />
              Start AFK
            </Button>

            <Button
              v-else
              @click="handleStopAFK"
              :disabled="loading"
              variant="destructive"
              class="flex-1"
              size="lg"
            >
              <Pause class="h-4 w-4 mr-2" />
              Stop AFK
            </Button>

            <Button
              v-if="canClaimRewards"
              @click="handleClaimRewards"
              :disabled="loading"
              variant="default"
              class="flex-1"
              size="lg"
            >
              <CheckCircle2 class="h-4 w-4 mr-2" />
              Claim Rewards
            </Button>
          </div>

          <Alert v-if="isAFKActive" class="mt-4">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription class="text-sm">
              AFK mode is active. You're earning credits! Remember to claim your
              rewards periodically.
            </AlertDescription>
          </Alert>
        </div>
      </Card>
    </div>
  </div>
</template>
