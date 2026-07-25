<template>
  <div class="dashboard">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <!-- Inspect Header -->
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">
            Live <span class="dashboard__heading--accent">Inspect</span>
          </h2>
          <p class="dashboard__heading-sub">
            Monitor interfaces, diagnostic history, and active sessions
          </p>
        </section>



        <!-- Main Workspace -->
        <section class="dashboard__panels stagger">
          
          <!-- Left Panel: Router Selection -->
          <div class="glass-card dashboard__panel dashboard__sidebar-panel">
            <div class="dashboard__panel-header">
              <h3 class="dashboard__panel-title">🖧 Routers</h3>
              <button class="btn-refresh" @click="fetchRouters" :disabled="loadingRouters" title="Refresh list">
                <span :class="{'spinning': loadingRouters}">🔄</span>
              </button>
            </div>
            
            <div class="router-list" v-if="routers.length > 0">
              <RouterCard 
                v-for="r in routers" 
                :key="r.id" 
                :router="r" 
                :is-selected="selectedRouter?.id === r.id"
                @select="selectRouter(r.id)"
              />
            </div>
            <div class="router-list empty-state" v-else-if="!loadingRouters">
              No routers found.
            </div>
            <div class="router-list skeleton-list" v-else>
               <div class="skeleton-card" v-for="n in 3" :key="n"></div>
            </div>
          </div>

          <!-- Right Panel: PPPoE Users / Details -->
          <div class="glass-card dashboard__panel dashboard__main-panel">
            <div class="dashboard__panel-header-tabs" v-if="selectedRouter">
              <button 
                class="panel-tab-btn" 
                :class="{ 'panel-tab-btn--active': activeTab === 'users' }"
                @click="activeTab = 'users'"
              >
                📡 Active Users
              </button>
              <button 
                class="panel-tab-btn" 
                :class="{ 'panel-tab-btn--active': activeTab === 'interfaces' }"
                @click="activeTab = 'interfaces'"
              >
                🔌 Interfaces
              </button>
              <button 
                class="panel-tab-btn" 
                :class="{ 'panel-tab-btn--active': activeTab === 'history' }"
                @click="activeTab = 'history'"
              >
                📋 Diagnostic History
              </button>
            </div>
            <div class="dashboard__panel-header" v-else>
              <h3 class="dashboard__panel-title">📡 User Details</h3>
            </div>
            
            <div class="panel-content" v-if="selectedRouter">
              <div v-show="activeTab === 'users'">
                <PppoeUserTable 
                  :users="pppoeUsers"
                  :loading="loadingUsers"
                  :is-syncing="syncing"
                  :pagination="pppoePagination"
                  @search="handleSearch"
                  @page-change="handlePageChange"
                  @force-sync="triggerForceSync"
                  @inspect="startTorch"
                />
              </div>

              <div v-if="activeTab === 'interfaces'">
                <RouterInterfaceViewer 
                  :router-id="selectedRouter.id"
                />
              </div>

              <div v-show="activeTab === 'history'">
                <TorchHistoryTable 
                  :router-id="selectedRouter.id"
                  @view-report="id => selectedReportId = id"
                />
              </div>
              
              <div v-if="syncError" class="alert alert-error fade-in">
                {{ syncError }}
              </div>
            </div>
            
            <div class="panel-content empty-selection" v-else>
              <div class="empty-icon">👈</div>
              <p>Select a router from the left panel to view PPPoE users and perform Torch inspection.</p>
            </div>
          </div>
          
        </section>
      </main>
    </div>

    <!-- Torch Viewer Overlay -->
    <TorchViewer 
      v-if="activeTorchUser && selectedRouter"
      :router-id="selectedRouter.id"
      :username="activeTorchUser"
      @close="activeTorchUser = null"
    />
    <!-- Torch Report Modal Overlay -->
    <TorchHistoryModal
      v-if="selectedReportId"
      :session-id="selectedReportId"
      @close="selectedReportId = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/authStore'
import { useRouterStore } from '../stores/routerStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import RouterCard from '../components/RouterCard.vue'
import PppoeUserTable from '../components/PppoeUserTable.vue'
import TorchViewer from '../components/TorchViewer.vue'
import TorchHistoryTable from '../components/TorchHistoryTable.vue'
import TorchHistoryModal from '../components/TorchHistoryModal.vue'
import RouterInterfaceViewer from '../components/RouterInterfaceViewer.vue'

const auth = useAuthStore()
const routerStore = useRouterStore()
const sidebarOpen = ref(false)
const activeTorchUser = ref<string | null>(null)
const activeTab = ref<'users' | 'interfaces' | 'history'>('users')
const selectedReportId = ref<number | null>(null)

const { 
  routers, 
  selectedRouter, 
  pppoeUsers, 
  pppoePagination, 
  loading: loadingRouters, 
  loadingUsers, 
  error 
} = storeToRefs(routerStore)

const { fetchRouters, selectRouter, fetchPppoeUsers, forceSync } = routerStore


// Lifecycle
let autoRefreshInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  fetchRouters()
  
  // Polling data every 60 seconds to keep dashboard fresh
  autoRefreshInterval = setInterval(() => {
    fetchRouters()
    if (selectedRouter.value) {
      // Refresh current page of PPPoE users with active search filter
      fetchPppoeUsers(pppoePagination.value.currentPage, currentSearch.value)
    }
  }, 60000)
})

onBeforeUnmount(() => {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval)
  }
})

// PppoeUserTable handlers
const currentSearch = ref('')
const syncing = ref(false)
const syncError = ref('')

function handleSearch(query: string) {
  currentSearch.value = query
  fetchPppoeUsers(1, query)
}

function handlePageChange(page: number) {
  fetchPppoeUsers(page, currentSearch.value)
}

async function triggerForceSync() {
  if (!selectedRouter.value) return
  syncing.value = true
  syncError.value = ''
  try {
    await forceSync(selectedRouter.value.id)
    // Wait a bit then refresh users list
    setTimeout(() => {
      fetchPppoeUsers(pppoePagination.value.currentPage, currentSearch.value)
      syncing.value = false
    }, 2000)
  } catch (err: any) {
    syncError.value = err.message
    syncing.value = false
    
    setTimeout(() => { syncError.value = '' }, 5000)
  }
}

function startTorch(username: string) {
  activeTorchUser.value = username
}
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
}

@media (max-width: 1200px) {
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
