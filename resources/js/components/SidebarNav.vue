<template>
  <aside class="sidebar" :class="{ 'sidebar--open': isOpen }">
    <!-- Overlay for mobile -->
    <div class="sidebar__overlay" @click="$emit('close')" />

    <nav class="sidebar__inner">
      <!-- Logo area -->
      <div class="sidebar__logo">
        <svg viewBox="0 0 400 100" xmlns="http://www.w3.org/2000/svg" class="sidebar__logo-svg">
          <defs>
            <linearGradient id="logoBlueGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#06b6d4" />
              <stop offset="100%" stop-color="#3b82f6" />
            </linearGradient>
          </defs>
          <!-- Netsight Text -->
          <text x="200" y="65" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="800" font-size="64" fill="var(--text-primary, #ffffff)" text-anchor="middle" letter-spacing="-1">
            Nets<tspan fill="url(#logoBlueGrad)">i</tspan>ght
          </text>
          <!-- Tagline -->
          <g transform="translate(200, 88)">
            <line x1="-190" y1="-4" x2="-130" y2="-4" stroke="url(#logoBlueGrad)" stroke-width="2.5" />
            <text x="0" y="0" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="700" font-size="12" fill="var(--text-secondary, #9ca3af)" text-anchor="middle" letter-spacing="2">
              INSPECT TRAFFIC. <tspan fill="url(#logoBlueGrad)">SOLVE FASTER.</tspan>
            </text>
            <line x1="130" y1="-4" x2="190" y2="-4" stroke="url(#logoBlueGrad)" stroke-width="2.5" />
          </g>
        </svg>
      </div>

      <!-- Navigation items -->
      <ul class="sidebar__nav">
        <li v-for="item in visibleItems" :key="item.key">
          <button
            class="sidebar__link"
            :class="{ 'sidebar__link--active': activeItem === item.key }"
            @click="handleNav(item.key)"
          >
            <span class="sidebar__link-icon">{{ item.icon }}</span>
            <span class="sidebar__link-label">{{ item.label }}</span>
            <span
              v-if="item.badge"
              class="sidebar__link-badge"
              :style="{ background: item.badgeColor }"
            >
              {{ item.badge }}
            </span>
          </button>
        </li>
      </ul>

      <!-- Bottom section -->
      <div class="sidebar__footer">
        <div class="sidebar__footer-line" />
        <div class="sidebar__footer-info">
          <span class="sidebar__footer-label">Netsight</span>
          <span class="sidebar__footer-version">By Vandiza Tech</span>
        </div>
      </div>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

interface NavItem {
  key: string
  label: string
  icon: string
  requiresTier2?: boolean
  requiresAdmin?: boolean
  badge?: string
  badgeColor?: string
}

defineProps<{
  isOpen: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const activeItem = ref('dashboard')

// Sync active item with current route
watch(() => route.path, (newPath) => {
  if (newPath === '/routers') {
    activeItem.value = 'routers'
  } else if (newPath === '/dashboard') {
    activeItem.value = 'dashboard'
  } else if (newPath === '/inspect') {
    activeItem.value = 'inspect'
  } else if (newPath === '/audit') {
    activeItem.value = 'audit'
  } else if (newPath === '/staff') {
    activeItem.value = 'staff'
  }
}, { immediate: true })

const navItems: NavItem[] = [
  { key: 'dashboard', label: 'Dashboard', icon: '📊' },
  { key: 'routers', label: 'Routers', icon: '🖧' },
  { key: 'inspect', label: 'Inspect', icon: '🔍', requiresTier2: true, badge: 'T2+', badgeColor: 'rgba(245, 158, 11, 0.25)' },
  { key: 'staff', label: 'Staff Management', icon: '👥', requiresAdmin: true, badge: 'ADM', badgeColor: 'rgba(239, 68, 68, 0.25)' },
  { key: 'audit', label: 'Audit Log', icon: '📋', requiresAdmin: true, badge: 'ADM', badgeColor: 'rgba(239, 68, 68, 0.25)' },
]

const visibleItems = computed(() =>
  navItems.filter((item) => {
    if (item.requiresAdmin && !auth.isAdmin) return false
    if (item.requiresTier2 && !auth.isTier2) return false
    return true
  })
)

function handleNav(key: string) {
  activeItem.value = key
  
  if (key === 'dashboard') {
    router.push('/dashboard')
  } else if (key === 'routers') {
    router.push('/routers')
  } else if (key === 'staff') {
    router.push('/staff')
  } else if (key === 'audit') {
    router.push('/audit')
  } else if (key === 'inspect') {
    router.push('/inspect')
  }
  
  emit('close') // Close sidebar on mobile
}
</script>

<style scoped>
.sidebar {
  position: sticky;
  top: 0;
  width: 240px;
  flex-shrink: 0;
  height: 100vh;
  height: 100dvh;
  z-index: 200;
}

.sidebar__overlay {
  display: none;
}

.sidebar__inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--bg-secondary);
  border-right: 1px solid var(--glass-border);
  padding: 20px 12px;
  overflow-y: auto;
}

/* Logo */
.sidebar__logo {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px 12px 24px;
}

.sidebar__logo-svg {
  width: 100%;
  max-width: 180px;
  height: auto;
  display: block;
}

/* Navigation */
.sidebar__nav {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 14px;
  border-radius: var(--radius-sm);
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-weight: 500;
  transition: all var(--transition-fast);
  position: relative;
}

.sidebar__link:hover {
  color: var(--text-primary);
  background: rgba(255, 255, 255, 0.04);
}

.sidebar__link--active {
  color: var(--accent-cyan);
  background: var(--accent-cyan-dim);
}
.sidebar__link--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 20px;
  border-radius: 0 3px 3px 0;
  background: var(--accent-cyan);
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
}

.sidebar__link-icon {
  font-size: 1.1rem;
  width: 24px;
  text-align: center;
  flex-shrink: 0;
}

.sidebar__link-label {
  flex: 1;
}

.sidebar__link-badge {
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 6px;
  border-radius: 4px;
  color: var(--text-secondary);
}

/* Footer */
.sidebar__footer {
  padding: 16px 12px 4px;
}

.sidebar__footer-line {
  height: 1px;
  background: var(--glass-border);
  margin-bottom: 12px;
}

.sidebar__footer-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sidebar__footer-label {
  font-size: 0.65rem;
  font-weight: 600;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.sidebar__footer-version {
  font-size: 0.6rem;
  color: var(--text-muted);
  font-family: var(--font-mono);
  opacity: 0.7;
}

/* ── Mobile ──────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 260px;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .sidebar--open {
    transform: translateX(0);
  }

  .sidebar--open .sidebar__overlay {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: -1;
  }

  .sidebar__inner {
    box-shadow: var(--shadow-elevated);
  }
}
</style>
