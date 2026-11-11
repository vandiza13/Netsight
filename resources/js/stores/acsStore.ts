import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/api'

export const useAcsStore = defineStore('acs', () => {
  const devices = ref([])
  const stats = ref<any>({
    total: 0,
    online: 0,
    offline: 0,
    critical_rx: 0,
    unlinked: 0,
    stale: 0,
    new_ont: 0,
    vendors: [],
    sync: null
  })
  const loading = ref(false)
  const syncing = ref(false)
  const syncStatus = ref<any>(null)
  const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
  const error = ref<string | null>(null)

  const fetchStats = async () => {
    try {
      const response = await api.get('/acs/stats')
      stats.value = response.data.data
      if (response.data.data?.sync) {
        syncStatus.value = response.data.data.sync
      }
    } catch (err: any) {
      console.error('Failed to fetch ACS stats', err)
    }
  }

  const triggerSync = async () => {
    syncing.value = true
    try {
      const response = await api.post('/acs/sync')
      if (response.data?.data) {
        syncStatus.value = response.data.data
      }
      await fetchStats()
      await fetchDevices(pagination.value.current_page)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal melakukan sinkronisasi dengan GenieACS.' }
    } finally {
      syncing.value = false
    }
  }

  const fetchDevices = async (
    page = 1,
    search = '',
    filters: { status?: string; vendor?: string; per_page?: number; sort_by?: string; sort_order?: string } = {}
  ) => {
    loading.value = true
    error.value = null
    try {
      const params: any = { page, search }
      if (filters.status && filters.status !== 'all') params.status = filters.status
      if (filters.vendor && filters.vendor !== 'all') params.vendor = filters.vendor
      if (filters.per_page) params.per_page = filters.per_page
      if (filters.sort_by) params.sort_by = filters.sort_by
      if (filters.sort_order) params.sort_order = filters.sort_order

      const response = await api.get('/acs/devices', { params })
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

  const fetchAdvancedWifi = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/advanced-wifi`)
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengambil konfigurasi Advanced WiFi.' }
    }
  }

  const updateAdvancedWifi = async (deviceId: number, data: any) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/advanced-wifi`, data)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menyimpan konfigurasi Advanced WiFi.' }
    }
  }

  const updateRadioConfig = async (deviceId: number, data: any) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/radio`, data)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menyimpan konfigurasi Radio WiFi.' }
    }
  }

  const deleteDevice = async (deviceId: number) => {
    try {
      const response = await api.delete(`/acs/devices/${deviceId}`)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menghapus perangkat.' }
    }
  }

  const profiles = ref<any[]>([])

  const fetchProfiles = async () => {
    try {
      const response = await api.get('/acs/profiles')
      profiles.value = response.data.data || []
      return profiles.value
    } catch (err: any) {
      console.error('Failed to fetch ACS profiles', err)
      return []
    }
  }

  const saveProfile = async (data: any) => {
    try {
      let response
      if (data.id) {
        response = await api.put(`/acs/profiles/${data.id}`, data)
      } else {
        response = await api.post('/acs/profiles', data)
      }
      await fetchProfiles()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menyimpan profil provisioning.' }
    }
  }

  const deleteProfile = async (id: number) => {
    try {
      const response = await api.delete(`/acs/profiles/${id}`)
      await fetchProfiles()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menghapus profil provisioning.' }
    }
  }

  const bulkAction = async (payload: {
    deviceIds?: number[]
    action: string
    params?: any
    selectAllMatching?: boolean
    filterSearch?: string
    filterVendor?: string
    filterStatus?: string
  }) => {
    try {
      const response = await api.post('/acs/bulk-action', {
        device_ids: payload.deviceIds || [],
        action: payload.action,
        params: payload.params || {},
        select_all_matching: payload.selectAllMatching || false,
        filter_search: payload.filterSearch || '',
        filter_vendor: payload.filterVendor || '',
        filter_status: payload.filterStatus || ''
      })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menjalankan aksi massal.' }
    }
  }

  const fetchBatchStatus = async (batchId: string) => {
    try {
      const response = await api.get(`/acs/batches/${batchId}`)
      return response.data.data
    } catch (err: any) {
      console.error('Failed to fetch batch status', err)
      return null
    }
  }

  const provisionDevice = async (deviceId: number, profileId: number) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/provision`, { profile_id: profileId })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menerapkan profil provisioning.' }
    }
  }

  const updateDeviceTag = async (deviceId: number, areaTag: string) => {
    try {
      const response = await api.post(`/acs/devices/${deviceId}/tag`, { area_tag: areaTag })
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal memperbarui tag area.' }
    }
  }

  const fetchDeviceTasks = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/tasks`)
      return response.data.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal mengambil riwayat antrean tugas.' }
    }
  }

  const fetchDeviceMetrics = async (deviceId: number, days: number = 7) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/metrics`, { params: { days } })
      return response.data.data || []
    } catch (err: any) {
      console.error('Failed to fetch device metrics', err)
      return []
    }
  }

  // ============================================================
  // FASE 4: ALERTS, NOTIFICATIONS & CORRELATION
  // ============================================================
  const alerts = ref<any[]>([])
  const alertRules = ref<any[]>([])
  const notificationConfig = ref<any>(null)
  const healthAnalytics = ref<any>(null)

  const fetchAlerts = async (page = 1, filters: { resolved?: boolean; severity?: string; search?: string } = {}) => {
    try {
      const params: any = { page }
      if (filters.resolved !== undefined) params.resolved = filters.resolved
      if (filters.severity && filters.severity !== 'all') params.severity = filters.severity
      if (filters.search) params.search = filters.search

      const response = await api.get('/acs/alerts', { params })
      alerts.value = response.data.data.data || []
      return response.data.data
    } catch (err: any) {
      console.error('Failed to fetch ACS alerts', err)
      return { data: [], current_page: 1, last_page: 1, total: 0 }
    }
  }

  const resolveAlert = async (alertId: number) => {
    try {
      const response = await api.post(`/acs/alerts/${alertId}/resolve`)
      await fetchStats()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menandai alert selesai.' }
    }
  }

  const resolveAllAlerts = async () => {
    try {
      const response = await api.post('/acs/alerts/resolve-all')
      await fetchStats()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menandai semua alert selesai.' }
    }
  }

  const fetchAlertRules = async () => {
    try {
      const response = await api.get('/acs/alert-rules')
      alertRules.value = response.data.data || []
      return alertRules.value
    } catch (err: any) {
      console.error('Failed to fetch alert rules', err)
      return []
    }
  }

  const saveAlertRule = async (data: any) => {
    try {
      const response = await api.post('/acs/alert-rules', data)
      await fetchAlertRules()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menyimpan aturan alert.' }
    }
  }

  const deleteAlertRule = async (id: number) => {
    try {
      const response = await api.delete(`/acs/alert-rules/${id}`)
      await fetchAlertRules()
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menghapus aturan alert.' }
    }
  }

  const fetchNotificationConfig = async () => {
    try {
      const response = await api.get('/acs/notification-config')
      notificationConfig.value = response.data.data
      return notificationConfig.value
    } catch (err: any) {
      console.error('Failed to fetch notification config', err)
      return null
    }
  }

  const saveNotificationConfig = async (data: any) => {
    try {
      const response = await api.post('/acs/notification-config', data)
      notificationConfig.value = response.data.data
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Gagal menyimpan pengaturan notifikasi.' }
    }
  }

  const testNotification = async (payload: { channel: string; [key: string]: any }) => {
    try {
      const response = await api.post('/acs/notifications/test', payload)
      return response.data
    } catch (err: any) {
      throw err.response?.data || { message: 'Uji coba pengiriman notifikasi gagal.' }
    }
  }

  const fetchHealthAnalytics = async () => {
    try {
      const response = await api.get('/acs/analytics/health')
      healthAnalytics.value = response.data.data
      return healthAnalytics.value
    } catch (err: any) {
      console.error('Failed to fetch health analytics', err)
      return null
    }
  }

  const fetchDeviceCorrelation = async (deviceId: number) => {
    try {
      const response = await api.get(`/acs/devices/${deviceId}/correlation`)
      return response.data.data
    } catch (err: any) {
      console.error('Failed to fetch device correlation', err)
      return null
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
    updatePppoeConfig,
    fetchAdvancedWifi,
    updateAdvancedWifi,
    updateRadioConfig,
    deleteDevice,
    bulkAction,
    fetchDeviceTasks,
    syncing,
    syncStatus,
    triggerSync,
    fetchDeviceMetrics,
    profiles,
    fetchProfiles,
    saveProfile,
    deleteProfile,
    fetchBatchStatus,
    provisionDevice,
    updateDeviceTag,
    alerts,
    alertRules,
    notificationConfig,
    healthAnalytics,
    fetchAlerts,
    resolveAlert,
    resolveAllAlerts,
    fetchAlertRules,
    saveAlertRule,
    deleteAlertRule,
    fetchNotificationConfig,
    saveNotificationConfig,
    testNotification,
    fetchHealthAnalytics,
    fetchDeviceCorrelation
  }
})
