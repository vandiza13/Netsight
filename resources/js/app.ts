import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'

// ── Theme Initialization ─────────────────────────────────────────
// Restore saved theme or respect system preference
const savedTheme = localStorage.getItem('netsight-theme')
if (savedTheme) {
  document.documentElement.setAttribute('data-theme', savedTheme)
} else {
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
  document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'dark')
  // Default to dark for NOC environments
}

// Listen for system theme changes (when no explicit preference saved)
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
  if (!localStorage.getItem('netsight-theme')) {
    document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light')
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
