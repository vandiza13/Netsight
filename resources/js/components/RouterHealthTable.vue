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
            <h4 class="router-name">{{ r.name }}</h4>
            <div class="router-meta mt-1">
              <span class="meta-ip font-mono">{{ r.host }}</span>
              <span class="meta-divider">•</span>
              <span class="meta-os">RouterOS {{ r.routeros_version || 'v7.x' }}</span>
              <span v-if="r.health?.board_name" class="meta-divider">•</span>
              <span v-if="r.health?.board_name" class="board-badge font-mono">{{ r.health.board_name }}</span>
            </div>
          </div>
          <div class="status-badge" :class="getStatusClass(r.status)">
            {{ r.status }}
            <span class="dot" :class="{'pulse': r.status === 'HEALTHY'}"></span>
          </div>
        </div>

        <!-- Card Body (Health Metrics) -->
        <div class="card-body">
          
          <!-- CPU Section -->
          <div class="cpu-section">
            <div class="cpu-left">
              <div class="metric-label">CPU Load {{ r.health?.cpu_load || 0 }}%</div>
              <div class="progress-bar">
                <div class="progress-fill" 
                     :class="getCpuBgColor(r.health?.cpu_load)" 
                     :style="{ width: (r.health?.cpu_load || 0) + '%' }">
                </div>
              </div>
            </div>
            
            <!-- Segmented Equalizer -->
            <div class="cpu-equalizer" v-if="r.health?.cpu_cores && r.health.cpu_cores.length > 1">
              <div class="core-stack" v-for="(coreLoad, idx) in r.health.cpu_cores" :key="idx" :title="'Core ' + (idx + 1) + ': ' + coreLoad + '%'">
                <div v-for="i in 8" :key="i" class="segment" :class="{ 'active': isSegmentActive(coreLoad, i, 8) }"></div>
              </div>
            </div>
          </div>

          <!-- RAM Section -->
          <div class="ram-section">
            <div class="ram-header">
              <div class="metric-label">RAM Usage {{ getRamPercent(r.health?.ram_used, r.health?.ram_total) }}%</div>
              <div class="ram-text font-mono">({{ formatRamValues(r.health?.ram_used, r.health?.ram_total) }})</div>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" 
                   :class="getRamColor(r.health?.ram_used, r.health?.ram_total)" 
                   :style="{ width: getRamPercent(r.health?.ram_used, r.health?.ram_total) + '%' }">
              </div>
            </div>
          </div>

          <!-- Telemetry Grid -->
          <div class="telemetry-grid">
            <div class="telemetry-card">
              <svg class="tele-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              <span class="telemetry-title">UPTIME</span>
              <span class="telemetry-value font-mono">{{ r.health?.uptime || '--' }}</span>
            </div>
            <div class="telemetry-card">
              <svg class="tele-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"></path></svg>
              <span class="telemetry-title">TEMP</span>
              <span class="telemetry-value font-mono">{{ r.health?.temperature ? r.health.temperature + '°C' : '--' }}</span>
            </div>
            <div class="telemetry-card">
              <svg class="tele-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
              <span class="telemetry-title">VOLTAGE</span>
              <span class="telemetry-value font-mono">{{ r.health?.voltage ? r.health.voltage + 'V' : '--' }}</span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer">
          <div class="last-sync font-mono">SYNC: {{ formatTimeAgo(r.last_synced_at) }}</div>
          <button class="btn-inspect" @click="goToInspect(r)">Inspect</button>
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

function isSegmentActive(load: number, segmentIndex: number, totalSegments: number) {
  // segmentIndex goes from 1 (top) to 8 (bottom)
  const threshold = ((totalSegments - segmentIndex) / totalSegments) * 100;
  return load > threshold;
}

function formatRamValues(used: number | undefined, total: number | undefined) {
  if (!used || !total) return '--';
  const usedMB = (used / 1024 / 1024).toFixed(0);
  if (total > 1024 * 1024 * 1024) {
    const totalGB = (total / 1024 / 1024 / 1024).toFixed(1);
    return `${usedMB}MB / ${totalGB}GB`;
  }
  const totalMB = (total / 1024 / 1024).toFixed(0);
  return `${usedMB}MB / ${totalMB}MB`;
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

/* Card Design - Glassmorphism Mockup Style */
.router-card {
  background: linear-gradient(145deg, rgba(17, 24, 39, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.router-card.border-green {
  border: 1px solid rgba(34, 197, 94, 0.3);
  box-shadow: 0 0 20px rgba(34, 197, 94, 0.05), inset 0 0 15px rgba(34, 197, 94, 0.03);
}

.router-card.border-yellow {
  border: 1px solid rgba(245, 158, 11, 0.3);
  box-shadow: 0 0 20px rgba(245, 158, 11, 0.05), inset 0 0 15px rgba(245, 158, 11, 0.03);
}

.router-card.border-red {
  border: 1px solid rgba(239, 68, 68, 0.3);
  box-shadow: 0 0 20px rgba(239, 68, 68, 0.05), inset 0 0 15px rgba(239, 68, 68, 0.03);
}

/* Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 18px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.header-info {
  display: flex;
  flex-direction: column;
}

.router-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
  letter-spacing: 0.02em;
}

.router-meta {
  font-size: 0.75rem;
  color: #94a3b8;
  display: flex;
  align-items: center;
  gap: 6px;
}

.board-badge {
  color: #94a3b8;
}

/* Status Badge - Mockup style */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 20px;
  white-space: nowrap;
  letter-spacing: 0.05em;
  background: transparent;
  border: 1px solid rgba(255,255,255,0.1);
}

.status-badge .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status--healthy { border-color: rgba(34, 197, 94, 0.5); color: #4ade80; box-shadow: 0 0 10px rgba(34, 197, 94, 0.2); }
.status--healthy .dot { background: #4ade80; box-shadow: 0 0 6px #4ade80; }

.status--degraded { border-color: rgba(245, 158, 11, 0.5); color: #fbbf24; box-shadow: 0 0 10px rgba(245, 158, 11, 0.2); }
.status--degraded .dot { background: #fbbf24; box-shadow: 0 0 6px #fbbf24; }

.status--unreachable { border-color: rgba(239, 68, 68, 0.5); color: #f87171; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); }
.status--unreachable .dot { background: #f87171; box-shadow: 0 0 6px #f87171; }

/* Body */
.card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* CPU Layout */
.cpu-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 20px;
}

.cpu-left {
  flex: 1;
}

/* RAM Layout */
.ram-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.ram-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.ram-text {
  font-size: 0.75rem;
  color: #94a3b8;
}

/* Progress Bars */
.metric-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: #e2e8f0;
  margin-bottom: 8px;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s ease-out;
}
.progress-fill.bg-green { box-shadow: 0 0 8px rgba(34, 197, 94, 0.6); }
.progress-fill.bg-yellow { box-shadow: 0 0 8px rgba(245, 158, 11, 0.6); }
.progress-fill.bg-red { box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); }

/* Segmented Equalizer */
.cpu-equalizer {
  display: flex;
  gap: 4px;
  align-items: flex-end;
}

.core-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.segment {
  width: 16px;
  height: 3px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 1px;
  transition: background 0.3s;
}

.segment.active {
  background: #4ade80;
  box-shadow: 0 0 4px rgba(74, 222, 128, 0.5);
}

/* Telemetry Grid */
.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 4px;
}

.telemetry-card {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  position: relative;
}

.tele-icon {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 14px;
  height: 14px;
  color: rgba(255, 255, 255, 0.3);
}

.telemetry-title {
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.telemetry-value {
  font-size: 1rem;
  font-weight: 600;
  color: #ffffff;
}

/* Footer */
.card-footer {
  padding: 12px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.last-sync {
  font-size: 0.7rem;
  color: #64748b;
}

.btn-inspect {
  padding: 5px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #e2e8f0;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-inspect:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
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
