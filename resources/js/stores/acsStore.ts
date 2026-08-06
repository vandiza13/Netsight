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

  const updateWifi = async (deviceId: number, ssid: string, password: string, band: string = '1') => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/wifi`, {
        ssid,
        password,
        band
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

  const updatePppoeConfig = async (deviceId: number, data: { username?: string, password?: string }) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/pppoe`, data)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengubah konfigurasi PPPoE.' }
    }
  }

  const fetchDeviceHosts = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/hosts`)
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal memuat data klien (host).' }
    }
  }

  const refreshDeviceHosts = async (deviceId: number) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/hosts/refresh`)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal refresh klien.' }
    }
  }

  const triggerPing = async (deviceId: number, host: string) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/ping`, { host })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menjalankan Ping.' }
    }
  }

  const fetchPingResult = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/ping/result`)
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengambil hasil Ping.' }
    }
  }

  const triggerTraceroute = async (deviceId: number, host: string) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/traceroute`, { host })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menjalankan Traceroute.' }
    }
  }

  const fetchTracerouteResult = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/traceroute/result`)
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengambil hasil Traceroute.' }
    }
  }

  const triggerSpeedtest = async (deviceId: number, url: string, type: 'download' | 'upload' = 'download') => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/speedtest`, { url, type })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menjalankan Speedtest.' }
    }
  }

  const fetchSpeedtestResult = async (deviceId: number, type: 'download' | 'upload' = 'download') => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/speedtest/result`, { params: { type } })
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengambil hasil Speedtest.' }
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
    factoryResetDevice,
    fetchDeviceHosts,
    refreshDeviceHosts,
    triggerPing,
    fetchPingResult,
    triggerTraceroute,
    fetchTracerouteResult,
    triggerSpeedtest,
    fetchSpeedtestResult,
    updatePppoeConfig
  }
})
