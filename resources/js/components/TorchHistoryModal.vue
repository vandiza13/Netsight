<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-card glass-card">
      <div class="modal-header">
        <h3 class="modal-title">📊 Inspection Report: <span class="accent">{{ sessionData?.username }}</span></h3>
        <div class="header-actions print-hidden">
          <button @click="printReport" class="btn-print">Export PDF</button>
          <button class="btn-close" @click="$emit('close')">✕</button>
        </div>
      </div>

      <div class="modal-body" v-if="loading">
        <div class="loading-state">
          <span class="loading-spinner">🌀</span> Loading diagnostic data...
        </div>
      </div>
      
      <div class="modal-body" v-else-if="error">
        <div class="alert alert-error">{{ error }}</div>
      </div>

      <div class="modal-body" v-else-if="sessionData">
        <div class="report-grid">
          <!-- Summary Details -->
          <div class="report-summary">
            <div class="summary-details">
              <div class="detail-item">
                <span class="label">Date & Time</span>
                <span class="value">{{ formatFullDate(sessionData.started_at) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Router</span>
                <span class="value">{{ sessionData.router?.name || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">NOC Inspector</span>
                <span class="value">{{ sessionData.initiator?.name || 'System' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Status</span>
                <span class="value status-badge" :class="sessionData.status.toLowerCase()">
                  {{ sessionData.status }}
                </span>
              </div>
            </div>

            <div class="diagnostic-card diag-info">
              <span class="diagnostic-icon">ℹ️</span>
              <div>
                <h6>Diagnostic Conclusion</h6>
                <p>{{ sessionData.diagnostic_conclusion || 'No conclusion recorded.' }}</p>
              </div>
            </div>
          </div>

          <!-- Line Chart -->
          <div class="report-chart">
            <LiveTrafficChart :samples="parsedSamples" />
          </div>

          <!-- App Distribution & Peaks -->
          <div class="report-details-bottom">
            <div class="report-distribution">
              <h5>App Bandwidth Distribution</h5>
              <div v-if="parsedDistribution.length === 0" class="empty-distribution text-muted">
                No app classification data recorded.
              </div>
              <div v-else class="distribution-list">
                <div v-for="app in parsedDistribution" :key="app.name" class="dist-row">
                  <div class="dist-label">
                    <span>{{ app.name }}</span>
                    <span class="dist-pct font-mono">{{ app.percentage }}%</span>
                  </div>
                  <div class="dist-bar-bg">
                    <div class="dist-bar-fill" :style="{ width: app.percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="report-peaks">
              <h5>Performance Peaks</h5>
              <div class="peaks-grid">
                <div class="peak-box">
                  <span class="peak-label">Peak Download (TX)</span>
                  <span class="peak-value tx-rate">{{ formatTraffic(sessionData.peak_tx_bps) }}</span>
                </div>
                <div class="peak-box">
                  <span class="peak-label">Peak Upload (RX)</span>
                  <span class="peak-value rx-rate">{{ formatTraffic(sessionData.peak_rx_bps) }}</span>
                </div>
                <div class="peak-box">
                  <span class="peak-label">Avg Download (TX)</span>
                  <span class="peak-value tx-rate">{{ formatTraffic(sessionData.avg_tx_bps) }}</span>
                </div>
                <div class="peak-box">
                  <span class="peak-label">Avg Upload (RX)</span>
                  <span class="peak-value rx-rate">{{ formatTraffic(sessionData.avg_rx_bps) }}</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Dedicated Print Layout -->
    <div class="print-only-report" v-if="sessionData">
      <div class="print-header">
        <div class="print-logo">Netsight</div>
        <div class="print-title">Official Diagnostic Report</div>
      </div>
      
      <div class="print-info-grid">
        <div class="info-box">
          <strong>Customer Username:</strong> {{ sessionData.username }}
        </div>
        <div class="info-box">
          <strong>Date & Time:</strong> {{ formatFullDate(sessionData.started_at) }}
        </div>
        <div class="info-box">
          <strong>Router:</strong> {{ sessionData.router?.name || '-' }}
        </div>
        <div class="info-box">
          <strong>Inspected By:</strong> {{ sessionData.initiator?.name || 'System' }}
        </div>
      </div>

      <div class="print-section print-conclusion">
        <h4>Diagnostic Conclusion</h4>
        <p>{{ sessionData.diagnostic_conclusion || 'No conclusion recorded.' }}</p>
      </div>

      <div class="print-section">
        <h4>Traffic Performance</h4>
        <div class="print-peaks-grid">
          <div class="peak-item"><span>Peak Download:</span> <strong>{{ formatTraffic(sessionData.peak_tx_bps) }}</strong></div>
          <div class="peak-item"><span>Peak Upload:</span> <strong>{{ formatTraffic(sessionData.peak_rx_bps) }}</strong></div>
          <div class="peak-item"><span>Avg Download:</span> <strong>{{ formatTraffic(sessionData.avg_tx_bps) }}</strong></div>
          <div class="peak-item"><span>Avg Upload:</span> <strong>{{ formatTraffic(sessionData.avg_rx_bps) }}</strong></div>
        </div>
      </div>

      <div class="print-section">
        <h4>Application Distribution</h4>
        <table class="print-table">
          <thead>
            <tr>
              <th>Application / Protocol</th>
              <th>Bandwidth Usage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="app in parsedDistribution" :key="app.name">
              <td>{{ app.name }}</td>
              <td>{{ app.percentage }}%</td>
            </tr>
            <tr v-if="parsedDistribution.length === 0">
              <td colspan="2">No app data recorded.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="print-footer">
        <p><strong>Disclaimer:</strong> Laporan ini dihasilkan secara otomatis oleh sistem diagnostik Netsight. Ditujukan murni sebagai informasi teknis mengenai kondisi jaringan pelanggan pada saat inspeksi dilakukan.</p>
        <p class="print-timestamp">Generated on: {{ new Date().toLocaleString() }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '../utils/api'
import LiveTrafficChart from './LiveTrafficChart.vue'

const props = defineProps<{
  sessionId: number
}>()

defineEmits<{
  (e: 'close'): void
}>()

const sessionData = ref<any>(null)
const loading = ref(true)
const error = ref<string | null>(null)

// Parse samples
const parsedSamples = computed(() => {
  if (!sessionData.value?.traffic_samples) return []
  try {
    const raw = sessionData.value.traffic_samples
    return typeof raw === 'string' ? JSON.parse(raw) : raw
  } catch (e) {
    return []
  }
})

// Parse distribution
const parsedDistribution = computed(() => {
  if (!sessionData.value?.app_distribution) return []
  try {
    const raw = sessionData.value.app_distribution
    return typeof raw === 'string' ? JSON.parse(raw) : raw
  } catch (e) {
    return []
  }
})

async function fetchDetails() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/torch/history/${props.sessionId}`)
    sessionData.value = data
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Failed to load report details'
  } finally {
    loading.value = false
  }
}

function formatFullDate(dateString: string): string {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString([], {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function formatTraffic(bps: number | string): string {
  const num = Number(bps)
  if (isNaN(num) || num === 0) return '0 bps'
  if (num >= 1000000000) return (num / 1000000000).toFixed(2) + ' Gbps'
  if (num >= 1000000) return (num / 1000000).toFixed(2) + ' Mbps'
  if (num >= 1000) return (num / 1000).toFixed(0) + ' Kbps'
  return num + ' bps'
}

const printReport = () => {
  window.print()
}

onMounted(() => {
  fetchDetails()
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(10, 14, 26, 0.85);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  animation: fadeIn 0.3s ease;
}

.modal-card {
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  background: rgba(0, 0, 0, 0.2);
}

.modal-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-primary);
}

.modal-title .accent {
  color: var(--accent-cyan);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.btn-print {
  background: var(--accent-cyan);
  color: #000;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}

.btn-close {
  background: transparent;
  border: none;
  color: var(--text-muted);
  font-size: 1.2rem;
  cursor: pointer;
  padding: 4px;
  transition: color 0.2s;
}

.btn-close:hover {
  color: var(--accent-red);
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.loading-state {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
  padding: 60px 0;
  color: var(--text-secondary);
}

.loading-spinner {
  font-size: 1.5rem;
  animation: spin 1s linear infinite;
}

.report-grid {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.report-summary {
  display: grid;
  grid-template-columns: 2fr 3fr;
  gap: 20px;
}

.summary-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--glass-border);
  padding: 16px;
  border-radius: 12px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-item .label {
  font-size: 0.7rem;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.detail-item .value {
  color: #f8fafc;
  font-weight: 500;
}

.print-only-report {
  display: none;
}

@media print {
  @page {
    size: A4;
    margin: 15mm;
  }
  
  body * {
    visibility: hidden;
  }
  
  /* Reset modal overlay for printing */
  .modal-overlay {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    height: auto !important;
    background: transparent !important;
    backdrop-filter: none !important;
    visibility: visible;
    display: block !important;
  }
  
  /* Hide the UI card entirely */
  .modal-card {
    display: none !important;
  }
  
  /* Show the dedicated print report */
  .print-only-report {
    display: block;
    visibility: visible;
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    color: #000;
    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
    background: #fff;
  }
  
  .print-only-report * {
    visibility: visible;
  }
  
  .print-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #111;
    padding-bottom: 15px;
    margin-bottom: 25px;
  }
  
  .print-logo {
    font-size: 28px;
    font-weight: 800;
    color: #111;
    letter-spacing: -1px;
  }
  
  .print-title {
    font-size: 16px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
  }
  
  .print-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 30px;
  }
  
  .info-box {
    border: 1px solid #ddd;
    padding: 12px 15px;
    border-radius: 6px;
    font-size: 14px;
    background: #fafafa;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .info-box strong {
    display: block;
    font-size: 11px;
    color: #777;
    text-transform: uppercase;
    margin-bottom: 6px;
  }
  
  .print-section {
    margin-bottom: 25px;
    page-break-inside: avoid;
  }
  
  .print-section h4 {
    margin: 0 0 12px 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 8px;
    font-size: 16px;
    color: #222;
  }
  
  .print-conclusion {
    background: #f0fdf4 !important; /* light green tint */
    border-left: 4px solid #16a34a !important;
    padding: 15px 20px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .print-conclusion p {
    margin: 0;
    font-size: 15px;
    line-height: 1.5;
  }
  
  .print-peaks-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 30px;
  }
  
  .peak-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #ccc;
    font-size: 14px;
  }
  
  .print-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  
  .print-table th, .print-table td {
    border: 1px solid #ddd;
    padding: 10px 15px;
    text-align: left;
    font-size: 13px;
  }
  
  .print-table th {
    background: #f8f9fa !important;
    font-weight: 600;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  
  .print-footer {
    margin-top: 50px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
    font-size: 11px;
    color: #666;
    line-height: 1.4;
  }
  
  .print-timestamp {
    text-align: right;
    margin-top: 10px;
    font-style: italic;
    color: #999;
  }
}

.status-badge {
  display: inline-block;
  align-self: flex-start;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}
.status-badge.completed { background: rgba(16, 185, 129, 0.15); color: var(--accent-green); }
.status-badge.cancelled { background: rgba(245, 158, 11, 0.15); color: var(--accent-amber); }
.status-badge.force_terminated { background: rgba(239, 68, 68, 0.15); color: var(--accent-red); }

.diagnostic-card {
  padding: 16px;
  border-radius: 12px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.diag-info {
  background: rgba(6, 182, 212, 0.08);
  border-left: 4px solid var(--accent-cyan);
}

.diagnostic-card h6 {
  margin: 0 0 6px 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--accent-cyan);
}

.diagnostic-card p {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.4;
  color: var(--text-primary);
}

.report-details-bottom {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.report-distribution, .report-peaks {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--glass-border);
  padding: 16px;
  border-radius: 12px;
}

.report-distribution h5, .report-peaks h5 {
  margin-top: 0;
  margin-bottom: 16px;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-secondary);
}

.dist-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.dist-label {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  font-weight: 600;
}

.dist-pct {
  color: var(--text-muted);
}

.dist-bar-bg {
  width: 100%;
  height: 6px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 3px;
  overflow: hidden;
}

.dist-bar-fill {
  height: 100%;
  border-radius: 3px;
  background: var(--accent-cyan);
}

.peaks-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.peak-box {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.02);
  padding: 12px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.peak-label {
  font-size: 0.65rem;
  color: var(--text-muted);
  text-transform: uppercase;
}

.peak-value {
  font-size: 1rem;
  font-weight: 700;
  font-family: var(--font-mono, monospace);
}

.tx-rate { color: var(--accent-amber); }
.rx-rate { color: var(--accent-cyan); }

.empty-distribution {
  font-size: 0.8rem;
  font-style: italic;
  padding: 24px 0;
  text-align: center;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Responsive */
@media (max-width: 768px) {
  .report-summary, .report-details-bottom {
    grid-template-columns: 1fr;
  }
}
</style>
