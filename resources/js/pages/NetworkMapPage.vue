<template>
  <div class="dashboard">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content map-page-layout">
        <div class="map-page-header">
          <MapFilterBar 
            @addNode="showNodeModal = true"
            @addLine="showLineModal = true"
          />
        </div>

        <div class="map-page-body" v-show="store.activeTab === 'map'">
          <MapCanvas />
          <MapNodeDrawer @edit="openEditNodeModal" />
        </div>

        <div class="map-page-body" v-show="store.activeTab === 'list'">
          <NetworkListView @edit="openEditNodeModal" />
        </div>
      </main>
    </div>

    <!-- Modals -->
    <MapNodeModal 
      :show="showNodeModal" 
      :edit-node="nodeToEdit" 
      @close="closeNodeModal" 
    />
    
    <MapLineModal 
      :show="showLineModal" 
      :edit-line="lineToEdit" 
      @close="closeLineModal" 
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import MapCanvas from '../components/map/MapCanvas.vue'
import MapFilterBar from '../components/map/MapFilterBar.vue'
import MapNodeDrawer from '../components/map/MapNodeDrawer.vue'
import MapNodeModal from '../components/map/MapNodeModal.vue'
import MapLineModal from '../components/map/MapLineModal.vue'
import NetworkListView from '../components/map/NetworkListView.vue'
import { useNetworkMapStore } from '../stores/networkMapStore'
import type { NetworkNode, FiberLine } from '../stores/networkMapStore'

const sidebarOpen = ref(false)
const store = useNetworkMapStore()

const showNodeModal = ref(false)
const showLineModal = ref(false)
const nodeToEdit = ref<NetworkNode | null>(null)
const lineToEdit = ref<FiberLine | null>(null)

onMounted(() => {
  store.fetchGeoJson()
  store.fetchStats()
  store.fetchNodes()
  store.fetchAllNodesForParent()
})

onUnmounted(() => {
  store.selectNode(null)
})

const openEditNodeModal = (node: NetworkNode) => {
  nodeToEdit.value = node
  showNodeModal.value = true
}

const closeNodeModal = () => {
  showNodeModal.value = false
  setTimeout(() => { nodeToEdit.value = null }, 300)
}

const openEditLineModal = (line: FiberLine) => {
  lineToEdit.value = line
  showLineModal.value = true
}

const closeLineModal = () => {
  showLineModal.value = false
  setTimeout(() => { lineToEdit.value = null }, 300)
}
</script>

<style scoped>
.dashboard {
  display: flex;
  min-height: 100vh;
  background: var(--bg-primary);
}

.dashboard__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.dashboard__content {
  height: calc(100vh - 64px);
  overflow: hidden;
}

.map-page-layout {
  display: flex;
  flex-direction: column;
  height: 100%;
  position: relative;
  padding: 0;
  margin: 0;
}

.map-page-header {
  padding: 16px;
  flex-shrink: 0;
}

.map-page-body {
  flex: 1;
  position: relative;
  margin: 0 16px 16px 16px;
  display: flex;
  flex-direction: column;
  min-height: 0; /* Important for flex children to scroll */
}
</style>
