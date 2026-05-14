<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from "vue";

const props = defineProps<{
  provider: string;
  config: any;
  theme?: string;
}>();

const emit = defineEmits<{
  (e: "verify", token: string): void;
  (e: "error", err: any): void;
  (e: "expire"): void;
}>();

const captchaContainer = ref<HTMLElement | null>(null);
const widgetId = ref<any>(null);
const friendlyWidget = ref<any>(null);
const reforgeInterval = ref<any>(null);
const lastReforgeToken = ref("");

const loadScript = (src: string, id: string): Promise<void> => {
  return new Promise((resolve, reject) => {
    if (document.getElementById(id)) {
      resolve();
      return;
    }
    const script = document.createElement("script");
    script.src = src;
    script.id = id;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve();
    script.onerror = (err) => reject(err);
    document.head.appendChild(script);
  });
};

const renderCaptcha = async () => {
  if (!captchaContainer.value || !props.config) return;

  const providerConfig = props.config[props.provider];
  if (!providerConfig || !providerConfig.site_key) return;

  const theme = props.theme || props.config.theme || "dark";

  try {
    switch (props.provider) {
      case "turnstile":
        await loadScript(
          "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit",
          "cf-turnstile-script"
        );
        // @ts-ignore
        widgetId.value = window.turnstile.render(captchaContainer.value, {
          sitekey: providerConfig.site_key,
          theme: theme,
          callback: (token: string) => emit("verify", token),
          "expired-callback": () => emit("expire"),
          "error-callback": (err: any) => emit("error", err),
        });
        break;

      case "hcaptcha":
        await loadScript(
          "https://js.hcaptcha.com/1/api.js?render=explicit",
          "hcaptcha-script"
        );
        // @ts-ignore
        widgetId.value = window.hcaptcha.render(captchaContainer.value, {
          sitekey: providerConfig.site_key,
          theme: theme,
          callback: (token: string) => emit("verify", token),
          "expired-callback": () => emit("expire"),
          "error-callback": (err: any) => emit("error", err),
        });
        break;

      case "recaptcha":
        await loadScript(
          "https://www.google.com/recaptcha/api.js?render=explicit",
          "google-recaptcha-script"
        );
        // @ts-ignore
        window.grecaptcha.ready(() => {
          if (providerConfig.version === "v3") {
            if (captchaContainer.value) {
                captchaContainer.value.innerHTML = `<div class="text-destructive font-medium text-sm text-center p-4 border border-destructive/20 rounded-lg bg-destructive/10">reCAPTCHA v3 is not supported.<br/>Please use reCAPTCHA v2.</div>`;
            }
            return;
          }
          // @ts-ignore
          widgetId.value = window.grecaptcha.render(captchaContainer.value, {
            sitekey: providerConfig.site_key,
            theme: theme,
            callback: (token: string) => emit("verify", token),
            "expired-callback": () => emit("expire"),
            "error-callback": (err: any) => emit("error", err),
          });
        });
        break;

      case "friendlycaptcha":
        await loadScript(
          "https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.14/widget.min.js",
          "friendly-challenge-widget-js"
        );
        // @ts-ignore
        if (window.friendlyChallenge?.WidgetInstance) {
           captchaContainer.value.classList.add('frc-captcha');
           if (theme === 'dark') captchaContainer.value.classList.add('dark');
           // @ts-ignore
           friendlyWidget.value = new window.friendlyChallenge.WidgetInstance(captchaContainer.value, {
               sitekey: providerConfig.site_key,
               startMode: 'focus',
               doneCallback: (solution: string) => emit("verify", solution),
               errorCallback: (err: any) => emit("error", err),
           });
        }
        break;

      case "reforge":
        await loadScript(
          "https://reforgecaptcha.cloud/api/widget",
          "reforge-captcha-script"
        );
        
        // Reforge uses data attributes on the div
        captchaContainer.value.className = 'reforge-captcha flex justify-center';
        captchaContainer.value.setAttribute('data-sitekey', providerConfig.site_key);
        captchaContainer.value.setAttribute('data-type', providerConfig.widget_type || 'checkbox');
        captchaContainer.value.setAttribute('data-theme', providerConfig.theme || 'auto');
        captchaContainer.value.setAttribute('data-size', providerConfig.size || 'normal');
        if (providerConfig.lang) {
            captchaContainer.value.setAttribute('data-lang', providerConfig.lang);
        }

        // Poll for token in hidden input
        reforgeInterval.value = setInterval(() => {
            const inp = document.querySelector<HTMLInputElement>('input[name="reforge-captcha-token"]');
            const v = inp?.value?.trim() ?? '';
            if (v && v !== lastReforgeToken.value) {
                lastReforgeToken.value = v;
                emit("verify", v);
            }
        }, 500);
        break;
    }
  } catch (err) {
    emit("error", err);
  }
};

const execute = (): Promise<string | void> => {
  return new Promise((resolve, reject) => {
    const providerConfig = props.config?.[props.provider];
    
    if (props.provider === 'recaptcha' && providerConfig?.version === 'v3') {
        reject(new Error("reCAPTCHA v3 is not supported. Please use reCAPTCHA v2 or another provider."));
        return;
    }

    if (widgetId.value === null) {
        resolve();
        return;
    }

    try {
      switch (props.provider) {
        case "turnstile":
          // @ts-ignore
          window.turnstile.execute(widgetId.value);
          resolve();
          break;
        case "hcaptcha":
          // @ts-ignore
          window.hcaptcha.execute(widgetId.value).then((token: string) => {
              emit("verify", token);
              resolve(token);
          });
          break;
        case "recaptcha":
          // @ts-ignore
          window.grecaptcha.execute(widgetId.value).then((token: string) => {
              emit("verify", token);
              resolve(token);
          });
          break;
        default:
          resolve();
      }
    } catch (err) {
      reject(err);
    }
  });
};

const reset = () => {
  if (reforgeInterval.value) {
      clearInterval(reforgeInterval.value);
      reforgeInterval.value = null;
  }
  lastReforgeToken.value = "";

  if (friendlyWidget.value) {
      friendlyWidget.value.reset();
  }

  if (widgetId.value === null) return;

  try {
    switch (props.provider) {
      case "turnstile":
        // @ts-ignore
        window.turnstile.reset(widgetId.value);
        break;
      case "hcaptcha":
        // @ts-ignore
        window.hcaptcha.reset(widgetId.value);
        break;
      case "recaptcha":
        // @ts-ignore
        window.grecaptcha.reset(widgetId.value);
        break;
    }
  } catch (err) {
    console.error("Failed to reset captcha:", err);
  }
};

defineExpose({ reset, execute });

onMounted(() => {
  renderCaptcha();
});

onUnmounted(() => {
  if (reforgeInterval.value) {
      clearInterval(reforgeInterval.value);
  }
  if (friendlyWidget.value) {
      friendlyWidget.value.destroy?.();
  }
});

watch([() => props.provider, () => props.config], () => {
  reset();
  renderCaptcha();
});
</script>

<template>
  <div class="captcha-wrapper flex justify-center py-4">
    <div ref="captchaContainer"></div>
  </div>
</template>
