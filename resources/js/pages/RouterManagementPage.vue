<template>
  <div class="router-management">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">Router <span class="dashboard__heading--accent">Management</span></h2>
          <p class="dashboard__heading-sub">Manage NOC routers, credentials, and configuration</p>
        </section>

        <section class="stagger">
          <div class="glass-card panel">
            <div class="panel-header">
              <h3 class="panel-title">Registered Routers</h3>
              <button class="btn btn-primary" @click="openAddModal">
                <span class="icon">➕</span> Add Router
              </button>
            </div>

            <div v-if="loading" class="loading-state">Loading routers...</div>
            <div v-else-if="error" class="alert alert-error">{{ error }}</div>
            
            <table v-else class="router-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>IP / Host</th>
                  <th>API Port</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="router in routers" :key="router.id">
                  <td>{{ router.name }}</td>
                  <td><code>{{ router.host }}</code></td>
                  <td>{{ router.api_port }}</td>
                  <td>
                    <span class="badge" :class="statusClass(router.status)">{{ router.status }}</span>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-icon" @click="openEditModal(router)" title="Edit">✏️</button>
                    <button class="btn btn-sm btn-icon btn-danger" @click="confirmDelete(router.id)" title="Delete">🗑️</button>
                  </td>
                </tr>
                <tr v-if="routers.length === 0">
                  <td colspan="5" class="text-center">No routers registered yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>

    <!-- Modal Form (Add/Edit) -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="glass-card modal-content fade-in">
        <h3 class="modal-title">{{ isEditing ? 'Edit Router' : 'Add New Router' }}</h3>
        
        <form @submit.prevent="submitForm" class="router-form">
          <div class="form-group">
            <label>Router Name</label>
            <input type="text" v-model="form.name" class="form-input" required placeholder="e.g. NOC Edge 01" />
          </div>
          
          <div class="form-group">
            <label>IP Address / Host</label>
            <input type="text" v-model="form.host" class="form-input" required placeholder="e.g. 192.168.1.1" />
          </div>

          <div class="form-group">
            <label>API Username</label>
            <input type="text" v-model="form.api_user" class="form-input" placeholder="admin" />
          </div>
          
          <div class="form-group">
            <label>API Port</label>
            <input type="number" v-model="form.api_port" class="form-input" required placeholder="8728 or 8729" />
          </div>
          
          <div class="form-group">
            <label>API Password</label>
            <input type="password" v-model="form.credential" class="form-input" :required="!isEditing" placeholder="API Password" />
            <small v-if="isEditing" class="form-help">Leave blank to keep current password</small>
          </div>
          
          <div class="form-group">
            <label>Sync Offset (minutes)</label>
            <input type="number" v-model="form.sync_offset_minutes" class="form-input" min="1" placeholder="15" />
          </div>

          <div v-if="formError" class="alert alert-error">{{ formError }}</div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? 'Saving...' : 'Save Router' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouterStore, type MikroTikRouter } from '../stores/routerStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'

const routerStore = useRouterStore()
const { routers, loading, error } = storeToRefs(routerStore)

const sidebarOpen = ref(false)

// Modal State
const showModal = ref(false)
const isEditing = ref(false)
const submitting = ref(false)
const formError = ref('')
let editingId: number | null = null

const form = reactive({
  name: '',
  host: '',
  api_user: 'admin',
  api_port: 8729,
  credential: '',
  sync_offset_minutes: 15
})

onMounted(() => {
  routerStore.fetchRouters()
})

function statusClass(status: string) {
  if (status === 'HEALTHY') return 'badge-success'
  if (status === 'DEGRADED') return 'badge-warning'
  return 'badge-error'
}

function resetForm() {
  form.name = ''
  form.host = ''
  form.api_user = 'admin'
  form.api_port = 8729
  form.credential = ''
  form.sync_offset_minutes = 15
  formError.value = ''
}

function openAddModal() {
  isEditing.value = false
  editingId = null
  resetForm()
  showModal.value = true
}

function openEditModal(router: MikroTikRouter) {
  isEditing.value = true
  editingId = router.id
  resetForm()
  form.name = router.name
  form.host = router.host
  form.api_user = router.api_user || 'admin'
  form.api_port = router.api_port
  form.sync_offset_minutes = 15 // Assuming default if not available
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function submitForm() {
  submitting.value = true
  formError.value = ''
  
  const payload: any = { ...form }
  if (isEditing.value && !payload.credential) {
    delete payload.credential
  }

  try {
    if (isEditing.value && editingId) {
      await routerStore.updateRouter(editingId, payload)
    } else {
      await routerStore.createRouter(payload)
    }
    closeModal()
  } catch (err: any) {
    formError.value = err.message
  } finally {
    submitting.value = false
  }
}

async function confirmDelete(id: number) {
  if (confirm('Are you sure you want to delete this router? This action cannot be undone.')) {
    try {
      await routerStore.deleteRouter(id)
    } catch (err: any) {
      alert('Failed to delete router: ' + err.message)
    }
  }
}
</script>

<style scoped>
.router-management {
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
}
.panel-title {
  font-size: 1.1rem;
  font-weight: 600;
}

.router-table {
  width: 100%;
  border-collapse: collapse;
}
.router-table th, .router-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--glass-border);
}
.router-table th {
  color: var(--text-muted);
  font-weight: 500;
  font-size: 0.85rem;
  text-transform: uppercase;
}
.router-table td {
  font-size: 0.9rem;
}
.text-center { text-align: center; color: var(--text-muted); }

/* Badges */
.badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
}
.badge-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
.badge-warning { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
.badge-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

/* Buttons */
.btn {
  padding: 8px 16px;
  border-radius: var(--radius-sm);
  font-weight: 500;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.btn-primary { background: var(--accent-cyan); color: #fff; }
.btn-primary:hover { opacity: 0.9; }
.btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary); }
.btn-secondary:hover { background: rgba(255,255,255,0.15); }
.btn-danger { color: #ef4444 !important; }
.btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
.btn-sm { padding: 4px 8px; font-size: 0.8rem; }
.btn-icon { background: transparent; border: 1px solid var(--glass-border); margin-right: 8px; }
.btn-icon:hover { background: rgba(255,255,255,0.05); }

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-content {
  width: 100%;
  max-width: 500px;
  padding: 24px;
}
.modal-title {
  font-size: 1.2rem;
  font-weight: 600;
  margin-bottom: 20px;
}
.form-group {
  margin-bottom: 16px;
}
.form-group label {
  display: block;
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-bottom: 6px;
}
.form-input {
  width: 100%;
  background: rgba(0,0,0,0.2);
  border: 1px solid var(--glass-border);
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  font-family: inherit;
}
.form-input:focus {
  outline: none;
  border-color: var(--accent-cyan);
}
.form-help {
  display: block;
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 4px;
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}
.alert {
  padding: 10px;
  border-radius: var(--radius-sm);
  margin-bottom: 16px;
  font-size: 0.85rem;
}
.alert-error {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
</style>
