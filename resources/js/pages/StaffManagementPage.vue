<template>
  <div class="staff-mgmt">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">Staff <span class="dashboard__heading--accent">Management</span></h2>
          <p class="dashboard__heading-sub">Kelola akun staf NOC dan akses otentikasi dua faktor (TOTP)</p>
        </section>

        <section class="stagger">
          <div class="glass-card panel">
            <div class="panel-header">
              <h3 class="panel-title">👥 Daftar Staf NOC</h3>
              <button class="btn btn-primary" @click="openModal()">+ Add Staff</button>
            </div>

            <div v-if="staffStore.loading && staffStore.staffList.length === 0" class="loading-state">
              Loading data staf...
            </div>
            
            <div v-else class="table-container">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th>MFA (TOTP)</th>
                    <th class="text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="staff in staffStore.staffList" :key="staff.id">
                    <td class="font-bold">{{ staff.name }}</td>
                    <td class="text-muted">{{ staff.email }}</td>
                    <td>
                      <span class="badge" :class="getRoleClass(staff.role)">{{ staff.role }}</span>
                    </td>
                    <td>
                      <span class="badge" :class="staff.is_active ? 'badge-success' : 'badge-danger'">
                        {{ staff.is_active ? 'ACTIVE' : 'INACTIVE' }}
                      </span>
                    </td>
                    <td>
                      <span v-if="staff.has_totp" class="status-totp status-totp--yes">✅ Setup</span>
                      <span v-else class="status-totp status-totp--no">❌ Belum Setup</span>
                    </td>
                    <td class="text-right actions-cell">
                      <button 
                        v-if="staff.has_totp" 
                        class="btn-icon btn-icon-warning" 
                        title="Reset TOTP" 
                        @click="confirmResetTotp(staff)"
                      >
                        🔄
                      </button>
                      <button class="btn-icon" title="Edit Staff" @click="openModal(staff)">✏️</button>
                      <button 
                        class="btn-icon btn-icon-danger" 
                        title="Delete Staff" 
                        @click="confirmDelete(staff)"
                        :disabled="auth.user?.email === staff.email"
                      >
                        🗑️
                      </button>
                    </td>
                  </tr>
                  <tr v-if="staffStore.staffList.length === 0">
                    <td colspan="6" class="text-center text-muted py-8">Belum ada data staf.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- Modal Form (Tambah/Edit) -->
    <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content glass-card fade-in">
        <h3 class="modal-title">{{ form.id ? 'Edit Staff' : 'Add New Staff' }}</h3>
        
        <form @submit.prevent="submitForm">
          <div class="form-group">
            <label>Nama Lengkap</label>
            <input v-model="form.name" type="text" class="form-input" required placeholder="Cth: Budi NOC" />
          </div>
          
          <div class="form-group">
            <label>Email</label>
            <input v-model="form.email" type="email" class="form-input" required placeholder="Cth: budi@netsight.local" />
          </div>

          <div class="form-group">
            <label>Sandi (Password)</label>
            <input v-model="form.password" type="password" class="form-input" :required="!form.id" :placeholder="form.id ? 'Kosongkan jika tidak ingin ganti sandi' : 'Minimal 8 karakter'" minlength="8" />
          </div>

          <div class="form-group">
            <label>Peran (Role)</label>
            <select v-model="form.role" class="form-input" required>
              <option value="TIER_1">TIER 1 (Pemantauan Dasar)</option>
              <option value="TIER_2">TIER 2 (Inspeksi Torch & Sync)</option>
              <option value="ADMIN">ADMIN (Manajemen Penuh)</option>
            </select>
          </div>

          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input v-model="form.is_active" type="checkbox" />
              <span>Akun Aktif (Dapat Login)</span>
            </label>
          </div>

          <div v-if="formError" class="alert alert-error">{{ formError }}</div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" @click="closeModal">Batal</button>
            <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useStaffStore, Staff } from '../stores/staffStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'

const auth = useAuthStore()
const staffStore = useStaffStore()

const sidebarOpen = ref(false)
const isModalOpen = ref(false)
const isSubmitting = ref(false)
const formError = ref('')

const form = reactive({
  id: null as number | null,
  name: '',
  email: '',
  password: '',
  role: 'TIER_1' as 'TIER_1' | 'TIER_2' | 'ADMIN',
  is_active: true
})

onMounted(() => {
  staffStore.fetchStaffList()
})

function getRoleClass(role: string) {
  if (role === 'ADMIN') return 'badge-danger'
  if (role === 'TIER_2') return 'badge-warning'
  return 'badge-info'
}

function openModal(staff?: Staff) {
  formError.value = ''
  if (staff) {
    form.id = staff.id
    form.name = staff.name
    form.email = staff.email
    form.password = '' // Don't fill password
    form.role = staff.role
    form.is_active = staff.is_active
  } else {
    form.id = null
    form.name = ''
    form.email = ''
    form.password = ''
    form.role = 'TIER_1'
    form.is_active = true
  }
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
}

async function submitForm() {
  isSubmitting.value = true
  formError.value = ''
  
  try {
    const payload: any = {
      name: form.name,
      email: form.email,
      role: form.role,
      is_active: form.is_active
    }
    
    if (form.password) {
      payload.password = form.password
    }
    
    if (form.id) {
      await staffStore.updateStaff(form.id, payload)
    } else {
      await staffStore.createStaff(payload)
    }
    closeModal()
  } catch (e: any) {
    formError.value = staffStore.error || 'Terjadi kesalahan saat menyimpan.'
  } finally {
    isSubmitting.value = false
  }
}

async function confirmDelete(staff: Staff) {
  if (confirm(`Apakah Anda yakin ingin menghapus staf "${staff.name}"?\nIni tidak dapat dibatalkan.`)) {
    try {
      await staffStore.deleteStaff(staff.id)
    } catch (e: any) {
      alert(staffStore.error || 'Gagal menghapus staf.')
    }
  }
}

async function confirmResetTotp(staff: Staff) {
  if (confirm(`PENTING: Reset TOTP untuk "${staff.name}"?\n\nStaf ini akan diwajibkan untuk memindai ulang kode QR MFA pada saat ia login berikutnya.`)) {
    try {
      await staffStore.resetTotp(staff.id)
      alert(`TOTP berhasil direset untuk ${staff.name}.`)
    } catch (e: any) {
      alert(staffStore.error || 'Gagal mereset TOTP.')
    }
  }
}
</script>

<style scoped>
.staff-mgmt {
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

.panel { padding: 24px; }
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

.table-container { overflow-x: auto; }
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .data-table td {
  padding: 12px 16px;
  text-align: left;
  border-bottom: 1px solid var(--glass-border);
  font-size: 0.85rem;
}
.data-table th {
  color: var(--text-muted);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.75rem;
}
.data-table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }

.font-bold { font-weight: 600; }
.text-muted { color: var(--text-muted); }
.text-right { text-align: right; }
.text-center { text-align: center; }
.py-8 { padding-top: 32px !important; padding-bottom: 32px !important; }

/* Badges */
.badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  display: inline-block;
}
.badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
.badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
.badge-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }

.status-totp {
  font-size: 0.8rem;
  font-weight: 600;
}
.status-totp--yes { color: #10b981; }
.status-totp--no { color: var(--text-muted); }

/* Buttons */
.btn {
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.btn-primary { background: var(--accent-blue); color: #fff; }
.btn-primary:hover:not(:disabled) { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
.btn-secondary:hover { background: rgba(255, 255, 255, 0.15); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

.btn-icon {
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-primary);
  border-radius: 4px;
  padding: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 1rem;
}
.btn-icon:hover:not(:disabled) { background: rgba(255, 255, 255, 0.1); border-color: var(--glass-border); }
.btn-icon-danger:hover:not(:disabled) { background: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3); }
.btn-icon-warning:hover:not(:disabled) { background: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.3); }
.btn-icon:disabled { opacity: 0.3; cursor: not-allowed; }

.actions-cell {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(10, 14, 26, 0.8);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
.modal-content {
  width: 100%;
  max-width: 450px;
  padding: 32px;
}
.modal-title {
  margin-top: 0;
  margin-bottom: 24px;
  font-size: 1.2rem;
  color: var(--text-primary);
}
.form-group { margin-bottom: 16px; }
.form-group label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.85rem;
  color: var(--text-secondary);
}
.form-input {
  width: 100%;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  color: var(--text-primary);
  font-family: inherit;
  font-size: 0.9rem;
}
.form-input:focus { outline: none; border-color: var(--accent-blue); box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
.checkbox-group { margin-top: 24px; margin-bottom: 24px; }
.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  color: var(--text-primary) !important;
}
.checkbox-label input[type="checkbox"] {
  width: 18px; height: 18px;
  accent-color: var(--accent-blue);
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
}
.alert { padding: 12px; border-radius: var(--radius-sm); margin-top: 16px; font-size: 0.85rem; }
.alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
</style>
