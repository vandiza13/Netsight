<template>
  <div class="map-canvas-container">
    <div id="netsight-leaflet-map" ref="mapRef" class="h-full w-full"></div>
    
    <!-- Floating controls -->
    <div class="map-controls-overlay">
      <button class="map-control-btn" @click="zoomIn" title="Zoom In">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <button class="map-control-btn" @click="zoomOut" title="Zoom Out">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
      <button class="map-control-btn" @click="locateUser" title="Lokasi Saya" :class="{ 'active': locating }">
        <svg :class="{ 'spinning': locating }" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
      <div class="map-control-separator"></div>
      <button class="map-control-btn" @click="toggleTheme" title="Ganti Tema Peta">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useNetworkMapStore } from '../../stores/networkMapStore'

const mapRef = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let geoJsonLayer: L.GeoJSON | null = null

const store = useNetworkMapStore()
const locating = ref(false)
const currentTheme = ref('dark') // 'dark', 'street', 'satellite'

// --- SVG Icons Definition ---
const SVG_ICONS = {
  server: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>`,
  olt: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#06b6d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>`,
  odc: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>`,
  odp: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>`,
  ont: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>`
}

const getStatusColor = (status: string) => {
  if (status === 'active') return '#10b981'
  if (status === 'warning') return '#f59e0b'
  if (status === 'critical' || status === 'offline' || status === 'los') return '#ef4444'
  if (status === 'maintenance') return '#6b7280'
  return '#3b82f6'
}

const createCustomIcon = (feature: any) => {
  const props = feature.properties
  const type = props.type as keyof typeof SVG_ICONS
  const statusColor = getStatusColor(props.status)
  
  // Custom wrapper with halo
  const html = `
    <div class="custom-map-marker type-${type} status-${props.status}">
      <div class="marker-halo" style="border-color: ${statusColor}"></div>
      <div class="marker-icon" style="color: ${statusColor}">
        ${SVG_ICONS[type] || SVG_ICONS.ont}
      </div>
      ${props.type === 'odp' ? `<div class="marker-badge">${props.used_ports}/${props.total_ports}</div>` : ''}
    </div>
  `

  return L.divIcon({
    html,
    className: 'transparent-leaflet-icon',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -16]
  })
}

// --- Init Map ---
onMounted(() => {
  if (!mapRef.value) return

  map = L.map(mapRef.value, {
    zoomControl: false,
    attributionControl: false
  }).setView([-6.200000, 106.816666], 12)

  setTileLayer()

  // Load geojson data
  if (store.geoJsonData) {
    renderGeoJson(store.geoJsonData)
  }
})

watch(() => store.geoJsonData, (newData) => {
  if (newData) renderGeoJson(newData)
}, { deep: true })

// Filter watch
watch(() => store.filters, () => {
  if (store.geoJsonData) renderGeoJson(store.geoJsonData)
}, { deep: true })

// Selection watch
watch(() => store.selectedNodeId, (id) => {
  if (id && geoJsonLayer) {
    geoJsonLayer.eachLayer((layer: any) => {
      if (layer.feature?.properties?.entity === 'node' && layer.feature?.properties?.id === id) {
        if (layer.getLatLng) {
          map?.flyTo(layer.getLatLng(), 17, { duration: 0.5 })
          layer.openPopup()
        }
      }
    })
  }
})

const setTileLayer = () => {
  if (!map) return
  
  // Remove existing layers
  map.eachLayer((layer) => {
    if (layer instanceof L.TileLayer) {
      map?.removeLayer(layer)
    }
  })

  let url = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png' // Dark Matter
  if (currentTheme.value === 'street') {
    url = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
  }

  L.tileLayer(url, {
    maxZoom: 20
  }).addTo(map)
}

const toggleTheme = () => {
  currentTheme.value = currentTheme.value === 'dark' ? 'street' : 'dark'
  setTileLayer()
}

const renderGeoJson = (data: any) => {
  if (!map) return

  if (geoJsonLayer) {
    map.removeLayer(geoJsonLayer)
  }

  geoJsonLayer = L.geoJSON(data, {
    filter: (feature) => {
      const props = feature.properties
      // Apply filters
      if (props.entity === 'node') {
        if (!store.filters.layers[props.type as keyof typeof store.filters.layers]) return false
        if (store.filters.status !== 'all' && props.status !== store.filters.status) return false
      }
      if (props.entity === 'line') {
        if (!store.filters.layers.lines) return false
        if (store.filters.status !== 'all' && props.status !== store.filters.status) return false
      }
      return true
    },
    pointToLayer: (feature, latlng) => {
      return L.marker(latlng, { icon: createCustomIcon(feature) })
    },
    style: (feature) => {
      if (feature?.properties.entity === 'line') {
        const type = feature.properties.cable_type
        let color = feature.properties.color || '#3b82f6'
        let weight = 3
        let dashArray = ''

        if (type === 'feeder' || type === 'backbone') { weight = 5; color = '#06b6d4' }
        if (type === 'distribution') { weight = 4; color = '#8b5cf6' }
        if (type === 'drop') { weight = 2; color = '#f59e0b' }
        
        if (feature.properties.status === 'damaged') {
          color = '#ef4444'
          dashArray = '5, 10'
        }

        return { color, weight, opacity: 0.8, dashArray }
      }
      return {}
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties
      if (p.entity === 'node') {
        const tooltipHTML = `<b>${p.name}</b><br><span style="font-size:11px;color:#888">${p.code || p.type.toUpperCase()}</span>`
        layer.bindTooltip(tooltipHTML)
        
        layer.on('click', () => {
          store.selectNode(p.id)
          store.getNodeDetail(p.id)
        })
      }
    }
  }).addTo(map)

  // Only fit bounds if we're not currently focusing on a node and not drawing
  if (!store.selectedNodeId && mapModeIsView()) {
    const bounds = geoJsonLayer.getBounds()
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 })
    }
  }
}

const mapModeIsView = () => store.mapMode === 'view'

const zoomIn = () => map?.zoomIn()
const zoomOut = () => map?.zoomOut()
const locateUser = () => {
  if (!map) return
  locating.value = true
  map.locate({ setView: true, maxZoom: 18 })
  map.once('locationfound', (e) => {
    locating.value = false
    L.circleMarker(e.latlng, { radius: 8, color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: 0.5 }).addTo(map!)
  })
  map.once('locationerror', () => {
    locating.value = false
    alert('Akses lokasi ditolak atau tidak tersedia.')
  })
}

onUnmounted(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<style>
.map-canvas-container {
  position: relative;
  width: 100%;
  height: 100%;
  background-color: var(--bg-primary);
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border-color);
}

.transparent-leaflet-icon {
  background: transparent !important;
  border: none !important;
}

.custom-map-marker {
  position: relative;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-secondary);
  border-radius: 50%;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
  transition: transform 0.2s;
  z-index: 10;
}

.custom-map-marker:hover {
  transform: scale(1.1);
  z-index: 100 !important;
}

.marker-halo {
  position: absolute;
  top: -4px;
  left: -4px;
  right: -4px;
  bottom: -4px;
  border: 2px solid;
  border-radius: 50%;
  opacity: 0.5;
  animation: pulse-ring 2s infinite cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes pulse-ring {
  0% { transform: scale(0.8); opacity: 0.8; }
  100% { transform: scale(1.4); opacity: 0; }
}

.marker-icon {
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
}

.marker-badge {
  position: absolute;
  bottom: -6px;
  right: -6px;
  background: var(--bg-tertiary);
  color: var(--text-primary);
  font-size: 10px;
  font-weight: 700;
  padding: 2px 4px;
  border-radius: 4px;
  border: 1px solid var(--border-color);
  z-index: 3;
}

/* Controls */
.map-controls-overlay {
  position: absolute;
  top: 16px;
  right: 16px;
  z-index: 400;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--bg-secondary);
  padding: 8px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.map-control-btn {
  background: transparent;
  border: none;
  color: var(--text-2);
  width: 32px;
  height: 32px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}

.map-control-btn:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.map-control-separator {
  height: 1px;
  background: var(--border-color);
  margin: 4px 0;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Dark mode overrides for Leaflet popups */
.leaflet-popup-content-wrapper, .leaflet-popup-tip {
  background: var(--bg-secondary) !important;
  color: var(--text-primary) !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
  border: 1px solid var(--border-color);
}
.leaflet-container a.leaflet-popup-close-button {
  color: var(--text-3) !important;
}
.leaflet-tooltip {
  background: var(--bg-secondary) !important;
  border: 1px solid var(--border-color) !important;
  color: var(--text-primary) !important;
  box-shadow: 0 2px 6px rgba(0,0,0,0.3) !important;
}
.leaflet-tooltip-top:before, 
.leaflet-tooltip-bottom:before, 
.leaflet-tooltip-left:before, 
.leaflet-tooltip-right:before {
  border-color: transparent !important;
}
</style>
