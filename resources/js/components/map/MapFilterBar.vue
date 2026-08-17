<template>
  <div class="map-filter-bar flex-header">
    <div class="header-action-buttons">
      <!-- Search Input -->
      <div class="search-wrap search-wrap--header">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input
          v-model="store.filters.search"
          @keyup.enter="handleSearch"
          type="text"
          placeholder="Cari perangkat..."
          class="input-modern search-input"
        >
      </div>

      <!-- Filter Tipe -->
      <select v-model="store.filters.type" class="input-modern" @change="handleSearch">
        <option value="all">Semua Tipe</option>
        <option value="server">Server</option>
        <option value="olt">OLT</option>
        <option value="odc">ODC</option>
        <option value="odp">ODP</option>
        <option value="ont">ONT</option>
      </select>

      <!-- Filter Status -->
      <select v-model="store.filters.status" class="input-modern" @change="handleSearch">
        <option value="all">Semua Status</option>
        <option value="active">Active / Online</option>
        <option value="warning">Warning / dBm Tinggi</option>
        <option value="critical">Critical / LOS</option>
        <option value="offline">Offline</option>
      </select>
      
      <!-- Layer Toggle (Dropup) -->
      <div class="layer-toggle">
        <button class="btn btn-secondary layer-btn" @click="showLayers = !showLayers">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
          Layers
        </button>
        <div v-if="showLayers" class="layer-menu">
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.server"> Server NOC</label>
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.olt"> OLT</label>
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.odc"> ODC</label>
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.odp"> ODP</label>
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.ont"> ONT / Pelanggan</label>
          <label class="layer-item"><input type="checkbox" v-model="store.filters.layers.lines"> Jalur Fiber</label>
        </div>
      </div>
      
      <!-- View Switcher -->
      <div class="view-switcher">
        <button 
          class="switch-btn" 
          :class="{ active: store.activeTab === 'map' }" 
          @click="store.setTab('map')"
          title="Map View"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
        </button>
        <button 
          class="switch-btn" 
          :class="{ active: store.activeTab === 'list' }" 
          @click="store.setTab('list')"
          title="List View"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        </button>
      </div>

      <!-- Add Buttons -->
      <div class="action-divider"></div>
      
      <button class="btn btn-primary" @click="$emit('addNode')" title="Tambah Titik (Node)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/><line x1="12" y1="7" x2="12" y2="13"/><line x1="9" y1="10" x2="15" y2="10"/></svg>
      </button>
      <button class="btn btn-primary" @click="$emit('addLine')" title="Tarik Kabel Fiber">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useNetworkMapStore } from '../../stores/networkMapStore'

const store = useNetworkMapStore()
const showLayers = ref(false)

const handleSearch = () => {
  if (store.activeTab === 'list') {
    store.fetchNodes()
    store.fetchLines()
  } else {
    // For map, local search filtering is done automatically via watcher
  }
}

defineEmits(['addNode', 'addLine'])
</script>

<style scoped>
.map-filter-bar {
  background: var(--surface-1);
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid var(--border);
  margin-bottom: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.header-action-buttons {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 10px;
  color: var(--text-3);
}

.search-input {
  padding-left: 36px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-1);
  border-radius: 6px;
  height: 36px;
  width: 200px;
}

.input-modern {
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-1);
  border-radius: 6px;
  height: 36px;
  padding: 0 12px;
}

.layer-toggle {
  position: relative;
}

.layer-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  height: 36px;
}

.layer-menu {
  position: absolute;
  top: 100%;
  left: 0;
  margin-top: 8px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px;
  min-width: 180px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  box-shadow: 0 10px 25px rgba(0,0,0,0.5);
  z-index: 50;
}

.layer-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-2);
  padding: 6px 8px;
  cursor: pointer;
  border-radius: 4px;
}

.layer-item:hover {
  background: var(--surface-1);
  color: var(--text-1);
}

.view-switcher {
  display: flex;
  background: var(--surface-2);
  border-radius: 6px;
  padding: 2px;
  border: 1px solid var(--border);
  height: 36px;
}

.switch-btn {
  background: transparent;
  border: none;
  padding: 0 12px;
  color: var(--text-3);
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.switch-btn:hover {
  color: var(--text-1);
}

.switch-btn.active {
  background: var(--surface-1);
  color: var(--accent);
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.action-divider {
  width: 1px;
  height: 24px;
  background: var(--border);
  margin: 0 4px;
}
</style>
