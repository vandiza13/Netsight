<template>
  <div class="network-list-view premium-card panel-full fade-in">
    <div class="table-container custom-scrollbar">
      <table class="premium-table">
        <thead>
          <tr>
            <th style="width: 5%;">TIPE</th>
            <th style="width: 25%;">NAMA / KODE</th>
            <th style="width: 20%;">UPLINK PARENT</th>
            <th style="width: 15%;">STATUS LIVE</th>
            <th style="width: 15%;">PORT TERPAKAI</th>
            <th style="width: 20%; text-align: right;">AKSI</th>
          </tr>
        </thead>
        <tbody v-if="store.loading && store.nodes.length === 0">
          <tr>
            <td colspan="6" class="text-center py-8">
              <svg class="spinning text-muted mx-auto" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
              <p class="text-muted mt-2">Memuat data...</p>
            </td>
          </tr>
        </tbody>
        <tbody v-else-if="store.nodes.length === 0">
          <tr>
            <td colspan="6">
              <EmptyState title="Tidak ada node ditemukan" message="Coba ubah filter pencarian." />
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="node in store.nodes" :key="node.id">
            <!-- TYPE ICON -->
            <td>
              <div class="type-icon" :class="`icon-${node.type}`" :title="node.type.toUpperCase()">
                <svg v-if="node.type === 'server'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <svg v-if="node.type === 'olt'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>
                <svg v-if="node.type === 'odc'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>
                <svg v-if="node.type === 'odp'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <svg v-if="node.type === 'ont'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
              </div>
            </td>
            
            <!-- NAME & CODE -->
            <td>
              <div class="fw-bold">{{ node.name }}</div>
              <div class="text-xs text-muted">{{ node.code || '-' }}</div>
            </td>
            
            <!-- UPLINK -->
            <td>
              <div v-if="node.parent" class="flex items-center gap-2">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                <div>
                  <span class="text-sm block">{{ node.parent.name }}</span>
                  <span class="text-xs text-muted" v-if="node.port_on_parent">Port {{ node.port_on_parent }}</span>
                </div>
              </div>
              <span v-else class="text-muted text-xs italic">Root (Tidak ada)</span>
            </td>

            <!-- STATUS LIVE -->
            <td>
              <span class="badge" :class="`badge--${node.status_live}`">
                {{ node.status_live?.toUpperCase() }}
              </span>
            </td>

            <!-- PORT CAPACITY -->
            <td>
              <div v-if="['odc', 'odp'].includes(node.type)">
                <div class="flex justify-between text-xs mb-1">
                  <span>{{ node.used_ports }}/{{ node.total_ports }}</span>
                  <span>{{ Math.round((node.used_ports / node.total_ports) * 100) }}%</span>
                </div>
                <div class="progress-bar-bg" style="height: 4px; background: var(--bg-tertiary); border-radius: 2px;">
                  <div class="progress-bar-fill" :style="{ width: `${(node.used_ports / node.total_ports) * 100}%`, height: '100%', background: 'var(--accent-cyan)' }"></div>
                </div>
              </div>
              <span v-else class="text-muted text-xs">-</span>
            </td>

            <!-- ACTIONS -->
            <td class="text-right">
              <div class="flex items-center justify-end gap-2">
                <button class="btn-icon" @click="viewOnMap(node.id)" title="Lihat di Peta">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                </button>
                <button class="btn-icon" @click="$emit('edit', node)" title="Edit">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </button>
                <button class="btn-icon text-red" @click="deleteNode(node.id)" title="Hapus">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useNetworkMapStore } from '../../stores/networkMapStore'
import EmptyState from '../EmptyState.vue'

const store = useNetworkMapStore()

onMounted(() => {
  store.fetchNodes()
})

const viewOnMap = (id: number) => {
  store.selectNode(id)
  store.setTab('map')
}

const deleteNode = async (id: number) => {
  if (confirm('Hapus node ini secara permanen?')) {
    try {
      await store.deleteNode(id)
      store.fetchNodes() // Refresh list
    } catch (e: any) {
      alert(e.message)
    }
  }
}

defineEmits(['edit'])
</script>

<style scoped>
.network-list-view {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 200px); /* Fill remaining space */
}

.table-container {
  flex: 1;
  overflow: auto;
}

.type-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-tertiary);
}

.icon-server { color: #3b82f6; }
.icon-olt { color: #06b6d4; }
.icon-odc { color: #8b5cf6; }
.icon-odp { color: #f59e0b; }
.icon-ont { color: #10b981; }

.btn-icon {
  background: transparent;
  border: none;
  width: 28px;
  height: 28px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-3);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: var(--bg-tertiary);
  color: var(--text-1);
}

.btn-icon.text-red:hover {
  color: var(--accent-red);
  background: rgba(239, 68, 68, 0.1);
}
</style>
