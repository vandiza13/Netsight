import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/api'

export const useAcsStore = defineStore('acs', () => {
  const devices = ref([])
  const loading = ref(false)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const error = ref<string | null>(null)

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

  return {
    devices,
    loading,
    pagination,
    error,
    fetchDevices,
    rebootDevice,
    updateWifi,
    refreshDevice
  }
})
