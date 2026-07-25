<template>
  <div class="router-grid-container">
    <div class="grid-header">
      <h3 class="grid-title">
        <span class="icon">🖧</span> Router Status & Fast Inspect
      </h3>
      <button class="btn-refresh" @click="fetchRouters" :disabled="loading" title="Refresh status">
        <span :class="{'spinning': loading}">🔄</span>
      </button>
    </div>

    <div class="router-cards" v-if="routers.length > 0">
      <div 
        class="router-card" 
        v-for="r in routers" 
        :key="r.id"
      >
        <div class="card-top">
          <div class="router-name">{{ r.name }}</div>
          <div class="status-badge" :class="getStatusClass(r.status)">
            <span class="dot"></span>
            {{ r.status }}
          </div>
        </div>

        <div class="card-details">
          <div class="detail-item">
            <span class="label">IP Host:</span>
            <span class="value">{{ r.host }}</span>
          </div>
          <div class="detail-item">
            <span class="label">RouterOS:</span>
            <span class="value">{{ r.routeros_version || 'v7.x' }}</span>
          </div>
          <div class="detail-item">
            <span class="label">Last Sync:</span>
            <span class="value">{{ formatTimeAgo(r.last_synced_at) }}</span>
          </div>
        </div>

        <button class="btn-inspect" @click="goToInspect(r)">
          🔍 Inspect Router
        </button>
      </div>
    </div>

    <div class="empty-state" v-else-if="!loading">
      No routers configured in system.
    </div>

    <div class="skeleton-grid" v-else>
      <div class="skeleton-card" v-for="n in 2" :key="n"></div>
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
.router-grid-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.grid-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
}

.grid-title .icon {
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

@keyframes spin {
  to { transform: rotate(360deg); }
}

.router-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.router-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.08));
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  transition: all 0.2s ease;
}

.router-card:hover {
  border-color: rgba(34, 211, 238, 0.3);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.router-name {
  font-weight: 600;
  font-size: 1rem;
  color: var(--text-primary);
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 20px;
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

.card-details {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.82rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
}

.detail-item .label {
  color: var(--text-muted, #8b96a5);
}

.detail-item .value {
  color: var(--text-primary);
  font-family: var(--font-mono, monospace);
}

.btn-inspect {
  margin-top: 4px;
  width: 100%;
  padding: 8px 12px;
  background: rgba(34, 211, 238, 0.1);
  border: 1px solid rgba(34, 211, 238, 0.25);
  color: var(--accent-cyan, #22d3ee);
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-inspect:hover {
  background: rgba(34, 211, 238, 0.2);
  border-color: rgba(34, 211, 238, 0.4);
}

.empty-state {
  color: var(--text-dim);
  font-size: 0.9rem;
  padding: 30px 0;
  text-align: center;
}

.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.skeleton-card {
  height: 120px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% { opacity: 0.6; }
  50% { opacity: 0.3; }
  100% { opacity: 0.6; }
}
</style>
