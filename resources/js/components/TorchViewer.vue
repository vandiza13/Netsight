<template>
  <div class="torch-viewer-overlay">
    <div class="shell">

      <!-- HEADER -->
      <div class="header">
        <div class="header-left">
          <span style="color:var(--text-dimmer); font-size: 1.2rem;">🔍</span>
          <div class="header-title-block">
            <div class="title">Live Inspection: <span class="muted">{{ username }}</span></div>
            <div class="device-chip">📶 {{ vendorName || 'Router Pelanggan' }}</div>
          </div>
          <span class="live-badge"><i class="live-dot"></i>{{ statusText }}</span>
        </div>
        <div class="actions">
          <button class="btn blue" @click="runTraceroute" :disabled="status !== 'ACTIVE' || isTracerouting">📍 Trace</button>
          <button class="btn green" @click="handlePingOnt" :disabled="status !== 'ACTIVE' || isPingingOnt">📶 Ping</button>
          <button class="btn amber" @click="handleFetchUserLogs" :disabled="status !== 'ACTIVE' || isLoadingUserLogs">📄 Logs</button>
          <button class="btn red" @click="handleKickSession" :disabled="status !== 'ACTIVE' || isKicking">⚡ Kick</button>
          <button class="btn" @click="winboxMode = !winboxMode">🖥 {{ winboxMode ? 'Modern' : 'Winbox' }}</button>
          <button class="btn danger-solid" @click="stopTorch" :disabled="status === 'STOPPING'">✕ Stop</button>
        </div>
      </div>

      <!-- BODY -->
      <div class="body-grid">

        <!-- MAIN COLUMN -->
        <div class="main-col">

          <div class="section-label">
            Real-Time Throughput (TX/RX)
            <span class="legend">
              <span><i style="background:var(--tx)"></i>TX (Download)</span>
              <span><i style="background:var(--rx)"></i>RX (Upload)</span>
            </span>
          </div>

          <div v-if="error" class="alert alert-error mb-4">
            {{ error }}
          </div>

          <!-- Live Line Chart -->
          <div class="chart-card mb-4" v-if="!winboxMode">
            <LiveTrafficChart :samples="liveSamples" />
          </div>

          <div class="section-label" style="margin-top:22px;">Active Connections</div>

          <div class="table-scroll" ref="tableContainer" :class="{ 'winbox-mode': winboxMode }">
            <table>
              <thead>
                <tr>
                  <th>Src Addr</th>
                  <th>Destination</th>
                  <th>App / Service</th>
                  <th>Proto</th>
                  <th>TX (Download)</th>
                  <th>RX (Upload)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="packets.length === 0 && status === 'CONNECTING'">
                  <td colspan="6" class="empty-state" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                    Connecting to router...
                  </td>
                </tr>
                <tr v-else-if="packets.length === 0">
                  <td colspan="6" class="empty-state" style="text-align: center; color: var(--text-dim); padding: 2rem;">
                    Waiting for traffic...
                  </td>
                </tr>
                <tr v-for="(packet, idx) in displayedPackets" :key="idx" class="fade-in-row">
                  <td>
                    <span v-if="!packet['src-address'] || packet['src-address'] === '-'" class="ip-empty"><span class="dot"></span>NAT · unresolved</span>
                    <span v-else class="font-mono">{{ packet['src-address'] }}</span>
                  </td>
                  <td>
                    <div class="dest-ip">{{ packet['dst-address'] || '-' }}</div>
                    <span class="dest-loc" v-if="packet._enriched?.geo_country">
                      {{ packet._enriched.geo_country }}<template v-if="packet._enriched.geo_city !== '-'"> ({{ packet._enriched.geo_city }})</template>
                    </span>
                    <div class="dest-sub" v-if="packet._enriched?.asn_org && packet._enriched.asn_org !== 'Unknown'">
                      {{ packet._enriched.asn_org }}
                    </div>
                  </td>
                  <td>
                    <span v-if="packet._enriched?.app_name" class="app-chip" :class="getAppClass(packet._enriched.app_category)">
                      <span>{{ packet._enriched.app_icon }}</span> {{ packet._enriched.app_name }}
                    </span>
                    <span v-else-if="packet._enriched?.port_service" class="app-chip" :class="getAppClass(packet._enriched.port_category)">
                      {{ packet._enriched.port_service }}
                    </span>
                    <div class="app-sub">Port: {{ packet.port || '-' }}</div>
                  </td>
                  <td><span class="proto">{{ packet.protocol || '-' }}</span></td>
                  <td class="num-tx">{{ formatTraffic(packet.tx) }}</td>
                  <td class="num-rx">{{ formatTraffic(packet.rx) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SIDE COLUMN -->
        <div class="side-col">

          <div>
            <div class="section-label">Diagnostic Assistant</div>
            <div class="diag-alert" :style="diagnosticResult.class === 'alert-warning' ? 'background: linear-gradient(135deg, rgba(239,68,68,.14), rgba(239,68,68,.05)); border-color: rgba(239,68,68,.35);' : 'background: linear-gradient(135deg, rgba(34,197,94,.14), rgba(34,197,94,.05)); border-color: rgba(34,197,94,.35);'">
              <span class="pulse-dot" :style="diagnosticResult.class === 'alert-warning' ? 'background: var(--red);' : 'background: var(--green);'"></span>
              <p :style="diagnosticResult.class === 'alert-warning' ? 'color: #fecaca;' : 'color: #bbf7d0;'">
                {{ diagnosticResult.message }}
              </p>
            </div>
          </div>

          <div class="panel">
            <div class="metric-row">
              <span class="k">Latest Ping</span>
              <span class="v" v-if="pingStats" :style="{ color: pingColor(pingStats.latestTime) }">{{ pingStats.latestTime }}</span>
              <span class="v text-muted" v-else>-</span>
            </div>
            <div class="metric-row">
              <span class="k">Loss</span>
              <span class="v" v-if="pingStats" :class="pingStats.loss > 0 ? 'text-red-500' : 'green'">{{ pingStats.loss }}%</span>
              <span class="v text-muted" v-else>-</span>
            </div>
            <div class="metric-row">
              <span class="k">Avg / Jitter</span>
              <span class="v" v-if="pingStats">{{ pingStats.avg }}ms / {{ pingStats.jitter }}ms</span>
              <span class="v text-muted" v-else>- / -</span>
            </div>
          </div>

          <!-- ONT Ping Test Result -->
          <div v-if="ontPingResult" class="panel" style="border-color: rgba(74, 222, 128, 0.4);">
            <div style="font-weight: 700; color: #4ade80; font-size: 11px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">🏓 ONT Ping Test ({{ ontPingResult.ip }}):</div>
            <div class="metric-row"><span class="k">Loss</span><span class="v" :class="ontPingResult.packet_loss === '0%' ? 'green' : 'text-red-500'">{{ ontPingResult.packet_loss }}</span></div>
            <div class="metric-row" v-if="ontPingResult.avg_rtt"><span class="k">Min/Avg/Max</span><span class="v" style="color: #7dd3fc;">{{ ontPingResult.min_rtt }} / {{ ontPingResult.avg_rtt }} / {{ ontPingResult.max_rtt }}</span></div>
          </div>
          <div v-if="ontPingError" class="alert alert-error text-xs" style="padding: 8px 10px;">
            {{ ontPingError }}
          </div>

          <div v-if="queueInfo">
            <div class="section-label">Queue Status</div>
            <div class="panel">
              <div class="queue-line"><span>Target Limit (DL/UL)</span><b>{{ formatTraffic(queueInfo.rx_limit) }} / {{ formatTraffic(queueInfo.tx_limit) }}</b></div>
              <div class="queue-line"><span>Actual Usage (DL/UL)</span><b style="color:var(--rx)">{{ formatTraffic(totalTx) }} / {{ formatTraffic(totalRx) }}</b></div>
              <div class="progress-track">
                <div class="progress-fill" :style="{ width: rxUsagePercent + '%' }"></div>
              </div>
              <span class="progress-pct">{{ rxUsagePercent }}% of allocated limit</span>
            </div>
          </div>

          <div>
            <div class="section-label">Traffic Distribution</div>
            <div class="panel">
              <div v-if="categoryStats.length === 0" class="text-muted text-xs text-center py-4">
                No data yet...
              </div>
              <div v-else class="donut-legend">
                <div v-for="stat in categoryStats" :key="stat.name" class="row">
                  <span>{{ stat.icon }} {{ stat.name }}</span>
                  <span class="pct">{{ stat.percentage }}%</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- FOOTER -->
      <div class="footer">
        <div class="stat-group">
          <div class="stat"><span class="k">Active Conn</span><span class="v">{{ activeConnectionsCount }}</span></div>
          <div class="stat"><span class="k">Dest IPs</span><span class="v">{{ uniqueDestinationsCount }}</span></div>
          <div class="stat"><span class="k">Total TX</span><span class="v" style="color:var(--tx)">{{ formatTraffic(totalTx) }}</span></div>
          <div class="stat"><span class="k">Total RX</span><span class="v" style="color:var(--rx)">{{ formatTraffic(totalRx) }}</span></div>
        </div>
        <div class="session-id">Session ID: {{ sessionTag || 'Initialising...' }}</div>
      </div>

    </div>
    
    <!-- Traceroute Modal -->
    <div v-if="showTraceroute" class="torch-viewer-overlay" style="z-index: 1000; display: flex; align-items: center; justify-content: center;">
      <div class="glass-card" style="padding: 1.5rem; border-radius: 0.75rem; border: 1px solid #374151; max-width: 42rem; width: 100%; background: #0f172a;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="font-size: 1.125rem; font-weight: 600; color: #e5e7eb; margin: 0;">📍 Path Analysis (Traceroute)</h3>
          <button @click="showTraceroute = false" style="background: none; border: none; color: #9ca3af; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <div v-if="isTracerouting" style="text-align: center; padding: 2rem; color: #9ca3af;">
          <div class="icon spin" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: inline-block;">🔄</div>
          <div style="margin-top: 8px;">Menganalisis rute ke pelanggan...</div>
        </div>
        
        <div v-else-if="tracerouteHops.length === 0" style="text-align: center; padding: 2rem; color: #f87171;">
          {{ tracerouteError || 'Gagal mendapatkan data traceroute.' }}
        </div>
        
        <div v-else style="overflow-x: auto;">
          <table class="torch-table" style="table-layout: auto; white-space: nowrap;">
            <thead>
              <tr>
                <th style="padding: 0.5rem 1rem;">Hop</th>
                <th style="padding: 0.5rem 1rem;">Address</th>
                <th style="padding: 0.5rem 1rem;">Loss %</th>
                <th style="padding: 0.5rem 1rem;">Sent</th>
                <th style="padding: 0.5rem 1rem;">Last</th>
                <th style="padding: 0.5rem 1rem;">Avg</th>
                <th style="padding: 0.5rem 1rem;">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(hop, index) in tracerouteHops" :key="hop.hop || index">
                <td style="padding: 0.5rem 1rem; font-family: monospace;">{{ hop.hop || (index + 1) }}</td>
                <td style="padding: 0.5rem 1rem; font-family: monospace;">{{ hop.address || '-' }}</td>
                <td style="padding: 0.5rem 1rem; font-family: monospace;" :style="hop.loss > 0 ? 'color: #f87171;' : ''">{{ hop.loss || 0 }}%</td>
                <td style="padding: 0.5rem 1rem;">{{ hop.sent || 0 }}</td>
                <td style="padding: 0.5rem 1rem; font-family: monospace;">{{ hop.last || '-' }}</td>
                <td style="padding: 0.5rem 1rem; font-family: monospace;">{{ hop.avg || '-' }}</td>
                <td style="padding: 0.5rem 1rem;">
                  <span v-if="hop.status === 'timeout'" style="color: #ef4444;">Timeout</span>
                  <span v-else-if="(parseInt(hop.avg) || 0) > 50" style="color: #eab308;">Slow</span>
                  <span v-else style="color: #22c55e;">OK</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- User Logs Modal -->
    <div v-if="showUserLogsModal" class="torch-viewer-overlay" style="z-index: 1000; display: flex; align-items: center; justify-content: center;">
      <div class="glass-card" style="padding: 1.5rem; border-radius: 0.75rem; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 44rem; width: 100%; background: #0f172a; max-height: 80vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="font-size: 1.125rem; font-weight: 600; color: #e5e7eb; margin: 0; display: flex; align-items: center; gap: 8px;">
            📜 Riwayat Log Sesi: <span style="color: #38bdf8;">{{ username }}</span>
          </h3>
          <button @click="showUserLogsModal = false" style="background: none; border: none; color: #9ca3af; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        
        <div v-if="isLoadingUserLogs" style="text-align: center; padding: 2rem; color: #9ca3af;">
          <div class="icon spin" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: inline-block;">🔄</div>
          <div style="margin-top: 8px;">Membaca log kejadian pengguna dari router...</div>
        </div>
        
        <div v-else-if="userLogsList.length === 0" style="text-align: center; padding: 2rem; color: #9ca3af;">
          Tidak ada catatan log kejadian khusus untuk pengguna ini.
        </div>
        
        <div v-else style="overflow-y: auto; flex: 1;">
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <div v-for="(log, idx) in userLogsList" :key="idx" style="padding: 10px 14px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <span style="font-size: 0.82rem; font-weight: 600; color: #f1f5f9;">{{ log.category }}</span>
                <span style="font-size: 0.75rem; font-family: monospace; color: #94a3b8;">{{ log.time }}</span>
              </div>
              <div style="font-size: 0.78rem; color: #cbd5e1; font-family: monospace; margin-bottom: 2px;">{{ log.message }}</div>
              <div style="font-size: 0.75rem; color: #38bdf8; font-style: italic;">💡 {{ log.detail }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import api from '../utils/api'
import { useAuthStore } from '../stores/authStore'
import LiveTrafficChart from './LiveTrafficChart.vue'
import MikrotikLogViewer from './MikrotikLogViewer.vue'

const props = defineProps<{
  routerId: number
  username: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const auth = useAuthStore()

const status = ref<'CONNECTING' | 'ACTIVE' | 'STOPPING' | 'ERROR' | 'CLOSED'>('CONNECTING')
const error = ref<string | null>(null)
const sessionTag = ref<string | null>(null)
const packets = ref<any[]>([])
const liveSamples = ref<{ tx: number; rx: number }[]>([])
const pingStats = ref<any>(null)
const systemLogs = ref<any[]>([])
const winboxMode = ref(false)
const queueInfo = ref<{ name: string, tx_limit: number, rx_limit: number, burst_limit: string | null, dynamic: boolean } | null>(null)
const isTracerouting = ref(false)
const showTraceroute = ref(false)
const tracerouteHops = ref<any[]>([])

const maxPackets = 300
let eventSource: EventSource | null = null
let heartbeatInterval: ReturnType<typeof setInterval> | null = null

// Computed
const statusText = computed(() => {
  switch (status.value) {
    case 'CONNECTING': return 'Connecting...'
    case 'ACTIVE': return 'Live'
    case 'STOPPING': return 'Stopping...'
    case 'ERROR': return 'Error'
    case 'CLOSED': return 'Ended'
    default: return 'Unknown'
  }
})

const statusClass = computed(() => {
  return `status--${status.value.toLowerCase()}`
})

const rxUsagePercent = computed(() => {
  if (!queueInfo.value || queueInfo.value.rx_limit === 0) return 0
  // totalTx is download (traffic TO customer), rx_limit is Mikrotik's RX which limits customer's download
  // Wait, in Mikrotik simple queue for PPPoE: tx-limit is upload from customer, rx-limit is download to customer
  const p = (totalTx.value / queueInfo.value.rx_limit) * 100
  return p > 100 ? 100 : p
})

const txUsagePercent = computed(() => {
  if (!queueInfo.value || queueInfo.value.tx_limit === 0) return 0
  const p = (totalRx.value / queueInfo.value.tx_limit) * 100
  return p > 100 ? 100 : p
})

const isQueueFull = computed(() => {
  return rxUsagePercent.value >= 95 || txUsagePercent.value >= 95
})

// Helper to group flat packets list into sorted batches (latest first)
const getUniqueBatches = () => {
  const batchMap = new Map<number, any[]>()
  packets.value.forEach(p => {
    if (!p.batchId) return
    if (!batchMap.has(p.batchId)) {
      batchMap.set(p.batchId, [])
    }
    batchMap.get(p.batchId)!.push(p)
  })
  return Array.from(batchMap.entries()).sort((a, b) => b[0] - a[0])
}

const latestBatch = computed(() => {
  const unique = getUniqueBatches()
  return unique.length > 0 ? unique[0][1] : []
})

const displayedPackets = computed(() => {
  // Sort latest batch by bandwidth (tx+rx) descending so heavy hitters are at the top
  return [...latestBatch.value].sort((a, b) => {
    const bandA = Number(a.tx || 0) + Number(a.rx || 0)
    const bandB = Number(b.tx || 0) + Number(b.rx || 0)
    return bandB - bandA
  })
})

const totalTx = computed(() => {
  return latestBatch.value.reduce((sum, p) => sum + Number(p.tx || 0), 0)
})

const totalRx = computed(() => {
  return latestBatch.value.reduce((sum, p) => sum + Number(p.rx || 0), 0)
})

// Phase 4: Category Distribution Chart Stats
const categoryStats = computed(() => {
  const unique = getUniqueBatches()
  if (unique.length === 0) return []
  
  // Take last 3 batches to average out rates
  const activeBatches = unique.slice(0, 3)
  const numBatches = activeBatches.length
  
  const apps: Record<string, { tx: number, category: string, icon: string, connections: Set<string> }> = {}
  let totalBandwidth = 0
  
  activeBatches.forEach(([batchId, batchPackets]) => {
    batchPackets.forEach(p => {
      const name = p._enriched?.app_name || p._enriched?.port_service || 'Unknown'
      const category = p._enriched?.app_category || p._enriched?.port_category || 'Other'
      const icon = p._enriched?.app_icon || '📦'
      
      const tx = Number(p.tx || 0)
      const rx = Number(p.rx || 0)
      const bandwidth = tx + rx
      
      const connId = `${p['src-address'] || ''}-${p['dst-address'] || ''}-${p['protocol'] || ''}`
      
      if (!apps[name]) {
        apps[name] = { tx: 0, category, icon, connections: new Set() }
      }
      apps[name].tx += bandwidth
      if (bandwidth > 0) {
        apps[name].connections.add(connId)
      }
      totalBandwidth += bandwidth
    })
  })
  
  if (totalBandwidth === 0) return []
  
  const stats = Object.keys(apps).map(name => ({
    name: name,
    category: apps[name].category,
    icon: apps[name].icon,
    tx: apps[name].tx / numBatches, // average rate over active batches
    connections: apps[name].connections.size,
    percentage: Math.min(100, Math.round((apps[name].tx / totalBandwidth) * 100))
  }))
  
  return stats.sort((a, b) => b.percentage - a.percentage)
})

const activeConnectionsCount = computed(() => {
  const conns = new Set()
  latestBatch.value.forEach(p => {
    const bandwidth = Number(p.tx || 0) + Number(p.rx || 0)
    if (bandwidth > 0) {
      conns.add(`${p['src-address'] || ''}-${p['dst-address'] || ''}-${p['protocol'] || ''}`)
    }
  })
  return conns.size
})

const uniqueDestinationsCount = computed(() => {
  const dests = new Set()
  latestBatch.value.forEach(p => {
    const bandwidth = Number(p.tx || 0) + Number(p.rx || 0)
    if (bandwidth > 0 && p['dst-address']) {
      dests.add(p['dst-address'].split(':')[0])
    }
  })
  return dests.size
})

function getAppClass(category: string): string {
  switch (category) {
    case 'Web': return 'cat-web'
    case 'Gaming': return 'cat-gaming'
    case 'Streaming': return 'cat-streaming'
    case 'Social Media': return 'cat-social'
    case 'P2P': return 'cat-p2p'
    case 'DNS': return 'cat-dns'
    case 'VoIP': return 'cat-voip'
    case 'CDN': return 'cat-cdn'
    case 'Cloud': return 'cat-cloud'
    default: return 'cat-other'
  }
}

// Phase 4: Diagnostic Engine
const diagnosticResult = computed(() => {
  if (status.value !== 'ACTIVE' && packets.value.length === 0) {
    return { icon: '⏳', message: 'Waiting for data...', class: 'diag-neutral' }
  }

  let isHighPing = false
  let pingMs = 0
  
  if (pingStats.value && pingStats.value.latestTime !== 'timeout') {
    pingMs = parseMikrotikTime(pingStats.value.latestTime)
    if (pingMs > 100) isHighPing = true
  }

  const txMbps = totalTx.value / 1000000
  const rxMbps = totalRx.value / 1000000
  const totalMbps = txMbps + rxMbps

  if (totalMbps > 20) { // Arbitrary heavy traffic threshold for NOC diagnostic
    return { 
      icon: '🔴', 
      message: 'Bandwidth jenuh (Trafik berat). Kecepatan mungkin menurun akibat pemakaian penuh.', 
      class: 'diag-danger' 
    }
  }

  if (pingStats.value && pingStats.value.loss >= 100) {
     return { icon: '🔴', message: 'Router gagal melakukan ping ke pelanggan (RTO). Cek modem.', class: 'diag-danger' }
  }

  if (pingStats.value && pingStats.value.loss > 0) {
     return { icon: '🟡', message: `Terdeteksi Packet Loss (${pingStats.value.loss}%). Koneksi mungkin tidak stabil.`, class: 'diag-warning' }
  }

  if (isHighPing) {
    return { 
      icon: '🟡', 
      message: `Ping pelanggan tinggi (${pingMs}ms). Cek kualitas sinyal atau redaman kabel optik.`, 
      class: 'diag-warning' 
    }
  }

  if (totalMbps > 0) {
    return { 
      icon: '🟢', 
      message: 'Koneksi normal. Latensi dan bandwidth terpantau stabil.', 
      class: 'diag-good' 
    }
  }

  return { icon: 'ℹ️', message: 'Menganalisis pola trafik...', class: 'diag-neutral' }
})

function pingColor(timeStr: string) {
  if (!timeStr) return '#94a3b8'
  if (timeStr === 'timeout') return '#ef4444'
  const ms = parseMikrotikTime(timeStr)
  if (ms > 100) return '#ef4444'
  if (ms > 50) return '#f59e0b'
  return '#10b981'
}

function parseMikrotikTime(timeStr: string): number {
  if (!timeStr) return 0;
  
  let totalMs = 0;
  const sMatch = timeStr.match(/(\d+)s/);
  if (sMatch) totalMs += parseInt(sMatch[1]) * 1000;
  
  const msMatch = timeStr.match(/(\d+)ms/);
  if (msMatch) totalMs += parseInt(msMatch[1]);
  
  const usMatch = timeStr.match(/(\d+)us/);
  if (usMatch) totalMs += parseInt(usMatch[1]) / 1000;
  
  return Math.round(totalMs);
}

// Keyboard Shortcuts
const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (showTraceroute.value) {
      showTraceroute.value = false
    } else if (status.value !== 'STOPPING' && status.value !== 'CLOSED') {
      stopTorch()
    }
  }
}

// Lifecycle
onMounted(async () => {
  window.addEventListener('keydown', handleKeydown)
  
  try {
    const { data } = await api.post('/torch/inspect', {
      router_id: props.routerId,
      username: props.username
    })
    
    sessionTag.value = data.session_tag
    if (data.queue) {
      queueInfo.value = data.queue
    }
    if (data.vendor_name) {
      vendorName.value = data.vendor_name
    }
    if (data.caller_id) {
      callerId.value = data.caller_id
    }
    
    if (data.warnings) {
      console.warn(data.warnings)
    }

    startStream(data.session_tag)
    startHeartbeat(data.session_tag)
    
  } catch (err: any) {
    status.value = 'ERROR'
    error.value = err.response?.data?.message || 'Failed to start Torch session'
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  cleanup()
})

// Methods
function startStream(tag: string) {
  const token = localStorage.getItem('netsight_token')
  let url = `/api/torch/${tag}/stream?token=${token}`
  
  const schema = localStorage.getItem('netsight_demo_schema')
  if (schema) {
    url += `&schema=${schema}`
  }
  
  eventSource = new EventSource(url)
  
  eventSource.onopen = () => {
    status.value = 'ACTIVE'
  }
  
  eventSource.onmessage = (event) => {
    if (event.data) {
      try {
        const payload = JSON.parse(event.data)
        
        if (payload.type === 'traffic' && Array.isArray(payload.data)) {
          const batchId = Date.now()
          let batchTx = 0
          let batchRx = 0
          
          payload.data.forEach((p: any) => {
            p.batchId = batchId
            packets.value.push(p)
            batchTx += Number(p.tx || 0)
            batchRx += Number(p.rx || 0)
          })
          
          liveSamples.value.push({ tx: batchTx, rx: batchRx })
          if (liveSamples.value.length > 50) {
            liveSamples.value.shift()
          }

          if (packets.value.length > maxPackets) {
            packets.value.splice(0, packets.value.length - maxPackets)
          }
        } else if (payload.type === 'ping' && Array.isArray(payload.data)) {
          const pingResults = payload.data
          if (pingResults.length > 0) {
            let timeoutCount = 0
            let times: number[] = []
            pingResults.forEach((p: any) => {
              if (p.status === 'timeout' || !p.time) {
                timeoutCount++
              } else {
                const ms = parseMikrotikTime(p.time)
                times.push(ms)
              }
            })
            const loss = Math.round((timeoutCount / pingResults.length) * 100)
            let avg = 0
            let jitter = 0
            if (times.length > 0) {
              avg = Math.round(times.reduce((a, b) => a + b, 0) / times.length)
              if (times.length > 1) {
                const dev = times.reduce((a, b) => a + Math.abs(b - avg), 0)
                jitter = Math.round(dev / times.length)
              }
            }
            const latest = pingResults[pingResults.length - 1]
            pingStats.value = {
              latestTime: latest.status === 'timeout' ? 'timeout' : (latest.time || '0ms'),
              loss,
              avg,
              jitter
            }
          }
        } else if (payload.type === 'logs' && Array.isArray(payload.data)) {
          systemLogs.value = payload.data
        } else if (Array.isArray(payload)) {
          // Fallback for old format
          const batchId = Date.now()
          let batchTx = 0
          let batchRx = 0
          
          payload.forEach(p => {
            p.batchId = batchId
            packets.value.push(p)
            batchTx += Number(p.tx || 0)
            batchRx += Number(p.rx || 0)
          })
          
          liveSamples.value.push({ tx: batchTx, rx: batchRx })
          if (liveSamples.value.length > 50) {
            liveSamples.value.shift()
          }

          if (packets.value.length > maxPackets) {
            packets.value.splice(0, packets.value.length - maxPackets)
          }
        }
      } catch (e) {
        console.error('Parse error', e)
      }
    }
  }
  
  eventSource.addEventListener('timeout', (e: any) => {
    error.value = e.data
    stopTorch(false)
  })
  
  eventSource.addEventListener('closed', (e: any) => {
    error.value = e.data
    stopTorch(false)
  })
  
  eventSource.onerror = () => {
    if (status.value !== 'STOPPING' && status.value !== 'CLOSED') {
      status.value = 'ERROR'
      error.value = 'Connection to router lost.'
      eventSource?.close()
    }
  }
}

function startHeartbeat(tag: string) {
  heartbeatInterval = setInterval(async () => {
    try {
      await api.post(`/torch/${tag}/heartbeat`)
    } catch (e) {
      console.error('Heartbeat failed', e)
    }
  }, 5000)
}

async function stopTorch(sendApiRequest = true) {
  status.value = 'STOPPING'
  cleanup()
  
  if (sendApiRequest && sessionTag.value) {
    try {
      await api.post(`/torch/${sessionTag.value}/cancel`)
    } catch (e) {
      console.error('Failed to cancel session', e)
    }
  }
  
  status.value = 'CLOSED'
  emit('close')
}

function cleanup() {
  if (eventSource) {
    eventSource.close()
    eventSource = null
  }
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
    heartbeatInterval = null
  }
}

function formatTraffic(bps: number | string): string {
  const num = Number(bps)
  if (isNaN(num) || num === 0) return '0 bps'
  
  if (num >= 1000000000) return (num / 1000000000).toFixed(2) + ' Gbps'
  if (num >= 1000000) return (num / 1000000).toFixed(2) + ' Mbps'
  if (num >= 1000) return (num / 1000).toFixed(2) + ' Kbps'
  return num + ' bps'
}

const tracerouteError = ref<string | null>(null)

async function runTraceroute() {
  if (!sessionTag.value) return
  isTracerouting.value = true
  showTraceroute.value = true
  tracerouteHops.value = []
  tracerouteError.value = null
  
  try {
    const { data } = await api.get(`/torch/${sessionTag.value}/traceroute`)
    if (data && data.data) {
      tracerouteHops.value = data.data
    } else {
      tracerouteError.value = 'Tidak ada data traceroute yang diterima.'
    }
  } catch (e: any) {
    console.error('Traceroute failed', e)
    tracerouteError.value = e.response?.data?.message || 'Gagal mendapatkan data traceroute.'
  } finally {
    isTracerouting.value = false
  }
}

// ── On-Demand Quick Actions & Vendor Info ──────────────────────────
const vendorName = ref<string | null>(null)
const callerId = ref<string | null>(null)
const isKicking = ref(false)
const isPingingOnt = ref(false)
const ontPingResult = ref<any>(null)
const ontPingError = ref<string | null>(null)

const showUserLogsModal = ref(false)
const userLogsList = ref<any[]>([])
const isLoadingUserLogs = ref(false)

async function handleFetchUserLogs() {
  if (!sessionTag.value) return
  isLoadingUserLogs.value = true
  showUserLogsModal.value = true
  userLogsList.value = []
  
  try {
    const { data } = await api.get(`/torch/${sessionTag.value}/user-logs`)
    if (data && data.data) {
      userLogsList.value = data.data
    }
  } catch (e: any) {
    console.error('Failed to fetch user logs', e)
  } finally {
    isLoadingUserLogs.value = false
  }
}

async function handleKickSession() {
  if (!sessionTag.value) return
  if (!confirm(`Apakah Anda yakin ingin memutus (kick) sesi PPPoE aktif untuk "${props.username}"?\n\nModem pelanggan akan otomatis melakukan Dial-Up ulang.`)) {
    return
  }

  isKicking.value = true
  try {
    const { data } = await api.post(`/torch/${sessionTag.value}/kick`)
    alert(data.message || 'Sesi berhasil diputus/re-dial.')
  } catch (e: any) {
    alert(e.response?.data?.message || 'Gagal memutus sesi PPPoE.')
  } finally {
    isKicking.value = false
  }
}

async function handlePingOnt() {
  if (!sessionTag.value) return
  isPingingOnt.value = true
  ontPingError.value = null
  ontPingResult.value = null
  
  try {
    const { data } = await api.post(`/torch/${sessionTag.value}/ping-ont`)
    if (data && data.data) {
      ontPingResult.value = data.data
    } else {
      ontPingError.value = 'Tidak ada respons dari tes ping ONT.'
    }
  } catch (e: any) {
    ontPingError.value = e.response?.data?.message || 'Gagal menjalankan ping ke ONT.'
  } finally {
    isPingingOnt.value = false
  }
}
</script>

<style scoped>
:root {
  --bg: #0a0e14;
  --panel: #10161f;
  --panel-2: #0d1219;
  --border: #1e2733;
  --border-soft: #161d27;
  --text: #e8ecf1;
  --text-dim: #8b96a5;
  --text-dimmer: #5c6774;
  --tx: #f5a623;       /* orange - download */
  --rx: #22d3ee;       /* cyan - upload */
  --red: #ef4444;
  --green: #22c55e;
  --amber: #f59e0b;
}

.torch-viewer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(10, 14, 20, 0.9);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease;
  padding: 24px;
}

.shell {
  max-width: 1180px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: #10161f;
  border: 1px solid #1e2733;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 20px 60px -20px rgba(0,0,0,.6);
}

/* ===== HEADER ===== */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border-bottom: 1px solid #1e2733;
  background: linear-gradient(180deg, rgba(255,255,255,.015), transparent);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-title-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.title {
  font-size: 16px;
  font-weight: 600;
  letter-spacing: .2px;
  color: #e8ecf1;
}

.title .muted {
  color: #8b96a5;
  font-weight: 500;
}

.live-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(34,197,94,.12);
  border: 1px solid rgba(34,197,94,.35);
  color: #22c55e;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .6px;
  padding: 4px 10px;
  border-radius: 20px;
}

.live-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 0 rgba(34,197,94,.6);
  animation: pulse 1.8s infinite;
}

@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(34,197,94,.55); }
  70% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
  100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
}

.device-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: #8b96a5;
  background: #0d1219;
  border: 1px solid #161d27;
  padding: 2px 8px;
  border-radius: 6px;
  width: fit-content;
}

.actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  font-weight: 600;
  padding: 7px 13px;
  border-radius: 8px;
  border: 1px solid #1e2733;
  background: #0d1219;
  color: #8b96a5;
  cursor: pointer;
  transition: all .15s ease;
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #2a3644;
  color: #e8ecf1;
}

.btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.btn.blue { color: #7dd3fc; border-color: rgba(125,211,252,.25); }
.btn.green { color: #4ade80; border-color: rgba(74,222,128,.25); }
.btn.amber { color: #f59e0b; border-color: rgba(245,158,11,.25); }
.btn.red { color: #fca5a5; border-color: rgba(248,113,113,.3); }
.btn.danger-solid {
  background: rgba(239,68,68,.12);
  color: #fca5a5;
  border-color: rgba(239,68,68,.4);
}
.btn.danger-solid:hover:not(:disabled) {
  background: rgba(239,68,68,.2);
}

/* ===== BODY GRID ===== */
.body-grid {
  display: grid;
  grid-template-columns: 1fr 300px;
  flex: 1;
  overflow: hidden;
}

.main-col {
  padding: 20px 24px;
  border-right: 1px solid #1e2733;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.side-col {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: #0d1219;
  overflow-y: auto;
}

.section-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1px;
  color: #8b96a5;
  text-transform: uppercase;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.legend {
  display: flex;
  gap: 14px;
  font-size: 11px;
  font-weight: 600;
  color: #8b96a5;
}

.legend span {
  display: flex;
  align-items: center;
  gap: 5px;
}

.legend i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

/* ===== CHART ===== */
.chart-card {
  background: #0d1219;
  border: 1px solid #161d27;
  border-radius: 10px;
  padding: 16px 16px 8px;
}

/* ===== TABLE ===== */
.table-scroll {
  border: 1px solid #161d27;
  border-radius: 10px;
  overflow-x: auto;
  overflow-y: auto;
  flex: 1;
  background: #0d1219;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

thead th {
  text-align: left;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .8px;
  color: #8b96a5;
  text-transform: uppercase;
  background: #131a24;
  padding: 10px 14px;
  border-bottom: 1px solid #1e2733;
  position: sticky;
  top: 0;
  z-index: 10;
}

tbody td {
  padding: 10px 14px;
  border-bottom: 1px solid #161d27;
  vertical-align: middle;
}

tbody tr { transition: background .12s ease; }
tbody tr:nth-child(even) { background: rgba(255,255,255,.012); }
tbody tr:hover { background: rgba(255,255,255,.035); }
tbody tr:last-child td { border-bottom: none; }

.ip-empty {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: #5c6774;
  font-family: var(--mono);
  font-size: 11px;
}

.ip-empty .dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #5c6774;
}

.dest-ip {
  font-family: var(--mono);
  font-weight: 600;
  color: #e8ecf1;
}

.dest-loc {
  display: inline-block;
  font-size: 10px;
  color: #8b96a5;
  background: #10161f;
  border: 1px solid #161d27;
  padding: 1px 6px;
  border-radius: 5px;
  margin-top: 3px;
}

.dest-sub {
  font-size: 11px;
  color: #5c6774;
  margin-top: 2px;
}

.app-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #1a2230;
  border: 1px solid #1e2733;
  padding: 4px 9px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.app-sub {
  font-size: 10.5px;
  color: #5c6774;
  margin-top: 3px;
}

.proto {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 600;
  color: #8b96a5;
  background: rgba(255,255,255,.04);
  padding: 2px 7px;
  border-radius: 5px;
}

.num-tx {
  font-family: var(--mono);
  font-weight: 700;
  color: #f5a623;
}

.num-rx {
  font-family: var(--mono);
  font-weight: 700;
  color: #22d3ee;
}

/* ===== FOOTER ===== */
.footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 24px;
  border-top: 1px solid #1e2733;
  background: #0d1219;
}

.stat-group {
  display: flex;
  gap: 28px;
}

.stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.stat .k {
  font-size: 10px;
  letter-spacing: .6px;
  color: #8b96a5;
  text-transform: uppercase;
}

.stat .v {
  font-family: var(--mono);
  font-size: 14px;
  font-weight: 700;
  color: #e8ecf1;
}

.session-id {
  font-family: var(--mono);
  font-size: 11px;
  color: #5c6774;
}

/* ===== SIDE PANELS ===== */
.panel {
  background: #0d1219;
  border: 1px solid #161d27;
  border-radius: 10px;
  padding: 14px;
}

.diag-alert {
  display: flex;
  gap: 10px;
  border-radius: 9px;
  padding: 12px;
}

.diag-alert .pulse-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  margin-top: 3px;
  flex-shrink: 0;
  box-shadow: 0 0 0 0 rgba(239,68,68,.6);
  animation: pulse-red 1.6s infinite;
}

@keyframes pulse-red {
  0% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
  70% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
  100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
}

.diag-alert p {
  font-size: 12.5px;
  line-height: 1.5;
}

.metric-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  padding: 8px 0;
  border-bottom: 1px solid #161d27;
  font-size: 12.5px;
}

.metric-row:last-child { border-bottom: none; }
.metric-row .k { color: #8b96a5; }
.metric-row .v { font-family: var(--mono); font-weight: 700; color: #e8ecf1; }
.metric-row .v.green { color: #22c55e; }

.queue-line {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #8b96a5;
  margin-bottom: 6px;
}

.queue-line b {
  color: #e8ecf1;
  font-family: var(--mono);
}

.progress-track {
  width: 100%;
  height: 9px;
  border-radius: 5px;
  background: #1a2230;
  overflow: hidden;
  position: relative;
  margin-top: 4px;
}

.progress-fill {
  height: 100%;
  border-radius: 5px;
  background: linear-gradient(90deg, #22d3ee, #f59e0b);
  position: relative;
}

.progress-pct {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 700;
  color: #f59e0b;
  margin-top: 5px;
  display: block;
}

.donut-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 12px;
}

.donut-legend .row {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #e8ecf1;
}

.donut-legend .pct {
  margin-left: auto;
  font-family: var(--mono);
  font-weight: 700;
  color: #8b96a5;
}

.torch-viewer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.7);
  gap: 12px;
}

.torch-viewer__title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.title-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.title-main {
  display: flex;
  align-items: center;
  gap: 8px;
}

.title-sub {
  display: flex;
  align-items: center;
}

.badge-vendor {
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.25);
  color: #38bdf8;
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 4px;
  font-weight: 500;
  white-space: nowrap;
}

.torch-viewer__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.torch-toolbar {
  display: flex;
  align-items: center;
  background: rgba(30, 41, 59, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 3px;
  border-radius: 8px;
  gap: 4px;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  white-space: nowrap;
  height: 30px;
  padding: 0 10px;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.btn-action:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  box-shadow: none;
}

/* Sub-variants with prominent button backgrounds & borders */
.btn-action--default {
  background: #1e293b;
  border-color: #334155;
  color: #f1f5f9;
}
.btn-action--default:hover:not(:disabled) {
  background: #334155;
  border-color: #475569;
  color: #ffffff;
  transform: translateY(-1px);
}

.btn-action--blue {
  background: rgba(30, 58, 138, 0.6);
  border-color: rgba(59, 130, 246, 0.5);
  color: #93c5fd;
}
.btn-action--blue:hover:not(:disabled) {
  background: rgba(37, 99, 235, 0.8);
  border-color: #60a5fa;
  color: #ffffff;
  box-shadow: 0 0 12px rgba(59, 130, 246, 0.4);
  transform: translateY(-1px);
}

.btn-action--emerald {
  background: rgba(6, 78, 59, 0.6);
  border-color: rgba(16, 185, 129, 0.5);
  color: #6ee7b7;
}
.btn-action--emerald:hover:not(:disabled) {
  background: rgba(5, 150, 105, 0.8);
  border-color: #34d399;
  color: #ffffff;
  box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
  transform: translateY(-1px);
}

.btn-action--amber {
  background: rgba(120, 53, 15, 0.6);
  border-color: rgba(245, 158, 11, 0.5);
  color: #fde68a;
}
.btn-action--amber:hover:not(:disabled) {
  background: rgba(217, 119, 6, 0.8);
  border-color: #fbbf24;
  color: #ffffff;
  box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
  transform: translateY(-1px);
}

.badge-vendor {
  background: rgba(56, 189, 248, 0.12);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 6px;
  font-weight: 600;
  white-space: nowrap;
}

.btn-action--rose {
  background: rgba(136, 19, 55, 0.6);
  border-color: rgba(244, 63, 94, 0.5);
  color: #fca5a5;
}
.btn-action--rose:hover:not(:disabled) {
  background: rgba(225, 29, 72, 0.8);
  border-color: #fb7185;
  color: #ffffff;
  box-shadow: 0 0 12px rgba(244, 63, 94, 0.4);
  transform: translateY(-1px);
}

.btn-action--stop {
  background: #991b1b;
  border-color: #dc2626;
  color: #fef2f2;
}
.btn-action--stop:hover:not(:disabled) {
  background: #dc2626;
  border-color: #ef4444;
  color: #ffffff;
  box-shadow: 0 0 14px rgba(239, 68, 68, 0.5);
  transform: translateY(-1px);
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status--connecting { background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); }
.status--connecting .status-dot { background: var(--accent-blue); animation: pulse 1s infinite; }

.status--active { background: rgba(16, 185, 129, 0.1); color: var(--accent-green); }
.status--active .status-dot { background: var(--accent-green); box-shadow: 0 0 8px var(--accent-green); }

.status--error { background: rgba(239, 68, 68, 0.1); color: var(--accent-red); }
.status--error .status-dot { background: var(--accent-red); }

.status--stopping, .status--closed { background: rgba(255, 255, 255, 0.1); color: var(--text-muted); }
.status--stopping .status-dot, .status--closed .status-dot { background: var(--text-muted); }

.btn-close {
  background: rgba(239, 68, 68, 0.15);
  color: var(--accent-red);
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-close:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.25);
  box-shadow: 0 0 12px rgba(239, 68, 68, 0.2);
}

.btn-close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.torch-viewer__body {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.torch-viewer__content {
  flex: 2;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding: 24px;
  border-right: 1px solid var(--glass-border);
}

.torch-viewer__sidebar {
  flex: 1;
  padding: 24px;
  background: rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.torch-viewer__sidebar h4 {
  margin-top: 0;
  margin-bottom: 24px;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-size: 0.9rem;
}
.alert-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: var(--accent-red);
}

.table-container {
  flex: 1;
  overflow-y: auto;
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  background: rgba(0, 0, 0, 0.2);
}

.torch-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  table-layout: fixed;
}

/* Column widths — fixed layout for predictable spacing */
.col-src   { width: 12%; }
.col-dst   { width: 28%; }
.col-app   { width: 22%; }
.col-proto { width: 8%; }
.col-tx    { width: 15%; text-align: right; }
.col-rx    { width: 15%; text-align: right; }

.torch-table th {
  position: sticky;
  top: 0;
  background: rgba(17, 24, 39, 0.97);
  backdrop-filter: blur(8px);
  padding: 14px 18px;
  font-weight: 600;
  color: var(--text-secondary);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  border-bottom: 1px solid var(--glass-border);
  z-index: 10;
  white-space: nowrap;
}

.torch-table td {
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.025);
  font-size: 0.88rem;
  vertical-align: top;
  line-height: 1.55;
}

.torch-table tbody tr {
  transition: background 0.15s ease;
}

.torch-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.035);
}

/* Cell-specific styles */
.cell-src {
  color: var(--text-primary);
  font-size: 0.82rem;
  letter-spacing: -0.01em;
}

.cell-dst .dst-ip {
  display: block;
  color: var(--text-primary);
  font-size: 0.82rem;
  margin-bottom: 4px;
}

.cell-dst .dst-meta {
  margin-top: 2px;
}

.cell-dst .dst-org {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 3px;
  line-height: 1.3;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cell-app {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cell-app .port-info {
  font-size: 0.7rem;
  color: var(--text-muted);
}

.cell-app .app-icon {
  margin-right: 4px;
}

.cell-proto {
  color: var(--text-muted);
  text-transform: lowercase;
  font-size: 0.82rem;
}

.cell-traffic {
  font-weight: 700;
  text-align: right;
  white-space: nowrap;
  font-size: 0.88rem;
}

.tx-rate { color: var(--accent-amber); }
.rx-rate { color: var(--accent-cyan); }

.badge-geo {
  display: inline-block;
  background: rgba(255,255,255,0.08);
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.72rem;
  color: var(--text-secondary);
}

.badge-app, .badge-service {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  background: rgba(255,255,255,0.1);
  white-space: nowrap;
}
.ml-1 { margin-left: 4px; }
.ml-4 { margin-left: 16px; }
.mt-1 { margin-top: 4px; }
.block { display: block; }
.text-xs { font-size: 0.75rem; }

.cat-web { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.cat-gaming { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.cat-streaming { background: rgba(167, 139, 250, 0.2); color: #c4b5fd; }
.cat-social { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
.cat-p2p { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.cat-dns { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.cat-voip { background: rgba(20, 184, 166, 0.2); color: #2dd4bf; }
.cat-cdn { background: rgba(249, 115, 22, 0.2); color: #fb923c; }
.cat-cloud { background: rgba(56, 189, 248, 0.2); color: #38bdf8; }
.cat-other { background: rgba(156, 163, 175, 0.2); color: #9ca3af; }

.chart-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.chart-bar-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.chart-label {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  font-weight: 600;
}

.chart-bar-bg {
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.chart-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.empty-state {
  text-align: center;
  padding: 60px !important;
  color: var(--text-muted);
  font-style: italic;
}

.fade-in-row {
  animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.torch-viewer__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: rgba(0, 0, 0, 0.3);
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.traffic-summary {
  display: flex;
  gap: 32px;
}

.summary-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.summary-item .label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.summary-item .value {
  font-size: 1.2rem;
  font-weight: 700;
  font-family: var(--font-mono, monospace);
}

.session-info {
  font-size: 0.75rem;
  font-family: var(--font-mono, monospace);
}

/* Phase 5: Winbox Mode */
.winbox-mode {
  background: #ffffff !important;
  border-radius: 0 !important;
  border: 1px solid #ccc !important;
}
.winbox-mode .torch-table th {
  background: #f0f0f0 !important;
  color: #333 !important;
  padding: 4px 8px !important;
  font-size: 0.75rem !important;
  border: 1px solid #dcdcdc !important;
  text-transform: none !important;
  letter-spacing: normal !important;
}
.winbox-mode .torch-table td {
  padding: 2px 8px !important;
  border: 1px solid #e0e0e0 !important;
  color: #000 !important;
  font-size: 0.75rem !important;
  font-family: var(--font-mono, monospace);
}
.winbox-mode .torch-table tbody tr:nth-child(even) {
  background: #f9f9f9 !important;
}
.winbox-mode .torch-table tbody tr:hover {
  background: #e6f7ff !important;
}
.winbox-mode .badge-service, .winbox-mode .badge-geo {
  background: transparent !important;
  color: #000 !important;
  padding: 0 !important;
  font-weight: normal !important;
}
.winbox-mode .tx-rate, .winbox-mode .rx-rate {
  color: #000 !important;
}
.winbox-mode .text-muted {
  color: #666 !important;
}

/* Diagnostic Assistant */
.diagnostic-assistant {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 8px;
  padding: 16px;
  border: 1px solid var(--glass-border);
}
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }

.diagnostic-card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 12px;
  border-radius: 8px;
  margin-top: 12px;
  background: rgba(255, 255, 255, 0.05);
}
.diagnostic-icon {
  font-size: 1.2rem;
  margin-top: -2px;
}
.diagnostic-card p {
  margin: 0;
  font-size: 0.85rem;
  line-height: 1.4;
  color: var(--text-primary);
}
.diag-danger {
  background: rgba(239, 68, 68, 0.1);
  border-left: 3px solid var(--accent-red);
}
.diag-warning {
  background: rgba(245, 158, 11, 0.1);
  border-left: 3px solid var(--accent-amber);
}
.diag-good {
  background: rgba(16, 185, 129, 0.1);
  border-left: 3px solid var(--accent-green);
}
.diag-neutral {
  background: rgba(255, 255, 255, 0.05);
  border-left: 3px solid var(--text-muted);
}

@media (max-width: 768px) {
  .torch-viewer {
    width: 100%;
    height: 100vh;
    border-radius: 0;
  }
  .torch-viewer__header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 16px;
  }
  .torch-viewer__title h3 {
    font-size: 1rem;
  }
  .torch-viewer__actions {
    width: 100%;
    flex-wrap: wrap;
    gap: 8px;
  }
  .torch-viewer__actions .btn-close {
    padding: 6px 10px;
    font-size: 0.75rem;
  }
  .status-badge {
    padding: 4px 8px;
    font-size: 0.7rem;
  }
  .torch-viewer__body {
    flex-direction: column;
    overflow-y: auto;
  }
  .torch-viewer__content {
    flex: none;
    border-right: none;
    border-bottom: 1px solid var(--glass-border);
    padding: 12px;
    overflow: visible;
  }
  .torch-viewer__sidebar {
    flex: none;
    padding: 12px;
  }
  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  .torch-table {
    table-layout: auto;
    min-width: 600px;
  }
  .torch-viewer__footer {
    flex-direction: column;
    gap: 8px;
    padding: 12px 16px;
  }
  .traffic-summary {
    flex-wrap: wrap;
    gap: 12px;
  }
}
</style>
