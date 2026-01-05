import path from "path"
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  base: "/ui/",
  plugins: [react(), tailwindcss()],
  server: {
    allowedHosts: ["react-dev"],
    origin: "https://db.cue.tools",
    hmr: {
      host: "db.cue.tools",
      protocol: "wss",
      clientPort: 443,
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
})
