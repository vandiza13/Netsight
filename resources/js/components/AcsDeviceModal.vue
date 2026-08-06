<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-container fade-in-up">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-header-left">
          <div class="modal-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
          </div>
          <h3 class="modal-title">ACS Device Control</h3>
        </div>
        <button class="modal-close-btn" @click="close">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        
        <div class="modal-tabs">
          <button type="button" :class="['tab-btn', { active: activeTab === 'config' }]" @click="activeTab = 'config'">Overview & Config</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'hosts' }]" @click="activeTab = 'hosts'">Connected Devices</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'diag' }]" @click="activeTab = 'diag'">Diagnostics</button>
        </div>

        <!-- Config Tab -->
        <div v-if="activeTab === 'config'" class="tab-pane fade-in">
          <!-- Modem Info Grid -->
          <div class="info-grid">
            <div class="info-cell">
              <span class="info-label">Status</span>
              <div class="status-cell">
                <span :class="['status-glow', device?.status === 'online' ? 'online' : 'offline']"></span>
                <span class="info-value capitalize">{{ device?.status || 'unknown' }}</span>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Optical Power</span>
              <div class="info-value-row">
                <span :class="getRxPowerColor(device?.rx_power_dbm)" class="info-value-bold">{{ device?.rx_power_dbm ?? 'N/A' }}</span>
                <span v-if="device?.rx_power_dbm" class="info-unit">dBm</span>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Model / Vendor</span>
              <span class="info-value">{{ device?.model || 'Unknown' }}</span>
              <span class="info-sub">{{ device?.vendor }}</span>
            </div>

            <div class="info-cell">
              <span class="info-label">IP Address</span>
              <span class="info-value">{{ device?.ip_address || 'N/A' }}</span>
            </div>
          </div>

          <hr class="modal-divider" />

          <!-- Config Forms -->
          <form @submit.prevent="saveWifi">
            <h4 class="form-section-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
              Wi-Fi Configuration
            </h4>

            <div class="form-stack">
              <div class="form-group">
                <label class="form-label">Frekuensi (Band)</label>
                <div class="band-selector">
                  <label class="radio-label">
                    <input type="radio" v-model="form.band" value="1"> 2.4 GHz
                  </label>
                  <label class="radio-label">
                    <input type="radio" v-model="form.band" value="5"> 5 GHz
                  </label>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">SSID Name</label>
                <div class="input-icon-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
                  <input v-model="form.ssid" type="text" required class="input-modern input-has-icon" placeholder="Network Name">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Wi-Fi Password</label>
                <div class="input-icon-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  <input v-model="form.password" :type="showPassword ? 'text' : 'password'" minlength="8" class="input-modern input-has-icon input-has-action" placeholder="Biarkan kosong jika tidak diubah">
                  <button type="button" @click="showPassword = !showPassword" class="input-action-btn">
                    <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
                </div>
                <span class="form-hint">Kosongkan jika hanya mengubah SSID</span>
              </div>
            </div>

            <!-- Error Alert -->
            <div v-if="error" class="alert-box alert-box--danger mt-3">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span>{{ error }}</span>
            </div>

            <!-- Config Footer Actions -->
            <div class="modal-footer">
              <div class="modal-footer-left">
                <button type="button" @click="reboot" :disabled="isRebooting || isResetting" class="btn btn-danger-outline btn-icon-text">
                  <svg v-if="!isRebooting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
                  <span v-if="isRebooting" class="spinning">🔄</span>
                  <span>{{ isRebooting ? 'Rebooting...' : 'Reboot' }}</span>
                </button>
                
                <button type="button" @click="factoryReset" :disabled="isRebooting || isResetting" class="btn btn-danger btn-icon-text">
                  <svg v-if="!isResetting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 12v.01"/></svg>
                  <span v-if="isResetting" class="spinning">🔄</span>
                  <span>{{ isResetting ? 'Resetting...' : 'Factory Reset' }}</span>
                </button>
              </div>

              <div class="modal-footer-right">
                <button type="button" @click="close" class="btn btn-secondary">Cancel</button>
                <button type="submit" :disabled="isSaving" class="btn btn-primary btn-icon-text">
                  <span v-if="isSaving" class="spinning">🔄</span>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>{{ isSaving ? 'Applying...' : 'Apply Changes' }}</span>
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Hosts Tab -->
        <div v-if="activeTab === 'hosts'" class="tab-pane fade-in">
          <div class="hosts-header">
            <h4 class="form-section-title mb-0">Connected Devices</h4>
            <button type="button" @click="refreshHosts" :disabled="isRefreshingHosts" class="btn btn-primary-outline btn-sm btn-icon-text">
              <span v-if="isRefreshingHosts" class="spinning">🔄</span>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              <span>{{ isRefreshingHosts ? 'Refreshing...' : 'Refresh dari Modem' }}</span>
            </button>
          </div>

          <div v-if="isLoadingHosts" class="alert-box alert-box--warning mt-3">
            <span class="spinning mr-2">🔄</span> Mengambil data klien...
          </div>
          <div v-else-if="hostsError" class="alert-box alert-box--danger mt-3">
            <span>{{ hostsError }}</span>
          </div>
          <div v-else-if="hosts.length === 0" class="empty-state mt-3">
            Belum ada data perangkat yang terhubung.
          </div>
          <div v-else class="table-responsive custom-scrollbar mt-3">
            <table class="table hosts-table">
              <thead>
                <tr>
                  <th>Hostname</th>
                  <th>IP Address</th>
                  <th>MAC Address</th>
                  <th>Tipe</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="host in hosts" :key="host.mac">
                  <td><strong>{{ host.hostname }}</strong></td>
                  <td><span class="badge badge-outline">{{ host.ip }}</span></td>
                  <td class="color-muted">{{ host.mac }}</td>
                  <td>
                    <span :class="['badge', String(host.type).includes('802.11') ? 'badge-primary' : 'badge-secondary']">
                      {{ host.type }}
                    </span>
                  </td>
                  <td>
                    <span class="status-cell">
                      <span :class="['status-glow', host.active ? 'online' : 'offline']"></span>
                      {{ host.active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Diagnostics Tab -->
        <div v-if="activeTab === 'diag'" class="tab-pane fade-in">
          <div class="hosts-header">
            <h4 class="form-section-title mb-0">Diagnostic Tools</h4>
          </div>

          <div class="form-stack mt-3">
            <div class="form-group">
              <label class="form-label">Ping Target (IP / Domain)</label>
              <div class="ping-input-row">
                <input v-model="pingForm.host" type="text" class="input-modern" placeholder="e.g. 8.8.8.8">
                <button type="button" @click="triggerPing" :disabled="isPinging" class="btn btn-primary">
                  <span v-if="isPinging" class="spinning mr-2">🔄</span>
                  {{ isPinging ? 'Pinging...' : 'Ping' }}
                </button>
              </div>
              <span class="form-hint">Tekan Ping untuk mengirim perintah ke modem. Proses memakan waktu sekitar 10 detik.</span>
            </div>
          </div>

          <div v-if="pingError" class="alert-box alert-box--danger mt-3">
            <span>{{ pingError }}</span>
          </div>

          <div v-if="pingResult" class="ping-result-box mt-3">
            <div class="ping-result-header">
              <strong class="color-text-1">Hasil Ping (State: {{ pingResult.state }})</strong>
              <button v-if="pingResult.state === 'Requested' || pingResult.state === 'None'" type="button" @click="fetchPingResult" class="btn btn-secondary btn-sm" :disabled="isFetchingPing">
                 <span v-if="isFetchingPing" class="spinning mr-2">🔄</span> Cek Hasil
              </button>
            </div>
            
            <div class="ping-stats" v-if="pingResult.state === 'Complete'">
              <div class="ping-stat">
                <span class="ping-label">Success</span>
                <span class="ping-value color-good">{{ pingResult.success_count }}</span>
              </div>
              <div class="ping-stat">
                <span class="ping-label">Failure</span>
                <span class="ping-value color-critical">{{ pingResult.failure_count }}</span>
              </div>
              <div class="ping-stat">
                <span class="ping-label">Avg. MS</span>
                <span class="ping-value">{{ pingResult.avg_response_time }}</span>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps<{
  show: boolean
  device: any
}>()

const emit = defineEmits(['close', 'updated'])

const store = useAcsStore()

const showPassword = ref(false)
const error = ref('')
const hostsError = ref('')
const isSaving = ref(false)
const isRebooting = ref(false)
const isResetting = ref(false)

const activeTab = ref('config')
const hosts = ref<any[]>([])
const isLoadingHosts = ref(false)
const isRefreshingHosts = ref(false)

const isPinging = ref(false)
const isFetchingPing = ref(false)
const pingError = ref('')
const pingResult = ref<any>(null)

const pingForm = reactive({
  host: '8.8.8.8'
})

const form = reactive({
  ssid: '',
  password: '',
  band: '1'
})

watch(() => props.show, (newVal) => {
  if (newVal && props.device) {
    form.ssid = props.device.wifi_ssid || ''
    form.password = ''
    form.band = '1'
    error.value = ''
    hostsError.value = ''
    pingError.value = ''
    pingResult.value = null
    showPassword.value = false
    activeTab.value = 'config'
    hosts.value = []
    
    // Auto load hosts if not loaded
    loadHosts()
  }
})

const loadHosts = async () => {
  if (!props.device) return
  isLoadingHosts.value = true
  hostsError.value = ''
  try {
    hosts.value = await store.fetchDeviceHosts(props.device.id)
  } catch (err: any) {
    hostsError.value = err.message
  } finally {
    isLoadingHosts.value = false
  }
}

const refreshHosts = async () => {
  if (!props.device) return
  isRefreshingHosts.value = true
  hostsError.value = ''
  try {
    await store.refreshDeviceHosts(props.device.id)
    // Re-load hosts after refresh task is sent
    // Note: TR-069 tasks are async. Data might not change instantly.
    setTimeout(loadHosts, 3000)
    emit('updated', 'Perintah refresh klien dikirim ke modem.')
  } catch (err: any) {
    hostsError.value = err.message
  } finally {
    isRefreshingHosts.value = false
  }
}

const getRxPowerColor = (power: number | null) => {
  if (power === null || power === undefined) return 'color-muted'
  if (power >= -25 && power <= -8) return 'color-good'
  if ((power < -25 && power >= -28) || (power > -8 && power <= -3)) return 'color-warning'
  return 'color-critical'
}

const close = () => {
  emit('close')
}

const saveWifi = async () => {
  error.value = ''
  isSaving.value = true
  try {
    await store.updateWifi(props.device.id, form.ssid, form.password, form.band)
    emit('updated', 'WiFi config update requested.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isSaving.value = false
  }
}

const triggerPing = async () => {
  if (!props.device) return
  if (!pingForm.host) {
    pingError.value = 'Target host tidak boleh kosong.'
    return
  }
  
  isPinging.value = true
  pingError.value = ''
  pingResult.value = { state: 'Requested' }
  
  try {
    await store.triggerPing(props.device.id, pingForm.host)
    // Auto fetch result after 10 seconds
    setTimeout(() => {
      fetchPingResult()
    }, 10000)
  } catch (err: any) {
    pingError.value = err.message
  } finally {
    isPinging.value = false
  }
}

const fetchPingResult = async () => {
  if (!props.device) return
  isFetchingPing.value = true
  pingError.value = ''
  try {
    const res = await store.fetchPingResult(props.device.id)
    if (res) {
      pingResult.value = res
    }
  } catch (err: any) {
    pingError.value = err.message
  } finally {
    isFetchingPing.value = false
  }
}

const reboot = async () => {
  if (!confirm('Yakin ingin mereboot modem ini? Koneksi pelanggan akan terputus sementara.')) {
    return
  }

  error.value = ''
  isRebooting.value = true
  try {
    await store.rebootDevice(props.device.id)
    emit('updated', 'Reboot command sent to modem.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isRebooting.value = false
  }
}

const factoryReset = async () => {
  const confirmText = prompt('BAHAYA: Modem akan di-reset ke setelan pabrik dan pelanggan akan kehilangan koneksi internet. Ketik "RESET" untuk melanjutkan:')
  if (confirmText !== 'RESET') {
    if (confirmText !== null) alert('Konfirmasi dibatalkan. Anda harus mengetik "RESET".')
    return
  }

  error.value = ''
  isResetting.value = true
  try {
    await store.factoryResetDevice(props.device.id)
    emit('updated', 'Factory Reset command sent to modem.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isResetting.value = false
  }
}
</script>

<style scoped>
/* ── Modal Overlay ──────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  padding: 16px;
}

/* ── Modal Container ────────────────────────────────────── */
.modal-container {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.04);
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
}

/* ── Header ─────────────────────────────────────────────── */
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}
.modal-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.modal-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--accent-dim);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent);
}
.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-1);
  margin: 0;
}
.modal-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-3);
  transition: all 0.2s;
  cursor: pointer;
  background: none;
  border: none;
}
.modal-close-btn:hover {
  background: var(--surface-2);
  color: var(--text-1);
}

/* ── Body ───────────────────────────────────────────────── */
.modal-body {
  padding: 24px;
}

/* ── Info Grid ──────────────────────────────────────────── */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}
.info-cell {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
}
.info-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-3);
  margin-bottom: 6px;
}
.info-value {
  color: var(--text-1);
  font-weight: 500;
  display: block;
}
.info-sub {
  display: block;
  font-size: 0.8rem;
  color: var(--text-3);
  margin-top: 2px;
}
.info-value-row {
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.info-value-bold {
  font-size: 1.25rem;
  font-weight: 700;
}
.info-unit {
  font-size: 0.8rem;
  color: var(--text-3);
}
.capitalize { text-transform: capitalize; }

/* ── Color helpers for rx_power ─────────────────────────── */
.color-good { color: #10b981; }
.color-warning { color: #f59e0b; }
.color-critical { color: #f43f5e; }
.color-muted { color: var(--text-3); }

/* ── Status in info grid ────────────────────────────────── */
.status-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.status-glow {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-glow.online { background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.6); }
.status-glow.offline { background: #f43f5e; box-shadow: 0 0 10px rgba(244, 63, 94, 0.6); }

/* ── Divider ────────────────────────────────────────────── */
.modal-divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 0 0 20px 0;
}

/* ── Form ───────────────────────────────────────────────── */
.form-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--accent);
  margin: 0 0 16px 0;
}
.form-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.form-group {
  display: flex;
  flex-direction: column;
}
.form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-2);
  margin-bottom: 6px;
}
.form-hint {
  font-size: 0.75rem;
  color: var(--text-3);
  margin-top: 4px;
}

/* ── Input ──────────────────────────────────────────────── */
.input-icon-wrap {
  position: relative;
}
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  pointer-events: none;
}
.input-modern {
  width: 100%;
  box-sizing: border-box;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 0.875rem;
  color: var(--text-1);
  transition: all 0.2s;
}
.input-modern:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-dim);
}
.input-modern::placeholder {
  color: var(--text-3);
}
.input-has-icon {
  padding-left: 42px;
}
.input-has-action {
  padding-right: 42px;
}
.input-action-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  cursor: pointer;
  background: none;
  border: none;
  padding: 4px;
  border-radius: 4px;
  transition: color 0.2s;
}
.input-action-btn:hover {
  color: var(--text-1);
}

/* ── Alert ──────────────────────────────────────────────── */
.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  margin-top: 14px;
  font-size: 0.85rem;
  font-weight: 500;
}
.alert-box svg { flex-shrink: 0; }
.alert-box--danger {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.2);
  color: #f43f5e;
}

/* ── Buttons ────────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border: 1px solid transparent;
}
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-primary {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
}
.btn-primary:hover:not(:disabled) {
  background: var(--accent-hover);
  box-shadow: 0 0 20px var(--accent-glow);
}
.btn-secondary {
  background: var(--surface-2);
  color: var(--text-2);
  border-color: var(--border);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--surface-3);
  color: var(--text-1);
}
.btn-danger-outline {
  background: rgba(245, 158, 11, 0.06);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.2);
}
.btn-danger-outline:hover:not(:disabled) {
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}
.btn-danger {
  background: #f43f5e;
  color: #fff;
  border: 1px solid #f43f5e;
}
.btn-danger:hover:not(:disabled) {
  background: #e11d48;
  box-shadow: 0 0 20px rgba(244, 63, 94, 0.4);
}
.btn-icon-text svg { flex-shrink: 0; }

/* ── Tabs ───────────────────────────────────────────────── */
.modal-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 12px;
}
.tab-btn {
  background: none;
  border: none;
  color: var(--text-3);
  font-size: 0.95rem;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn:hover {
  background: var(--surface-2);
  color: var(--text-2);
}
.tab-btn.active {
  background: var(--surface-3);
  color: var(--text-1);
}
.tab-pane {
  animation: fadeIn 0.3s ease;
}

/* ── Hosts Table ────────────────────────────────────────── */
.hosts-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mb-0 { margin-bottom: 0 !important; }
.mt-3 { margin-top: 16px; }
.mr-2 { margin-right: 8px; }
.hosts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.hosts-table th {
  text-align: left;
  padding: 12px;
  color: var(--text-3);
  border-bottom: 1px solid var(--border);
  font-weight: 500;
}
.hosts-table td {
  padding: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.02);
  color: var(--text-2);
}
.hosts-table tr:hover td {
  background: var(--surface-2);
}

/* ── Form Extensions ────────────────────────────────────── */
.band-selector {
  display: flex;
  gap: 16px;
  margin-top: 8px;
}
.radio-label {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-2);
  cursor: pointer;
  font-size: 0.95rem;
}
.ping-input-row {
  display: flex;
  gap: 8px;
}
.ping-input-row input {
  flex: 1;
}

/* ── Ping Diagnostics Box ───────────────────────────────── */
.ping-result-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
}
.ping-result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.ping-stats {
  display: flex;
  gap: 24px;
  background: var(--surface-3);
  padding: 12px;
  border-radius: 6px;
}
.ping-stat {
  display: flex;
  flex-direction: column;
}
.ping-label {
  font-size: 0.8rem;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.ping-value {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 4px;
}

/* ── Footer ─────────────────────────────────────────────── */
.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}
.modal-footer-left {
  display: flex;
  gap: 10px;
}
.modal-footer-right {
  display: flex;
  gap: 10px;
}

/* ── Animations ─────────────────────────────────────────── */
.fade-in-up {
  animation: fadeInUp 0.3s ease-out forwards;
}
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(16px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Mobile ─────────────────────────────────────────────── */
@media (max-width: 640px) {
  .modal-container {
    max-height: 95vh;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .modal-footer {
    flex-direction: column;
    gap: 12px;
  }
  .modal-footer-right {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
