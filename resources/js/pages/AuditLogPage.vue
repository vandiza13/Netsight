<template>
  <div class="audit-log">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">System <span class="dashboard__heading--accent">Audit Log</span></h2>
          <p class="dashboard__heading-sub">Security and operational trail (append-only)</p>
        </section>

        <section class="stagger">
          <div class="glass-card panel">
            <div class="panel-header">
              <h3 class="panel-title">Activity Trail</h3>
              
              <div class="filter-controls">
                <input type="text" v-model="filters.action" placeholder="Filter by action..." class="form-input" @keyup.enter="fetchLogs(1)" />
                <input type="text" v-model="filters.target" placeholder="Filter by target..." class="form-input" @keyup.enter="fetchLogs(1)" />
                <button class="btn btn-secondary" @click="fetchLogs(1)" :disabled="loading">Search</button>
              </div>
            </div>

            <div v-if="loading" class="loading-state">Loading logs...</div>
            <div v-else-if="error" class="alert alert-error">{{ error }}</div>
            
            <div v-else class="table-container">
              <table class="audit-table">
                <thead>
                  <tr>
                    <th>Timestamp</th>
                    <th>Action</th>
                    <th>Staff / Actor</th>
                    <th>Router / Target</th>
                    <th>IP Address</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in logs" :key="log.id">
                    <td class="cell-time">{{ formatDate(log.created_at) }}</td>
                    <td>
                      <span class="badge badge-action">{{ log.action }}</span>
                    </td>
                    <td>
                      <div class="staff-info">
                        <strong>{{ log.staff?.name || 'SYSTEM' }}</strong>
                        <span class="text-xs text-muted block">{{ log.staff?.email }}</span>
                      </div>
                    </td>
                    <td>
                      <div v-if="log.router_id || log.target_username">
                        <span v-if="log.router" class="badge badge-router">{{ log.router.name }}</span>
                        <span v-if="log.target_username" class="target-user">{{ log.target_username }}</span>
                      </div>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td class="cell-mono">{{ log.ip_address || '-' }}</td>
                  </tr>
                  <tr v-if="logs.length === 0">
                    <td colspan="5" class="text-center">No audit logs found.</td>
                  </tr>
                </tbody>
              </table>
              
              <!-- Pagination -->
              <div class="pagination" v-if="pagination.last_page > 1">
                <button class="btn btn-sm btn-icon" :disabled="pagination.current_page === 1" @click="fetchLogs(pagination.current_page - 1)">◀</button>
                <span class="page-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                <button class="btn btn-sm btn-icon" :disabled="pagination.current_page === pagination.last_page" @click="fetchLogs(pagination.current_page + 1)">▶</button>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import api from '../utils/api'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'

const sidebarOpen = ref(false)
const loading = ref(false)
const error = ref('')
const logs = ref<any[]>([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0
})

const filters = reactive({
  action: '',
  target: ''
})

onMounted(() => {
  fetchLogs(1)
})

async function fetchLogs(page: number) {
  loading.value = true
  error.value = ''
  try {
    const params: any = { page }
    if (filters.action) params.action = filters.action
    if (filters.target) params.target = filters.target
    
    const { data } = await api.get('/audit-logs', { params })
    logs.value = data.data
    pagination.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to fetch audit logs'
  } finally {
    loading.value = false
  }
}

function formatDate(dateString: string) {
  if (!dateString) return '-'
  const d = new Date(dateString)
  return d.toLocaleString('id-ID', {
    year: 'numeric', month: 'short', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
}
</script>

<style scoped>
.audit-log {
  display: flex;
  min-height: 100vh;
  background: var(--bg-primary);
}
.dashboard__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.dashboard__content {
  flex: 1;
  padding: 28px 32px;
  overflow-y: auto;
}
.dashboard__heading {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 4px;
}
.dashboard__heading--accent {
  color: var(--accent-cyan);
}
.dashboard__heading-sub {
  font-size: 0.82rem;
  color: var(--text-muted);
}
.dashboard__welcome { margin-bottom: 28px; }

.panel {
  padding: 24px;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 16px;
}
.panel-title {
  font-size: 1.1rem;
  font-weight: 600;
}
.filter-controls {
  display: flex;
  gap: 12px;
}

.table-container {
  overflow-x: auto;
}
.audit-table {
  width: 100%;
  border-collapse: collapse;
}
.audit-table th, .audit-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--glass-border);
}
.audit-table th {
  color: var(--text-muted);
  font-weight: 500;
  font-size: 0.85rem;
  text-transform: uppercase;
}
.audit-table td {
  font-size: 0.85rem;
  vertical-align: top;
}
.text-center { text-align: center; color: var(--text-muted); }

.cell-time {
  font-family: var(--font-mono);
  color: var(--text-secondary);
  white-space: nowrap;
}
.cell-mono {
  font-family: var(--font-mono);
  color: var(--text-secondary);
}

.staff-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.text-xs { font-size: 0.75rem; }
.text-muted { color: var(--text-muted); }
.block { display: block; }

/* Badges */
.badge {
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  display: inline-block;
}
.badge-action {
  background: rgba(59, 130, 246, 0.2);
  color: #3b82f6;
  border: 1px solid rgba(59, 130, 246, 0.3);
}
.badge-router {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  margin-bottom: 4px;
}
.target-user {
  font-family: var(--font-mono);
  background: rgba(255,255,255,0.05);
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: 4px;
}

/* Inputs & Buttons */
.form-input {
  background: rgba(0,0,0,0.2);
  border: 1px solid var(--glass-border);
  padding: 8px 12px;
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  font-size: 0.85rem;
}
.btn {
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-weight: 500;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.btn-secondary:hover { background: rgba(255,255,255,0.15); }
.btn-sm { padding: 4px 8px; }
.btn-icon { background: transparent; border: 1px solid var(--glass-border); }
.btn-icon:hover:not(:disabled) { background: rgba(255,255,255,0.05); }
.btn-icon:disabled { opacity: 0.3; cursor: not-allowed; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 16px;
}
.page-info {
  font-size: 0.85rem;
  color: var(--text-secondary);
}
.alert {
  padding: 10px;
  border-radius: var(--radius-sm);
  margin-bottom: 16px;
  font-size: 0.85rem;
}
.alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
</style>
