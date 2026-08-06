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
          <div v-if="error" class="alert-box alert-box--danger">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>{{ error }}</span>
          </div>

          <!-- Footer Actions -->
          <div class="modal-footer">
            <button type="button" @click="reboot" :disabled="isRebooting" class="btn btn-danger-outline btn-icon-text">
              <svg v-if="!isRebooting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              <span v-if="isRebooting" class="spinning">🔄</span>
              <span>{{ isRebooting ? 'Rebooting...' : 'Reboot Modem' }}</span>
            </button>

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
const isSaving = ref(false)
const isRebooting = ref(false)

const form = reactive({
  ssid: '',
  password: ''
})

watch(() => props.show, (newVal) => {
  if (newVal && props.device) {
    form.ssid = props.device.wifi_ssid || ''
    form.password = ''
    error.value = ''
    showPassword.value = false
  }
})

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
    await store.updateWifi(props.device.id, form.ssid, form.password)
    emit('updated', 'WiFi config update requested.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isSaving.value = false
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
.btn-icon-text svg { flex-shrink: 0; }

/* ── Footer ─────────────────────────────────────────────── */
.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
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
