<template>
  <header class="topbar glass-card">
    <!-- Left: page context -->
    <div class="topbar__left">
      <button class="topbar__menu-btn" @click="$emit('toggle-sidebar')" aria-label="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
          <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </button>
      <h1 class="topbar__title">
        <span class="topbar__brand">NET</span><span class="topbar__brand topbar__brand--accent">SIGHT</span>
        <span class="topbar__version">v2.1</span>
      </h1>
    </div>

    <!-- Right: user info -->
    <div class="topbar__right">
      <!-- Status indicator -->
      <div class="topbar__status">
        <span class="topbar__status-dot" />
        <span class="topbar__status-text">System Online</span>
      </div>

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
          <div v-if="dropdownOpen" class="topbar__dropdown-menu glass-card">
            <router-link to="/profile" class="dropdown-item" @click="dropdownOpen = false">
              <span class="dropdown-item-icon">👤</span>
              My Profile
            </router-link>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item dropdown-item--danger" @click="handleLogout">
              <span class="dropdown-item-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <polyline points="16,17 21,12 16,7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <line x1="21" y1="12" x2="9" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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
  height: 64px;
  border-radius: 0;
  border-top: none;
  border-left: none;
  border-right: none;
  position: sticky;
  top: 0;
  z-index: 100;
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
  width: 36px;
  height: 36px;
  color: var(--text-secondary);
  border-radius: var(--radius-sm);
  transition: all var(--transition-fast);
}
.topbar__menu-btn:hover {
  color: var(--text-primary);
  background: rgba(255, 255, 255, 0.06);
}

@media (max-width: 768px) {
  .topbar__menu-btn {
    display: flex;
  }
}

.topbar__title {
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  display: flex;
  align-items: baseline;
  gap: 2px;
}

.topbar__brand {
  color: var(--text-primary);
}
.topbar__brand--accent {
  color: var(--accent-cyan);
  text-shadow: 0 0 12px rgba(6, 182, 212, 0.4);
}

.topbar__version {
  font-size: 0.65rem;
  font-weight: 500;
  color: var(--text-muted);
  margin-left: 6px;
  font-family: var(--font-mono);
}

.topbar__right {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Status */
.topbar__status {
  display: flex;
  align-items: center;
  gap: 6px;
}
.topbar__status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--accent-green);
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
  animation: pulse 2s ease-in-out infinite;
}
.topbar__status-text {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 500;
}

.topbar__divider {
  width: 1px;
  height: 28px;
  background: var(--glass-border);
}

/* User */
.topbar__user-container {
  position: relative;
}

.topbar__user {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  transition: background 0.2s;
}
.topbar__user:hover {
  background: rgba(255, 255, 255, 0.05);
}

.topbar__dropdown-icon {
  color: var(--text-muted);
  transition: transform 0.2s ease;
}
.topbar__dropdown-icon--open {
  transform: rotate(180deg);
}

.topbar__avatar {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.85rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}

.topbar__user-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.topbar__user-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
  line-height: 1;
}

.topbar__role-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: 4px;
  line-height: 1;
  width: fit-content;
}

.topbar__dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 180px;
  padding: 8px;
  border-radius: var(--radius-md);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 200;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s;
  background: transparent;
  border: none;
  cursor: pointer;
  text-align: left;
  width: 100%;
}
.dropdown-item:hover {
  background: rgba(255, 255, 255, 0.08);
}
.dropdown-item-icon {
  font-size: 1rem;
  display: flex;
  align-items: center;
  color: var(--text-muted);
}
.dropdown-item--danger:hover {
  background: var(--accent-red-dim);
  color: var(--accent-red);
}
.dropdown-item--danger:hover .dropdown-item-icon {
  color: var(--accent-red);
}

.dropdown-divider {
  height: 1px;
  background: var(--glass-border);
  margin: 4px 0;
}

/* Animations */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.2s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* Responsive: hide text on small screens */
@media (max-width: 640px) {
  .topbar__status-text,
  .topbar__user-info {
    display: none;
  }
}
</style>
