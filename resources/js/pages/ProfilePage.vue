<template>
  <div class="profile-page">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <section class="dashboard__welcome fade-in">
          <h2 class="dashboard__heading">My <span class="dashboard__heading--accent">Profile</span></h2>
          <p class="dashboard__heading-sub">Kelola keamanan akun dan kata sandi Anda</p>
        </section>

        <section class="stagger profile-grid">
          
          <!-- User Info Summary -->
          <div class="glass-card panel">
            <div class="panel-header">
              <h3 class="panel-title">👤 Informasi Akun</h3>
            </div>
            <div class="info-content">
              <div class="info-item">
                <span class="info-label">Nama</span>
                <span class="info-value">{{ auth.user?.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value">{{ auth.user?.email }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Peran</span>
                <span class="badge" :class="getRoleClass(auth.user?.role || '')">{{ auth.user?.role }}</span>
              </div>
            </div>
          </div>

          <!-- Change Password -->
          <div class="glass-card panel">
            <div class="panel-header">
              <h3 class="panel-title">🔒 Ganti Kata Sandi</h3>
            </div>
            <form @submit.prevent="updatePassword" class="profile-form">
              <div class="form-group">
                <label>Kata Sandi Saat Ini</label>
                <input v-model="passForm.current_password" type="password" class="form-input" required />
              </div>
              <div class="form-group">
                <label>Kata Sandi Baru</label>
                <input v-model="passForm.new_password" type="password" class="form-input" required minlength="6" />
              </div>
              <div class="form-group">
                <label>Konfirmasi Kata Sandi Baru</label>
                <input v-model="passForm.new_password_confirmation" type="password" class="form-input" required minlength="6" />
              </div>
              
              <div v-if="passError" class="alert alert-error">{{ passError }}</div>
              <div v-if="passSuccess" class="alert alert-success">{{ passSuccess }}</div>

              <div class="form-actions text-right mt-4">
                <button type="submit" class="btn btn-primary" :disabled="passLoading">
                  {{ passLoading ? 'Menyimpan...' : 'Simpan Kata Sandi' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Two-Factor Authentication (MFA/TOTP) -->
          <div class="glass-card panel">
            <div class="panel-header flex justify-between items-center">
              <h3 class="panel-title">🛡️ Autentikasi Dua Faktor (MFA)</h3>
              <span v-if="auth.user?.mfa_enabled" class="badge badge-success">✅ Aktif</span>
              <span v-else class="badge badge-warning">❌ Belum Aktif</span>
            </div>

            <div class="profile-form mt-2">
              <p class="text-sm text-gray-400 mb-4">
                Tingkatkan keamanan akun Anda dengan mewajibkan kode 6 digit dari aplikasi authenticator (Google Authenticator, Authy, dll) setiap kali login.
              </p>

              <!-- IF TOTP NOT ACTIVE -->
              <div v-if="!auth.user?.mfa_enabled">
                <button v-if="!showTotpSetup" class="btn btn-primary" @click="startTotpSetup">Setup MFA Sekarang</button>
                
                <div v-if="showTotpSetup" class="totp-setup-area fade-in mt-4">
                  <div v-if="totpLoading" class="text-sm text-gray-400">Loading QR Code...</div>
                  <div v-else-if="totpData" class="flex flex-col gap-4">
                    <div class="flex items-center gap-4 bg-black/30 p-4 rounded border border-gray-800">
                      <div class="bg-white p-2 rounded">
                        <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(totpData.qr_code_url)}`" alt="QR Code" class="w-24 h-24" />
                      </div>
                      <div>
                        <p class="text-sm text-gray-300 mb-1">Scan QR Code ini, atau masukkan kode rahasia berikut secara manual:</p>
                        <code class="text-accent-cyan font-mono font-bold">{{ totpData.secret }}</code>
                      </div>
                    </div>
                    
                    <form @submit.prevent="enableTotp" class="flex items-end gap-3 mt-2">
                      <div class="form-group flex-1 mb-0">
                        <label>Masukkan Kode 6-Digit</label>
                        <input v-model="totpCode" type="text" class="form-input font-mono tracking-widest" maxlength="6" required placeholder="000000" />
                      </div>
                      <button type="submit" class="btn btn-success whitespace-nowrap" :disabled="totpSubmitting">
                        {{ totpSubmitting ? 'Verifikasi...' : 'Aktifkan MFA' }}
                      </button>
                      <button type="button" class="btn btn-secondary whitespace-nowrap" @click="showTotpSetup = false">Batal</button>
                    </form>
                  </div>
                  <div v-if="totpError" class="alert alert-error mt-3">{{ totpError }}</div>
                </div>
              </div>

              <!-- IF TOTP ACTIVE -->
              <div v-else>
                <p class="text-sm text-emerald-400 mb-4 font-medium">Akun Anda sudah terlindungi dengan MFA.</p>
                <button v-if="!showTotpDisable" class="btn btn-danger" @click="showTotpDisable = true">Nonaktifkan MFA</button>
                
                <form v-if="showTotpDisable" @submit.prevent="disableTotp" class="mt-4 fade-in">
                  <div class="form-group">
                    <label>Masukkan Kata Sandi untuk Konfirmasi</label>
                    <input v-model="disableTotpPassword" type="password" class="form-input" required />
                  </div>
                  <div v-if="totpError" class="alert alert-error mt-2">{{ totpError }}</div>
                  <div class="flex gap-2 mt-3">
                    <button type="submit" class="btn btn-danger" :disabled="totpSubmitting">Konfirmasi Nonaktifkan</button>
                    <button type="button" class="btn btn-secondary" @click="showTotpDisable = false">Batal</button>
                  </div>
                </form>
              </div>

            </div>
          </div>

        </section>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import { useAuthStore } from '../stores/authStore'
import api from '../utils/api'

const auth = useAuthStore()
const sidebarOpen = ref(false)

onMounted(() => {
  auth.fetchUser()
})

// Role Badge
function getRoleClass(role: string) {
  if (role === 'ADMIN') return 'badge-danger'
  if (role === 'TIER_2') return 'badge-warning'
  return 'badge-info'
}

// Password State
const passForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})
const passLoading = ref(false)
const passError = ref('')
const passSuccess = ref('')

async function updatePassword() {
  passError.value = ''
  passSuccess.value = ''
  if (passForm.new_password !== passForm.new_password_confirmation) {
    passError.value = 'Konfirmasi kata sandi tidak cocok.'
    return
  }
  
  passLoading.value = true
  try {
    const res = await api.put('/auth/password', passForm)
    passSuccess.value = res.data.message || 'Password berhasil diubah.'
    passForm.current_password = ''
    passForm.new_password = ''
    passForm.new_password_confirmation = ''
  } catch (err: any) {
    passError.value = err.response?.data?.message || 'Gagal mengubah password.'
  } finally {
    passLoading.value = false
  }
}

// TOTP State
const showTotpSetup = ref(false)
const totpLoading = ref(false)
const totpSubmitting = ref(false)
const totpData = ref<{ secret: string, qr_code_url: string } | null>(null)
const totpCode = ref('')
const totpError = ref('')

const showTotpDisable = ref(false)
const disableTotpPassword = ref('')

async function startTotpSetup() {
  showTotpSetup.value = true
  totpLoading.value = true
  totpError.value = ''
  totpCode.value = ''
  
  try {
    const res = await api.get('/auth/generate-totp-setup')
    totpData.value = res.data
  } catch (err: any) {
    totpError.value = 'Gagal memuat kode QR.'
  } finally {
    totpLoading.value = false
  }
}

async function enableTotp() {
  if (!totpData.value || !totpCode.value) return
  
  totpSubmitting.value = true
  totpError.value = ''
  
  try {
    const res = await api.post('/auth/totp/enable', {
      totp_secret: totpData.value.secret,
      totp_code: totpCode.value
    })
    
    alert(res.data.message || 'MFA berhasil diaktifkan!')
    showTotpSetup.value = false
    
    // Refresh auth state
    auth.fetchUser() 
  } catch (err: any) {
    totpError.value = err.response?.data?.message || 'Kode tidak valid.'
  } finally {
    totpSubmitting.value = false
  }
}

async function disableTotp() {
  if (!disableTotpPassword.value) return
  
  totpSubmitting.value = true
  totpError.value = ''
  
  try {
    const res = await api.post('/auth/totp/disable', {
      password: disableTotpPassword.value
    })
    
    alert(res.data.message || 'MFA berhasil dinonaktifkan.')
    showTotpDisable.value = false
    disableTotpPassword.value = ''
    
    // Refresh auth state
    auth.fetchUser()
  } catch (err: any) {
    totpError.value = err.response?.data?.message || 'Password salah.'
  } finally {
    totpSubmitting.value = false
  }
}
</script>

<style scoped>
.profile-page {
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

.profile-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
  max-width: 800px;
}

@media (min-width: 1024px) {
  .profile-grid {
    grid-template-columns: 1fr 1fr;
  }
  .profile-grid > .panel:nth-child(1) { grid-column: span 2; }
}

.panel { padding: 24px; }
.panel-header {
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  padding-bottom: 12px;
}
.panel-title {
  font-size: 1.1rem;
  font-weight: 600;
}

.info-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.info-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.info-label {
  font-size: 0.85rem;
  color: var(--text-muted);
  font-weight: 500;
}
.info-value {
  font-size: 0.95rem;
  font-weight: 600;
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

.alert { padding: 12px; border-radius: var(--radius-sm); margin-top: 16px; font-size: 0.85rem; }
.alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
.alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

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
.btn-success { background: #10b981; color: #fff; }
.btn-success:hover:not(:disabled) { background: #059669; }
.btn-danger { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.btn-danger:hover:not(:disabled) { background: rgba(239, 68, 68, 0.3); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

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

.text-accent-cyan { color: var(--accent-cyan); }
</style>
