<template>
  <div class="router-cards-container">
    <div class="header-section">
      <h3 class="section-title">
        <svg class="title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
        OLT Overview
      </h3>
      <div class="header-actions">
        <button class="btn-refresh" @click="fetchOlts" :disabled="loading" title="Refresh data">
          <svg class="refresh-icon" :class="{'spinning': loading}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div class="empty-state" v-if="!loading && olts.length === 0">
      Belum ada OLT.
    </div>

    <!-- Skeleton Loader -->
    <div class="skeleton-grid" v-else-if="loading && olts.length === 0">
      <div class="skeleton-card" v-for="n in 3" :key="n"></div>
    </div>

    <!-- Card Grid -->
    <div class="cards-grid" v-else>
      <div class="router-card border-cyan" v-for="olt in olts" :key="olt.id">
        <!-- Card Header -->
        <div class="card-header">
          <div class="header-info">
            <h4 class="router-name">{{ olt.name }}</h4>
            <div class="router-meta mt-1">
              <span class="meta-ip font-mono">{{ olt.ip_address }}</span>
              <span class="meta-divider">•</span>
              <span class="meta-os">{{ getVendorName(olt.vendor_code) }}</span>
              <span class="meta-divider">•</span>
              <span class="board-badge font-mono">{{ olt.technology ? olt.technology.toUpperCase() : 'EPON' }} ({{ olt.total_pons || 4 }} PON)</span>
            </div>
          </div>
          <div class="status-badge" :class="olt.status === 'online' ? 'status--healthy' : 'status--unreachable'">
            {{ olt.status === 'online' ? 'ONLINE' : 'OFFLINE' }}
            <span class="dot" :class="{'pulse': olt.status === 'online'}"></span>
          </div>
        </div>

        <!-- Card Body -->
        <div class="card-body">
          
          <!-- ONU Online Rate -->
          <div class="cpu-section">
            <div class="cpu-left">
              <div class="metric-label" style="display: flex; justify-content: space-between;">
                <span>ONU Online ({{ getOnlineRate(olt) }}%)</span>
                <span class="text-dim font-mono" style="font-size: 0.75rem;">{{ olt.onus_online || 0 }} / {{ olt.onus_count || 0 }} ONU</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" 
                     :class="getRateColor(getOnlineRate(olt))" 
                     :style="{ width: getOnlineRate(olt) + '%' }">
                </div>
              </div>
            </div>
          </div>

          <!-- Optical Health Rate -->
          <div class="cpu-section">
            <div class="cpu-left">
              <div class="metric-label" style="display: flex; justify-content: space-between;">
                <span>Optical Health</span>
                <span class="text-dim font-mono" style="font-size: 0.75rem;">{{ olt.onus_los || 0 }} LOS</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill bg-cyan" 
                     :style="{ width: getHealthRate(olt) + '%' }">
                </div>
              </div>
            </div>
          </div>

          <!-- Telemetry Grid -->
          <div class="telemetry-grid">
            <div class="telemetry-card">
              <svg class="tele-icon text-dim" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              <span class="telemetry-title">UPTIME</span>
              <span class="telemetry-value font-mono">{{ olt.uptime || '--d --h' }}</span>
            </div>
            <div class="telemetry-card">
              <svg class="tele-icon text-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
              <span class="telemetry-title">ONLINE</span>
              <span class="telemetry-value font-mono text-green">{{ olt.onus_online || 0 }}</span>
            </div>
            <div class="telemetry-card">
              <svg class="tele-icon text-red" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 9-6 6"/><path d="m9 9 6 6"/><circle cx="12" cy="12" r="10"/></svg>
              <span class="telemetry-title">LOS/OFF</span>
              <span class="telemetry-value font-mono text-red">{{ (olt.onus_los || 0) + (olt.onus_offline || 0) }}</span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="card-footer">
          <div class="last-sync font-mono">SYNC: {{ formatTimeAgo(null) }}</div>
          <button class="btn-inspect" @click="goToInspect(olt)">Inspect</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useOltStore, type Olt } from '../stores/oltStore'
import { onMounted } from 'vue'

const router = useRouter()
const oltStore = useOltStore()
const { olts, loading, profiles } = storeToRefs(oltStore)
const { fetchOlts, selectOlt } = oltStore

onMounted(() => {
  if (olts.value.length === 0) {
    fetchOlts()
  }
})

function getVendorName(code: string) {
  const profile = profiles.value.find(p => p.code === code)
  return profile ? profile.vendor : code.toUpperCase()
}

function getOnlineRate(olt: Olt) {
  if (!olt.onus_count || olt.onus_count === 0) return 0
  return Math.round(((olt.onus_online || 0) / olt.onus_count) * 100)
}

function getHealthRate(olt: Olt) {
  if (!olt.onus_count || olt.onus_count === 0) return 100
  const bad = (olt.onus_los || 0)
  const rate = Math.round(((olt.onus_count - bad) / olt.onus_count) * 100)
  return Math.max(0, rate)
}

function getRateColor(rate: number) {
  if (rate > 85) return 'bg-green'
  if (rate > 60) return 'bg-yellow'
  return 'bg-red'
}

function goToInspect(olt: Olt) {
  selectOlt(olt.id)
  router.push('/olts')
}

function formatTimeAgo(dateString: string | null) {
  if (!dateString) return '1m ago'
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

/* Card Design */
.router-card {
  background: var(--surface-1);
  border: 1px solid var(--border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  overflow: hidden;
  box-shadow: var(--card-shadow);
}

[data-theme="dark"] .router-card {
  background: linear-gradient(145deg, rgba(17, 24, 39, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.router-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.3);
  border-color: rgba(255, 255, 255, 0.2);
}
[data-theme="dark"] .router-card:hover {
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.7);
}

.router-card.border-cyan {
  border: 1px solid rgba(6, 182, 212, 0.3);
  box-shadow: 0 0 20px rgba(6, 182, 212, 0.05), inset 0 0 15px rgba(6, 182, 212, 0.03);
}
.router-card.border-cyan:hover {
  border-color: rgba(6, 182, 212, 0.6);
  box-shadow: 0 16px 40px rgba(0,0,0,0.5), 0 0 30px rgba(6, 182, 212, 0.15), inset 0 0 15px rgba(6, 182, 212, 0.05);
}

/* Header */
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
}

.header-info {
  display: flex;
  flex-direction: column;
}

.router-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-1);
  margin: 0;
  letter-spacing: 0.02em;
}

.router-meta {
  font-size: 0.75rem;
  color: var(--text-2);
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.meta-os {
  white-space: nowrap;
}

.board-badge {
  color: var(--text-2);
  white-space: nowrap;
}

/* Status Badge */
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
  border: 1px solid var(--border);
}

.status-badge .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status--healthy { border-color: rgba(34, 197, 94, 0.5); color: #4ade80; box-shadow: 0 0 10px rgba(34, 197, 94, 0.2); }
.status--healthy .dot { background: #4ade80; box-shadow: 0 0 6px #4ade80; }

.status--unreachable { border-color: rgba(239, 68, 68, 0.5); color: #f87171; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); }
.status--unreachable .dot { background: #f87171; box-shadow: 0 0 6px #f87171; }

/* Body */
.card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
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

/* Progress Bars */
.metric-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-1);
  margin-bottom: 8px;
  white-space: nowrap;
}

.progress-bar {
  width: 100%;
  height: 6px;
  background: var(--surface-3);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s ease-out;
}
.progress-fill.bg-green { box-shadow: 0 0 8px rgba(34, 197, 94, 0.6); background-color: #22c55e;}
.progress-fill.bg-yellow { box-shadow: 0 0 8px rgba(245, 158, 11, 0.6); background-color: #f59e0b;}
.progress-fill.bg-red { box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); background-color: #ef4444;}
.progress-fill.bg-cyan { box-shadow: 0 0 8px rgba(6, 182, 212, 0.6); background-color: #06b6d4; }

/* Telemetry Grid */
.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-top: 4px;
}

.telemetry-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
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
}

.telemetry-title {
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: var(--text-2);
  white-space: nowrap;
}

.telemetry-value {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-1);
  white-space: nowrap;
}

/* Footer */
.card-footer {
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.last-sync {
  font-size: 0.7rem;
  color: var(--text-3);
}

.btn-inspect {
  padding: 5px 16px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-1);
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-inspect:hover {
  background: var(--surface-3);
  color: var(--text-1);
}

/* Colors */
.text-green { color: #22c55e; }
.text-yellow { color: #f59e0b; }
.text-red { color: #ef4444; }
.text-cyan { color: #06b6d4; }
.text-dim { color: #64748b; }

.bg-green { background-color: #22c55e; }
.bg-yellow { background-color: #f59e0b; }
.bg-red { background-color: #ef4444; }
.bg-cyan { background-color: #06b6d4; }
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
