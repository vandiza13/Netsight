<template>
  <div class="dashboard">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <!-- Welcome banner -->
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">
            Welcome back, <span class="dashboard__heading--accent">{{ auth.user?.name || 'Operator' }}</span>
          </h2>
          <p class="dashboard__heading-sub">
            Network Operations Center — real-time overview
          </p>
        </section>

        <!-- Stat Cards -->
        <section class="dashboard__stats stagger">
          <StatCard
            title="Routers Online"
            :value="stats.routersOnline"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>'
            :accent-color="'var(--accent-green)'"
            subtitle="All regions"
          />
          <StatCard
            title="Active PPPoE Sessions"
            :value="stats.pppoeSessions"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12h4l2.25 -4.5 4.5 9 4.5 -9 2.25 4.5h4"/></svg>'
            :accent-color="'var(--accent-cyan)'"
            subtitle="Across all routers"
          />
          <StatCard
            title="Active Torch Sessions"
            :value="stats.torchSessions"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>'
            :accent-color="'var(--accent-amber)'"
            subtitle="Live inspections"
          />
          <StatCard
            title="System Status"
            :value="stats.systemStatus"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>'
            :accent-color="stats.systemStatus === 'Healthy' ? 'var(--accent-green)' : 'var(--accent-red)'"
            subtitle="All services operational"
          />
        </section>

        <!-- OLT Stats Row -->
        <section class="dashboard__stats stagger" style="margin-top: 16px;">
          <StatCard
            title="Master OLTs"
            :value="oltStats.totalOlts"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>'
            :accent-color="'var(--accent-cyan)'"
            subtitle="Total active OLT units"
          />
          <StatCard
            title="ONU Online"
            :value="oltStats.onlineOnus"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>'
            :accent-color="'var(--accent-green)'"
            subtitle="Connected subscribers"
          />
          <StatCard
            title="ONU LOS"
            :value="oltStats.losOnus"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 9-6 6"/><path d="m9 9 6 6"/><circle cx="12" cy="12" r="10"/></svg>'
            :accent-color="'var(--accent-red)'"
            subtitle="Require immediate attention"
          />
          <StatCard
            title="Total ONUs"
            :value="oltStats.totalOnus"
            icon='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8v1a2 2 0 0 1 -2 2h-4a2 2 0 0 1 -2 -2V8a2 2 0 0 1 2 -2h4a2 2 0 0 1 2 2z"/><path d="M12 13v9"/></svg>'
            :accent-color="'var(--accent-amber)'"
            subtitle="Total configured ports"
          />
        </section>

        <!-- Global Traffic Chart -->
        <section class="dashboard__traffic stagger" style="margin-top: 24px;">
          <GlobalTrafficChart />
        </section>

        <!-- Main Layout -->
        <section class="dashboard__layout stagger" style="margin-top: 24px;">
          <!-- Left Column (Main) -->
          <div class="layout-main">
            <div class="glass-card table-panel">
              <RouterHealthTable />
            </div>
          </div>

          <!-- Right Column (Sidebar/Feeds) -->
          <div class="layout-sidebar">
            <div class="glass-card feed-panel" style="margin-bottom: 20px;">
              <ActivityFeed />
            </div>
            <div class="glass-card torch-panel">
              <RecentTorchWidget />
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/authStore'
import { useRouterStore } from '../stores/routerStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import StatCard from '../components/StatCard.vue'
import ActivityFeed from '../components/ActivityFeed.vue'
import RouterHealthTable from '../components/RouterHealthTable.vue'
import RecentTorchWidget from '../components/RecentTorchWidget.vue'
import GlobalTrafficChart from '../components/GlobalTrafficChart.vue'
import { useOltStore } from '../stores/oltStore'

const auth = useAuthStore()
const routerStore = useRouterStore()
const oltStore = useOltStore()
const sidebarOpen = ref(false)

const { 
  routers, 
  selectedRouter,
  pppoePagination
} = storeToRefs(routerStore)

const { fetchRouters } = routerStore

// Computed stats
const stats = computed(() => {
  const healthy = routers.value.filter(r => r.status === 'HEALTHY').length
  const totalPppoe = routers.value.reduce((acc, r) => acc + (parseInt(r.active_pppoe_count as any) || 0), 0)

  return {
    routersOnline: `${healthy} / ${routers.value.length}`,
    pppoeSessions: routers.value.length > 0 ? totalPppoe.toString() : '---',
    torchSessions: 0,
    systemStatus: healthy === routers.value.length && healthy > 0 ? 'Healthy' : 'Degraded',
  }
})

// OLT Computed stats
const oltStats = computed(() => {
  let totalOlts = oltStore.olts.length
  let totalOnus = 0
  let onlineOnus = 0
  let losOnus = 0

  oltStore.olts.forEach(olt => {
    totalOnus += olt.onus_count || 0
    onlineOnus += olt.onus_online || 0
    losOnus += olt.onus_los || 0
  })

  return {
    totalOlts,
    totalOnus,
    onlineOnus,
    losOnus
  }
})

// Lifecycle
let autoRefreshInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchRouters()
  oltStore.fetchOlts()
  
  // Polling data every 60 seconds to keep dashboard fresh
  autoRefreshInterval = setInterval(() => {
    fetchRouters()
    oltStore.fetchOlts()
  }, 60000)
})

onBeforeUnmount(() => {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval)
  }
})
</script>

<style scoped>
.dashboard {
  display: flex;
  min-height: 100vh;
  background: var(--bg-primary);
}

.dashboard__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.dashboard__content {
  flex: 1;
  padding: 28px 32px;
  overflow-y: auto;
}

/* Welcome */
.dashboard__welcome {
  margin-bottom: 28px;
}

.dashboard__heading {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 4px;
}

.dashboard__heading--accent {
  color: var(--accent-cyan);
}

.dashboard__heading-sub {
  font-size: 0.82rem;
  color: var(--text-muted);
}

/* Stat Grid */
.dashboard__stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 28px;
}

/* Panels */
.dashboard__panels {
  display: grid;
  grid-template-columns: 350px 1fr;
  gap: 24px;
  align-items: start;
}

.dashboard__panel {
  padding: 20px;
  display: flex;
  flex-direction: column;
  min-height: 500px;
}

.dashboard__panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.dashboard__panel-header-tabs {
  display: flex;
  gap: 16px;
  border-bottom: 1px solid var(--glass-border);
  margin-bottom: 20px;
  padding-bottom: 8px;
}

.panel-tab-btn {
  background: transparent;
  border: none;
  color: var(--text-secondary);
  font-size: 0.95rem;
  font-weight: 600;
  padding: 6px 12px;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
}

.panel-tab-btn:hover {
  color: var(--text-primary);
}

.panel-tab-btn--active {
  color: var(--accent-cyan);
}

.panel-tab-btn--active::after {
  content: '';
  position: absolute;
  bottom: -9px;
  left: 0;
  right: 0;
  height: 2px;
  background: var(--accent-cyan);
  box-shadow: 0 0 6px var(--accent-cyan);
}

.dashboard__panel-title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.btn-refresh {
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 1.1rem;
  opacity: 0.7;
  transition: opacity 0.2s;
}

.btn-refresh:hover:not(:disabled) {
  opacity: 1;
}

.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

.router-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  overflow-y: auto;
  max-height: 600px;
  padding-right: 8px;
}

.router-list::-webkit-scrollbar {
  width: 6px;
}
.router-list::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.empty-state {
  color: var(--text-muted);
  text-align: center;
  padding: 40px 0;
  font-size: 0.9rem;
}

.skeleton-card {
  height: 90px;
  background: linear-gradient(90deg, rgba(255,255,255,0.02) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.02) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 8px;
  border: 1px solid var(--glass-border);
}

.panel-content {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.empty-selection {
  justify-content: center;
  align-items: center;
  text-align: center;
  color: var(--text-muted);
}

.empty-icon {
  font-size: 3rem;
  margin-bottom: 16px;
  animation: pulse 2s infinite;
}

.alert-error {
  margin-top: 16px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: var(--accent-red);
  padding: 12px;
  border-radius: 8px;
  font-size: 0.85rem;
  gap: 20px;
}

/* Dashboard Layout */
.dashboard__layout {
  display: grid;
  grid-template-columns: 7fr 3fr;
  gap: 24px;
}

.table-panel {
  padding: 24px;
  min-height: 500px;
}

.feed-panel {
  padding: 20px;
  min-height: 250px;
}

.torch-panel {
  padding: 20px;
  min-height: 250px;
}

/* Responsive */
@media (max-width: 1200px) {
  .dashboard__layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 1024px) {
  .dashboard__stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 900px) {
  .dashboard__panels {
    grid-template-columns: 1fr;
  }
  .dashboard__panel {
    min-height: auto;
  }
}

@media (max-width: 768px) {
  .dashboard__content {
    padding: 16px 12px;
  }
  .dashboard__stats {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  .dashboard__panels {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .dashboard__panel {
    min-height: auto;
    padding: 16px;
  }
  .dashboard__heading {
    font-size: 1.25rem;
  }
  .dashboard__welcome {
    margin-bottom: 16px;
  }
  .dashboard__panel-header-tabs {
    gap: 8px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .panel-tab-btn {
    font-size: 0.85rem;
    padding: 6px 8px;
    white-space: nowrap;
  }
}
</style>
