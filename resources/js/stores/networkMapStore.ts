import { defineStore } from 'pinia'
import api from '../utils/api'

export interface NetworkNode {
  id: number
  name: string
  code: string | null
  type: 'server' | 'olt' | 'odc' | 'odp' | 'ont'
  parent_id: number | null
  port_on_parent: number | null
  latitude: number | null
  longitude: number | null
  address: string | null
  total_ports: number
  used_ports: number
  status: 'active' | 'warning' | 'critical' | 'offline' | 'maintenance'
  status_live?: string
  olt_id?: number | null
  olt_onu_id?: number | null
  acs_device_id?: number | null
  router_id?: number | null
  notes?: string | null
  metadata?: any
  parent?: NetworkNode
  port_slots?: Record<number, any>
}

export interface FiberLine {
  id: number
  name: string
  code: string | null
  cable_type: 'backbone' | 'feeder' | 'distribution' | 'drop'
  source_node_id: number | null
  target_node_id: number | null
  core_count: number
  used_cores: number
  length_meters: number | null
  coordinates: [number, number][] | null
  color: string | null
  status: 'active' | 'damaged' | 'maintenance' | 'planned'
  notes?: string | null
}

export const useNetworkMapStore = defineStore('networkMap', {
  state: () => ({
    loading: false,
    error: null as string | null,
    stats: null as any,
    nodes: [] as NetworkNode[],
    lines: [] as FiberLine[],
    geoJsonData: null as any,
    unmappedDevices: null as any,
    
    // View state
    activeTab: 'map' as 'map' | 'list',
    mapMode: 'view' as 'view' | 'add_node' | 'draw_line' | 'measure',
    selectedNodeId: null as number | null,
    
    // Filters
    filters: {
      search: '',
      type: 'all',
      status: 'all',
      layers: {
        server: true,
        olt: true,
        odc: true,
        odp: true,
        ont: true,
        lines: true
      }
    }
  }),

  actions: {
    async fetchStats() {
      try {
        const { data } = await api.get('/network-map/stats')
        this.stats = data
      } catch (e: any) {
        console.error('Failed to fetch map stats', e)
      }
    },

    async fetchUnmappedDevices(query: string = '') {
      try {
        const { data } = await api.get('/network-map/unmapped', { params: { q: query } })
        this.unmappedDevices = data
      } catch (e: any) {
        console.error('Failed to fetch unmapped devices', e)
      }
    },

    async fetchGeoJson() {
      this.loading = true
      try {
        const { data } = await api.get('/network-map/geojson')
        this.geoJsonData = data
      } catch (e: any) {
        this.error = e.response?.data?.message || 'Gagal memuat data peta'
      } finally {
        this.loading = false
      }
    },

    async fetchNodes(page = 1) {
      this.loading = true
      try {
        const { data } = await api.get('/network-map/nodes', {
          params: {
            page,
            type: this.filters.type,
            search: this.filters.search
          }
        })
        this.nodes = data.data
        return data
      } catch (e: any) {
        this.error = e.response?.data?.message || 'Gagal memuat list node'
        return null
      } finally {
        this.loading = false
      }
    },

    async fetchLines(page = 1) {
      this.loading = true
      try {
        const { data } = await api.get('/network-map/lines', {
          params: {
            page,
            search: this.filters.search
          }
        })
        return data
      } catch (e: any) {
        this.error = e.response?.data?.message || 'Gagal memuat list kabel'
        return null
      } finally {
        this.loading = false
      }
    },

    async getNodeDetail(id: number) {
      this.loading = true
      try {
        const { data } = await api.get(`/network-map/nodes/${id}`)
        return data
      } catch (e: any) {
        this.error = e.response?.data?.message || 'Gagal memuat detail node'
        return null
      } finally {
        this.loading = false
      }
    },

    async createNode(payload: any) {
      this.loading = true
      try {
        const { data } = await api.post('/network-map/nodes', payload)
        this.fetchGeoJson()
        this.fetchStats()
        return data
      } catch (e: any) {
        throw new Error(e.response?.data?.message || 'Gagal menyimpan node')
      } finally {
        this.loading = false
      }
    },

    async updateNode(id: number, payload: any) {
      this.loading = true
      try {
        const { data } = await api.put(`/network-map/nodes/${id}`, payload)
        this.fetchGeoJson()
        return data
      } catch (e: any) {
        throw new Error(e.response?.data?.message || 'Gagal update node')
      } finally {
        this.loading = false
      }
    },

    async deleteNode(id: number) {
      this.loading = true
      try {
        await api.delete(`/network-map/nodes/${id}`)
        if (this.selectedNodeId === id) this.selectedNodeId = null
        this.fetchGeoJson()
        this.fetchStats()
      } catch (e: any) {
        throw new Error(e.response?.data?.message || 'Gagal hapus node')
      } finally {
        this.loading = false
      }
    },

    async createLine(payload: any) {
      this.loading = true
      try {
        const { data } = await api.post('/network-map/lines', payload)
        this.fetchGeoJson()
        this.fetchStats()
        return data
      } catch (e: any) {
        throw new Error(e.response?.data?.message || 'Gagal menyimpan jalur')
      } finally {
        this.loading = false
      }
    },
    
    setTab(tab: 'map' | 'list') {
      this.activeTab = tab
    },
    
    setMapMode(mode: 'view' | 'add_node' | 'draw_line' | 'measure') {
      this.mapMode = mode
    },
    
    selectNode(id: number | null) {
      this.selectedNodeId = id
    }
  }
})
