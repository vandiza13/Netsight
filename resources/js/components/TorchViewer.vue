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
                      <span v-html="getAppLogoHtml(packet._enriched.app_name, packet._enriched.app_icon)"></span>
                      <span>{{ packet._enriched.app_name }}</span>
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
            <div class="diag-alert" :style="{ background: diagnosticResult.bgGradient, borderColor: diagnosticResult.borderColor }">
              <span class="pulse-dot" :style="{ background: diagnosticResult.pulseColor }"></span>
              <p :style="{ color: diagnosticResult.textColor }">
                {{ diagnosticResult.message }}
              </p>
            </div>
          </div>

          <div class="panel">
            <div class="metric-row">
              <span class="k">Latest Ping</span>
              <span class="v" v-if="pingStats" :style="{ color: pingColor(pingStats.latestTime) }">{{ formatPingMs(pingStats.latestTime) }}</span>
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
            <div class="metric-row" v-if="ontPingResult.avg_rtt"><span class="k">Min/Avg/Max</span><span class="v" style="color: #7dd3fc;">{{ formatPingMs(ontPingResult.min_rtt) }} / {{ formatPingMs(ontPingResult.avg_rtt) }} / {{ formatPingMs(ontPingResult.max_rtt) }}</span></div>
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
              <span class="progress-pct">{{ Math.round(rxUsagePercent) }}% of allocated limit</span>
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
                  <span class="flex items-center gap-1.5"><span v-html="getAppLogoHtml(stat.name, stat.icon)"></span> {{ stat.name }}</span>
                  <span class="pct">{{ Math.round(stat.percentage) }}%</span>
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
          <div class="stat"><span class="k">Total TX</span><span class="v num-tx">{{ formatTraffic(totalTx) }}</span></div>
          <div class="stat"><span class="k">Total RX</span><span class="v num-rx">{{ formatTraffic(totalRx) }}</span></div>
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
    return {
      icon: '🔍',
      message: 'Menganalisis pola trafik & kualitas koneksi pelanggan...',
      class: 'diag-info',
      pulseColor: '#38bdf8',
      bgGradient: 'linear-gradient(135deg, rgba(56,189,248,.14), rgba(56,189,248,.05))',
      borderColor: 'rgba(56,189,248,.35)',
      textColor: '#bae6fd'
    }
  }

  let isHighPing = false
  let pingMs = 0
  let isRto = false
  let hasLoss = false
  let lossPct = 0
  
  if (pingStats.value) {
    if (pingStats.value.latestTime === 'timeout') {
      isRto = true
    } else {
      pingMs = parseMikrotikTime(pingStats.value.latestTime)
      if (pingMs > 100) isHighPing = true
    }

    lossPct = Number(pingStats.value.loss || 0)
    if (lossPct >= 100) isRto = true
    else if (lossPct > 0) hasLoss = true
  }

  // Dominant application detection
  const topApp = categoryStats.value.length > 0 ? categoryStats.value[0] : null
  const dominantAppText = topApp && topApp.percentage >= 30 
    ? ` Trafik didominasi oleh ${topApp.name} (${Math.round(topApp.percentage)}%).` 
    : ''

  // Dynamic limit percent calculation
  const limitPercent = Math.round(rxUsagePercent.value || 0)

  // 1. CRITICAL / DANGER (Red 🔴)
  if (isRto) {
    return {
      icon: '🔴',
      message: 'Gagal melakukan Ping ke pelanggan (RTO / Request Timed Out). Cek modem/kabel optik.',
      class: 'diag-danger',
      pulseColor: 'var(--red)',
      bgGradient: 'linear-gradient(135deg, rgba(239,68,68,.14), rgba(239,68,68,.05))',
      borderColor: 'rgba(239,68,68,.35)',
      textColor: '#fecaca'
    }
  }

  if (limitPercent >= 90 || isQueueFull.value) {
    return {
      icon: '🔴',
      message: `Bandwidth Jenuh (100% Full Limit). Pelanggan mengalami pemadatan kecepatan.` + dominantAppText,
      class: 'diag-danger',
      pulseColor: 'var(--red)',
      bgGradient: 'linear-gradient(135deg, rgba(239,68,68,.14), rgba(239,68,68,.05))',
      borderColor: 'rgba(239,68,68,.35)',
      textColor: '#fecaca'
    }
  }

  if (lossPct > 5 || pingMs > 120) {
    return {
      icon: '🔴',
      message: `Kualitas sinyal/latensi terdegradasi parah (${pingMs > 0 ? 'Ping ' + pingMs + 'ms' : ''}${hasLoss ? ', Loss ' + lossPct + '%' : ''}).` + dominantAppText,
      class: 'diag-danger',
      pulseColor: 'var(--red)',
      bgGradient: 'linear-gradient(135deg, rgba(239,68,68,.14), rgba(239,68,68,.05))',
      borderColor: 'rgba(239,68,68,.35)',
      textColor: '#fecaca'
    }
  }

  // 2. WARNING / PERHATIAN (Yellow/Amber 🟡)
  if (limitPercent >= 75) {
    return {
      icon: '🟡',
      message: `Bandwidth mendekati batas paket (${limitPercent}% Full).` + dominantAppText,
      class: 'diag-warning',
      pulseColor: 'var(--amber)',
      bgGradient: 'linear-gradient(135deg, rgba(245,158,11,.14), rgba(245,158,11,.05))',
      borderColor: 'rgba(245,158,11,.35)',
      textColor: '#fef08a'
    }
  }

  if (hasLoss || pingMs > 50) {
    return {
      icon: '🟡',
      message: `Terdeteksi latensi tinggi/loss (${pingMs > 0 ? 'Ping ' + pingMs + 'ms' : ''}${hasLoss ? ' Loss ' + lossPct + '%' : ''}).` + dominantAppText,
      class: 'diag-warning',
      pulseColor: 'var(--amber)',
      bgGradient: 'linear-gradient(135deg, rgba(245,158,11,.14), rgba(245,158,11,.05))',
      borderColor: 'rgba(245,158,11,.35)',
      textColor: '#fef08a'
    }
  }

  // 3. GOOD / NORMAL (Green 🟢)
  return {
    icon: '🟢',
    message: `Koneksi sangat lancar & stabil. Pemakaian ${limitPercent}% dari limit paket.` + (pingMs > 0 ? ` Latensi ${pingMs}ms.` : ''),
    class: 'diag-good',
    pulseColor: 'var(--green)',
    bgGradient: 'linear-gradient(135deg, rgba(34,197,94,.14), rgba(34,197,94,.05))',
    borderColor: 'rgba(34,197,94,.35)',
    textColor: '#bbf7d0'
  }
})

function getAppLogoHtml(appName?: string, defaultIcon?: string): string {
  if (!appName) return defaultIcon || '📦'
  const name = appName.toLowerCase()

  if (name.includes('youtube')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#FF0000" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>`
  }
  if (name.includes('tiktok')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#EE1D52" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.67 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.82.57-1.31 1.56-1.3 2.56.02.92.48 1.8 1.25 2.31.87.58 2 .64 2.91.24.87-.37 1.52-1.19 1.68-2.11.04-.37.03-.75.03-1.12V.02z"/></svg>`
  }
  if (name.includes('instagram')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><defs><radialGradient id="ig-grad-h" cx="30%" cy="100%" r="150%"><stop offset="0%" stop-color="#fdf497"/><stop offset="5%" stop-color="#fdf497"/><stop offset="45%" stop-color="#fd5949"/><stop offset="60%" stop-color="#d6249f"/><stop offset="90%" stop-color="#285AEB"/></radialGradient></defs><path fill="url(#ig-grad-h)" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>`
  }
  if (name.includes('facebook')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#1877F2" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`
  }
  if (name.includes('meta')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#0668E1" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M22.959 8.22c-1.686-2.92-5.46-3.877-8.312-1.983a5.53 5.53 0 0 0-1.892 2.378 5.672 5.672 0 0 0-2.316-2.52C7.625 4.394 3.93 5.176 2.054 7.95 0 10.923.364 15.011 3.518 17.514a6.768 6.768 0 0 0 4.108 1.442 5.764 5.764 0 0 0 4.225-2.036 5.897 5.897 0 0 0 5.174 2.115 6.71 6.71 0 0 0 4.417-1.67c2.812-2.311 3.321-6.198 1.517-9.145zm-1.838 7.424c-2.18 1.776-5.183 1.341-6.273-.667a4.238 4.238 0 0 1-.295-3.376 4.382 4.382 0 0 1 1.96-2.312c2.148-1.286 5.09-.594 6.276 1.328 1.258 2.052.793 4.908-1.668 5.027zm-9.06-2.385c-.93 1.564-2.825 2.502-4.838 2.456-1.996-.062-3.87-1.121-4.81-2.613-1.077-1.748-.684-4.524 1.326-6.12 2.196-1.737 5.197-1.246 6.272.775 1.05 1.956.126 4.46-2.05 5.502h-.9zm1.096-1.574z"/></svg>`
  }
  if (name.includes('google')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.15C3.26 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.28 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.39l3.99-3.15z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.61l3.99 3.15c.95-2.85 3.6-4.96 6.72-4.96z"/></svg>`
  }
  if (name.includes('zoom')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#2D8CFF" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M4.5 4.5A2.5 2.5 0 0 0 2 7v10a2.5 2.5 0 0 0 2.5 2.5h11a2.5 2.5 0 0 0 2.5-2.5v-2.18l3.7 2.47a1 1 0 0 0 1.55-.84V7.55a1 1 0 0 0-1.55-.84L18 9.18V7a2.5 2.5 0 0 0-2.5-2.5h-11z"/></svg>`
  }
  if (name.includes('netflix')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#E50914" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M5.398 0v24c1.884-.33 3.77-.66 5.655-1.002V13.82L16.29 24c1.884-.33 3.77-.66 5.655-1.002V0h-5.655v10.18L11.053 0H5.398z"/></svg>`
  }
  if (name.includes('steam')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#66c0f4" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M12 0a12 12 0 0 0-11.968 11.07l5.247 2.169a3.528 3.528 0 0 1 2.451-.976c.266 0 .524.03.774.086l2.846-4.137V8.12A3.529 3.529 0 0 1 14.88 4.6a3.529 3.529 0 0 1 3.53 3.52 3.529 3.529 0 0 1-3.53 3.529h-.088l-4.1 2.872a3.524 3.524 0 0 1-4.088 1.488L1.314 13.87A12 12 0 1 0 12 0z"/></svg>`
  }
  if (name.includes('whatsapp')) {
    return `<svg class="app-logo-svg" viewBox="0 0 24 24" width="15" height="15" fill="#25D366" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.705 1.758zm6.654-4.225l.48.285c1.46.867 3.14 1.324 4.863 1.325 5.277 0 9.571-4.294 9.574-9.573.001-2.557-.996-4.96-2.809-6.772-1.813-1.813-4.216-2.812-6.775-2.812-5.278 0-9.572 4.294-9.575 9.573-.001 1.77.469 3.498 1.36 5.016l.313.528-1.005 3.673 3.759-.986z"/></svg>`
  }

  return defaultIcon || '📦'
}

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

function formatPingMs(timeStr: string): string {
  if (!timeStr) return '';
  if (timeStr === 'timeout') return 'timeout';
  
  let totalMs = 0;
  const sMatch = timeStr.match(/(\d+)s/);
  if (sMatch) totalMs += parseInt(sMatch[1]) * 1000;
  
  const msMatch = timeStr.match(/(\d+)ms/);
  if (msMatch) totalMs += parseInt(msMatch[1]);
  
  const usMatch = timeStr.match(/(\d+)us/);
  if (usMatch) totalMs += parseInt(usMatch[1]) / 1000;
  
  if (totalMs === 0) return '0ms';
  if (totalMs < 1) return '<1ms';
  return `${Math.round(totalMs)}ms`;
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
  min-height: 230px;
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
  color: #f5a623 !important;
}

.num-rx {
  font-family: var(--mono);
  font-weight: 700;
  color: #22d3ee !important;
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
