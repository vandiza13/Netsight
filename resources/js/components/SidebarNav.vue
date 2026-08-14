<template>
  <aside class="sidebar" :class="{ 'sidebar--open': isOpen }">
    <!-- Overlay for mobile -->
    <div class="sidebar__overlay" @click="$emit('close')" />

    <nav class="sidebar__inner">
      <!-- Logo area -->
      <div class="sidebar__logo">
        <svg viewBox="0 0 320 100" xmlns="http://www.w3.org/2000/svg" class="sidebar__logo-svg">
          <!-- Netsight Text -->
          <text x="10" y="60" font-family="var(--font-sans)" font-weight="800" font-size="60" fill="var(--text-1)" letter-spacing="-1">
            Nets<tspan fill="#3b82f6">i</tspan>ght
          </text>
          <!-- Tagline -->
          <g transform="translate(10, 85)">
            <text x="0" y="0" font-family="var(--font-sans)" font-weight="700" font-size="14" fill="var(--text-3)" letter-spacing="1.5">
              INSPECT TRAFFIC. <tspan fill="#3b82f6">SOLVE FASTER.</tspan>
            </text>
          </g>
        </svg>
      </div>

      <!-- Section: Main -->
      <div class="sidebar__section">
        <span class="sidebar__section-title">Menu</span>
      </div>

      <!-- Navigation items -->
      <ul class="sidebar__nav">
        <li v-for="item in visibleItems" :key="item.key">
          <button
            class="sidebar__link"
            :class="{ 'sidebar__link--active': activeItem === item.key }"
            @click="handleNav(item.key)"
          >
            <span class="sidebar__link-icon" v-html="item.svgIcon"></span>
            <span class="sidebar__link-label">{{ item.label }}</span>
            <span
              v-if="item.badge"
              class="sidebar__link-badge"
              :class="item.badgeClass"
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

// ── Lucide-style SVG Icons (inline, 18x18, stroke-based) ──────
const icons = {
  dashboard: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
  routers: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="12" x2="6" y2="12.01"/><line x1="10" y1="12" x2="10" y2="12.01"/><path d="M6 6V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/></svg>',
  olts: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
  acs: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>',
  inspect: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
  staff: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  audit: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
}

interface NavItem {
  key: string
  label: string
  svgIcon: string
  requiresTier2?: boolean
  requiresAdmin?: boolean
  badge?: string
  badgeClass?: string
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
  } else if (newPath === '/olts') {
    activeItem.value = 'olts'
  } else if (newPath === '/acs') {
    activeItem.value = 'acs'
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
  { key: 'dashboard', label: 'Dashboard', svgIcon: icons.dashboard },
  { key: 'routers', label: 'Routers', svgIcon: icons.routers },
  { key: 'olts', label: 'OLT Management', svgIcon: icons.olts, requiresTier2: true, badge: 'T2+', badgeClass: 'sidebar__link-badge--warning' },
  { key: 'acs', label: 'TR-069 ACS', svgIcon: icons.acs, requiresTier2: true, badge: 'T2+', badgeClass: 'sidebar__link-badge--warning' },
  { key: 'inspect', label: 'Inspect', svgIcon: icons.inspect, requiresTier2: true, badge: 'T2+', badgeClass: 'sidebar__link-badge--warning' },
  { key: 'staff', label: 'Staff Management', svgIcon: icons.staff, requiresAdmin: true, badge: 'ADM', badgeClass: 'sidebar__link-badge--danger' },
  { key: 'audit', label: 'Audit Log', svgIcon: icons.audit, requiresAdmin: true, badge: 'ADM', badgeClass: 'sidebar__link-badge--danger' },
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
  } else if (key === 'olts') {
    router.push('/olts')
  } else if (key === 'acs') {
    router.push('/acs')
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
  width: var(--sidebar-width);
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
  background: var(--surface-1);
  border-right: 1px solid var(--border);
  padding: 16px 12px;
  overflow-y: auto;
  transition: background var(--transition-slow), border-color var(--transition-slow);
}

/* ── Logo ──────────────────────────────────────────────────── */
.sidebar__logo {
  padding: 8px 8px 20px;
}

.sidebar__logo-svg {
  width: 100%;
  max-width: 190px;
  height: auto;
  display: block;
}

/* ── Section Title ────────────────────────────────────────── */
.sidebar__section {
  padding: 0 8px 6px;
}

.sidebar__section-title {
  font-size: 0.6875rem;
  font-weight: 600;
  color: var(--text-3);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* ── Navigation ───────────────────────────────────────────── */
.sidebar__nav {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 12px;
  border-radius: var(--radius-md);
  color: var(--text-2);
  font-size: 0.8125rem;
  font-weight: 500;
  transition: all var(--transition-fast);
  position: relative;
}

.sidebar__link:hover {
  color: var(--text-1);
  background: var(--surface-2);
}

.sidebar__link:focus-visible {
  outline: none;
  box-shadow: 0 0 0 2px var(--surface-0), 0 0 0 4px var(--accent);
}

.sidebar__link--active {
  color: var(--accent);
  background: var(--accent-dim);
}
.sidebar__link--active:hover {
  background: var(--accent-dim);
}
.sidebar__link--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 16px;
  border-radius: 0 3px 3px 0;
  background: var(--accent);
  box-shadow: 0 0 8px var(--accent-glow);
}

.sidebar__link-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Ensure inline SVG icons inherit color */
.sidebar__link-icon :deep(svg) {
  display: block;
}

.sidebar__link-label {
  flex: 1;
}

.sidebar__link-badge {
  font-size: 0.5625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 1px 6px;
  border-radius: var(--radius-full);
}

.sidebar__link-badge--warning {
  background: var(--warning-dim);
  color: var(--warning);
}

.sidebar__link-badge--danger {
  background: var(--danger-dim);
  color: var(--danger);
}

/* ── Footer ───────────────────────────────────────────────── */
.sidebar__footer {
  padding: 12px 8px 4px;
}

.sidebar__footer-line {
  height: 1px;
  background: var(--border);
  margin-bottom: 12px;
}

.sidebar__footer-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sidebar__footer-label {
  font-size: 0.625rem;
  font-weight: 600;
  color: var(--text-3);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.sidebar__footer-version {
  font-size: 0.5625rem;
  color: var(--text-3);
  font-family: var(--font-mono);
  opacity: 0.7;
}

/* ── Mobile ───────────────────────────────────────────────── */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 260px;
    transform: translateX(-100%);
    transition: transform 0.3s var(--ease);
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
    background: var(--overlay);
    z-index: -1;
  }

  .sidebar__inner {
    box-shadow: var(--elevated-shadow);
  }
}
</style>
