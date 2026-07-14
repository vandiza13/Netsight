import { defineStore } from 'pinia'
import api from '../utils/api'

export interface Staff {
  id: number
  name: string
  email: string
  role: 'TIER_1' | 'TIER_2' | 'ADMIN'
  is_active: boolean
  has_totp: boolean
  created_at: string
}

export const useStaffStore = defineStore('staff', {
  state: () => ({
    staffList: [] as Staff[],
    loading: false,
    error: null as string | null
  }),
  
  actions: {
    async fetchStaffList() {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.get('/staff')
        this.staffList = data
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to fetch staff list'
        throw err
      } finally {
        this.loading = false
      }
    },

    async createStaff(payload: any) {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.post('/staff', payload)
        await this.fetchStaffList()
        return data.message
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to create staff'
        throw err
      } finally {
        this.loading = false
      }
    },

    async updateStaff(id: number, payload: any) {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.put(`/staff/${id}`, payload)
        await this.fetchStaffList()
        return data.message
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to update staff'
        throw err
      } finally {
        this.loading = false
      }
    },

    async deleteStaff(id: number) {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.delete(`/staff/${id}`)
        await this.fetchStaffList()
        return data.message
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to delete staff'
        throw err
      } finally {
        this.loading = false
      }
    },

    async resetTotp(id: number) {
      this.loading = true
      this.error = null
      try {
        const { data } = await api.post(`/staff/${id}/reset-totp`)
        await this.fetchStaffList()
        return data.message
      } catch (err: any) {
        this.error = err.response?.data?.message || 'Failed to reset TOTP'
        throw err
      } finally {
        this.loading = false
      }
    }
  }
})
