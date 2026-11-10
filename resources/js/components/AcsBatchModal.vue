<template>
  <div v-if="show" class="batch-overlay" @click.self="handleOverlayClick">
    <div class="batch-modal slide-in-down">
      <!-- Header -->
      <div class="batch-header">
        <div class="batch-header-left">
          <div class="batch-icon-wrap" :class="{ 'spinning': batchData?.status === 'running' }">
            <svg v-if="batchData?.status === 'running'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <svg v-else-if="batchData?.failed > 0" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <div>
            <h3 class="batch-title">Eksekusi Operasi Massal</h3>
            <span class="batch-subtitle">
              ID Batch: <code>{{ batchId }}</code> &bull; Tindakan: <strong class="capitalize">{{ actionLabel }}</strong>
            </span>
          </div>
        </div>
        <button class="batch-close-btn" @click="close" :disabled="batchData?.status === 'running'" title="Tutup">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="batch-body custom-scrollbar">
        <!-- Progress Bar & Summary -->
        <div class="batch-progress-card">
          <div class="progress-info-row">
            <span class="progress-status-text">
              <span v-if="batchData?.status === 'running'" class="pulse-dot"></span>
              {{ statusText }}
            </span>
            <span class="progress-percent-text">{{ percentComplete }}%</span>
          </div>

          <div class="progress-bar-track">
            <div
              class="progress-bar-fill"
              :class="{ 'bar-complete': batchData?.status === 'completed' }"
              :style="{ width: percentComplete + '%' }"
            ></div>
          </div>

          <div class="batch-stats-grid">
            <div class="b-stat">
              <span class="b-val">{{ batchData?.total || 0 }}</span>
              <span class="b-lbl">Total Target</span>
            </div>
            <div class="b-stat text-emerald">
              <span class="b-val">{{ batchData?.success || 0 }}</span>
              <span class="b-lbl">Berhasil</span>
            </div>
            <div class="b-stat text-rose">
              <span class="b-val">{{ batchData?.failed || 0 }}</span>
              <span class="b-lbl">Gagal</span>
            </div>
            <div class="b-stat text-amber">
              <span class="b-val">{{ pendingCount }}</span>
              <span class="b-lbl">Dalam Antrean</span>
            </div>
          </div>
        </div>

        <!-- Task Items Breakdown -->
        <div class="batch-tasks-section">
          <h4 class="section-heading">Rincian Perangkat ({{ batchData?.tasks?.length || 0 }})</h4>

          <div v-if="!batchData?.tasks || batchData.tasks.length === 0" class="empty-batch-tasks">
            <div class="spinning-icon">⏳</div>
            <p>Menyiapkan antrean tugas untuk perangkat...</p>
          </div>

          <div v-else class="batch-task-table-wrap">
            <table class="batch-table">
              <thead>
                <tr>
                  <th>Perangkat / SN</th>
                  <th>IP / Model</th>
                  <th>Tipe Eksekusi</th>
                  <th>Status</th>
                  <th>Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in batchData.tasks" :key="t.id">
                  <td>
                    <div class="dev-sn">{{ t.device?.serial_number || t.genieacs_id }}</div>
                    <div class="dev-vendor">{{ t.device?.vendor || 'Unknown Vendor' }}</div>
                  </td>
                  <td>
                    <div class="dev-ip">{{ t.device?.ip_address || '-' }}</div>
                    <div class="dev-model">{{ t.device?.model || '-' }}</div>
                  </td>
                  <td>
                    <span class="badge-subtle">{{ t.execution_type }}</span>
                  </td>
                  <td>
                    <span v-if="t.status === 'success'" class="task-badge badge-success">✓ Berhasil</span>
                    <span v-else-if="t.status === 'failed'" class="task-badge badge-failed">✗ Gagal</span>
                    <span v-else class="task-badge badge-pending">⏳ Antrean</span>
                  </td>
                  <td class="task-error-cell">
                    <span v-if="t.error_message" class="error-msg" :title="t.error_message">
                      {{ t.error_message }}
                    </span>
                    <span v-else-if="t.status === 'success'" class="text-emerald text-xs">
                      Tugas dikirim ke NBI
                    </span>
                    <span v-else class="text-slate text-xs">Menunggu CPE...</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="batch-footer">
        <span class="footer-note" v-if="batchData?.status === 'running'">
          ⚠️ Harap tunggu, operasi sedang diproses bertahap agar tidak membebani server ACS.
        </span>
        <span class="footer-note text-emerald" v-else>
          ✅ Seluruh perintah massal telah selesai dieksekusi.
        </span>
        <button
          class="btn btn-primary btn-sm"
          @click="close"
          :disabled="batchData?.status === 'running'"
        >
          {{ batchData?.status === 'running' ? 'Memproses...' : 'Tutup' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  batchId: {
    type: String,
    default: ''
  },
  action: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'finished'])
const store = useAcsStore()

const batchData = ref<any>(null)
let pollInterval: any = null

const actionLabel = computed(() => {
  const map: Record<string, string> = {
    reboot: 'Reboot Massal',
    factory_reset: 'Factory Reset Massal',
    refresh: 'Refresh / Connection Request',
    set_admin_password: 'Ganti Sandi Web Admin',
    set_inform_interval: 'Atur Interval Inform',
    apply_profile: 'Terapkan Profil Provisioning'
  }
  return map[props.action] || props.action || 'Operasi Massal'
})

const percentComplete = computed(() => {
  if (!batchData.value || !batchData.value.total) return 0
  const processed = batchData.value.processed || 0
  return Math.min(100, Math.round((processed / batchData.value.total) * 100))
})

const pendingCount = computed(() => {
  if (!batchData.value || !batchData.value.total) return 0
  const processed = batchData.value.processed || 0
  return Math.max(0, batchData.value.total - processed)
})

const statusText = computed(() => {
  if (!batchData.value) return 'Memulai antrean...'
  if (batchData.value.status === 'completed') return 'Selesai'
  return `Memproses (${batchData.value.processed || 0} dari ${batchData.value.total})`
})

const fetchStatus = async () => {
  if (!props.batchId) return
  const data = await store.fetchBatchStatus(props.batchId)
  if (data) {
    batchData.value = data
    if (data.status === 'completed') {
      stopPolling()
      emit('finished')
    }
  }
}

const startPolling = () => {
  stopPolling()
  fetchStatus()
  pollInterval = setInterval(fetchStatus, 1500)
}

const stopPolling = () => {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

const close = () => {
  if (batchData.value?.status === 'running') return
  stopPolling()
  emit('close')
}

const handleOverlayClick = () => {
  if (batchData.value?.status !== 'running') {
    close()
  }
}

watch(() => props.show, (newVal) => {
  if (newVal && props.batchId) {
    batchData.value = null
    startPolling()
  } else {
    stopPolling()
  }
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.batch-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1.5rem;
}

.batch-modal {
  width: 100%;
  max-width: 780px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 30px rgba(59, 130, 246, 0.15);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
  overflow: hidden;
}

.batch-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.5);
}

.batch-header-left {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.batch-icon-wrap {
  width: 42px;
  height: 42px;
  border-radius: 0.625rem;
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(59, 130, 246, 0.25);
}

.batch-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.batch-subtitle {
  font-size: 0.8125rem;
  color: #94a3b8;
}

.batch-subtitle code {
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  color: #38bdf8;
  font-family: monospace;
}

.batch-close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.35rem;
  border-radius: 0.375rem;
  transition: all 0.2s;
}

.batch-close-btn:hover:not(:disabled) {
  color: #f8fafc;
  background: rgba(255, 255, 255, 0.1);
}

.batch-close-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.batch-body {
  padding: 1.5rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.batch-progress-card {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.75rem;
  padding: 1.25rem;
}

.progress-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.progress-status-text {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #e2e8f0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
  box-shadow: 0 0 10px #3b82f6;
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.9); opacity: 0.6; }
  50% { transform: scale(1.2); opacity: 1; }
  100% { transform: scale(0.9); opacity: 0.6; }
}

.progress-percent-text {
  font-size: 1.125rem;
  font-weight: 700;
  color: #38bdf8;
  font-family: monospace;
}

.progress-bar-track {
  height: 10px;
  background: rgba(15, 23, 42, 0.8);
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.05);
  margin-bottom: 1.25rem;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #06b6d4);
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.progress-bar-fill.bar-complete {
  background: linear-gradient(90deg, #10b981, #059669);
}

.batch-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.75rem;
}

.b-stat {
  background: rgba(15, 23, 42, 0.5);
  padding: 0.75rem;
  border-radius: 0.5rem;
  text-align: center;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.b-val {
  display: block;
  font-size: 1.25rem;
  font-weight: 800;
  color: inherit;
}

.b-lbl {
  font-size: 0.75rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.section-heading {
  font-size: 0.875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  margin-bottom: 0.75rem;
}

.batch-task-table-wrap {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.625rem;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.4);
}

.batch-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.batch-table th {
  background: rgba(30, 41, 59, 0.7);
  padding: 0.625rem 0.875rem;
  text-align: left;
  color: #94a3b8;
  font-weight: 600;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.batch-table td {
  padding: 0.625rem 0.875rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: #cbd5e1;
}

.dev-sn {
  font-weight: 600;
  color: #f1f5f9;
  font-family: monospace;
}

.dev-vendor, .dev-model {
  font-size: 0.75rem;
  color: #64748b;
}

.dev-ip {
  font-family: monospace;
  color: #38bdf8;
}

.badge-subtle {
  background: rgba(255, 255, 255, 0.06);
  padding: 0.15rem 0.45rem;
  border-radius: 0.25rem;
  font-size: 0.6875rem;
  color: #94a3b8;
}

.task-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
}

.badge-success {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-failed {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-pending {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.task-error-cell {
  max-width: 220px;
}

.error-msg {
  color: #f87171;
  font-size: 0.75rem;
  display: block;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.batch-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.4);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.footer-note {
  font-size: 0.8125rem;
  color: #94a3b8;
}

.empty-batch-tasks {
  padding: 2.5rem;
  text-align: center;
  color: #94a3b8;
}

.spinning-icon {
  font-size: 1.75rem;
  margin-bottom: 0.5rem;
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.slide-in-down {
  animation: slideDown 0.25s ease-out;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
