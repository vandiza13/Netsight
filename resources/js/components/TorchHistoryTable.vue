<template>
  <div class="history-table-container">
    <div class="history-filters mb-4">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input 
          type="text" 
          v-model="searchQuery" 
          @input="handleSearch" 
          class="search-input" 
          placeholder="Filter by username..." 
        />
      </div>
      <button class="btn-refresh" @click="fetchHistory(1)" :disabled="loading">
        🔄 Refresh
      </button>
    </div>

    <div class="table-wrapper">
      <table class="history-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Username</th>
            <th>NOC Staff</th>
            <th>Peak Bandwidth</th>
            <th>Conclusion</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr v-for="n in 5" :key="n" class="skeleton-row">
            <td v-for="c in 6" :key="c"><div class="skeleton-line"></div></td>
          </tr>
        </tbody>
        <tbody v-else-if="history.length === 0">
          <tr>
            <td colspan="6" class="empty-state">
              No diagnostic history found.
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="session in history" :key="session.id">
            <td class="col-date">{{ formatDate(session.started_at) }}</td>
            <td class="col-username font-semibold">{{ session.username }}</td>
            <td class="col-staff">{{ session.initiator?.name || 'System' }}</td>
            <td class="col-bandwidth font-mono">
              <span class="tx-rate">▲ {{ formatTraffic(session.peak_tx_bps) }}</span> / 
              <span class="rx-rate">▼ {{ formatTraffic(session.peak_rx_bps) }}</span>
            </td>
            <td class="col-conclusion text-muted text-xs">
              {{ session.diagnostic_conclusion || 'No conclusion recorded.' }}
            </td>
            <td class="col-actions">
              <button class="btn-view" @click="$emit('view-report', session.id)">
                📊 View Chart
              </button>
              <button v-if="auth.isAdmin" class="btn-delete" @click="deleteHistory(session.id)">
                🗑️
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pagination" v-if="pagination.lastPage > 1">
      <button 
        :disabled="pagination.currentPage === 1 || loading" 
        @click="changePage(pagination.currentPage - 1)"
        class="page-btn"
      >
        &laquo; Prev
      </button>
      <span class="page-info">
        Page {{ pagination.currentPage }} of {{ pagination.lastPage }} 
        <span class="total-users">({{ pagination.total }} reports)</span>
      </span>
      <button 
        :disabled="pagination.currentPage === pagination.lastPage || loading" 
        @click="changePage(pagination.currentPage + 1)"
        class="page-btn"
      >
        Next &raquo;
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import api from '../utils/api'
import { useAuthStore } from '../stores/authStore'

const props = defineProps<{
  routerId: number
}>()

defineEmits<{
  (e: 'view-report', sessionId: number): void
}>()

const auth = useAuthStore()
const history = ref<any[]>([])
const loading = ref(false)
const searchQuery = ref('')
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  total: 0
})

let searchTimeout: ReturnType<typeof setTimeout> | null = null

async function fetchHistory(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/torch/history', {
      params: {
        page,
        router_id: props.routerId,
        username: searchQuery.value
      }
    })
    history.value = data.data
    pagination.value = {
      currentPage: data.current_page,
      lastPage: data.last_page,
      total: data.total
    }
  } catch (e) {
    console.error('Failed to fetch history', e)
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchHistory(1)
  }, 400)
}

function changePage(page: number) {
  fetchHistory(page)
}

async function deleteHistory(id: number) {
  if (!confirm('Are you sure you want to delete this diagnostic history?')) return
  try {
    loading.value = true
    await api.delete(`/torch/history/${id}`)
    await fetchHistory(pagination.value.currentPage)
  } catch (e) {
    console.error('Failed to delete history', e)
    alert('Failed to delete history')
  } finally {
    loading.value = false
  }
}

function formatDate(dateString: string): string {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString([], {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatTraffic(bps: number | string): string {
  const num = Number(bps)
  if (isNaN(num) || num === 0) return '0 bps'
  if (num >= 1000000) return (num / 1000000).toFixed(1) + ' Mbps'
  if (num >= 1000) return (num / 1000).toFixed(0) + ' Kbps'
  return num + ' bps'
}

// Reload when router changes
watch(() => props.routerId, () => {
  searchQuery.value = ''
  fetchHistory(1)
}, { immediate: true })
</script>

<style scoped>
.history-table-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.history-filters {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.search-box {
  position: relative;
  width: 300px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.9rem;
  opacity: 0.5;
}

.search-input {
  width: 100%;
  background: rgba(17, 24, 39, 0.6);
  border: 1px solid var(--glass-border);
  color: var(--text-primary);
  padding: 8px 12px 8px 36px;
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.search-input:focus {
  outline: none;
  border-color: var(--accent-cyan);
  box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.2);
}

.btn-refresh {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--glass-border);
  color: var(--text-secondary);
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-refresh:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
}

.table-wrapper {
  overflow-x: auto;
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  backdrop-filter: blur(16px);
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.85rem;
}

.history-table th {
  padding: 12px 16px;
  font-weight: 600;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--glass-border);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.75rem;
}

.history-table td {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
}

.history-table tr:last-child td {
  border-bottom: none;
}

.history-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.02);
}

.font-semibold { font-weight: 600; }
.font-mono { font-family: var(--font-mono, monospace); }
.text-xs { font-size: 0.75rem; }
.text-muted { color: var(--text-muted); }

.tx-rate { color: var(--accent-amber); }
.rx-rate { color: var(--accent-cyan); }

.col-conclusion {
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.col-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  white-space: nowrap;
}

.btn-view {
  background: rgba(6, 182, 212, 0.1);
  color: var(--accent-cyan);
  border: 1px solid rgba(6, 182, 212, 0.3);
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-view:hover {
  background: rgba(6, 182, 212, 0.2);
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.2);
}

.btn-delete {
  background: rgba(239, 68, 68, 0.1);
  color: var(--accent-red);
  border: 1px solid rgba(239, 68, 68, 0.3);
  padding: 6px;
  border-radius: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-delete:hover {
  background: rgba(239, 68, 68, 0.2);
  box-shadow: 0 0 8px rgba(239, 68, 68, 0.2);
}

.empty-state {
  text-align: center;
  padding: 40px !important;
  color: var(--text-muted);
}

.skeleton-row .skeleton-line {
  height: 16px;
  background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 8px;
}

.page-btn {
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  color: var(--text-primary);
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.total-users {
  color: var(--text-muted);
  font-size: 0.75rem;
}
</style>
