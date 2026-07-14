<template>
  <div class="user-table-container">
    <div class="user-table-header">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input 
          type="text" 
          v-model="searchQuery" 
          @input="handleSearch"
          placeholder="Search by username..." 
          class="search-input"
        />
      </div>
      
      <div class="table-actions">
        <button 
          class="btn-sync" 
          @click="$emit('force-sync')" 
          :disabled="isSyncing"
          title="Force Sync (TIER_2+)"
        >
          <span class="sync-icon" :class="{'spinning': isSyncing}">🔄</span>
          Force Sync
        </button>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="user-table">
        <thead>
          <tr>
            <th>Username</th>
            <th>Profile</th>
            <th>Rate Limit</th>
            <th>Status</th>
            <th>Last Synced</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody v-if="loading">
          <tr v-for="n in 5" :key="n" class="skeleton-row">
            <td v-for="c in 6" :key="c"><div class="skeleton-line"></div></td>
          </tr>
        </tbody>
        <tbody v-else-if="users.length === 0">
          <tr>
            <td colspan="6" class="empty-state">
              No users found.
            </td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="user in users" :key="user.id">
            <td class="col-username">{{ user.username }}</td>
            <td class="col-profile">
              <span class="badge badge--profile">{{ user.profile || 'default' }}</span>
            </td>
            <td class="col-limit">
              <span v-if="user.package_limit_mbps">{{ user.package_limit_mbps }} Mbps</span>
              <span v-else class="text-muted">Unlimited</span>
            </td>
            <td class="col-status">
              <span class="status-indicator" :class="user.is_active_last_check ? 'status-active' : 'status-inactive'">
                {{ user.is_active_last_check ? 'Active' : 'Disabled' }}
              </span>
            </td>
            <td class="col-sync text-muted">{{ formatTime(user.synced_at) }}</td>
            <td class="col-actions">
              <button class="btn-action btn-torch" @click="$emit('inspect', user.username)" title="Inspect Traffic (Torch)">
                🔦 Inspect
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
        <span class="total-users">({{ pagination.total }} users)</span>
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
import { ref } from 'vue'
import type { PppoeUser } from '../stores/routerStore'

const props = defineProps<{
  users: PppoeUser[]
  loading: boolean
  isSyncing: boolean
  pagination: {
    currentPage: number
    lastPage: number
    total: number
  }
}>()

const emit = defineEmits<{
  (e: 'search', query: string): void
  (e: 'page-change', page: number): void
  (e: 'force-sync'): void
  (e: 'inspect', username: string): void
}>()

const searchQuery = ref('')
let searchTimeout: ReturnType<typeof setTimeout> | null = null

function handleSearch() {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emit('search', searchQuery.value)
  }, 300) // Debounce 300ms
}

function changePage(page: number) {
  emit('page-change', page)
}

function formatTime(dateString: string | null): string {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
</script>

<style scoped>
.user-table-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: 100%;
}

.user-table-header {
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

.btn-sync {
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(59, 130, 246, 0.1);
  color: var(--accent-blue);
  border: 1px solid rgba(59, 130, 246, 0.3);
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-sync:hover:not(:disabled) {
  background: rgba(59, 130, 246, 0.2);
}

.btn-sync:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

.table-wrapper {
  overflow-x: auto;
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  backdrop-filter: blur(16px);
}

.user-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.85rem;
}

.user-table th {
  padding: 12px 16px;
  font-weight: 600;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--glass-border);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.75rem;
}

.user-table td {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
}

.user-table tr:last-child td {
  border-bottom: none;
}

.user-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.02);
}

.col-username {
  font-weight: 600;
  color: var(--text-primary);
}

.badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge--profile {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-active {
  background: rgba(16, 185, 129, 0.1);
  color: var(--accent-green);
}

.status-inactive {
  background: rgba(239, 68, 68, 0.1);
  color: var(--accent-red);
}

.text-muted {
  color: var(--text-muted);
}

.btn-action {
  background: transparent;
  border: 1px solid var(--glass-border);
  color: var(--text-primary);
  padding: 6px 10px;
  border-radius: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-torch:hover {
  border-color: var(--accent-amber);
  color: var(--accent-amber);
  background: rgba(245, 158, 11, 0.1);
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

@media (max-width: 640px) {
  .user-table-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  .search-box {
    width: 100%;
  }
  .btn-sync {
    width: 100%;
    justify-content: center;
  }
}
</style>
