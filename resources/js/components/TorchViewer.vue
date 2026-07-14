<template>
  <div class="torch-viewer-overlay">
    <div class="torch-viewer glass-card">
      <div class="torch-viewer__header">
        <div class="torch-viewer__title">
          <span class="icon-pulse">🔦</span>
          <h3>Live Inspection: <span>{{ username }}</span></h3>
        </div>
        <div class="torch-viewer__actions">
          <div class="status-badge" :class="statusClass">
            <span class="status-dot"></span>
            {{ statusText }}
          </div>
          <button class="btn-close" @click="winboxMode = !winboxMode" style="margin-right: 8px;">
            {{ winboxMode ? 'Modern UI' : 'Winbox UI' }}
          </button>
          <button class="btn-close" @click="runTraceroute" :disabled="status !== 'ACTIVE' || isTracerouting" style="margin-right: 8px; background: rgba(59, 130, 246, 0.2); border-color: #3b82f6; color: #60a5fa;">
            <span v-if="isTracerouting" class="icon spin">🔄</span>
            <span v-else>📍 Traceroute</span>
          </button>
          <button class="btn-close" @click="stopTorch" :disabled="status === 'STOPPING'">
            Stop Inspection
          </button>
        </div>
      </div>

      <div class="torch-viewer__body">
        <div class="torch-viewer__content">
          <div v-if="error" class="alert alert-error">
            {{ error }}
          </div>
          
          <!-- Live Line Chart for real-time visualization (except in Winbox mode) -->
          <LiveTrafficChart :samples="liveSamples" v-if="!winboxMode" />
          
          <div class="table-container" ref="tableContainer" :class="{ 'winbox-mode': winboxMode }">
            <table class="torch-table">
              <thead>
                <tr>
                  <th class="col-src">Src Address</th>
                  <th class="col-dst">Destination</th>
                  <th class="col-app">App / Service</th>
                  <th class="col-proto">Protocol</th>
                  <th class="col-tx">TX (Download)</th>
                  <th class="col-rx">RX (Upload)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="packets.length === 0 && status === 'CONNECTING'">
                  <td colspan="6" class="empty-state">
                    Connecting to router...
                  </td>
                </tr>
                <tr v-else-if="packets.length === 0">
                  <td colspan="6" class="empty-state">
                    Waiting for traffic...
                  </td>
                </tr>
                <tr v-for="(packet, idx) in displayedPackets" :key="idx" class="fade-in-row">
                  <td class="cell-src font-mono">{{ packet['src-address'] || '-' }}</td>
                  <td class="cell-dst">
                    <span class="dst-ip font-mono">{{ packet['dst-address'] || '-' }}</span>
                    <div class="dst-meta" v-if="packet._enriched?.geo_country">
                      <span class="badge-geo">{{ packet._enriched.geo_country }}<template v-if="packet._enriched.geo_city !== '-'"> ({{ packet._enriched.geo_city }})</template></span>
                    </div>
                    <div class="dst-org" v-if="packet._enriched?.asn_org && packet._enriched.asn_org !== 'Unknown'">
                      {{ packet._enriched.asn_org }}
                    </div>
                  </td>
                  <td class="cell-app">
                    <span v-if="packet._enriched?.app_name" class="badge-app" :class="getAppClass(packet._enriched.app_category)">
                      <span class="app-icon">{{ packet._enriched.app_icon }}</span> {{ packet._enriched.app_name }}
                    </span>
                    <span v-else-if="packet._enriched?.port_service" class="badge-service" :class="getAppClass(packet._enriched.port_category)">
                      {{ packet._enriched.port_service }}
                    </span>
                    <span class="port-info">Port: {{ packet.port || '-' }}</span>
                  </td>
                  <td class="cell-proto font-mono">{{ packet.protocol || '-' }}</td>
                  <td class="cell-traffic tx-rate font-mono">{{ formatTraffic(packet.tx) }}</td>
                  <td class="cell-traffic rx-rate font-mono">{{ formatTraffic(packet.rx) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="torch-viewer__sidebar">
          <div class="diagnostic-assistant mb-4">
            <h4>Diagnostic Assistant</h4>
            <div class="diagnostic-card" :class="diagnosticResult.class">
              <span class="diagnostic-icon">{{ diagnosticResult.icon }}</span>
              <p>{{ diagnosticResult.message }}</p>
            </div>
            <div v-if="pingStats" class="ping-stats mt-2">
              <span class="text-xs text-muted">Latest Ping: </span>
              <span class="font-mono text-sm" :style="{ color: pingColor(pingStats.latestTime) }">
                {{ pingStats.latestTime }}
              </span>
              <div class="ping-details mt-1 text-xs">
                <div>Loss: <span :class="pingStats.loss > 0 ? 'text-red-500' : 'text-green-500'">{{ pingStats.loss }}%</span></div>
                <div>Avg: <span>{{ pingStats.avg }}ms</span> | Jitter: <span>{{ pingStats.jitter }}ms</span></div>
              </div>
            </div>
          </div>
          
          <div class="queue-status-panel" style="margin-bottom: 1rem;" v-if="queueInfo">
            <h4>Queue Status</h4>
            <div class="queue-card" style="margin-top: 0.5rem; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid" :style="isQueueFull ? 'border-color: #ef4444; background-color: rgba(127, 29, 29, 0.2)' : 'border-color: #374151; background-color: rgba(31, 41, 55, 0.4)'">
              <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.25rem;">
                <span style="color: #9ca3af;">Target Limit (DL/UL):</span>
                <span style="font-family: monospace; font-weight: 600;">{{ formatTraffic(queueInfo.rx_limit) }} / {{ formatTraffic(queueInfo.tx_limit) }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.5rem;">
                <span style="color: #9ca3af;">Actual Usage (DL/UL):</span>
                <span style="font-family: monospace; color: #22d3ee;">{{ formatTraffic(totalTx) }} / {{ formatTraffic(totalRx) }}</span>
              </div>
              <div style="height: 0.5rem; width: 100%; background-color: #374151; border-radius: 9999px; overflow: hidden; display: flex;">
                <div style="height: 100%; background-color: #06b6d4;" :style="{ width: rxUsagePercent + '%' }"></div>
              </div>
              <div v-if="isQueueFull" style="font-size: 0.75rem; color: #f87171; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.25rem;">
                <span>⚠️</span> Pelanggan mencapai batas kecepatan (Limit/FUP).
              </div>
            </div>
          </div>

          <h4 class="mt-4">Traffic Distribution</h4>
          <div class="chart-container">
            <div v-if="categoryStats.length === 0" class="text-muted text-sm text-center mt-8">
              No data yet...
            </div>
            <div v-for="stat in categoryStats" :key="stat.name" class="chart-bar-wrap">
              <div class="chart-label">
                <span>{{ stat.icon }} {{ stat.name }} <span class="text-xs text-muted ml-1">({{ stat.connections }} conn)</span></span>
                <span class="tx-rate">{{ formatTraffic(stat.tx) }}</span>
              </div>
              <div class="chart-bar-bg">
                <div class="chart-bar-fill" :class="getAppClass(stat.category)" :style="{ width: stat.percentage + '%' }"></div>
              </div>
            </div>
          </div>
          
          <MikrotikLogViewer :logs="systemLogs" v-if="sessionTag" />
        </div>
      </div>
      
      <div class="torch-viewer__footer">
        <div class="traffic-summary">
          <div class="summary-item">
            <span class="label">Active Conn:</span>
            <span class="value">{{ activeConnectionsCount }}</span>
          </div>
          <div class="summary-item">
            <span class="label">Dest IPs:</span>
            <span class="value">{{ uniqueDestinationsCount }}</span>
          </div>
          <div class="summary-item ml-4">
            <span class="label">Total TX:</span>
            <span class="value tx-rate">{{ formatTraffic(totalTx) }}</span>
          </div>
          <div class="summary-item">
            <span class="label">Total RX:</span>
            <span class="value rx-rate">{{ formatTraffic(totalRx) }}</span>
          </div>
        </div>
        <div class="session-info text-muted">
          Session ID: {{ sessionTag || 'Initializing...' }}
        </div>
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
          <table class="torch-table">
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
              <tr v-for="hop in tracerouteHops" :key="hop.address">
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
  const url = `/api/torch/${tag}/stream?token=${token}`
  
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
</script>

<style scoped>
.torch-viewer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(10, 14, 26, 0.85);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  animation: fadeIn 0.3s ease;
}

.torch-viewer {
  width: 90%;
  max-width: 1200px;
  height: 85vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  border-radius: 16px;
  overflow: hidden;
}

.torch-viewer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  background: rgba(0, 0, 0, 0.2);
}

.torch-viewer__title {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-pulse {
  font-size: 1.5rem;
  animation: pulse 2s infinite;
}

.torch-viewer__title h3 {
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}

.torch-viewer__title span {
  color: var(--accent-amber);
}

.torch-viewer__actions {
  display: flex;
  align-items: center;
  gap: 16px;
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
</style>
