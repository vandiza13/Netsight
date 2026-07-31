<template>
  <div class="router-cards-container">
    <div class="header-section">
      <h3 class="section-title">
        <span class="icon">🖧</span> Router Overview
      </h3>
      <div class="header-actions">
        <button class="btn-refresh" @click="fetchRouters" :disabled="loading" title="Refresh data">
          <span :class="{'spinning': loading}">🔄</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div class="empty-state" v-if="!loading && routers.length === 0">
      No routers configured.
    </div>

    <!-- Skeleton Loader -->
    <div class="skeleton-grid" v-else-if="loading && routers.length === 0">
      <div class="skeleton-card" v-for="n in 3" :key="n"></div>
    </div>

    <!-- Card Grid -->
    <div class="cards-grid" v-else>
      <div class="router-card" v-for="r in routers" :key="r.id" :class="getBorderClass(r)">
        <!-- Card Header -->
        <div class="card-header">
          <div class="header-info">
            <h4 class="router-name">{{ r.name }}</h4>
            <div class="router-meta">
              <span class="meta-ip">🌐 {{ r.host }}</span>
              <span class="meta-os">• RouterOS {{ r.routeros_version || 'v7.x' }}</span>
            </div>
          </div>
          <div class="status-badge" :class="getStatusClass(r.status)">
            <span class="dot"></span> {{ r.status }}
          </div>
        </div>

        <!-- Card Body (Health Metrics) -->
        <div class="card-body">
          <div class="metric-row">
            <!-- CPU Load -->
            <div class="metric-block">
              <div class="metric-label">
                <span>🧠 CPU Load</span>
                <span class="metric-value font-mono" :class="getCpuTextColor(r.health?.cpu_load)">
                  {{ r.health ? r.health.cpu_load + '%' : '--' }}
                </span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" 
                     :class="getCpuBgColor(r.health?.cpu_load)" 
                     :style="{ width: (r.health?.cpu_load || 0) + '%' }">
                </div>
              </div>
              <!-- Multi-core display -->
              <div class="cpu-cores-grid" v-if="r.health?.cpu_cores && r.health.cpu_cores.length > 1">
                <div class="core-item" v-for="(coreLoad, idx) in r.health.cpu_cores" :key="idx" :title="'Core ' + idx + ': ' + coreLoad + '%'">
                  <div class="core-bar" :class="getCpuBgColor(coreLoad)" :style="{ height: coreLoad + '%' }"></div>
                </div>
              </div>
            </div>

            <!-- RAM Usage -->
            <div class="metric-block">
              <div class="metric-label">
                <span>🗄️ RAM Usage</span>
                <span class="metric-value font-mono">
                  {{ formatRam(r.health?.ram_used, r.health?.ram_total) }}
                </span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" 
                     :class="getRamColor(r.health?.ram_used, r.health?.ram_total)" 
                     :style="{ width: getRamPercent(r.health?.ram_used, r.health?.ram_total) + '%' }">
                </div>
              </div>
            </div>
          </div>

          <div class="metric-row secondary-metrics">
            <div class="metric-item">
              <span class="icon">⏱️</span> 
              <span class="value">{{ r.health?.uptime || '--' }}</span>
            </div>
            <div class="metric-item">
              <span class="icon">🌡️</span> 
              <span class="value">{{ r.health?.temperature ? r.health.temperature + '°C' : '--' }}</span>
            </div>
            <div class="metric-item">
              <span class="icon">⚡</span> 
              <span class="value">{{ r.health?.voltage ? r.health.voltage + 'V' : '--' }}</span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer">
          <div class="last-sync text-muted text-xs">
            🕒 Sync: {{ formatTimeAgo(r.last_synced_at) }}
          </div>
          <button class="btn-inspect" @click="goToInspect(r)">
            🔍 Inspect
          </button>
        </div>
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

function getBorderClass(r: MikroTikRouter) {
  if (r.status !== 'HEALTHY') return 'border-red'
  if (r.health && r.health.cpu_load > 85) return 'border-yellow'
  return 'border-green'
}

function getCpuTextColor(cpu: number | undefined) {
  if (cpu === undefined) return 'text-dim'
  if (cpu > 85) return 'text-red'
  if (cpu > 60) return 'text-yellow'
  return 'text-green'
}

function getCpuBgColor(cpu: number | undefined) {
  if (cpu === undefined) return 'bg-dim'
  if (cpu > 85) return 'bg-red'
  if (cpu > 60) return 'bg-yellow'
  return 'bg-green'
}

function getRamPercent(used: number | undefined, total: number | undefined) {
  if (!used || !total || total === 0) return 0
  return Math.round((used / total) * 100)
}

function getRamColor(used: number | undefined, total: number | undefined) {
  const pct = getRamPercent(used, total)
  if (pct === 0) return 'bg-dim'
  if (pct > 90) return 'bg-red'
  if (pct > 75) return 'bg-yellow'
  return 'bg-cyan'
}

function formatRam(used: number | undefined, total: number | undefined) {
  if (!used || !total) return '--'
  const pct = getRamPercent(used, total)
  
  // Format to MB or GB
  const usedMB = (used / 1024 / 1024).toFixed(0)
  const totalGB = (total / 1024 / 1024 / 1024).toFixed(1)
  
  if (total > 1024 * 1024 * 1024) {
    return `${pct}% (${usedMB}MB / ${totalGB}GB)`
  }
  const totalMB = (total / 1024 / 1024).toFixed(0)
  return `${pct}% (${usedMB}MB / ${totalMB}MB)`
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
.router-cards-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
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
.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Grid Layout */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}

/* Card Design */
.router-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  backdrop-filter: blur(10px);
  transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  overflow: hidden;
}

.router-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

.border-green { border-top: 2px solid rgba(34, 197, 94, 0.5); }
.border-yellow { border-top: 2px solid rgba(245, 166, 35, 0.6); }
.border-red { border-top: 2px solid rgba(239, 68, 68, 0.6); }

/* Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 16px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
}

.header-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.router-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.router-meta {
  font-size: 0.8rem;
  color: var(--text-muted);
  display: flex;
  gap: 6px;
}

.meta-ip { color: var(--cyan, #22d3ee); font-family: var(--font-mono, monospace); }

/* Body */
.card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  flex: 1;
}

.metric-row {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.metric-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.metric-label {
  display: flex;
  justify-content: space-between;
  font-size: 0.85rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.metric-value {
  font-weight: 600;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.5s ease-out, background-color 0.3s;
}

.secondary-metrics {
  flex-direction: row;
  justify-content: space-between;
  padding-top: 10px;
  border-top: 1px dashed rgba(255, 255, 255, 0.05);
}

.metric-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.metric-item .value {
  font-family: var(--font-mono, monospace);
  color: var(--text-primary);
}

/* Footer */
.card-footer {
  padding: 14px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.03);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(0, 0, 0, 0.1);
}

/* Colors */
.text-green { color: var(--accent-green, #22c55e); }
.text-yellow { color: var(--accent-amber, #f5a623); }
.text-red { color: var(--accent-red, #ef4444); }
.text-cyan { color: var(--accent-cyan, #22d3ee); }
.text-dim { color: var(--text-dim, #5c6774); }

.bg-green { background-color: var(--accent-green, #22c55e); }
.bg-yellow { background-color: var(--accent-amber, #f5a623); }
.bg-red { background-color: var(--accent-red, #ef4444); }
.bg-cyan { background-color: var(--accent-cyan, #22d3ee); }
.bg-dim { background-color: rgba(255,255,255,0.1); }

.cpu-cores-grid {
  display: flex;
  gap: 2px;
  margin-top: 4px;
  height: 12px;
}
.core-item {
  flex: 1;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 2px;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.core-bar {
  width: 100%;
  border-radius: 2px;
  transition: height 0.5s ease-out;
}

.font-mono { font-family: var(--font-mono, monospace); }

/* Buttons & Badges */
.btn-inspect {
  padding: 6px 14px;
  background: rgba(34, 211, 238, 0.1);
  border: 1px solid rgba(34, 211, 238, 0.25);
  color: var(--accent-cyan, #22d3ee);
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-inspect:hover {
  background: rgba(34, 211, 238, 0.2);
  border-color: rgba(34, 211, 238, 0.4);
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 12px;
  white-space: nowrap;
}
.status-badge .dot { width: 6px; height: 6px; border-radius: 50%; }

.status--healthy { background: rgba(34, 197, 94, 0.15); color: var(--accent-green, #22c55e); }
.status--healthy .dot { background: var(--accent-green, #22c55e); }
.status--degraded { background: rgba(245, 166, 35, 0.15); color: var(--accent-amber, #f5a623); }
.status--degraded .dot { background: var(--accent-amber, #f5a623); }
.status--unreachable { background: rgba(239, 68, 68, 0.15); color: var(--accent-red, #ef4444); }
.status--unreachable .dot { background: var(--accent-red, #ef4444); }

/* Skeleton */
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 20px;
}
.skeleton-card {
  height: 240px;
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
