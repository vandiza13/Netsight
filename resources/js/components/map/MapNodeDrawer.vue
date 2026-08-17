<template>
  <div class="drawer" :class="{ 'drawer--open': !!store.selectedNodeId }">
    <div class="drawer__header">
      <div class="flex items-center gap-3">
        <div class="drawer__icon" :class="`icon-${node?.type}`">
          <!-- Server SVG -->
          <svg v-if="node?.type === 'server'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
          <!-- OLT SVG -->
          <svg v-if="node?.type === 'olt'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
          <!-- ODC SVG -->
          <svg v-if="node?.type === 'odc'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>
          <!-- ODP SVG -->
          <svg v-if="node?.type === 'odp'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
          <!-- ONT SVG -->
          <svg v-if="node?.type === 'ont'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
        </div>
        <div>
          <h3 class="drawer__title">{{ node?.name || 'Loading...' }}</h3>
          <p class="drawer__subtitle">{{ node?.code || node?.type?.toUpperCase() }}</p>
        </div>
      </div>
      <button class="drawer__close" @click="closeDrawer" title="Tutup">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="drawer__content custom-scrollbar" v-if="node && !store.loading">
      <!-- Status Badge -->
      <div class="drawer__section flex items-center justify-between">
        <span class="text-xs text-muted">Status Live</span>
        <span class="badge" :class="`badge--${node.status_live}`">
          <svg v-if="node.status_live === 'active'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          <svg v-else-if="node.status_live === 'offline' || node.status_live === 'critical'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          {{ node.status_live?.toUpperCase() }}
        </span>
      </div>

      <!-- Capacity Info (For ODC/ODP) -->
      <div class="drawer__section" v-if="['odc', 'odp'].includes(node.type)">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm fw-bold">Kapasitas Port ({{ node.used_ports }}/{{ node.total_ports }})</span>
          <span class="text-xs text-muted">{{ Math.round((node.used_ports / node.total_ports) * 100) }}% Terisi</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" :style="{ width: `${(node.used_ports / node.total_ports) * 100}%` }"></div>
        </div>
      </div>

      <!-- Live Device Link (OLT/ONU/ACS) -->
      <div class="drawer__section" v-if="node.oltOnu || node.acsDevice">
        <div class="device-card">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-muted">Linked Device</span>
            <span v-if="node.oltOnu" class="badge badge--cyan">OLT ONU</span>
            <span v-if="node.acsDevice" class="badge badge--purple">GenieACS</span>
          </div>
          <p class="text-sm fw-bold">{{ node.acsDevice?.pppoe_username || node.oltOnu?.pppoe_username || 'Unknown' }}</p>
          <div class="flex items-center gap-4 mt-2">
            <div class="signal-box">
              <span class="text-xs text-muted block">Optical Rx</span>
              <span class="text-sm fw-bold" :class="{'text-red': getSignal(node) < -25}">{{ getSignal(node) }} dBm</span>
            </div>
            <div class="signal-box">
              <span class="text-xs text-muted block">Distance</span>
              <span class="text-sm fw-bold">{{ node.oltOnu?.distance_meters || '-' }} m</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Port Slots Grid (ODC/ODP) -->
      <div class="drawer__section" v-if="['odc', 'odp'].includes(node.type) && node.port_slots">
        <h4 class="text-sm fw-bold mb-3 flex items-center gap-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><path d="M6 8h.01"/><path d="M10 8h.01"/><path d="M14 8h.01"/><path d="M18 8h.01"/><path d="M6 16h.01"/><path d="M10 16h.01"/><path d="M14 16h.01"/><path d="M18 16h.01"/></svg>
          Slot Port Splitter
        </h4>
        <div class="port-grid">
          <div v-for="portNum in node.total_ports" :key="portNum" class="port-slot" :class="{ 'port-slot--empty': !node.port_slots[portNum] }">
            <div class="port-num">{{ portNum }}</div>
            <div class="port-info" v-if="node.port_slots[portNum]">
              <span class="port-name">{{ node.port_slots[portNum].name }}</span>
              <span class="port-status" :class="`text-${getStatusColor(node.port_slots[portNum].status)}`">● {{ node.port_slots[portNum].status }}</span>
            </div>
            <div class="port-info" v-else>
              <span class="text-muted text-xs italic">Kosong</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Connected Lines -->
      <div class="drawer__section" v-if="node.incoming_lines?.length || node.outgoing_lines?.length">
        <h4 class="text-sm fw-bold mb-3 flex items-center gap-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Kabel Terhubung
        </h4>
        <div class="line-list">
          <div v-for="line in node.incoming_lines" :key="'in-'+line.id" class="line-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
            <div class="flex-1">
              <p class="text-xs fw-bold">{{ line.name }}</p>
              <p class="text-xs text-muted">Dari: {{ line.source_node?.name }} ({{ line.core_count }} Core)</p>
            </div>
          </div>
          <div v-for="line in node.outgoing_lines" :key="'out-'+line.id" class="line-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            <div class="flex-1">
              <p class="text-xs fw-bold">{{ line.name }}</p>
              <p class="text-xs text-muted">Ke: {{ line.target_node?.name }} ({{ line.core_count }} Core)</p>
            </div>
          </div>
        </div>
      </div>

    </div>
    
    <div class="drawer__footer" v-if="node && !store.loading">
      <button class="btn btn-secondary flex-1" @click="$emit('edit', node)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        Edit
      </button>
      <button class="btn btn-danger" @click="deleteNode(node.id)" title="Hapus Node">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </button>
    </div>
    
    <div class="drawer__loader" v-if="store.loading && store.selectedNodeId">
      <svg class="spinning" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useNetworkMapStore } from '../../stores/networkMapStore'

const store = useNetworkMapStore()

const node = computed(() => {
  return store.nodes.find(n => n.id === store.selectedNodeId) || null
})

const closeDrawer = () => {
  store.selectNode(null)
}

const getSignal = (n: any) => {
  if (n.acsDevice) return n.acsDevice.rx_power_dbm
  if (n.oltOnu) return n.oltOnu.rx_power_dbm
  return '-'
}

const getStatusColor = (status: string) => {
  if (status === 'active') return 'green'
  if (status === 'warning') return 'amber'
  if (status === 'critical' || status === 'offline' || status === 'los') return 'red'
  return 'cyan'
}

const deleteNode = async (id: number) => {
  if (confirm('Hapus node ini? Aksi ini tidak dapat dibatalkan.')) {
    try {
      await store.deleteNode(id)
    } catch (e: any) {
      alert(e.message)
    }
  }
}

defineEmits(['edit'])
</script>

<style scoped>
.drawer {
  position: absolute;
  top: 16px;
  left: 16px;
  bottom: 16px;
  width: 320px;
  background: var(--bg-primary);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
  transform: translateX(-150%);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 500;
  overflow: hidden;
}

.drawer--open {
  transform: translateX(0);
}

.drawer__header {
  padding: 16px;
  border-bottom: 1px solid var(--border-color);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  background: var(--bg-secondary);
}

.drawer__icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--bg-tertiary);
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon-server { color: #3b82f6; }
.icon-olt { color: #06b6d4; }
.icon-odc { color: #8b5cf6; }
.icon-odp { color: #f59e0b; }
.icon-ont { color: #10b981; }

.drawer__title {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-1);
  margin-bottom: 2px;
}

.drawer__subtitle {
  font-size: 12px;
  color: var(--text-3);
}

.drawer__close {
  background: transparent;
  border: none;
  color: var(--text-3);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.drawer__close:hover {
  background: var(--bg-tertiary);
  color: var(--text-1);
}

.drawer__content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.drawer__section {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 12px;
}

.progress-bar-bg {
  height: 6px;
  background: var(--bg-tertiary);
  border-radius: 3px;
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: var(--accent-cyan);
  transition: width 0.3s;
}

.device-card {
  background: var(--bg-tertiary);
  border-radius: 6px;
  padding: 10px;
  border: 1px solid rgba(255,255,255,0.05);
}

.signal-box {
  background: var(--bg-primary);
  padding: 6px 10px;
  border-radius: 4px;
  border: 1px solid var(--border-color);
  flex: 1;
}

.port-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 6px;
}

.port-slot {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--bg-tertiary);
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--border-color);
}

.port-slot--empty {
  opacity: 0.6;
  border-style: dashed;
}

.port-num {
  width: 20px;
  height: 20px;
  background: var(--bg-primary);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: bold;
  color: var(--text-2);
}

.port-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.port-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-1);
}

.port-status {
  font-size: 10px;
}

.line-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.line-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border-color);
}
.line-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.drawer__footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border-color);
  display: flex;
  gap: 8px;
  background: var(--bg-secondary);
}

.drawer__loader {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}
</style>
