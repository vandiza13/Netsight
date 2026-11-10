<template>
  <div v-if="show" class="profiles-overlay" @click.self="close">
    <div class="profiles-modal slide-in-up">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-left">
          <div class="header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          </div>
          <div>
            <h3 class="modal-title">Kelola Profil Provisioning ACS</h3>
            <span class="modal-subtitle">Konfigurasi otomatis &amp; Zero-Touch TR-069 untuk ONT baru dan massal</span>
          </div>
        </div>
        <button class="close-btn" @click="close" title="Tutup">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body custom-scrollbar">
        <!-- View Mode: List vs Form -->
        <div v-if="mode === 'list'" class="profile-list-view">
          <div class="actions-bar">
            <p class="section-desc">
              Profil provisioning menentukan parameter standar (interval inform, kata sandi web admin, template SSID) yang dapat dipush massal atau dipasang otomatis (Zero-Touch) saat modem baru terdeteksi.
            </p>
            <button class="btn btn-primary btn-sm" @click="openCreateForm">
              + Buat Profil Baru
            </button>
          </div>

          <!-- Loading & Empty States -->
          <div v-if="isLoading" class="loading-state">
            <span class="spinning">🔄</span>
            <p>Memuat profil provisioning...</p>
          </div>
          <div v-else-if="profiles.length === 0" class="empty-state">
            <div class="empty-icon">📋</div>
            <h4>Belum Ada Profil Provisioning</h4>
            <p>Buat profil pertama Anda untuk mengotomatiskan setup modem pelanggan baru.</p>
            <button class="btn btn-primary btn-sm mt-3" @click="openCreateForm">+ Buat Profil</button>
          </div>

          <!-- Profiles Cards Grid -->
          <div v-else class="profiles-grid">
            <div
              v-for="prof in profiles"
              :key="prof.id"
              class="profile-card"
              :class="{ 'card-inactive': !prof.is_active }"
            >
              <div class="card-top">
                <div>
                  <div class="flex align-center gap-2">
                    <h4 class="card-name">{{ prof.name }}</h4>
                    <span v-if="prof.is_auto_apply" class="pill-badge pill-auto" title="Diterapkan otomatis untuk ONT baru yang cocok">
                      ⚡ Zero-Touch
                    </span>
                    <span v-if="!prof.is_active" class="pill-badge pill-inactive">Nonaktif</span>
                  </div>
                  <p class="card-desc">{{ prof.description || 'Tidak ada deskripsi.' }}</p>
                </div>
                <div class="card-actions">
                  <button class="icon-btn" @click="openEditForm(prof)" title="Edit Profil">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                  </button>
                  <button class="icon-btn text-rose" @click="handleDelete(prof)" title="Hapus Profil">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </div>
              </div>

              <div class="card-specs">
                <div class="spec-item">
                  <span class="spec-label">Target Vendor:</span>
                  <span class="spec-val">{{ prof.vendor || 'Semua Vendor' }}</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Target Model:</span>
                  <span class="spec-val">{{ prof.model || 'Semua Model' }}</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Inform Interval:</span>
                  <span class="spec-val">{{ prof.periodic_inform_interval || 300 }} detik</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Admin Web Pass:</span>
                  <span class="spec-val">{{ prof.admin_password ? '••••••••' : '(Tidak diubah)' }}</span>
                </div>
                <div class="spec-item" v-if="prof.wifi_ssid_template">
                  <span class="spec-label">Template SSID:</span>
                  <span class="spec-val code">{{ prof.wifi_ssid_template }}</span>
                </div>
                <div class="spec-item">
                  <span class="spec-label">Perangkat Terkait:</span>
                  <span class="spec-val highlight">{{ prof.devices_count || 0 }} ONT</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Mode: Create / Edit -->
        <div v-else class="profile-form-view">
          <div class="form-header-bar">
            <button class="btn btn-secondary btn-sm" @click="mode = 'list'">
              &larr; Kembali ke Daftar
            </button>
            <h4 class="form-title">{{ form.id ? 'Edit Profil Provisioning' : 'Tambah Profil Baru' }}</h4>
          </div>

          <form @submit.prevent="handleSubmit" class="profile-form">
            <div class="form-grid">
              <!-- Name -->
              <div class="form-group col-span-2">
                <label>Nama Profil <span class="text-rose">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  placeholder="Contoh: Standar ZTE F477 / F660"
                  required
                />
              </div>

              <!-- Description -->
              <div class="form-group col-span-2">
                <label>Deskripsi (Opsional)</label>
                <textarea
                  v-model="form.description"
                  class="form-control"
                  rows="2"
                  placeholder="Catatan penggunaan profil ini..."
                ></textarea>
              </div>

              <!-- Target Vendor -->
              <div class="form-group">
                <label>Target Vendor</label>
                <input
                  v-model="form.vendor"
                  type="text"
                  class="form-control"
                  placeholder="Kosongkan untuk semua vendor (ZTE, Huawei, dll)"
                />
              </div>

              <!-- Target Model -->
              <div class="form-group">
                <label>Target Model</label>
                <input
                  v-model="form.model"
                  type="text"
                  class="form-control"
                  placeholder="Kosongkan untuk semua model (F477V2, F609, dll)"
                />
              </div>

              <!-- Periodic Inform Interval -->
              <div class="form-group">
                <label>Interval Inform CWMP (Detik)</label>
                <input
                  v-model.number="form.periodic_inform_interval"
                  type="number"
                  min="30"
                  max="86400"
                  class="form-control"
                  placeholder="300"
                />
                <small class="help-text">Rekomendasi: 300 detik (5 menit).</small>
              </div>

              <!-- Admin Password Hardening -->
              <div class="form-group">
                <label>Kata Sandi Web Admin ONT</label>
                <input
                  v-model="form.admin_password"
                  type="text"
                  class="form-control font-mono"
                  placeholder="Kosongkan jika tidak ingin mengubah password bawaan"
                />
                <small class="help-text">Mengubah password web login modem untuk keamanan.</small>
              </div>

              <!-- WiFi SSID Template -->
              <div class="form-group">
                <label>Template SSID Wi-Fi</label>
                <input
                  v-model="form.wifi_ssid_template"
                  type="text"
                  class="form-control"
                  placeholder="{pppoe_username}_Home"
                />
                <small class="help-text">Token: <code>{pppoe_username}</code>, <code>{serial_number}</code></small>
              </div>

              <!-- WiFi Password Template -->
              <div class="form-group">
                <label>Template Sandi Wi-Fi</label>
                <input
                  v-model="form.wifi_password_template"
                  type="text"
                  class="form-control font-mono"
                  placeholder="{pppoe_username}123"
                />
              </div>

              <!-- Toggles -->
              <div class="form-group col-span-2 toggles-wrap">
                <label class="toggle-card">
                  <input type="checkbox" v-model="form.is_auto_apply" />
                  <div class="toggle-info">
                    <span class="toggle-title">⚡ Zero-Touch Auto-Provisioning</span>
                    <span class="toggle-desc">
                      Terapkan profil ini secara otomatis pada setiap ONT baru yang terdeteksi dan cocok dengan vendor/model di atas.
                    </span>
                  </div>
                </label>

                <label class="toggle-card">
                  <input type="checkbox" v-model="form.is_active" />
                  <div class="toggle-info">
                    <span class="toggle-title">Status Profil Aktif</span>
                    <span class="toggle-desc">Hanya profil aktif yang dapat digunakan untuk operasi massal dan Zero-Touch.</span>
                  </div>
                </label>
              </div>
            </div>

            <div class="form-actions">
              <button type="button" class="btn btn-secondary" @click="mode = 'list'">Batal</button>
              <button type="submit" class="btn btn-primary" :disabled="isSaving">
                {{ isSaving ? 'Menyimpan...' : 'Simpan Profil' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'updated'])
const store = useAcsStore()

const mode = ref<'list' | 'form'>('list')
const isLoading = ref(false)
const isSaving = ref(false)
const profiles = ref<any[]>([])

const form = reactive({
  id: null as number | null,
  name: '',
  description: '',
  vendor: '',
  model: '',
  periodic_inform_interval: 300,
  admin_username: 'admin',
  admin_password: '',
  wifi_ssid_template: '',
  wifi_password_template: '',
  is_auto_apply: false,
  is_active: true
})

const loadProfiles = async () => {
  isLoading.value = true
  try {
    profiles.value = await store.fetchProfiles()
  } finally {
    isLoading.value = false
  }
}

const openCreateForm = () => {
  Object.assign(form, {
    id: null,
    name: '',
    description: '',
    vendor: '',
    model: '',
    periodic_inform_interval: 300,
    admin_username: 'admin',
    admin_password: '',
    wifi_ssid_template: '',
    wifi_password_template: '',
    is_auto_apply: false,
    is_active: true
  })
  mode.value = 'form'
}

const openEditForm = (prof: any) => {
  Object.assign(form, {
    id: prof.id,
    name: prof.name,
    description: prof.description || '',
    vendor: prof.vendor || '',
    model: prof.model || '',
    periodic_inform_interval: prof.periodic_inform_interval || 300,
    admin_username: prof.admin_username || 'admin',
    admin_password: prof.admin_password || '',
    wifi_ssid_template: prof.wifi_ssid_template || '',
    wifi_password_template: prof.wifi_password_template || '',
    is_auto_apply: Boolean(prof.is_auto_apply),
    is_active: Boolean(prof.is_active)
  })
  mode.value = 'form'
}

const handleSubmit = async () => {
  isSaving.value = true
  try {
    await store.saveProfile(form)
    await loadProfiles()
    mode.value = 'list'
    emit('updated')
  } catch (err: any) {
    alert(err.message || 'Gagal menyimpan profil.')
  } finally {
    isSaving.value = false
  }
}

const handleDelete = async (prof: any) => {
  if (confirm(`Apakah Anda yakin ingin menghapus profil "${prof.name}"?`)) {
    try {
      await store.deleteProfile(prof.id)
      await loadProfiles()
      emit('updated')
    } catch (err: any) {
      alert(err.message || 'Gagal menghapus profil.')
    }
  }
}

const close = () => {
  emit('close')
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    mode.value = 'list'
    loadProfiles()
  }
})
</script>

<style scoped>
.profiles-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1040;
  padding: 1.5rem;
}

.profiles-modal {
  width: 100%;
  max-width: 860px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  max-height: 88vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.5);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.header-icon {
  width: 42px;
  height: 42px;
  border-radius: 0.625rem;
  background: rgba(168, 85, 247, 0.15);
  color: #c084fc;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(168, 85, 247, 0.25);
}

.modal-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.modal-subtitle {
  font-size: 0.8125rem;
  color: #94a3b8;
}

.close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.35rem;
  border-radius: 0.375rem;
  transition: all 0.2s;
}

.close-btn:hover {
  color: #f8fafc;
  background: rgba(255, 255, 255, 0.1);
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}

.actions-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
  gap: 1rem;
}

.section-desc {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0;
  line-height: 1.4;
}

.profiles-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1rem;
}

.profile-card {
  background: rgba(30, 41, 59, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: all 0.2s;
}

.profile-card:hover {
  border-color: rgba(168, 85, 247, 0.3);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.card-inactive {
  opacity: 0.6;
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.card-name {
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.card-desc {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0.25rem 0 0;
}

.card-actions {
  display: flex;
  gap: 0.25rem;
}

.icon-btn {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #cbd5e1;
  padding: 0.4rem;
  border-radius: 0.375rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.icon-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
}

.pill-badge {
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
}

.pill-auto {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.pill-inactive {
  background: rgba(100, 116, 139, 0.2);
  color: #94a3b8;
  border: 1px solid rgba(100, 116, 139, 0.4);
}

.card-specs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1rem;
  font-size: 0.8125rem;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding-top: 0.75rem;
}

.spec-item {
  display: flex;
  flex-direction: column;
}

.spec-label {
  font-size: 0.6875rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.spec-val {
  color: #cbd5e1;
  font-weight: 500;
}

.spec-val.code {
  font-family: monospace;
  color: #38bdf8;
}

.spec-val.highlight {
  color: #a855f7;
  font-weight: 700;
}

/* Form Styles */
.form-header-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.form-title {
  font-size: 1.0625rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.col-span-2 {
  grid-column: span 2;
}

.form-group label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #cbd5e1;
  margin-bottom: 0.35rem;
}

.form-control {
  width: 100%;
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  color: #f8fafc;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.form-control:focus {
  outline: none;
  border-color: #a855f7;
  box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.2);
}

.help-text {
  display: block;
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.help-text code {
  background: rgba(255, 255, 255, 0.06);
  padding: 0.1rem 0.3rem;
  border-radius: 0.2rem;
  color: #38bdf8;
}

.toggles-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.toggle-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  background: rgba(30, 41, 59, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.5rem;
  padding: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.toggle-card:hover {
  background: rgba(30, 41, 59, 0.7);
}

.toggle-card input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #a855f7;
  margin-top: 0.15rem;
}

.toggle-info {
  display: flex;
  flex-direction: column;
}

.toggle-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #f8fafc;
}

.toggle-desc {
  font-size: 0.75rem;
  color: #94a3b8;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.loading-state, .empty-state {
  padding: 3rem;
  text-align: center;
  color: #94a3b8;
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.font-mono {
  font-family: monospace;
}
</style>
