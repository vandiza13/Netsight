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
            
            <div v-else class="table-responsive">
              <table class="router-table">
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
                      <button class="btn btn-sm btn-icon" @click="testConnection(router)" :disabled="testingId === router.id" title="Test Connection">
                        <span v-if="testingId === router.id">⏳</span>
                        <span v-else>🔌</span>
                      </button>
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
          </div>
        </section>
      </main>
    </div>

    <!-- Modal Form (Add/Edit) -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="glass-card modal-content fade-in">
        <h3 class="modal-title">{{ isEditing ? 'Edit Router' : 'Add New Router' }}</h3>
        
        <form @submit.prevent="submitForm" class="router-form">
          <div class="form-grid">
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
            
            <div class="form-group full-width">
              <label>SNMP Community (Optional)</label>
              <input type="text" v-model="form.snmp_community" class="form-input" placeholder="e.g. public or BimaNet-SNMP" />
              
              <!-- Smart SNMP Quick Setup Helper -->
              <div class="snmp-helper-box mt-2">
                <div class="helper-header">
                  <span class="helper-title">⚡ MikroTik SNMP Quick-Setup Script</span>
                  <button type="button" class="btn-copy" @click="copySnmpScript">
                    {{ copied ? 'Copied! ✓' : '📋 Copy Script' }}
                  </button>
                </div>
                <pre class="script-code font-mono">{{ generatedSnmpScript }}</pre>
                <small class="helper-note">Copas script ini ke Terminal Winbox untuk mengaktifkan SNMP secara instan.</small>
              </div>
            </div>

            <div class="form-group">
              <label>Monitored Interface (Optional)</label>
              <div v-if="isEditing">
                <select v-if="!loadingInterfaces && availableInterfaces.length > 0" v-model="form.monitored_interface" class="form-input">
                  <option value="">-- Select Interface --</option>
                  <option v-for="iface in availableInterfaces" :key="iface.name" :value="iface.name">{{ iface.name }}</option>
                </select>
                <input v-else type="text" v-model="form.monitored_interface" class="form-input" :placeholder="loadingInterfaces ? 'Loading interfaces...' : 'e.g. ether1'" :disabled="loadingInterfaces" />
              </div>
              <div v-else>
                <input type="text" v-model="form.monitored_interface" class="form-input" placeholder="Save router first to load interfaces, or type manually" />
              </div>
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
import { ref, onMounted, reactive, computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouterStore, type MikroTikRouter } from '../stores/routerStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import api from '../utils/api'

const routerStore = useRouterStore()
const { routers, loading, error } = storeToRefs(routerStore)

const sidebarOpen = ref(false)

// Modal State
const showModal = ref(false)
const isEditing = ref(false)
const submitting = ref(false)
const formError = ref('')
const testingId = ref<number | null>(null)
let editingId: number | null = null

const availableInterfaces = ref<{name: string}[]>([])
const loadingInterfaces = ref(false)

const form = reactive({
  name: '',
  host: '',
  api_user: 'admin',
  api_port: 8729,
  credential: '',
  sync_offset_minutes: 15,
  snmp_community: '',
  monitored_interface: ''
})

const copied = ref(false)

const generatedSnmpScript = computed(() => {
  const comm = form.snmp_community.trim() || 'public'
  return `/snmp set enabled=yes; :if ([:len [/snmp community find name="${comm}"]] = 0) do={ /snmp community add name="${comm}" }`
})

function copySnmpScript() {
  navigator.clipboard.writeText(generatedSnmpScript.value)
  copied.value = true
  setTimeout(() => {
    copied.value = false
  }, 2000)
}

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
  form.snmp_community = ''
  form.monitored_interface = ''
  formError.value = ''
}

function openAddModal() {
  isEditing.value = false
  editingId = null
  resetForm()
  showModal.value = true
}

async function openEditModal(router: MikroTikRouter) {
  isEditing.value = true
  editingId = router.id
  resetForm()
  form.name = router.name
  form.host = router.host
  form.api_user = router.api_user || 'admin'
  form.api_port = router.api_port
  form.sync_offset_minutes = router.sync_offset_minutes || 15
  form.snmp_community = router.snmp_community || ''
  form.monitored_interface = router.monitored_interface || ''
  showModal.value = true

  // Fetch available interfaces
  availableInterfaces.value = []
  loadingInterfaces.value = true
  try {
    const res = await api.get(`/routers/${router.id}/interfaces`)
    availableInterfaces.value = res.data.data || []
  } catch (err) {
    console.error('Failed to load interfaces', err)
  } finally {
    loadingInterfaces.value = false
  }
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

async function testConnection(router: MikroTikRouter) {
  testingId.value = router.id
  try {
    const result = await routerStore.testConnection(router.id)
    alert(`✅ Connection Successful!\nRouterOS Version: ${result.version || 'Unknown'}\nCPU Load: ${result.cpu_load}%\nUptime: ${result.uptime}`)
  } catch (err: any) {
    alert(`❌ Connection Failed:\n${err.message}`)
  } finally {
    testingId.value = null
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
  max-width: 600px;
  padding: 24px;
  max-height: 90vh;
  overflow-y: auto;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 640px) {
  .modal-content {
    margin: 16px;
    padding: 20px;
  }
}
.modal-title {
  font-size: 1.2rem;
  font-weight: 600;
  margin-bottom: 20px;
}
.form-group {
  margin-bottom: 0;
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
.full-width {
  grid-column: 1 / -1;
}

.snmp-helper-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 12px;
  margin-top: 8px;
}

.helper-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.helper-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--accent-cyan);
}

.btn-copy {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 4px;
  background: var(--surface-3);
  color: var(--text-primary);
  border: 1px solid var(--border);
  cursor: pointer;
  transition: all 0.2s;
}

.btn-copy:hover {
  background: var(--accent-dim);
  color: var(--accent-cyan);
}

.script-code {
  background: var(--surface-0);
  color: #4ade80;
  padding: 8px 10px;
  border-radius: 4px;
  font-size: 0.75rem;
  white-space: pre-wrap;
  word-break: break-all;
  border: 1px solid var(--border);
}

.helper-note {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin-top: 6px;
  display: block;
}
</style>
