import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Sous Docker Desktop (Windows), les modifications des fichiers montés ne
    // déclenchent pas d'événement dans le conteneur : sans polling, Vite
    // continue de servir l'ancienne version.
    watch: { usePolling: true, interval: 500 },
  },
})
