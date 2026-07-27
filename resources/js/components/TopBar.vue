<template>
  <header class="topbar">
    <!-- Left: page context -->
    <div class="topbar__left">
      <button class="topbar__menu-btn" @click="$emit('toggle-sidebar')" aria-label="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
          <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </button>
      <h1 class="topbar__title">
        <span class="topbar__brand">NET</span><span class="topbar__brand topbar__brand--accent">SIGHT</span>
        <span class="topbar__version">v3.0</span>
      </h1>
    </div>

    <!-- Right: actions -->
    <div class="topbar__right">
      <!-- Status indicator -->
      <div class="topbar__status">
        <span class="topbar__status-dot" />
        <span class="topbar__status-text">System Online</span>
      </div>

      <div class="topbar__divider" />

      <!-- Theme toggle -->
      <ThemeToggle />

      <div class="topbar__divider" />

      <!-- User block with dropdown -->
      <div class="topbar__user-container" @click="dropdownOpen = !dropdownOpen" ref="dropdownRef">
        <div class="topbar__user">
          <div class="topbar__avatar">
            {{ userInitial }}
          </div>
          <div class="topbar__user-info">
            <span class="topbar__user-name">{{ auth.user?.name || 'Operator' }}</span>
            <span
              class="topbar__role-badge"
              :style="{ background: auth.roleBadge.color + '22', color: auth.roleBadge.color }"
            >
              {{ auth.roleBadge.label }}
            </span>
          </div>
          <svg class="topbar__dropdown-icon" :class="{'topbar__dropdown-icon--open': dropdownOpen}" width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Dropdown Menu -->
        <Transition name="fade-slide">
          <div v-if="dropdownOpen" class="topbar__dropdown-menu">
            <router-link to="/profile" class="dropdown-item" @click="dropdownOpen = false">
              <span class="dropdown-item-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              </span>
              My Profile
            </router-link>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item dropdown-item--danger" @click="handleLogout">
              <span class="dropdown-item-icon">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                  <polyline points="16,17 21,12 16,7"/>
                  <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
              </span>
              Logout
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import ThemeToggle from './ThemeToggle.vue'

defineEmits<{
  'toggle-sidebar': []
}>()

const auth = useAuthStore()
const router = useRouter()

const dropdownOpen = ref(false)
const dropdownRef = ref<HTMLElement | null>(null)

// Close dropdown when clicking outside
function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    dropdownOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const userInitial = computed(() =>
  auth.user?.name?.charAt(0).toUpperCase() || 'N'
)

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  height: var(--topbar-height);
  background: var(--surface-1);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
  transition: background var(--transition-slow), border-color var(--transition-slow);
}

.topbar__left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.topbar__menu-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  color: var(--text-2);
  border-radius: var(--radius-md);
  transition: all var(--transition-fast);
}
.topbar__menu-btn:hover {
  color: var(--text-1);
  background: var(--surface-2);
}

@media (max-width: 768px) {
  .topbar__menu-btn {
    display: flex;
  }
}

.topbar__title {
  font-size: 0.9375rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  display: flex;
  align-items: baseline;
  gap: 1px;
}

.topbar__brand {
  color: var(--text-1);
}
.topbar__brand--accent {
  color: var(--accent);
}

.topbar__version {
  font-size: 0.625rem;
  font-weight: 500;
  color: var(--text-3);
  margin-left: 8px;
  font-family: var(--font-mono);
  background: var(--surface-2);
  padding: 1px 5px;
  border-radius: var(--radius-xs);
}

.topbar__right {
  display: flex;
  align-items: center;
  gap: 12px;
}

/* Status */
.topbar__status {
  display: flex;
  align-items: center;
  gap: 6px;
}
.topbar__status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--success);
  box-shadow: 0 0 6px var(--success-dim);
  animation: pulse 2s ease-in-out infinite;
}
.topbar__status-text {
  font-size: 0.75rem;
  color: var(--text-3);
  font-weight: 500;
}

.topbar__divider {
  width: 1px;
  height: 24px;
  background: var(--border);
}

/* User */
.topbar__user-container {
  position: relative;
}

.topbar__user {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: var(--radius-md);
  transition: background var(--transition-fast);
}
.topbar__user:hover {
  background: var(--surface-2);
}

.topbar__dropdown-icon {
  color: var(--text-3);
  transition: transform var(--transition-fast);
}
.topbar__dropdown-icon--open {
  transform: rotate(180deg);
}

.topbar__avatar {
  width: 30px;
  height: 30px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--accent), var(--info));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.topbar__user-info {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.topbar__user-name {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-1);
  line-height: 1;
}

.topbar__role-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.5625rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 1px 5px;
  border-radius: var(--radius-xs);
  line-height: 1;
  width: fit-content;
}

.topbar__dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 180px;
  padding: 6px;
  background: var(--elevated-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--elevated-shadow);
  display: flex;
  flex-direction: column;
  gap: 2px;
  z-index: 200;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: var(--radius-md);
  color: var(--text-1);
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  transition: all var(--transition-fast);
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  width: 100%;
}
.dropdown-item:hover {
  background: var(--surface-2);
}
.dropdown-item-icon {
  display: flex;
  align-items: center;
  color: var(--text-3);
}
.dropdown-item--danger:hover {
  background: var(--danger-dim);
  color: var(--danger);
}
.dropdown-item--danger:hover .dropdown-item-icon {
  color: var(--danger);
}

.dropdown-divider {
  height: 1px;
  background: var(--border);
  margin: 2px 0;
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 200ms var(--ease);
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* Responsive: hide text on small screens */
@media (max-width: 640px) {
  .topbar__status-text,
  .topbar__user-info {
    display: none;
  }
}
</style>
