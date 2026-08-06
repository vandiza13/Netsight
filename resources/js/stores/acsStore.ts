import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/api'

export const useAcsStore = defineStore('acs', () => {
  const devices = ref([])
  const stats = ref({ total: 0, online: 0, offline: 0, critical_rx: 0 })
  const loading = ref(false)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const error = ref<string | null>(null)

  const fetchStats = async () => {
    try {
      const response = await api.get('/acs/stats')
      stats.value = response.data.data
    } catch (err: any) {
      console.error('Failed to fetch ACS stats', err)
    }
  }

  const fetchDevices = async (page = 1, search = '') => {
    loading.value = true
    error.value = null
    try {
      const response = await api.get('/acs/devices', {
        params: { page, search }
      })
      devices.value = response.data.data.data
      pagination.value = {
        current_page: response.data.data.current_page,
        last_page: response.data.data.last_page,
        total: response.data.data.total
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal mengambil data modem.'
    } finally {
      loading.value = false
    }
  }

  const rebootDevice = async (deviceId: number) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/reboot`)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal melakukan reboot modem.' }
    }
  }

  const updateWifi = async (deviceId: number, ssid: string, password: string) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/wifi`, {
        ssid,
        password
      })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengubah konfigurasi WiFi.' }
    }
  }

  const refreshDevice = async (deviceId: number) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/refresh`)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal sinkronisasi dengan modem.' }
    }
  }

  const factoryResetDevice = async (deviceId: number) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/factory-reset`)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal melakukan factory reset modem.' }
    }
  }

  return {
    devices,
    stats,
    loading,
    pagination,
    error,
    fetchStats,
    fetchDevices,
    rebootDevice,
    updateWifi,
    refreshDevice,
    factoryResetDevice
  }
})
