<template>
  <div class="router-table-container">
    <div class="table-header">
      <h3 class="table-title">
        <span class="icon">🖧</span> Router Overview
      </h3>
      <div class="header-actions">
        <button class="btn-refresh" @click="fetchRouters" :disabled="loading" title="Refresh data">
          <span :class="{'spinning': loading}">🔄</span>
        </button>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="router-table" v-if="routers.length > 0">
        <thead>
          <tr>
            <th>Router Name</th>
            <th>Host IP</th>
            <th>RouterOS</th>
            <th>Status</th>
            <th>Last Sync</th>
            <th class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in routers" :key="r.id">
            <td class="font-semibold text-primary">{{ r.name }}</td>
            <td class="font-mono">{{ r.host }}</td>
            <td class="text-muted">{{ r.routeros_version || 'v7.x' }}</td>
            <td>
              <div class="status-badge" :class="getStatusClass(r.status)">
                <span class="dot"></span> {{ r.status }}
              </div>
            </td>
            <td class="text-muted text-xs">{{ formatTimeAgo(r.last_synced_at) }}</td>
            <td class="text-right">
              <button class="btn-inspect" @click="goToInspect(r)">
                🔍 Inspect
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty State -->
      <div class="empty-state" v-else-if="!loading">
        No routers configured.
      </div>
      
      <!-- Skeleton Loader -->
      <div class="skeleton-wrapper" v-else>
        <div class="skeleton-row" v-for="n in 3" :key="n"></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useRouterStore, type MikroTikRouter } from '../stores/routerStore'

const router = useRouter()
const routerStore = useRouterStore()
const { routers, loading } = storeToRefs(routerStore)
const { fetchRouters, selectRouter } = routerStore

function getStatusClass(status: string) {
  if (status === 'HEALTHY') return 'status--healthy'
  if (status === 'DEGRADED') return 'status--degraded'
  return 'status--unreachable'
}

function goToInspect(r: MikroTikRouter) {
  selectRouter(r.id)
  router.push('/inspect')
}

function formatTimeAgo(dateString: string | null) {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.round((now.getTime() - date.getTime()) / 1000)
  
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.round(hours / 24)}d ago`
}
</script>

<style scoped>
.router-table-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.table-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
}

.table-title .icon {
  font-size: 1.2rem;
}

.btn-refresh {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-secondary);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-refresh:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
}

.spinning {
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table Styles */
.table-wrapper {
  overflow-x: auto;
  flex: 1;
}

.router-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
}

.router-table th {
  padding: 12px 16px;
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-dim, #5c6774);
  font-weight: 600;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.router-table td {
  padding: 14px 16px;
  font-size: 0.9rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
  vertical-align: middle;
}

.router-table tr:last-child td {
  border-bottom: none;
}

.router-table tr:hover td {
  background: rgba(255, 255, 255, 0.02);
}

/* Typography Utils */
.font-semibold { font-weight: 600; }
.font-mono { font-family: var(--font-mono, monospace); font-size: 0.85rem; color: var(--cyan, #22d3ee); }
.text-primary { color: var(--text-primary); }
.text-muted { color: var(--text-muted, #8b96a5); }
.text-xs { font-size: 0.8rem; }
.text-right { text-align: right; }

/* Status Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 20px;
  white-space: nowrap;
}

.status-badge .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status--healthy {
  background: rgba(34, 197, 94, 0.15);
  color: var(--accent-green, #22c55e);
}
.status--healthy .dot { background: var(--accent-green, #22c55e); }

.status--degraded {
  background: rgba(245, 166, 35, 0.15);
  color: var(--accent-amber, #f5a623);
}
.status--degraded .dot { background: var(--accent-amber, #f5a623); }

.status--unreachable {
  background: rgba(239, 68, 68, 0.15);
  color: var(--accent-red, #ef4444);
}
.status--unreachable .dot { background: var(--accent-red, #ef4444); }

/* Buttons */
.btn-inspect {
  padding: 6px 12px;
  background: rgba(34, 211, 238, 0.1);
  border: 1px solid rgba(34, 211, 238, 0.25);
  color: var(--accent-cyan, #22d3ee);
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-inspect:hover {
  background: rgba(34, 211, 238, 0.2);
  border-color: rgba(34, 211, 238, 0.4);
}

/* States */
.empty-state {
  padding: 40px 0;
  text-align: center;
  color: var(--text-dim);
  font-size: 0.9rem;
}

.skeleton-wrapper {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
}

.skeleton-row {
  height: 40px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 6px;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% { opacity: 0.6; }
  50% { opacity: 0.3; }
  100% { opacity: 0.6; }
}
</style>
