<template>
  <div class="interface-viewer">
    <!-- HEADER -->
    <div class="panel-top">
      <div>
        <div class="panel-title">⚡ Hardware Interfaces &amp; Port Status</div>
        <div class="panel-sub">Real-time port link speed &amp; traffic monitoring</div>
      </div>
      <div class="top-actions">
        <button 
          class="paused-pill"
          :class="{ 'paused-pill--active': !isLivePaused }"
          @click="toggleLive"
          :title="isLivePaused ? 'Resume Live Traffic Stream' : 'Pause Live Traffic Stream'"
        >
          <span class="dot" :class="{ 'dot--pulse': !isLivePaused }"></span>
          {{ isLivePaused ? 'PAUSED' : 'LIVE (3s)' }}
        </button>
        <div class="icon-btn" @click="fetchInterfaces(true)" :disabled="loading" title="Refresh list">
          <span :class="{ 'spinning': loading }">⟳</span>
        </div>
      </div>
    </div>

    <!-- METAL RACK PANEL -->
    <div class="rack">
      <!-- Loading Skeleton -->
      <div v-if="loading && interfaces.length === 0" class="rack-loading">
        Loading interface ports...
      </div>

      <!-- Empty State -->
      <div v-else-if="interfaces.length === 0" class="rack-empty">
        Tidak ada interface yang ditemukan di router ini.
      </div>

      <!-- ================= PHYSICAL INTERFACES ================= -->
      <div class="super-group-label" v-if="groupedInterfaces.rj45.length || groupedInterfaces.sfp.length">
        <span class="icon">🔌</span> PHYSICAL INTERFACES
      </div>

      <div class="super-group-content">
        <!-- 1. LAN RJ45 -->
        <div class="group" v-if="groupedInterfaces.rj45.length > 0">
          <div class="group-label">LAN RJ45<span class="line"></span></div>
          <div class="port-row">
            <div 
              v-for="iface in groupedInterfaces.rj45" 
              :key="iface.name"
              class="port"
              :class="{
                'selected': selectedInterface?.name === iface.name,
                'dim': !iface.is_running || iface.is_disabled
              }"
              @click="selectInterface(iface)"
            >
              <!-- Jack graphic -->
              <div class="jack" :class="{'sfp': iface.type === 'sfp'}">
                <div class="jack-bezel"></div>
                <div class="jack-slot"></div>
                <div class="jack-pins">
                  <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                </div>
                <div class="jack-tab"></div>
                <span class="led" :class="getLedClass(iface)"></span>
              </div>
              <div class="port-label" :title="iface.name">{{ shortenName(iface.name) }}</div>
              <div class="port-sub"><span class="port-tag">RJ45</span></div>
              <div class="port-speed">{{ formatSpeed(iface) }}</div>
            </div>
          </div>
        </div>

        <!-- 2. SFP Modules -->
        <div class="group" v-if="groupedInterfaces.sfp.length > 0">
          <div class="group-label">SFP Modules<span class="line"></span></div>
          <div class="port-row">
            <div 
              v-for="iface in groupedInterfaces.sfp" 
              :key="iface.name"
              class="port"
              :class="{
                'selected': selectedInterface?.name === iface.name,
                'dim': !iface.is_running || iface.is_disabled
              }"
              @click="selectInterface(iface)"
            >
              <div class="jack sfp">
                <div class="jack-bezel"></div>
                <div class="jack-slot"></div>
                <span class="led" :class="getLedClass(iface)"></span>
              </div>
              <div class="port-label" :title="iface.name">{{ shortenName(iface.name) }}</div>
              <div class="port-sub"><span class="port-tag">SFP+</span></div>
              <div class="port-speed">{{ formatSpeed(iface) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= VIRTUAL INTERFACES ================= -->
      <div class="super-group-label virtual-label" v-if="groupedInterfaces.vlan.length || groupedInterfaces.vpn.length">
        <span class="icon">☁</span> VIRTUAL INTERFACES
      </div>

      <div class="super-group-content">
        <!-- 3. VLAN Interfaces -->
        <div class="group" v-if="groupedInterfaces.vlan.length > 0">
          <div class="group-label">VLAN Interfaces<span class="line"></span></div>
          <div class="port-row">
            <div 
              v-for="iface in groupedInterfaces.vlan" 
              :key="iface.name"
              class="port"
              :class="{
                'selected': selectedInterface?.name === iface.name,
                'dim': !iface.is_running || iface.is_disabled
              }"
              @click="selectInterface(iface)"
            >
              <div class="jack virtual">
                <div class="jack-bezel"></div>
                <div class="jack-slot"></div>
                <div class="jack-pins"></div>
                <div class="jack-tab"></div>
                <span class="led" :class="getLedClass(iface)"></span>
              </div>
              <div class="port-label" :title="iface.name">{{ shortenName(iface.name) }}</div>
              <div class="port-speed">{{ formatSpeed(iface) }}</div>
            </div>
          </div>
        </div>

        <!-- 4. VPN & Services -->
        <div class="group" v-if="groupedInterfaces.vpn.length > 0">
          <div class="group-label">VPN &amp; Services<span class="line"></span></div>
          <div class="port-row">
            <div 
              v-for="iface in groupedInterfaces.vpn" 
              :key="iface.name"
              class="port"
              :class="{
                'selected': selectedInterface?.name === iface.name,
                'dim': !iface.is_running || iface.is_disabled
              }"
              @click="selectInterface(iface)"
            >
              <div class="jack virtual">
                <div class="jack-bezel"></div>
                <div class="jack-slot"></div>
                <div class="jack-pins"></div>
                <div class="jack-tab"></div>
                <span class="led" :class="getLedClass(iface)"></span>
              </div>
              <div class="port-label" :title="iface.name">{{ shortenName(iface.name) }}</div>
              <div class="port-speed">{{ formatSpeed(iface) }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIVE MONITOR SLIDE PANEL -->
    <Teleport to="body">
      <div class="monitor-overlay" v-if="selectedInterface" @click="selectedInterface = null"></div>
      <div class="monitor-panel" :class="{ 'is-open': selectedInterface }">
        <div v-if="selectedInterface" class="monitor-panel-content">
          <div class="monitor-head">
            <div class="monitor-title">⚡ Live Monitor: <span class="name">{{ selectedInterface.name }}</span></div>
            <button class="btn-close-monitor" @click="selectedInterface = null" title="Close Panel">✕</button>
          </div>
          <div class="monitor-subhead">
            <span class="link-up" :class="selectedInterface.is_running ? 'link-up--active' : 'link-up--down'">
              <span class="dot"></span>
              {{ selectedInterface.is_running ? 'LINK UP (Running)' : 'LINK DOWN' }}
            </span>
            <div class="mac">MAC: {{ selectedInterface.mac_address || 'N/A' }}</div>
          </div>

          <div class="traffic-grid">
            <!-- Download RX -->
            <div class="traffic-card">
              <div class="traffic-label">Download Traffic (RX)</div>
              <div class="traffic-value rx">
                {{ formatBpsParts(trafficData.rx_bps).val }} 
                <span class="unit">{{ formatBpsParts(trafficData.rx_bps).unit }}</span>
              </div>
              <div class="traffic-pkts">{{ trafficData.rx_packet_per_sec.toLocaleString() }} pkts/sec</div>
              <svg class="spark" width="100%" height="34" viewBox="0 0 220 34" preserveAspectRatio="none">
                <polyline fill="none" stroke="var(--cyan)" stroke-width="1.6" :points="rxSparkPoints"/>
              </svg>
            </div>

            <!-- Upload TX -->
            <div class="traffic-card">
              <div class="traffic-label">Upload Traffic (TX)</div>
              <div class="traffic-value tx">
                {{ formatBpsParts(trafficData.tx_bps).val }} 
                <span class="unit">{{ formatBpsParts(trafficData.tx_bps).unit }}</span>
              </div>
              <div class="traffic-pkts">{{ trafficData.tx_packet_per_sec.toLocaleString() }} pkts/sec</div>
              <svg class="spark" width="100%" height="34" viewBox="0 0 220 34" preserveAspectRatio="none">
                <polyline fill="none" stroke="var(--orange)" stroke-width="1.6" :points="txSparkPoints"/>
              </svg>
            </div>
          </div>

          <div class="meta-row">
            <span>Link Speed: <b>{{ selectedInterface.link_speed || 'N/A' }}</b></span>
            <span>Type: <b>{{ selectedInterface.type.toUpperCase() }}</b></span>
            <span>Status: <b :style="{ color: selectedInterface.is_disabled ? 'var(--orange)' : 'var(--green)' }">{{ selectedInterface.is_disabled ? 'Disabled' : 'Enabled' }}</b></span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
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
const isLivePaused = ref(false)

const trafficData = ref({
  rx_bps: 0,
  tx_bps: 0,
  rx_packet_per_sec: 0,
  tx_packet_per_sec: 0,
})

const rxHistory = ref<number[]>([15, 20, 18, 24, 19, 22, 17, 21, 16, 20, 18, 19, 15, 17, 20])
const txHistory = ref<number[]>([10, 12, 9, 14, 11, 13, 10, 12, 8, 11, 10, 11, 9, 10, 12])

let trafficInterval: ReturnType<typeof setInterval> | null = null

function formatSpeed(iface: RouterInterface): string {
  if (!iface.is_running && !iface.is_disabled) return 'Down'
  if (iface.is_disabled) return 'Disabled'
  
  // If it's an SFP+ port, force it to 10Gbps if it's 1Gbps or null, as requested
  const nameLower = iface.name.toLowerCase()
  if (nameLower.includes('sfp+') || nameLower.includes('sfpplus') || nameLower.includes('10g')) {
    return '10Gbps'
  }
  
  if (iface.link_speed) return iface.link_speed
  return 'Connected'
}

// Grouping interfaces dynamically into 4 categories
const groupedInterfaces = computed(() => {
  const rj45: RouterInterface[] = []
  const sfp: RouterInterface[] = []
  const vlan: RouterInterface[] = []
  const vpn: RouterInterface[] = []

  interfaces.value.forEach(i => {
    const nameLower = i.name.toLowerCase()
    const typeLower = i.type.toLowerCase()

    if (typeLower === 'sfp' || nameLower.includes('sfp')) {
      sfp.push(i)
    } else if (typeLower === 'vlan' || nameLower.includes('vlan')) {
      vlan.push(i)
    } else if (
      nameLower.startsWith('wg') || 
      nameLower.startsWith('eoip') || 
      nameLower.startsWith('gre') || 
      nameLower.startsWith('l2tp') || 
      nameLower.startsWith('ovpn') || 
      nameLower.startsWith('pptp') || 
      nameLower.startsWith('sstp') || 
      nameLower === 'lo' ||
      nameLower.includes('vpn') ||
      typeLower.includes('vpn') ||
      typeLower.includes('ovpn')
    ) {
      vpn.push(i)
    } else {
      rj45.push(i)
    }
  })

  return { rj45, sfp, vlan, vpn }
})

async function fetchInterfaces(force = false) {
  loading.value = true
  try {
    const url = force ? `/routers/${props.routerId}/interfaces?refresh=true` : `/routers/${props.routerId}/interfaces`
    const { data } = await api.get(url)
    interfaces.value = data.data || []
  } catch (err) {
    console.error('Failed to fetch router interfaces:', err)
  } finally {
    loading.value = false
  }
}

function toggleLive() {
  isLivePaused.value = !isLivePaused.value
  if (!isLivePaused.value && selectedInterface.value) {
    fetchTraffic()
  }
}

function selectInterface(iface: RouterInterface) {
  // Toggle selection if clicking the already selected interface
  if (selectedInterface.value?.name === iface.name) {
    selectedInterface.value = null
    if (trafficInterval) clearInterval(trafficInterval)
    return
  }

  selectedInterface.value = iface
  rxHistory.value = Array(15).fill(10)
  txHistory.value = Array(15).fill(10)

  if (!isLivePaused.value) {
    fetchTraffic()
  }
  
  // Restart 3-second live polling interval
  if (trafficInterval) clearInterval(trafficInterval)
  trafficInterval = setInterval(() => {
    if (!isLivePaused.value && !document.hidden && selectedInterface.value) {
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
      const rx = data.data.rx_bps || 0
      const tx = data.data.tx_bps || 0
      trafficData.value = {
        rx_bps: rx,
        tx_bps: tx,
        rx_packet_per_sec: data.data.rx_packet_per_sec || 0,
        tx_packet_per_sec: data.data.tx_packet_per_sec || 0,
      }

      // Append to sparkline history
      rxHistory.value.push(Math.min(30, Math.max(4, Math.round((rx / 10000000) * 10))))
      if (rxHistory.value.length > 15) rxHistory.value.shift()

      txHistory.value.push(Math.min(30, Math.max(4, Math.round((tx / 2000000) * 10))))
      if (txHistory.value.length > 15) txHistory.value.shift()
    }
  } catch (err) {
    console.error('Failed to fetch interface traffic:', err)
  }
}

const rxSparkPoints = computed(() => {
  return rxHistory.value.map((val, idx) => `${(idx * (220 / 14)).toFixed(1)},${34 - val}`).join(' ')
})

const txSparkPoints = computed(() => {
  return txHistory.value.map((val, idx) => `${(idx * (220 / 14)).toFixed(1)},${34 - val}`).join(' ')
})

function getLedClass(iface: RouterInterface): string {
  if (iface.is_disabled) return 'off'
  if (iface.is_running) return 'up'
  return 'down'
}

function getPortTag(iface: RouterInterface): string {
  if (iface.type === 'sfp' || iface.name.toLowerCase().includes('sfp')) return 'SFP'
  return 'RJ45'
}

function shortenName(name: string): string {
  if (name.length > 14) {
    return name.substring(0, 12) + '…'
  }
  return name
}

function formatBpsParts(bps: number): { val: string, unit: string } {
  if (!bps || bps <= 0) return { val: '0.00', unit: 'bps' }
  if (bps >= 1000000000) return { val: (bps / 1000000000).toFixed(2), unit: 'Gbps' }
  if (bps >= 1000000) return { val: (bps / 1000000).toFixed(2), unit: 'Mbps' }
  if (bps >= 1000) return { val: (bps / 1000).toFixed(1), unit: 'Kbps' }
  return { val: bps.toString(), unit: 'bps' }
}

watch(() => props.routerId, () => {
  selectedInterface.value = null
  if (trafficInterval) clearInterval(trafficInterval)
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
  --bg: #0a0e14;
  --panel: #10161f;
  --panel-2: #0d1219;
  --border: #1e2733;
  --border-soft: #161d27;
  --text: #e8ecf1;
  --text-dim: #8b96a5;
  --text-dimmer: #5c6774;
  --teal: #2dd4bf;
  --cyan: #22d3ee;
  --green: #22c55e;
  --orange: #f5a623;
  --red: #ef4444;
  --purple: #c4b5fd;
  --mono: 'JetBrains Mono', 'SF Mono', Consolas, monospace;
  --sans: 'Inter', -apple-system, sans-serif;
}

.interface-viewer {
  display: flex;
  flex-direction: column;
  gap: 18px;
  font-family: var(--sans);
  color: var(--text);
}

/* HEADER */
.panel-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 18px;
}

.panel-title {
  font-size: 17px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--text);
}

.panel-sub {
  font-size: 12.5px;
  color: var(--text-dim);
  margin-top: 4px;
}

.top-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.paused-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(245, 166, 35, 0.12);
  border: 1px solid rgba(245, 166, 35, 0.4);
  color: var(--orange);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.4px;
  padding: 5px 11px;
  border-radius: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.paused-pill--active {
  background: rgba(34, 197, 94, 0.12);
  border-color: rgba(34, 197, 94, 0.4);
  color: var(--green);
}

.paused-pill .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--orange);
}

.paused-pill--active .dot {
  background: var(--green);
  box-shadow: 0 0 6px var(--green);
}

.dot--pulse {
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.4; transform: scale(1.2); }
  100% { opacity: 1; transform: scale(1); }
}

.icon-btn {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--panel-2);
  border: 1px solid var(--border-soft);
  color: var(--text-dim);
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s ease;
}

.icon-btn:hover {
  color: var(--cyan);
  border-color: rgba(34, 211, 238, 0.35);
}

.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* RACK PANEL */
.rack {
  background:
    repeating-linear-gradient(100deg, rgba(255,255,255,0.012) 0px, rgba(255,255,255,0.012) 1px, transparent 1px, transparent 3px),
    linear-gradient(180deg, #12181f, #0c1017);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 22px 22px 26px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.03), 0 12px 30px -14px rgba(0,0,0,0.5);
}

.rack-loading, .rack-empty {
  text-align: center;
  padding: 40px 0;
  color: var(--text-dim);
  font-size: 0.9rem;
}

.group {
  margin-bottom: 22px;
}
.group:last-child {
  margin-bottom: 0;
}

.group-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 1.2px;
  color: var(--text-dimmer);
  text-transform: uppercase;
  margin-bottom: 10px;
}

.group-label .line {
  flex: 1;
  height: 1px;
  background: var(--border-soft);
}

.super-group-label {
  font-size: 13.5px;
  font-weight: 800;
  letter-spacing: 1.5px;
  color: var(--text);
  margin-bottom: 16px;
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.super-group-label.virtual-label {
  margin-top: 36px;
}

.super-group-label .icon {
  opacity: 0.8;
}

.super-group-content {
  margin-left: 6px;
}

.port-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

/* PORT (RJ45 style) */
.port {
  width: 104px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 6px 9px;
  border-radius: 9px;
  border: 1px solid var(--border-soft);
  background: linear-gradient(180deg, #171e28, #10151d);
  transition: transform 0.12s ease, border-color 0.12s ease, box-shadow 0.12s ease;
  position: relative;
}

.port:hover {
  transform: translateY(-2px);
  border-color: #2a3644;
}

.port.selected {
  border-color: var(--cyan);
  box-shadow: 0 0 0 1px rgba(34,211,238,0.35), 0 0 18px rgba(34,211,238,0.18);
  background: linear-gradient(180deg, rgba(34,211,238,0.08), #10151d);
}

/* Jack Graphic */
.jack {
  width: 56px;
  height: 34px;
  position: relative;
  margin-bottom: 6px;
}

.jack-bezel {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, #3a4350, #1c232d);
  border-radius: 3px 3px 6px 6px;
  border: 1px solid #060a0f;
}

.jack-slot {
  position: absolute;
  top: 5px;
  left: 8px;
  right: 8px;
  bottom: 9px;
  background: linear-gradient(180deg, #0a0d12, #04060a);
  border-radius: 2px;
  box-shadow: inset 0 1px 2px rgba(0,0,0,0.6);
}

.jack-pins {
  position: absolute;
  top: 8px;
  left: 11px;
  right: 11px;
  height: 4px;
  display: flex;
  justify-content: space-between;
}

.jack-pins i {
  width: 1.6px;
  background: #c9a24a;
  opacity: 0.85;
}

.jack-tab {
  position: absolute;
  bottom: -3px;
  left: 50%;
  transform: translateX(-50%);
  width: 14px;
  height: 5px;
  background: #0f141b;
  border-radius: 0 0 3px 3px;
  border: 1px solid #060a0f;
  border-top: none;
}

.led {
  position: absolute;
  top: 3px;
  right: 6px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  box-shadow: 0 0 6px currentColor;
}

.led.up { background: var(--green); color: var(--green); }
.led.degraded { background: var(--orange); color: var(--orange); }
.led.down { background: var(--red); color: var(--red); box-shadow: none; }
.led.off { background: #3a4350; color: transparent; box-shadow: none; }

/* SFP Variant */
.jack.sfp .jack-bezel {
  background: linear-gradient(180deg, #2a3a3d, #141d1f);
  border-radius: 4px;
}

.jack.sfp .jack-slot {
  top: 8px;
  bottom: 8px;
  left: 6px;
  right: 6px;
  background: linear-gradient(90deg, #0a1516, #04090a);
}

.jack.sfp .jack-pins { display: none; }
.jack.sfp .jack-tab { display: none; }

.jack.sfp::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 26px;
  height: 3px;
  background: #1e3538;
  border-radius: 2px;
}

/* Virtual Variant */
.jack.virtual .jack-bezel {
  background: linear-gradient(180deg, #242b35, #121820);
  border-radius: 6px;
  border: 1px dashed #3a4350;
}

.jack.virtual .jack-slot {
  top: 10px;
  bottom: 10px;
  left: 10px;
  right: 10px;
  background: #0a0d12;
  border-radius: 4px;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.8);
}

.jack.virtual .jack-pins,
.jack.virtual .jack-tab {
  display: none;
}

.jack.virtual .led {
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  right: auto;
  width: 8px;
  height: 8px;
}

.port-label {
  font-size: 11px;
  font-weight: 700;
  text-align: center;
  line-height: 1.25;
  max-width: 96px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.port-sub {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-top: 3px;
}

.port-tag {
  font-family: var(--mono);
  font-size: 8.5px;
  font-weight: 700;
  color: var(--text-dimmer);
  background: rgba(255, 255, 255, 0.04);
  padding: 1px 5px;
  border-radius: 4px;
}

.port-speed {
  font-family: var(--mono);
  font-size: 9px;
  color: var(--text-dimmer);
  margin-top: 1px;
}

.port.dim .port-label, .port.dim .port-speed {
  color: var(--text-dimmer);
}

.port.dim .jack-bezel {
  opacity: 0.55;
}

</style>

<style>
/* LIVE MONITOR SLIDE PANEL (Unscoped for Teleport) */
.monitor-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(2px);
  z-index: 900;
  animation: fadeIn 0.3s ease;
}

.monitor-panel {
  --bg: #0a0e14;
  --panel: #10161f;
  --panel-2: #0d1219;
  --border: #1e2733;
  --border-soft: #161d27;
  --text: #e8ecf1;
  --text-dim: #8b96a5;
  --text-dimmer: #5c6774;
  --teal: #2dd4bf;
  --cyan: #22d3ee;
  --green: #22c55e;
  --orange: #f5a623;
  --red: #ef4444;
  --mono: 'JetBrains Mono', 'SF Mono', Consolas, monospace;
  --sans: 'Inter', -apple-system, sans-serif;

  position: fixed;
  top: 0;
  right: 0;
  width: 400px;
  height: 100vh;
  height: 100dvh;
  background: #10161f;
  color: var(--text);
  font-family: var(--sans);
  border-left: 1px solid rgba(34, 211, 238, 0.2);
  box-shadow: -10px 0 30px rgba(0,0,0,0.5);
  z-index: 910;
  transform: translateX(100%);
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
}

.monitor-panel.is-open {
  transform: translateX(0);
}

.monitor-panel-content {
  padding: 24px;
  overflow-y: auto;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 24px;
  background: linear-gradient(160deg, rgba(34,211,238,0.03), transparent 30%);
}

.monitor-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.btn-close-monitor {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: var(--text-dim);
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
}
.btn-close-monitor:hover {
  background: rgba(239, 68, 68, 0.15);
  color: var(--red);
  border-color: rgba(239, 68, 68, 0.3);
}

.monitor-subhead {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: -10px;
}

.monitor-title {
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.monitor-title .name {
  color: var(--cyan);
}

.link-up {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(34,197,94,0.12);
  border: 1px solid rgba(34,197,94,0.4);
  color: var(--green);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.3px;
  padding: 5px 11px;
  border-radius: 20px;
}

.link-up--active {
  background: rgba(34, 197, 94, 0.12);
  border-color: rgba(34, 197, 94, 0.4);
  color: var(--green);
}

.link-up--down {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.4);
  color: var(--red);
}

.link-up .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 6px var(--green);
}

.link-up--down .dot {
  background: var(--red);
  box-shadow: none;
}

.mac {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--text-dimmer);
}

.traffic-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.traffic-card {
  background: var(--panel-2);
  border: 1px solid var(--border-soft);
  border-radius: 10px;
  padding: 14px 16px;
}

.traffic-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.7px;
  color: var(--text-dim);
  text-transform: uppercase;
  margin-bottom: 8px;
}

.traffic-value {
  font-family: var(--mono);
  font-size: 26px;
  font-weight: 700;
}

.traffic-value.rx { color: var(--cyan); }
.traffic-value.tx { color: var(--orange); }

.traffic-value .unit {
  font-size: 15px;
  color: var(--text-dimmer);
}

.traffic-pkts {
  font-size: 11px;
  color: var(--text-dimmer);
  margin-top: 2px;
}

.spark {
  margin-top: 8px;
  display: block;
}

.meta-row {
  display: flex;
  gap: 28px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--border-soft);
  font-size: 12px;
  color: var(--text-dim);
}

.meta-row b {
  color: var(--text);
  font-family: var(--mono);
  font-weight: 700;
}

.fade-in {
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@media (max-width: 768px) {
  .monitor-panel {
    top: auto;
    bottom: 0;
    right: 0;
    width: 100vw;
    height: 70vh;
    border-left: none;
    border-top: 1px solid rgba(34, 211, 238, 0.2);
    border-radius: 20px 20px 0 0;
    transform: translateY(100%);
  }
  .monitor-panel.is-open {
    transform: translateY(0);
  }
}
</style>
