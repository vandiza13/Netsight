<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="analytics-modal slide-in-up">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-left">
          <div class="header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
          </div>
          <div>
            <h3 class="modal-title">Pusat Monitoring &amp; Analitik ACS</h3>
            <span class="modal-subtitle">Feed peringatan real-time, spektrum redaman optik, dan radar kesehatan wilayah</span>
          </div>
        </div>
        <button class="close-btn" @click="close" title="Tutup">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <div class="modal-tabs">
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'alerts' }]"
          @click="activeTab = 'alerts'"
        >
          🚨 Peringatan Aktif ({{ unresolvedAlertsCount }})
        </button>
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'spectrum' }]"
          @click="activeTab = 'spectrum'"
        >
          📶 Spektrum Redaman (RX)
        </button>
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'areas' }]"
          @click="activeTab = 'areas'"
        >
          🏢 Kesehatan Wilayah
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body custom-scrollbar">
        <!-- TAB 1: ALERTS FEED -->
        <div v-if="activeTab === 'alerts'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-3">
            <span class="text-xs text-muted">
              Menampilkan peringatan telemetri aktif yang belum ditandai selesai.
            </span>
            <button
              v-if="alerts.length > 0"
              class="btn btn-secondary btn-xs"
              @click="handleResolveAll"
              title="Tandai semua alert sebagai terselesaikan"
            >
              ✓ Selesaikan Semua (Resolve All)
            </button>
          </div>

          <div v-if="isLoadingAlerts" class="loading-state">
            <span class="spinning">🔄</span>
            <p>Memuat daftar peringatan...</p>
          </div>

          <div v-else-if="alerts.length === 0" class="empty-state">
            <div class="empty-icon">🛡️</div>
            <h4>Semua Sistem Normal</h4>
            <p>Tidak ada peringatan redaman optik atau pemutusan TR-069 saat ini.</p>
          </div>

          <div v-else class="alerts-feed">
            <div
              v-for="alert in alerts"
              :key="alert.id"
              class="alert-feed-card"
              :class="alert.severity"
            >
              <div class="alert-top">
                <div class="alert-badge-row">
                  <span :class="['severity-tag', alert.severity]">
                    {{ alert.severity === 'critical' ? '🚨 Kritis' : (alert.severity === 'warning' ? '⚠️ Peringatan' : 'ℹ️ Info') }}
                  </span>
                  <span class="alert-time">{{ formatTime(alert.created_at) }}</span>
                </div>
                <button
                  class="btn-resolve"
                  @click="handleResolve(alert.id)"
                  title="Tandai Selesai"
                >
                  ✓ Selesai
                </button>
              </div>

              <h4 class="alert-title">{{ alert.title }}</h4>
              <p class="alert-msg">{{ alert.message }}</p>

              <!-- Device Snapshot Pills -->
              <div class="alert-device-bar">
                <span class="dev-item">
                  ONT: <strong>{{ alert.device?.serial_number || 'Unknown SN' }}</strong>
                </span>
                <span class="dev-item" v-if="alert.device?.pppoe_username">
                  PPPoE: <code>{{ alert.device?.pppoe_username }}</code>
                </span>
                <span class="dev-item" v-if="alert.device?.area_tag">
                  Wilayah: <strong>{{ alert.device?.area_tag }}</strong>
                </span>
                <span class="dev-item" v-if="alert.metric_snapshot?.rx_power_dbm !== undefined">
                  RX: <strong class="text-rose">{{ alert.metric_snapshot?.rx_power_dbm }} dBm</strong>
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 2: SPEKTRUM REDAMAN OPTIK -->
        <div v-if="activeTab === 'spectrum'" class="tab-pane fade-in">
          <div class="spectrum-intro">
            <h4>Distribusi Kualitas Sinyal Optik (RX Power Spectrum)</h4>
            <p>
              Analisis kesehatan jaringan fiber optik berdasarkan daya terima yang dilaporkan modem seluruh pelanggan.
            </p>
          </div>

          <div class="spectrum-cards-grid" v-if="analytics?.rx_spectrum">
            <!-- Sangat Bagus -->
            <div class="spectrum-card card-excellent">
              <div class="card-icon">💎</div>
              <div class="card-metric">
                <span class="metric-val">{{ analytics.rx_spectrum.excellent || 0 }}</span>
                <span class="metric-lbl">Sangat Bagus (-15 s/d -20 dBm)</span>
              </div>
              <div class="spectrum-bar">
                <div class="spectrum-fill bg-emerald" :style="{ width: getSpectrumPercent(analytics.rx_spectrum.excellent) + '%' }"></div>
              </div>
              <span class="spectrum-sub">{{ getSpectrumPercent(analytics.rx_spectrum.excellent) }}% dari total ONT</span>
            </div>

            <!-- Bagus -->
            <div class="spectrum-card card-good">
              <div class="card-icon">⚡</div>
              <div class="card-metric">
                <span class="metric-val">{{ analytics.rx_spectrum.good || 0 }}</span>
                <span class="metric-lbl">Bagus (-20 s/d -24 dBm)</span>
              </div>
              <div class="spectrum-bar">
                <div class="spectrum-fill bg-cyan" :style="{ width: getSpectrumPercent(analytics.rx_spectrum.good) + '%' }"></div>
              </div>
              <span class="spectrum-sub">{{ getSpectrumPercent(analytics.rx_spectrum.good) }}% dari total ONT</span>
            </div>

            <!-- Batas Waspada -->
            <div class="spectrum-card card-warning">
              <div class="card-icon">⚠️</div>
              <div class="card-metric">
                <span class="metric-val">{{ analytics.rx_spectrum.warning || 0 }}</span>
                <span class="metric-lbl">Batas Waspada (-24 s/d -27 dBm)</span>
              </div>
              <div class="spectrum-bar">
                <div class="spectrum-fill bg-amber" :style="{ width: getSpectrumPercent(analytics.rx_spectrum.warning) + '%' }"></div>
              </div>
              <span class="spectrum-sub">{{ getSpectrumPercent(analytics.rx_spectrum.warning) }}% dari total ONT</span>
            </div>

            <!-- Kritis -->
            <div class="spectrum-card card-critical">
              <div class="card-icon">🚨</div>
              <div class="card-metric">
                <span class="metric-val">{{ analytics.rx_spectrum.critical || 0 }}</span>
                <span class="metric-lbl">Kritis (&lt; -27 / &gt; -8 dBm)</span>
              </div>
              <div class="spectrum-bar">
                <div class="spectrum-fill bg-rose" :style="{ width: getSpectrumPercent(analytics.rx_spectrum.critical) + '%' }"></div>
              </div>
              <span class="spectrum-sub">{{ getSpectrumPercent(analytics.rx_spectrum.critical) }}% dari total ONT</span>
            </div>
          </div>
        </div>

        <!-- TAB 3: RADAR KESEHATAN WILAYAH -->
        <div v-if="activeTab === 'areas'" class="tab-pane fade-in">
          <div class="areas-intro">
            <h4>Kesehatan Jaringan per Wilayah / Router</h4>
            <p>Tingkat keandalan dan persentase modem online bebas redaman kritis per area distribusi.</p>
          </div>

          <div class="areas-table-wrap" v-if="analytics?.areas && analytics.areas.length > 0">
            <table class="areas-table">
              <thead>
                <tr>
                  <th>Wilayah / Router</th>
                  <th>Total ONT</th>
                  <th>Online</th>
                  <th>Redaman Kritis</th>
                  <th>Skor Kesehatan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="area in analytics.areas" :key="area.area">
                  <td>
                    <div class="area-name">📍 {{ area.area }}</div>
                  </td>
                  <td>{{ area.total }}</td>
                  <td>
                    <span class="text-emerald font-bold">{{ area.online }}</span>
                  </td>
                  <td>
                    <span :class="area.critical_rx > 0 ? 'text-rose font-bold' : 'text-slate'">
                      {{ area.critical_rx }}
                    </span>
                  </td>
                  <td>
                    <div class="health-cell">
                      <div class="health-track">
                        <div
                          class="health-bar"
                          :class="getHealthClass(area.health_score)"
                          :style="{ width: area.health_score + '%' }"
                        ></div>
                      </div>
                      <span class="health-text" :class="getHealthClass(area.health_score)">
                        {{ area.health_score }}%
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="empty-state">
            <p>Belum ada data wilayah yang terpetakan.</p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" @click="close">Tutup</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'updated'])
const store = useAcsStore()

const activeTab = ref<'alerts' | 'spectrum' | 'areas'>('alerts')
const isLoadingAlerts = ref(false)
const alerts = ref<any[]>([])
const analytics = ref<any>(null)

const unresolvedAlertsCount = computed(() => {
  return alerts.value.length
})

const totalDevicesSpectrum = computed(() => {
  if (!analytics.value?.rx_spectrum) return 0
  const s = analytics.value.rx_spectrum
  return (s.excellent || 0) + (s.good || 0) + (s.warning || 0) + (s.critical || 0)
})

const getSpectrumPercent = (count: number) => {
  if (totalDevicesSpectrum.value === 0) return 0
  return Math.round((count / totalDevicesSpectrum.value) * 100)
}

const getHealthClass = (score: number) => {
  if (score >= 90) return 'text-emerald'
  if (score >= 75) return 'text-cyan'
  if (score >= 50) return 'text-amber'
  return 'text-rose'
}

const formatTime = (iso: string) => {
  if (!iso) return '-'
  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const loadData = async () => {
  isLoadingAlerts.value = true
  try {
    const alertData = await store.fetchAlerts(1, { resolved: false })
    alerts.value = alertData.data || []
    analytics.value = await store.fetchHealthAnalytics()
  } finally {
    isLoadingAlerts.value = false
  }
}

const handleResolve = async (id: number) => {
  try {
    await store.resolveAlert(id)
    alerts.value = alerts.value.filter(a => a.id !== id)
    emit('updated')
  } catch (err: any) {
    alert(err.message || 'Gagal menyelesaikan alert.')
  }
}

const handleResolveAll = async () => {
  if (confirm('Tandai seluruh alert aktif sebagai selesai?')) {
    try {
      await store.resolveAllAlerts()
      alerts.value = []
      emit('updated')
    } catch (err: any) {
      alert(err.message || 'Gagal menyelesaikan alert.')
    }
  }
}

const close = () => {
  emit('close')
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    loadData()
  }
})
</script>

<style scoped>
.modal-overlay {
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

.analytics-modal {
  width: 100%;
  max-width: 860px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
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
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(16, 185, 129, 0.25);
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

.modal-tabs {
  display: flex;
  background: rgba(15, 23, 42, 0.6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding: 0 1.5rem;
}

.tab-btn {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 0.875rem 1.25rem;
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #f8fafc;
}

.tab-btn.active {
  color: #34d399;
  border-bottom-color: #34d399;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}

/* Alert Feed Tab */
.alerts-feed {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}

.alert-feed-card {
  background: rgba(30, 41, 59, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.625rem;
  padding: 1.125rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  transition: all 0.2s;
}

.alert-feed-card.critical {
  border-left: 4px solid #f43f5e;
  background: rgba(244, 63, 94, 0.04);
}

.alert-feed-card.warning {
  border-left: 4px solid #f59e0b;
  background: rgba(245, 158, 11, 0.04);
}

.alert-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-badge-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.severity-tag {
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  text-transform: uppercase;
}

.severity-tag.critical {
  background: rgba(244, 63, 94, 0.2);
  color: #fb7185;
}

.severity-tag.warning {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
}

.alert-time {
  font-size: 0.75rem;
  color: #64748b;
}

.btn-resolve {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.65rem;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-resolve:hover {
  background: #10b981;
  color: #0f172a;
}

.alert-title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.alert-msg {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0;
  line-height: 1.45;
}

.alert-device-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  background: rgba(15, 23, 42, 0.5);
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  color: #cbd5e1;
  margin-top: 0.25rem;
}

.alert-device-bar code {
  color: #38bdf8;
}

/* Spectrum Tab */
.spectrum-intro {
  margin-bottom: 1.5rem;
}

.spectrum-intro h4 {
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0 0 0.25rem;
}

.spectrum-intro p {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0;
}

.spectrum-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1rem;
}

.spectrum-card {
  background: rgba(30, 41, 59, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.spectrum-card.card-excellent { border-left: 4px solid #10b981; }
.spectrum-card.card-good { border-left: 4px solid #06b6d4; }
.spectrum-card.card-warning { border-left: 4px solid #f59e0b; }
.spectrum-card.card-critical { border-left: 4px solid #f43f5e; }

.card-icon {
  font-size: 1.5rem;
}

.card-metric {
  display: flex;
  flex-direction: column;
}

.metric-val {
  font-size: 1.75rem;
  font-weight: 800;
  color: #f8fafc;
}

.metric-lbl {
  font-size: 0.8125rem;
  color: #94a3b8;
}

.spectrum-bar {
  height: 8px;
  background: rgba(15, 23, 42, 0.8);
  border-radius: 999px;
  overflow: hidden;
}

.spectrum-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s ease;
}

.bg-emerald { background: #10b981; }
.bg-cyan { background: #06b6d4; }
.bg-amber { background: #f59e0b; }
.bg-rose { background: #f43f5e; }

.spectrum-sub {
  font-size: 0.75rem;
  color: #64748b;
}

/* Areas Tab */
.areas-intro {
  margin-bottom: 1.25rem;
}

.areas-intro h4 {
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0 0 0.25rem;
}

.areas-intro p {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0;
}

.areas-table-wrap {
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.625rem;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.4);
}

.areas-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}

.areas-table th {
  background: rgba(30, 41, 59, 0.7);
  padding: 0.625rem 0.875rem;
  text-align: left;
  color: #94a3b8;
  font-weight: 600;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.areas-table td {
  padding: 0.75rem 0.875rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  color: #cbd5e1;
}

.area-name {
  font-weight: 600;
  color: #f1f5f9;
}

.health-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.health-track {
  flex: 1;
  height: 6px;
  background: rgba(15, 23, 42, 0.8);
  border-radius: 999px;
  overflow: hidden;
}

.health-bar {
  height: 100%;
  background: currentColor;
  border-radius: 999px;
}

.health-text {
  font-weight: 700;
  font-size: 0.75rem;
  min-width: 38px;
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

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.4);
  display: flex;
  align-items: center;
  justify-content: flex-end;
}
</style>
