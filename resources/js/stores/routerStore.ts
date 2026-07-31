import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../utils/api'

export interface MikroTikRouter {
  id: number
  name: string
  host: string
  api_user?: string
  api_port: number
  routeros_version: string | null
  status: 'HEALTHY' | 'DEGRADED' | 'UNREACHABLE'
  last_synced_at: string | null
  consecutive_sync_failures: number
  snmp_community?: string | null
  monitored_interface?: string | null
  health?: {
    cpu_load: number
    cpu_cores?: number[]
    ram_total: number
    ram_used: number
    uptime: string
    voltage: number | null
    temperature: number | null
    timestamp: number
  } | null
}

export interface PppoeUser {
  id: number
  username: string
  profile: string | null
  package_limit_mbps: number | null
  is_active_last_check: boolean
  synced_at: string | null
}

export interface PaginatedResponse<T> {
  current_page: number
  data: T[]
  last_page: number
  total: number
}

/**
 * Router store — handles routers list and selected router's PPPoE users cache.
 */
export const useRouterStore = defineStore('routers', () => {
  const routers = ref<MikroTikRouter[]>([])
  const selectedRouter = ref<MikroTikRouter | null>(null)
  
  const pppoeUsers = ref<PppoeUser[]>([])
  const pppoePagination = ref({
    currentPage: 1,
    lastPage: 1,
    total: 0
  })

  const loading = ref(false)
  const loadingUsers = ref(false)
  const error = ref<string | null>(null)

  async function fetchRouters(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await api.get<{ data: MikroTikRouter[] }>('/routers')
      routers.value = data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch routers.'
    } finally {
      loading.value = false
    }
  }

  async function forceSync(routerId: number): Promise<void> {
    try {
      await api.post(`/routers/${routerId}/force-sync`)
      // Refresh routers to see if status changed
      await fetchRouters()
    } catch (err: any) {
      const msg = err.response?.data?.message || 'Failed to trigger sync.'
      error.value = msg
      throw new Error(msg)
    }
  }

  async function fetchPppoeUsers(page: number = 1, search: string = ''): Promise<void> {
    if (!selectedRouter.value) return

    loadingUsers.value = true
    try {
      const { data } = await api.get<PaginatedResponse<PppoeUser>>(
        `/routers/${selectedRouter.value.id}/users`,
        { params: { page, search } }
      )
      pppoeUsers.value = data.data
      pppoePagination.value = {
        currentPage: data.current_page,
        lastPage: data.last_page,
        total: data.total
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch PPPoE users.'
    } finally {
      loadingUsers.value = false
    }
  }

  async function testConnection(id: number): Promise<any> {
    try {
      const { data } = await api.get(`/routers/${id}/health-check`)
      return data
    } catch (err: any) {
      throw new Error(err.response?.data?.message || 'Failed to connect to router.')
    }
  }

  function selectRouter(id: number): void {
    selectedRouter.value = routers.value.find((r) => r.id === id) ?? null
    // Fetch users for the selected router
    if (selectedRouter.value) {
      fetchPppoeUsers(1)
    }
  }

  function clearSelection(): void {
    selectedRouter.value = null
    pppoeUsers.value = []
  }

    async function createRouter(data: Partial<MikroTikRouter> & { credential?: string }): Promise<void> {
      try {
        await api.post('/routers', data)
        await fetchRouters()
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to create router.')
      }
    }

    async function updateRouter(id: number, data: Partial<MikroTikRouter> & { credential?: string }): Promise<void> {
      try {
        await api.put(`/routers/${id}`, data)
        await fetchRouters()
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to update router.')
      }
    }

    async function deleteRouter(id: number): Promise<void> {
      try {
        await api.delete(`/routers/${id}`)
        await fetchRouters()
        if (selectedRouter.value?.id === id) {
          clearSelection()
        }
      } catch (err: any) {
        throw new Error(err.response?.data?.message || 'Failed to delete router.')
      }
    }

    return {
      routers,
      selectedRouter,
      pppoeUsers,
      pppoePagination,
      loading,
      loadingUsers,
      error,
      fetchRouters,
      fetchPppoeUsers,
      forceSync,
      selectRouter,
      clearSelection,
      createRouter,
      updateRouter,
      deleteRouter,
      testConnection,
    }
})
