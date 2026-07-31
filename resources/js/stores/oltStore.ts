import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/api'

export interface OltProfile {
  vendor: string
  code: string
}

export interface Olt {
  id: number
  name: string
  ip_address: string
  snmp_port: number
  snmp_community: string
  technology: string
  vendor_code: string
  status: string
  total_pons: number
  notes: string | null
  onus_count?: number
  onus_online?: number
  onus_offline?: number
  onus_los?: number
}

export interface OltOnu {
  id: number
  olt_id: number
  pon_port: string
  onu_index: number
  serial_number: string
  mac_address: string
  onu_description: string | null
  customer_name: string | null
  pppoe_username: string | null
  status: 'online' | 'offline' | 'los'
  rx_power_dbm: number | null
  tx_power_dbm: number | null
  distance_meters: number | null
  last_updated_at: string | null
}

export const useOltStore = defineStore('olts', () => {
  const olts = ref<Olt[]>([])
  const profiles = ref<OltProfile[]>([])
  const selectedOlt = ref<Olt | null>(null)
  
  const onus = ref<OltOnu[]>([])

  const loading = ref(false)
  const loadingOnus = ref(false)
  const error = ref<string | null>(null)

  async function fetchOlts(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<{ data: Olt[], profiles: OltProfile[] }>('/olts')
      olts.value = data.data
      profiles.value = data.profiles
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch OLTs.'
    } finally {
      loading.value = false
    }
  }

  async function fetchOnus(oltId: number): Promise<void> {
    loadingOnus.value = true
    try {
      const { data } = await api.get<{ data: OltOnu[] }>(`/olts/${oltId}/onus`)
      onus.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch ONUs.'
    } finally {
      loadingOnus.value = false
    }
  }

  function selectOlt(id: number): void {
    selectedOlt.value = olts.value.find((o) => o.id === id) ?? null
    if (selectedOlt.value) {
      fetchOnus(selectedOlt.value.id)
    }
  }

  function clearSelection(): void {
    selectedOlt.value = null
    onus.value = []
  }

  async function createOlt(data: Partial<Olt>): Promise<void> {
    try {
      await api.post('/olts', data)
      await fetchOlts()
    } catch (err: any) {
      throw new Error(err.response?.data?.message || 'Failed to create OLT.')
    }
  }

  async function updateOlt(id: number, data: Partial<Olt>): Promise<void> {
    try {
      await api.put(`/olts/${id}`, data)
      await fetchOlts()
    } catch (err: any) {
      throw new Error(err.response?.data?.message || 'Failed to update OLT.')
    }
  }

  async function deleteOlt(id: number): Promise<void> {
    try {
      await api.delete(`/olts/${id}`)
      await fetchOlts()
      if (selectedOlt.value?.id === id) {
        clearSelection()
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.message || 'Failed to delete OLT.')
    }
  }

  async function syncOlt(id: number): Promise<void> {
    try {
      await api.post(`/olts/${id}/sync`)
      // Refresh ONUs if this is the currently selected OLT
      if (selectedOlt.value?.id === id) {
        await fetchOnus(id)
      }
    } catch (err: any) {
      throw new Error(err.response?.data?.message || 'Failed to sync OLT.')
    }
  }

  return {
    olts,
    profiles,
    selectedOlt,
    onus,
    loading,
    loadingOnus,
    error,
    fetchOlts,
    fetchOnus,
    selectOlt,
    clearSelection,
    createOlt,
    updateOlt,
    deleteOlt,
    syncOlt
  }
})
