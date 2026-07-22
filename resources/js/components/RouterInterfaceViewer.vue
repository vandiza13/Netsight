<template>
  <div class="interface-viewer">
    <!-- Header Summary & Quick Stats -->
    <div class="interface-viewer__header">
      <div class="header-left">
        <h4 class="title">🔌 Hardware Interfaces & Port Status</h4>
        <span class="subtitle">Real-time port link speed & traffic monitoring</span>
      </div>
      <div class="header-right">
        <button 
          class="btn-toggle-live" 
          :class="isLivePaused ? 'btn-live--paused' : 'btn-live--active'"
          @click="toggleLive" 
          :title="isLivePaused ? 'Resume Live Monitoring' : 'Pause Live Monitoring'"
        >
          <span class="live-dot" :class="{'live-dot--pulse': !isLivePaused}"></span>
          {{ isLivePaused ? 'PAUSED' : 'LIVE (3s)' }}
        </button>
        <button class="btn-refresh" @click="fetchInterfaces" :disabled="loading" title="Refresh interfaces">
          <span :class="{'spinning': loading}">🔄</span>
        </button>
      </div>
    </div>

    <!-- Visual Switch Port Bar -->
    <div class="port-bar-card glass-card">
      <div class="port-bar-title">PORT SWITCH PANEL</div>
      <div class="port-grid" v-if="interfaces.length > 0">
        <div 
          v-for="iface in interfaces" 
          :key="iface.name"
          class="port-box"
          :class="{
            'port-box--active': selectedInterface?.name === iface.name,
            'port-box--up': iface.is_running && !iface.is_disabled,
            'port-box--down': !iface.is_running && !iface.is_disabled,
            'port-box--disabled': iface.is_disabled
          }"
          @click="selectInterface(iface)"
        >
          <div class="port-icon">
            <span class="led-dot" :class="getLedClass(iface)"></span>
            <span class="port-type-badge">{{ iface.type === 'sfp' ? 'SFP' : 'RJ45' }}</span>
          </div>
          <div class="port-name" :title="iface.name">{{ shortenName(iface.name) }}</div>
          <div class="port-speed">{{ iface.link_speed || (iface.is_running ? 'Connected' : 'Down') }}</div>
        </div>
      </div>
      <div class="empty-ports" v-else-if="!loading">
        No interfaces found for this router.
      </div>
      <div class="skeleton-ports" v-else>
        <div class="skeleton-port" v-for="n in 5" :key="n"></div>
      </div>
    </div>

    <!-- Selected Port Detailed Monitor Panel -->
    <div class="port-detail-panel glass-card" v-if="selectedInterface">
      <div class="detail-header">
        <div class="detail-title-group">
          <h5>⚡ Live Monitor: <span class="accent-text">{{ selectedInterface.name }}</span></h5>
          <span class="mac-badge" v-if="selectedInterface.mac_address">MAC: {{ selectedInterface.mac_address }}</span>
        </div>
        <div class="status-badge" :class="selectedInterface.is_running ? 'badge-green' : 'badge-red'">
          {{ selectedInterface.is_running ? 'LINK UP (Running)' : 'LINK DOWN' }}
        </div>
      </div>

      <!-- Real-time Traffic Gauges -->
      <div class="traffic-metrics-grid">
        <div class="metric-card rx-card">
          <div class="metric-label">DOWNLOAD TRAFFIC (RX)</div>
          <div class="metric-value">{{ formatBps(trafficData.rx_bps) }}</div>
          <div class="metric-sub">{{ trafficData.rx_packet_per_sec.toLocaleString() }} pkts/sec</div>
        </div>

        <div class="metric-card tx-card">
          <div class="metric-label">UPLOAD TRAFFIC (TX)</div>
          <div class="metric-value">{{ formatBps(trafficData.tx_bps) }}</div>
          <div class="metric-sub">{{ trafficData.tx_packet_per_sec.toLocaleString() }} pkts/sec</div>
        </div>
      </div>

      <!-- Quick Actions / Link Speed Info -->
      <div class="detail-footer">
        <div class="info-pill">
          <span class="label">Link Speed:</span>
          <span class="value">{{ selectedInterface.link_speed || 'N/A' }}</span>
        </div>
        <div class="info-pill">
          <span class="label">Type:</span>
          <span class="value text-uppercase">{{ selectedInterface.type }}</span>
        </div>
        <div class="info-pill">
          <span class="label">Status:</span>
          <span class="value" :class="selectedInterface.is_disabled ? 'text-amber' : 'text-green'">
            {{ selectedInterface.is_disabled ? 'Disabled' : 'Enabled' }}
          </span>
        </div>
      </div>
    </div>

    <div class="empty-selection-hint glass-card" v-else-if="!loading && interfaces.length > 0">
      👆 Klik salah satu port di atas untuk melihat monitor trafik Rx/Tx secara real-time.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import api from '../utils/api'

const props = defineProps<{
  routerId: number
}>()

interface RouterInterface {
  id?: number
  name: string
  type: string
  mac_address: string | null
  is_running: boolean
  is_disabled: boolean
  link_speed: string | null
}

const interfaces = ref<RouterInterface[]>([])
const selectedInterface = ref<RouterInterface | null>(null)
const loading = ref(false)
const trafficData = ref({
  rx_bps: 0,
  tx_bps: 0,
  rx_packet_per_sec: 0,
  tx_packet_per_sec: 0,
})

let trafficInterval: ReturnType<typeof setInterval> | null = null

async function fetchInterfaces() {
  loading.value = true
  try {
    const { data } = await api.get(`/routers/${props.routerId}/interfaces`)
    interfaces.value = data.data || []
    
    // Auto-select first running interface if none selected
    if (!selectedInterface.value && interfaces.value.length > 0) {
      const firstRunning = interfaces.value.find(i => i.is_running && !i.is_disabled)
      selectInterface(firstRunning || interfaces.value[0])
    }
  } catch (err) {
    console.error('Failed to fetch router interfaces:', err)
  } finally {
    loading.value = false
  }
}

const isLivePaused = ref(false)

function toggleLive() {
  isLivePaused.value = !isLivePaused.value
  if (!isLivePaused.value && selectedInterface.value) {
    fetchTraffic()
  }
}

function selectInterface(iface: RouterInterface) {
  selectedInterface.value = iface
  if (!isLivePaused.value) {
    fetchTraffic()
  }
  
  // Reset and restart 3-second live polling interval
  if (trafficInterval) clearInterval(trafficInterval)
  trafficInterval = setInterval(() => {
    if (!isLivePaused.value && !document.hidden) {
      fetchTraffic()
    }
  }, 3000)
}

async function fetchTraffic() {
  if (!selectedInterface.value || isLivePaused.value || document.hidden) return
  try {
    const name = encodeURIComponent(selectedInterface.value.name)
    const { data } = await api.get(`/routers/${props.routerId}/interfaces/${name}/traffic`)
    if (data && data.data) {
      trafficData.value = {
        rx_bps: data.data.rx_bps || 0,
        tx_bps: data.data.tx_bps || 0,
        rx_packet_per_sec: data.data.rx_packet_per_sec || 0,
        tx_packet_per_sec: data.data.tx_packet_per_sec || 0,
      }
    }
  } catch (err) {
    console.error('Failed to fetch interface traffic:', err)
  }
}

function getLedClass(iface: RouterInterface): string {
  if (iface.is_disabled) return 'led-off'
  if (iface.is_running) return 'led-on-green'
  return 'led-on-amber'
}

function shortenName(name: string): string {
  if (name.length > 14) {
    return name.substring(0, 12) + '…'
  }
  return name
}

function formatBps(bps: number): string {
  if (!bps || bps <= 0) return '0 bps'
  if (bps >= 1000000000) return (bps / 1000000000).toFixed(2) + ' Gbps'
  if (bps >= 1000000) return (bps / 1000000).toFixed(2) + ' Mbps'
  if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps'
  return bps + ' bps'
}

watch(() => props.routerId, () => {
  selectedInterface.value = null
  fetchInterfaces()
})

onMounted(() => {
  fetchInterfaces()
})

onBeforeUnmount(() => {
  if (trafficInterval) clearInterval(trafficInterval)
})
</script>

<style scoped>
.interface-viewer {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.interface-viewer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-toggle-live {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.btn-live--active {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
  border-color: rgba(34, 197, 94, 0.3);
}

.btn-live--paused {
  background: rgba(245, 158, 11, 0.15);
  color: #f59e0b;
  border-color: rgba(245, 158, 11, 0.3);
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
}

.live-dot--pulse {
  animation: pulse 1.5s infinite;
}

.header-left .title {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 2px 0;
}

.header-left .subtitle {
  font-size: 0.78rem;
  color: var(--text-muted);
}

.btn-refresh {
  background: transparent;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  opacity: 0.8;
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

/* Visual Switch Panel */
.port-bar-card {
  padding: 16px;
  border: 1px solid var(--glass-border);
  background: rgba(13, 18, 25, 0.6);
  border-radius: 12px;
}

.port-bar-title {
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: var(--text-muted);
  margin-bottom: 12px;
  text-transform: uppercase;
}

.port-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}

.port-box {
  background: rgba(16, 22, 31, 0.8);
  border: 1px solid var(--glass-border);
  border-radius: 8px;
  padding: 10px 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.port-box:hover {
  transform: translateY(-2px);
  border-color: rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.port-box--active {
  border-color: var(--accent-cyan) !important;
  background: rgba(6, 182, 212, 0.1) !important;
  box-shadow: 0 0 0 1px var(--accent-cyan);
}

.port-box--up {
  border-left: 3px solid var(--accent-green);
}
.port-box--down {
  border-left: 3px solid rgba(255, 255, 255, 0.2);
}
.port-box--disabled {
  opacity: 0.5;
  border-left: 3px solid var(--accent-red);
}

.port-icon {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.led-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.led-on-green {
  background: #22c55e;
  box-shadow: 0 0 8px #22c55e;
}
.led-on-amber {
  background: #f59e0b;
}
.led-off {
  background: #4b5563;
}

.port-type-badge {
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 4px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.05);
  color: var(--text-muted);
}

.port-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--text-primary);
  font-family: var(--font-mono, monospace);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.port-speed {
  font-size: 0.7rem;
  color: var(--text-secondary);
}

/* Detail Panel */
.port-detail-panel {
  padding: 16px;
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-title-group h5 {
  margin: 0 0 4px 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
}

.accent-text {
  color: var(--accent-cyan);
}

.mac-badge {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-family: var(--font-mono, monospace);
}

.status-badge {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 12px;
  letter-spacing: 0.05em;
}

.badge-green {
  background: rgba(34, 197, 94, 0.15);
  color: #22c55e;
}
.badge-red {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

/* Traffic Metrics */
.traffic-metrics-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.metric-card {
  padding: 14px;
  border-radius: 8px;
  border: 1px solid var(--glass-border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rx-card {
  background: linear-gradient(135deg, rgba(34, 211, 238, 0.08), rgba(34, 211, 238, 0.02));
  border-color: rgba(34, 211, 238, 0.2);
}

.tx-card {
  background: linear-gradient(135deg, rgba(245, 166, 35, 0.08), rgba(245, 166, 35, 0.02));
  border-color: rgba(245, 166, 35, 0.2);
}

.metric-label {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  color: var(--text-muted);
}

.rx-card .metric-value {
  font-size: 1.4rem;
  font-weight: 800;
  color: #22d3ee;
  font-family: var(--font-mono, monospace);
}

.tx-card .metric-value {
  font-size: 1.4rem;
  font-weight: 800;
  color: #f5a623;
  font-family: var(--font-mono, monospace);
}

.metric-sub {
  font-size: 0.72rem;
  color: var(--text-secondary);
}

/* Detail Footer Info */
.detail-footer {
  display: flex;
  gap: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding-top: 12px;
}

.info-pill {
  font-size: 0.78rem;
  display: flex;
  gap: 6px;
}
.info-pill .label {
  color: var(--text-muted);
}
.info-pill .value {
  font-weight: 600;
  color: var(--text-primary);
}

.empty-selection-hint {
  padding: 24px;
  text-align: center;
  color: var(--text-muted);
  font-size: 0.85rem;
  border: 1px dashed var(--glass-border);
  border-radius: 8px;
}

.text-uppercase { text-transform: uppercase; }
.text-amber { color: var(--accent-amber); }
.text-green { color: var(--accent-green); }
</style>
