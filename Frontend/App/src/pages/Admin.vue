<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { Card } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import Textarea from "@/components/ui/textarea/Textarea.vue";
import { Badge } from "@/components/ui/badge";
import {
  Loader2,
  Settings,
  BarChart3,
  Save,
  ChevronLeft,
  ChevronRight,
  Clock,
  DollarSign,
  ToggleLeft,
  ToggleRight,
} from "lucide-vue-next";
import {
  useAFKAdminAPI,
  type AFKSettings,
  type UserAFKStats,
} from "@/composables/useAFKAdminAPI";
import { useToast } from "vue-toastification";

const toast = useToast();
const { getSettings, updateSettings, getAllStats, loading } = useAFKAdminAPI();

// Settings
const settings = ref<AFKSettings | null>(null);
const savingSettings = ref(false);

// Statistics
const userStats = ref<UserAFKStats[]>([]);
const currentPage = ref(1);
const totalPages = ref(1);
const totalUsers = ref(0);
const loadingStats = ref(false);

// Active tab
const activeTab = ref("settings");

// Watch for tab changes
watch(activeTab, (newTab) => {
  if (newTab === "settings" && !settings.value) {
    loadSettings();
  } else if (newTab === "statistics" && userStats.value.length === 0) {
    loadStats();
  }
});

const loadSettings = async () => {
  try {
    settings.value = await getSettings();
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to load settings");
  }
};

const saveSettings = async () => {
  if (!settings.value) return;

  savingSettings.value = true;
  try {
    settings.value = await updateSettings(settings.value);
    toast.success("Settings saved successfully!");
  } catch (err) {
    toast.error(err instanceof Error ? err.message : "Failed to save settings");
  } finally {
    savingSettings.value = false;
  }
};

const loadStats = async (page: number = 1) => {
  currentPage.value = page;
  loadingStats.value = true;
  try {
    const response = await getAllStats(page, 20);
    userStats.value = response.data;
    totalPages.value = response.meta.pagination.total_pages;
    totalUsers.value = response.meta.pagination.total;
  } catch (err) {
    toast.error(
      err instanceof Error ? err.message : "Failed to load statistics"
    );
  } finally {
    loadingStats.value = false;
  }
};

onMounted(() => {
  if (activeTab.value === "settings") {
    loadSettings();
  }
});
</script>

<template>
  <div class="w-full h-full overflow-auto p-4">
    <div class="container mx-auto max-w-6xl">
      <div class="mb-6">
        <h1 class="text-2xl font-semibold">AFK Rewards - Admin</h1>
        <p class="text-sm text-muted-foreground">
          Configure AFK rewards settings and view user statistics
        </p>
      </div>

      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="grid w-full grid-cols-2">
          <TabsTrigger value="settings">
            <Settings class="h-4 w-4 mr-2" />
            Settings
          </TabsTrigger>
          <TabsTrigger value="statistics">
            <BarChart3 class="h-4 w-4 mr-2" />
            Statistics
          </TabsTrigger>
        </TabsList>

        <TabsContent value="settings" class="mt-4">
          <Card>
            <div class="p-6">
              <div
                v-if="loading && !settings"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <form
                v-else-if="settings"
                @submit.prevent="saveSettings"
                class="space-y-6"
              >
                <!-- Enable/Disable -->
                <div
                  class="flex items-center justify-between p-4 border rounded-lg"
                >
                  <div>
                    <Label class="text-base font-semibold"
                      >Enable AFK Rewards</Label
                    >
                    <p class="text-sm text-muted-foreground">
                      Allow users to earn credits by staying AFK
                    </p>
                  </div>
                  <Button
                    type="button"
                    @click="settings.is_enabled = !settings.is_enabled"
                    variant="ghost"
                    size="sm"
                  >
                    <ToggleRight
                      v-if="settings.is_enabled"
                      class="h-6 w-6 text-primary"
                    />
                    <ToggleLeft v-else class="h-6 w-6 text-muted-foreground" />
                  </Button>
                </div>

                <!-- Credits Configuration -->
                <div class="space-y-4">
                  <h3 class="text-lg font-semibold">Credits Configuration</h3>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <Label for="credits_per_minute">Credits per Minute</Label>
                      <Input
                        id="credits_per_minute"
                        v-model.number="settings.credits_per_minute"
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-2"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        How many credits users earn per minute of AFK time
                      </p>
                    </div>

                    <div>
                      <Label for="minutes_per_credit"
                        >Minutes per Credit (Alternative)</Label
                      >
                      <Input
                        id="minutes_per_credit"
                        :model-value="settings.minutes_per_credit ?? undefined"
                        @update:model-value="
                          settings.minutes_per_credit = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        step="0.01"
                        min="0"
                        class="mt-2"
                        placeholder="Leave empty to use credits per minute"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Alternative: minutes required to earn 1 credit
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Reward Interval -->
                <div>
                  <Label for="reward_interval_seconds"
                    >Reward Interval (seconds)</Label
                  >
                  <Input
                    id="reward_interval_seconds"
                    v-model.number="settings.reward_interval_seconds"
                    type="number"
                    min="1"
                    class="mt-2"
                  />
                  <p class="text-sm text-muted-foreground mt-1">
                    How often to calculate and award credits (in seconds)
                  </p>
                </div>

                <!-- Limits -->
                <div class="space-y-4">
                  <h3 class="text-lg font-semibold">Session Limits</h3>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <Label for="max_credits_per_session"
                        >Max Credits per Session</Label
                      >
                      <Input
                        id="max_credits_per_session"
                        :model-value="
                          settings.max_credits_per_session ?? undefined
                        "
                        @update:model-value="
                          settings.max_credits_per_session = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        min="0"
                        class="mt-2"
                        placeholder="No limit"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Maximum credits that can be earned in a single session
                        (leave empty for no limit)
                      </p>
                    </div>

                    <div>
                      <Label for="max_session_duration_seconds"
                        >Max Session Duration (seconds)</Label
                      >
                      <Input
                        id="max_session_duration_seconds"
                        :model-value="
                          settings.max_session_duration_seconds ?? undefined
                        "
                        @update:model-value="
                          settings.max_session_duration_seconds = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        min="0"
                        class="mt-2"
                        placeholder="No limit"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Maximum session duration in seconds (leave empty for no
                        limit)
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Daily Limits -->
                <div class="space-y-4">
                  <h3 class="text-lg font-semibold">Daily Limits</h3>
                  <p class="text-sm text-muted-foreground">
                    Set maximum limits per user per day to prevent abuse
                  </p>

                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <Label for="max_credits_per_day"
                        >Max Credits per Day</Label
                      >
                      <Input
                        id="max_credits_per_day"
                        :model-value="settings.max_credits_per_day ?? undefined"
                        @update:model-value="
                          settings.max_credits_per_day = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        min="0"
                        class="mt-2"
                        placeholder="No limit"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Maximum credits a user can earn per day (leave empty for
                        no limit)
                      </p>
                    </div>

                    <div>
                      <Label for="max_sessions_per_day"
                        >Max Sessions per Day</Label
                      >
                      <Input
                        id="max_sessions_per_day"
                        :model-value="
                          settings.max_sessions_per_day ?? undefined
                        "
                        @update:model-value="
                          settings.max_sessions_per_day = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        min="0"
                        class="mt-2"
                        placeholder="No limit"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Maximum number of AFK sessions per day (leave empty for
                        no limit)
                      </p>
                    </div>

                    <div>
                      <Label for="max_time_per_day_seconds"
                        >Max Time per Day (seconds)</Label
                      >
                      <Input
                        id="max_time_per_day_seconds"
                        :model-value="
                          settings.max_time_per_day_seconds ?? undefined
                        "
                        @update:model-value="
                          settings.max_time_per_day_seconds = $event
                            ? Number($event)
                            : null
                        "
                        type="number"
                        min="0"
                        class="mt-2"
                        placeholder="No limit"
                      />
                      <p class="text-sm text-muted-foreground mt-1">
                        Maximum total AFK time per day in seconds (leave empty
                        for no limit)
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Claim Settings -->
                <div class="space-y-4">
                  <h3 class="text-lg font-semibold">Claim Settings</h3>

                  <div
                    class="flex items-center justify-between p-4 border rounded-lg"
                  >
                    <div>
                      <Label class="text-base font-semibold"
                        >Require Manual Claim</Label
                      >
                      <p class="text-sm text-muted-foreground">
                        Users must manually claim their rewards
                      </p>
                    </div>
                    <Button
                      type="button"
                      @click="settings.require_claim = !settings.require_claim"
                      variant="ghost"
                      size="sm"
                    >
                      <ToggleRight
                        v-if="settings.require_claim"
                        class="h-6 w-6 text-primary"
                      />
                      <ToggleLeft
                        v-else
                        class="h-6 w-6 text-muted-foreground"
                      />
                    </Button>
                  </div>

                  <div v-if="!settings.require_claim">
                    <Label for="auto_claim_interval_seconds"
                      >Auto-Claim Interval (seconds)</Label
                    >
                    <Input
                      id="auto_claim_interval_seconds"
                      :model-value="
                        settings.auto_claim_interval_seconds ?? undefined
                      "
                      @update:model-value="
                        settings.auto_claim_interval_seconds = $event
                          ? Number($event)
                          : null
                      "
                      type="number"
                      min="1"
                      class="mt-2"
                      placeholder="Disabled"
                    />
                    <p class="text-sm text-muted-foreground mt-1">
                      Automatically claim rewards after this many seconds (leave
                      empty to disable)
                    </p>
                  </div>
                </div>

                <!-- JavaScript Injection -->
                <div>
                  <h3 class="text-lg font-semibold mb-2">
                    JavaScript Injection
                  </h3>
                  <Label for="javascript_injection"
                    >Custom JavaScript Code</Label
                  >
                  <Textarea
                    id="javascript_injection"
                    v-model="settings.javascript_injection"
                    rows="6"
                    class="mt-2 font-mono text-sm"
                    placeholder="// Inject custom JavaScript (e.g., for ads)&#10;// This code will be executed on the AFK page"
                  />
                  <p class="text-sm text-muted-foreground mt-1">
                    Custom JavaScript code to inject into the AFK page (e.g.,
                    for ads, analytics, etc.)
                  </p>
                </div>

                <div class="flex justify-end pt-4 border-t">
                  <Button type="submit" :disabled="savingSettings">
                    <Loader2
                      v-if="savingSettings"
                      class="h-4 w-4 mr-2 animate-spin"
                    />
                    <Save v-else class="h-4 w-4 mr-2" />
                    Save Settings
                  </Button>
                </div>
              </form>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="statistics" class="mt-4">
          <Card>
            <div class="p-6">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">User Statistics</h3>
                <Button
                  @click="loadStats(currentPage)"
                  variant="outline"
                  size="sm"
                >
                  Refresh
                </Button>
              </div>

              <div
                v-if="loadingStats && userStats.length === 0"
                class="flex items-center justify-center py-12"
              >
                <Loader2 class="h-8 w-8 animate-spin" />
              </div>
              <div
                v-else-if="userStats.length === 0"
                class="text-center py-12 text-muted-foreground"
              >
                No statistics available
              </div>
              <div v-else class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="border-b">
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        User
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Total Time
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Credits Earned
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Sessions
                      </th>
                      <th
                        class="text-left p-4 text-sm font-medium text-muted-foreground"
                      >
                        Last Session
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="stat in userStats"
                      :key="stat.user_id"
                      class="border-b hover:bg-muted/50"
                    >
                      <td class="p-4">
                        <div>
                          <div class="font-medium">{{ stat.username }}</div>
                          <div class="text-sm text-muted-foreground">
                            {{ stat.email || "N/A" }}
                          </div>
                        </div>
                      </td>
                      <td class="p-4">
                        <div class="flex items-center gap-2">
                          <Clock class="h-4 w-4 text-muted-foreground" />
                          {{ stat.total_time_formatted }}
                        </div>
                      </td>
                      <td class="p-4">
                        <div class="flex items-center gap-2">
                          <DollarSign class="h-4 w-4 text-muted-foreground" />
                          {{ stat.total_credits_formatted }}
                        </div>
                      </td>
                      <td class="p-4">
                        <Badge variant="secondary">
                          {{ stat.sessions_count }}
                        </Badge>
                      </td>
                      <td class="p-4 text-sm text-muted-foreground">
                        {{ stat.last_session_at || "Never" }}
                      </td>
                    </tr>
                  </tbody>
                </table>

                <!-- Pagination -->
                <div
                  v-if="totalPages > 1"
                  class="flex items-center justify-center gap-2 mt-6"
                >
                  <Button
                    @click="loadStats(currentPage - 1)"
                    :disabled="currentPage === 1"
                    variant="outline"
                    size="sm"
                  >
                    <ChevronLeft class="h-4 w-4" />
                  </Button>
                  <span class="text-sm text-muted-foreground">
                    Page {{ currentPage }} of {{ totalPages }} ({{ totalUsers }}
                    total users)
                  </span>
                  <Button
                    @click="loadStats(currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    variant="outline"
                    size="sm"
                  >
                    <ChevronRight class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  </div>
</template>
