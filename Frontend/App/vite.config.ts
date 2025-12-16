import path from "node:path";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  base: "./",
  build: {
    outDir: "../Components/billingafk/dist/",
    emptyOutDir: true,
    rollupOptions: {
      input: {
        afk: "./afk.html",
        admin: "./admin.html",
      },
    },
  },
});
