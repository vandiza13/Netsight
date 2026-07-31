<template>
  <div class="router-cards-container">
    <div class="header-section">
      <h3 class="section-title">
        <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="2" width="20" height="8" rx="2"/>
          <rect x="2" y="14" width="20" height="8" rx="2"/>
          <line x1="6" y1="6" x2="6.01" y2="6"/>
          <line x1="6" y1="18" x2="6.01" y2="18"/>
        </svg>
        Router Overview
      </h3>
      <div class="header-actions">
        <button class="btn-refresh" @click="fetchRouters" :disabled="loading" title="Refresh data">
          <svg class="refresh-icon" :class="{'spinning': loading}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
          </svg>
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
            <div class="title-row">
              <h4 class="router-name">{{ r.name }}</h4>
              <span v-if="r.health?.board_name" class="board-badge font-mono">{{ r.health.board_name }}</span>
            </div>
            <div class="router-meta">
              <span class="meta-ip font-mono">{{ r.host }}</span>
              <span class="meta-divider">•</span>
              <span class="meta-os font-mono">RouterOS {{ r.routeros_version || 'v7.x' }}</span>
            </div>
          </div>
          <div class="status-badge" :class="getStatusClass(r.status)">
            <span class="dot" :class="{'pulse': r.status === 'HEALTHY'}"></span>
            {{ r.status }}
          </div>
        </div>

        <!-- Card Body (Health Metrics) -->
        <div class="card-body">
          <div class="metric-row">
            <!-- CPU Load -->
            <div class="metric-block">
              <div class="metric-label">
                <span class="label-text">CPU LOAD</span>
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
              <!-- Multi-core Spectrum -->
              <div class="cpu-cores-grid" v-if="r.health?.cpu_cores && r.health.cpu_cores.length > 1">
                <div class="core-item" v-for="(coreLoad, idx) in r.health.cpu_cores" :key="idx" :title="'Core ' + (idx + 1) + ': ' + coreLoad + '%'">
                  <div class="core-bar" :class="getCpuBgColor(coreLoad)" :style="{ height: coreLoad + '%' }"></div>
                </div>
              </div>
            </div>

            <!-- RAM Usage -->
            <div class="metric-block">
              <div class="metric-label">
                <span class="label-text">RAM USAGE</span>
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

          <!-- Telemetry Grid -->
          <div class="telemetry-grid">
            <div class="telemetry-card">
              <span class="telemetry-title">UPTIME</span>
              <span class="telemetry-value font-mono">{{ r.health?.uptime || '--' }}</span>
            </div>
            <div class="telemetry-card">
              <span class="telemetry-title">TEMP</span>
              <span class="telemetry-value font-mono">{{ r.health?.temperature ? r.health.temperature + '°C' : '--' }}</span>
            </div>
            <div class="telemetry-card">
              <span class="telemetry-title">VOLTAGE</span>
              <span class="telemetry-value font-mono">{{ r.health?.voltage ? r.health.voltage + 'V' : '--' }}</span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer">
          <div class="last-sync text-muted text-xs font-mono">
            SYNC: {{ formatTimeAgo(r.last_synced_at) }}
          </div>
          <button class="btn-inspect" @click="goToInspect(r)">
            Inspect
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
  
  const usedMB = (used / 1024 / 1024).toFixed(0)
  if (total > 1024 * 1024 * 1024) {
    const totalGB = (total / 1024 / 1024 / 1024).toFixed(1)
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
  if (!dateString) return 'NEVER'
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
  margin-bottom: 18px;
}

.section-title {
  font-size: 1.05rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text-primary, #f8fafc);
  letter-spacing: 0.01em;
}

.title-icon {
  width: 18px;
  height: 18px;
  color: var(--accent-cyan, #06b6d4);
}

.btn-refresh {
  background: #1e293b;
  border: 1px solid #334155;
  color: #94a3b8;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-refresh:hover {
  background: #334155;
  color: #f8fafc;
}
.refresh-icon { width: 14px; height: 14px; }
.spinning { animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Grid Layout */
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 18px;
}

/* Card Design - NOC Slate Style */
.router-card {
  background: #111827;
  border: 1px solid #1f2937;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  transition: border-color 0.2s, box-shadow 0.2s;
  overflow: hidden;
}

.router-card:hover {
  border-color: #374151;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.border-green { border-top: 2px solid #22c55e; }
.border-yellow { border-top: 2px solid #f59e0b; }
.border-red { border-top: 2px solid #ef4444; }

/* Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 16px;
  border-bottom: 1px solid #1f2937;
  background: #111827;
}

.header-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.router-name {
  font-size: 1rem;
  font-weight: 600;
  color: #f8fafc;
  margin: 0;
}

.board-badge {
  font-size: 0.65rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #1e3a8a;
  border: 1px solid #1d4ed8;
  color: #93c5fd;
  letter-spacing: 0.03em;
}

.router-meta {
  font-size: 0.75rem;
  color: #9ca3af;
  display: flex;
  align-items: center;
  gap: 6px;
}

.meta-ip { color: #60a5fa; }
.meta-divider { color: #4b5563; }

/* Body */
.card-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
}

.metric-row {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.metric-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.metric-label {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.label-text {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  color: #9ca3af;
}

.metric-value {
  font-size: 0.78rem;
  font-weight: 600;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: #1f2937;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease-out;
}

/* Multi-core Spectrum */
.cpu-cores-grid {
  display: flex;
  gap: 3px;
  margin-top: 4px;
  height: 24px;
  overflow-x: auto;
  padding-bottom: 2px;
}
.cpu-cores-grid::-webkit-scrollbar { height: 2px; }
.cpu-cores-grid::-webkit-scrollbar-thumb { background: #374151; }

.core-item {
  width: 6px;
  flex-shrink: 0;
  background: transparent;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.core-bar {
  width: 100%;
  transition: height 0.4s ease-out;
  border-radius: 1px 1px 0 0;
}

/* Telemetry Grid */
.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  padding-top: 8px;
}

.telemetry-card {
  background: #1f2937;
  border: 1px solid #374151;
  border-radius: 6px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.telemetry-title {
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #9ca3af;
}

.telemetry-value {
  font-size: 0.85rem;
  font-weight: 600;
  color: #f3f4f6;
}

/* Footer */
.card-footer {
  padding: 12px 16px;
  border-top: 1px solid #1f2937;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #111827;
}

.last-sync {
  font-size: 0.7rem;
  color: #6b7280;
  letter-spacing: 0.02em;
}

/* Colors */
.text-green { color: #22c55e; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }
.text-cyan { color: #38bdf8; }
.text-dim { color: #64748b; }

.bg-green { background-color: #22c55e; }
.bg-yellow { background-color: #f59e0b; }
.bg-red { background-color: #ef4444; }
.bg-cyan { background-color: #38bdf8; }
.bg-dim { background-color: #334155; }

.font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

/* Buttons & Badges */
.btn-inspect {
  padding: 4px 14px;
  background: #1e3a8a;
  border: 1px solid #1d4ed8;
  color: #93c5fd;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-inspect:hover {
  background: #1d4ed8;
  color: #ffffff;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.65rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 4px;
  white-space: nowrap;
  letter-spacing: 0.05em;
}

.status-badge .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status-badge .dot.pulse {
  animation: status-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes status-ping {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(1.4); }
}

.status--healthy { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
.status--healthy .dot { background: #4ade80; }
.status--degraded { background: rgba(245, 158, 11, 0.1); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.2); }
.status--degraded .dot { background: #fbbf24; }
.status--unreachable { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
.status--unreachable .dot { background: #f87171; }

/* Skeleton */
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 18px;
}
.skeleton-card {
  height: 220px;
  background: #0f172a;
  border: 1px solid #1e293b;
  border-radius: 8px;
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0% { opacity: 0.6; }
  50% { opacity: 0.3; }
  100% { opacity: 0.6; }
}
</style>
