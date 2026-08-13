<template>
  <div class="dashboard">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <!-- Page Header -->
        <section class="dashboard__welcome fade-in flex-header">
          <div>
            <h2 class="dashboard__heading">
              TR-069 <span class="dashboard__heading--accent">ACS Management</span>
            </h2>
            <p class="dashboard__heading-sub">
              Remote management modem pelanggan via Auto Configuration Server (GenieACS)
            </p>
          </div>

          <div class="header-action-buttons">
            <div class="search-wrap search-wrap--header">
              <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                v-model="searchQuery"
                @keyup.enter="handleSearch"
                type="text"
                placeholder="Cari SN, MAC, PPPoE..."
                class="input-modern search-input"
              >
            </div>
            <button class="btn btn-secondary" @click="fetchData(1)" :disabled="store.loading">
              <svg :class="{ spinning: store.loading }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              Refresh
            </button>
          </div>
        </section>

        <!-- Analytics Cards -->
        <section class="analytics-grid" v-if="store.stats">
          <div class="stat-card fade-in">
            <div class="stat-icon-wrap">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Total ONT</span>
              <span class="stat-value">{{ store.stats.total }}</span>
            </div>
          </div>
          <div class="stat-card stat-online fade-in" style="animation-delay: 0.1s">
            <div class="stat-icon-wrap">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Online</span>
              <span class="stat-value">{{ store.stats.online }}</span>
            </div>
          </div>
          <div class="stat-card stat-offline fade-in" style="animation-delay: 0.2s">
            <div class="stat-icon-wrap">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Offline</span>
              <span class="stat-value">{{ store.stats.offline }}</span>
            </div>
          </div>
          <div class="stat-card stat-critical fade-in" style="animation-delay: 0.3s">
            <div class="stat-icon-wrap">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Redaman Kritis</span>
              <span class="stat-value">{{ store.stats.critical_rx }}</span>
            </div>
          </div>
        </section>

        <!-- Error Alert -->
        <div v-if="store.error" class="alert-box alert-box--danger fade-in">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <div>
            <strong>Sync Error</strong>
            <p>{{ store.error }}</p>
          </div>
        </div>

        <!-- Success Notification -->
        <div v-if="notification" :class="['alert-box', `alert-box--${notificationType}`, 'fade-in']">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <span>{{ notification }}</span>
        </div>

        <!-- Main Table -->
        <section class="stagger">
          <div class="premium-card panel-full">
            <div class="table-container custom-scrollbar">
              <table class="premium-table">
                <thead>
                  <tr>
                    <th style="width: 8%;">STATUS</th>
                    <th style="width: 20%;">DEVICE INFO</th>
                    <th style="width: 16%;">PPPoE</th>
                    <th style="width: 12%;">REDAMAN (RX)</th>
                    <th style="width: 14%;">Wi-Fi SSID</th>
                    <th style="width: 14%;">LAST INFORM</th>
                    <th style="width: 16%; text-align: right;">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Loading State -->
                  <tr v-if="store.loading && store.devices.length === 0">
                    <td colspan="6" class="loading-state">
                      <span class="spinning">🔄</span> Fetching TR-069 Devices...
                    </td>
                  </tr>
                  <!-- Empty State -->
                  <tr v-else-if="store.devices.length === 0">
                    <td colspan="6" class="empty-state">
                      Belum ada perangkat ACS yang tersinkronisasi.
                    </td>
                  </tr>
                  <!-- Data Rows -->
                  <tr v-for="device in store.devices" :key="device.id" class="table-row-hover">
                    <!-- Status -->
                    <td>
                      <div class="status-cell">
                        <span :class="['status-glow', device.status === 'online' ? 'online' : 'offline']"></span>
                        <span>{{ device.status === 'online' ? 'Online' : 'Offline' }}</span>
                      </div>
                    </td>
                    <!-- Device Info -->
                    <td>
                      <div class="device-info-col">
                        <span class="cust-title">{{ device.serial_number || 'Unknown SN' }}</span>
                        <code class="val-code-xs">{{ device.mac_address || 'No MAC' }}</code>
                        <span class="device-vendor-text">{{ device.vendor }} / {{ device.model }}</span>
                      </div>
                    </td>
                    <!-- PPPoE -->
                    <td>
                      <div class="device-info-col">
                        <span class="pppoe-tag" v-if="device.pppoe_username">{{ device.pppoe_username }}</span>
                        <span v-else class="text-muted">-</span>
                        <span class="val-code-xs">{{ device.ip_address || '-' }}</span>
                      </div>
                    </td>
                    <!-- Optical Power -->
                    <td>
                      <div :class="['dbm-pill', getDbmClass(device.rx_power_dbm)]">
                        <span class="val">{{ device.rx_power_dbm !== null ? device.rx_power_dbm + ' dBm' : '-' }}</span>
                      </div>
                    </td>
                    <!-- WiFi SSID -->
                    <td>
                      <div class="wifi-badge" v-if="device.wifi_ssid">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
                        <span class="wifi-name">{{ device.wifi_ssid }}</span>
                      </div>
                      <span v-else class="text-muted">Hidden/None</span>
                    </td>
                    <!-- Last Inform -->
                    <td>
                      <span :class="['inform-text', getInformClass(device.last_inform_at)]">
                        {{ formatRelativeTime(device.last_inform_at) }}
                      </span>
                    </td>
                    <!-- Actions -->
                    <td class="text-right">
                      <div class="action-buttons-row">
                        <button class="btn-action-icon btn-action-icon--danger" @click="deleteDeviceConfirm(device.id, device.serial_number)" title="Delete Device">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                        <button class="btn-action-icon" @click="refreshParams(device.id)" title="Refresh Data">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
                        </button>
                        <button class="btn btn-sm btn-primary btn-icon-text" @click="openModal(device)" title="Manage Device">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                          <span>Kelola</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar">
              <span class="pagination-info">
                Halaman <strong>{{ store.pagination.current_page }}</strong> dari <strong>{{ store.pagination.last_page }}</strong>
                (Total: {{ store.pagination.total }})
              </span>
              <div class="pagination-actions">
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(store.pagination.current_page - 1)"
                  :disabled="store.pagination.current_page <= 1"
                >
                  Prev
                </button>
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(store.pagination.current_page + 1)"
                  :disabled="store.pagination.current_page >= store.pagination.last_page"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Modal -->
        <AcsDeviceModal
          :show="isModalOpen"
          :device="selectedDevice"
          @close="isModalOpen = false"
          @updated="onDeviceUpdated"
        />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAcsStore } from '../stores/acsStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import AcsDeviceModal from '../components/AcsDeviceModal.vue'

const store = useAcsStore()
const sidebarOpen = ref(false)
const searchQuery = ref('')
const notification = ref('')
const notificationType = ref('success')
const isModalOpen = ref(false)
const selectedDevice = ref(null)

onMounted(() => {
  store.fetchStats()
  fetchData(1)
})

const handleSearch = () => {
  fetchData(1)
}

const fetchData = (page: number) => {
  store.fetchDevices(page, searchQuery.value)
}

const openModal = (device: any) => {
  selectedDevice.value = device
  isModalOpen.value = true
}

const showNotification = (msg: string, type: string = 'success') => {
  notification.value = msg
  notificationType.value = type
  setTimeout(() => {
    notification.value = ''
  }, 5000)
}

const onDeviceUpdated = (msg: string) => {
  showNotification(msg)
  setTimeout(() => fetchData(store.pagination.current_page), 2000)
}

const refreshParams = async (deviceId: number) => {
  try {
    const res = await store.refreshDevice(deviceId)
    const status = res?.status || 'success'
    const msg = res?.message || 'Sinkronisasi berhasil.'
    if (status === 'warning') {
      showNotification(msg, 'warning')
    } else {
      showNotification(msg)
    }
  } catch (err: any) {
    showNotification(err?.message || 'Gagal menghubungi modem.', 'error')
  }
}

const getDbmClass = (power: number | null) => {
  if (power === null || power === undefined) return 'dbm-offline'
  if (power >= -25 && power <= -8) return 'dbm-good'
  if ((power < -25 && power >= -28) || (power > -8 && power <= -3)) return 'dbm-warning'
  return 'dbm-critical'
}

const formatRelativeTime = (dateStr: string | null) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  
  if (diffMins < 60) return `${diffMins} menit lalu`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours} jam lalu`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays} hari lalu`
}

const getInformClass = (dateStr: string | null) => {
  if (!dateStr) return 'inform-dead'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  
  if (diffMins < 10) return 'inform-fresh'
  if (diffMins <= 24 * 60) return 'inform-stale'
  return 'inform-dead'
}

const deleteDeviceConfirm = async (deviceId: number, serialNumber: string) => {
  if (confirm(`Hapus perangkat ${serialNumber}? Data akan dihapus dari ACS dan database.`)) {
    try {
      await store.deleteDevice(deviceId)
      showNotification('Perangkat berhasil dihapus dari ACS dan database.')
      fetchData(1)
    } catch (err: any) {
      alert(err.message)
    }
  }
}
</script>

<style scoped>
/* ── Layout (same as OltManagementPage) ─────────────────── */
.dashboard {
  display: flex;
  min-height: 100vh;
  background: var(--surface-0);
}
.dashboard__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.dashboard__content {
  flex: 1;
  padding: 32px 40px;
  overflow-y: auto;
}
.dashboard__heading {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-1);
  margin-bottom: 6px;
  letter-spacing: -0.02em;
}
.dashboard__heading--accent {
  background: linear-gradient(135deg, #38bdf8 0%, #3b82f6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.dashboard__heading-sub {
  font-size: 0.875rem;
  color: var(--text-2);
}
.dashboard__welcome { margin-bottom: 32px; }

.flex-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.header-action-buttons {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

/* ── Animations ─────────────────────────────────────────── */
.fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.stagger > * { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
.stagger > *:nth-child(1) { animation-delay: 0.1s; }
.stagger > *:nth-child(2) { animation-delay: 0.2s; }

.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Analytics Cards ────────────────────────────────────── */
.analytics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}
.stat-card {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 14px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}
.stat-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface-2);
  color: var(--text-2);
}
.stat-info {
  display: flex;
  flex-direction: column;
}
.stat-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}
.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-1);
}
.stat-online .stat-icon-wrap { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.stat-offline .stat-icon-wrap { background: rgba(107, 114, 128, 0.1); color: #9ca3af; }
.stat-critical .stat-icon-wrap { background: rgba(244, 63, 94, 0.1); color: #f43f5e; }

/* ── Scrollbar ──────────────────────────────────────────── */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.5); }

/* ── Search ─────────────────────────────────────────────── */
.search-wrap {
  position: relative;
  min-width: 250px;
}
.search-wrap--header {
  flex: 0 1 300px;
}
.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-2);
}
.search-input {
  width: 100%;
  padding-left: 42px !important;
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
  border-color: var(--border-hover);
}
.btn-outline {
  background: transparent;
  color: var(--text-2);
  border: 1px solid var(--border);
}
.btn-outline:hover:not(:disabled) {
  background: var(--surface-2);
  color: var(--text-1);
  border-color: var(--border-hover);
}
.btn-sm {
  padding: 6px 12px;
  font-size: 0.8rem;
}
.btn-icon-text {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-icon-text svg {
  flex-shrink: 0;
}

.btn-action-icon {
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-2);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-action-icon:hover {
  background: var(--surface-3);
  color: var(--text-1);
  border-color: var(--border-hover);
}
.btn-action-icon--danger:hover {
  background: rgba(244, 63, 94, 0.1);
  color: #f43f5e;
  border-color: rgba(244, 63, 94, 0.3);
}

/* ── Inform Status ──────────────────────────────────────── */
.inform-text {
  font-weight: 500;
  font-size: 0.85rem;
}
.inform-fresh { color: #10b981; }
.inform-stale { color: #f59e0b; }
.inform-dead { color: #f43f5e; }

/* ── Alert Boxes ────────────────────────────────────────── */
.alert-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.875rem;
}
.alert-box strong {
  display: block;
  margin-bottom: 2px;
}
.alert-box p {
  margin: 0;
  opacity: 0.85;
}
.alert-box--danger {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.2);
  color: #f43f5e;
}
.alert-box--error {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.2);
  color: #f43f5e;
}
.alert-box--warning {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  color: #f59e0b;
}
.alert-box--success {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #10b981;
}
.alert-box svg {
  flex-shrink: 0;
  margin-top: 2px;
}

/* ── Table Card ─────────────────────────────────────────── */
.premium-card {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  border-radius: 16px;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.panel-full { padding: 24px; }

.table-container {
  max-height: 550px;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface-1);
}
.premium-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
}
.premium-table th {
  position: sticky;
  top: 0;
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(8px);
  padding: 16px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-2);
  border-bottom: 1px solid var(--border);
  z-index: 10;
}
.premium-table td {
  padding: 16px;
  border-bottom: 1px solid rgba(255,255,255,0.03);
  font-size: 0.875rem;
  color: var(--text-1);
  vertical-align: middle;
}
.premium-table th:last-child, .premium-table td:last-child {
  padding-right: 24px;
}
.table-row-hover { transition: background 0.2s; }
.table-row-hover:hover { background: rgba(255, 255, 255, 0.03); }
.table-row-hover:last-child td { border-bottom: none; }

/* ── Table Cell Content ─────────────────────────────────── */
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
.status-glow.offline { background: #6b7280; box-shadow: 0 0 10px rgba(107, 114, 128, 0.4); }

.device-info-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.cust-title { font-weight: 600; color: var(--text-1); }
.val-code-xs { font-size: 0.75rem; color: var(--text-2); font-family: var(--font-mono); }
.device-vendor-text { font-size: 0.75rem; color: var(--text-3); }

.pppoe-tag {
  font-size: 0.8rem;
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
  display: inline-block;
  font-weight: 500;
}

.text-muted { color: var(--text-3); }
.text-right { text-align: right; }

/* ── dBm Pill ───────────────────────────────────────────── */
.dbm-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  border-radius: 9999px;
  font-weight: 700;
  font-family: var(--font-mono);
  font-size: 0.8rem;
  letter-spacing: 0.02em;
  min-width: 90px;
  border: 1px solid transparent;
}
.dbm-good { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
.dbm-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
.dbm-critical { background: rgba(244, 63, 94, 0.15); color: #f43f5e; border-color: rgba(244, 63, 94, 0.3); }
.dbm-offline { background: rgba(255,255,255,0.05); color: var(--text-3); border-color: rgba(255,255,255,0.1); }

/* ── WiFi Badge ─────────────────────────────────────────── */
.wifi-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  padding: 5px 12px;
  border-radius: 8px;
  max-width: 180px;
}
.wifi-badge svg {
  flex-shrink: 0;
  color: var(--text-3);
}
.wifi-name {
  font-weight: 500;
  color: var(--text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── Action Buttons ─────────────────────────────────────── */
.action-buttons-row {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: nowrap;
}

/* ── Pagination ─────────────────────────────────────────── */
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}
.pagination-info {
  font-size: 0.85rem;
  color: var(--text-2);
}
.pagination-info strong {
  color: var(--text-1);
}
.pagination-actions {
  display: flex;
  gap: 8px;
}

/* ── Loading / Empty ────────────────────────────────────── */
.loading-state {
  text-align: center;
  padding: 40px 0;
  color: var(--text-2);
  font-size: 0.9rem;
}
.empty-state {
  text-align: center;
  padding: 40px 0;
  color: var(--text-3);
}

/* ── Mobile ─────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .analytics-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .dashboard__content {
    padding: 16px;
  }
  .analytics-grid { grid-template-columns: 1fr; }
  .flex-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .header-action-buttons {
    width: 100%;
  }
  .search-wrap--header {
    flex: 1;
  }
  .premium-table th,
  .premium-table td {
    padding: 10px 8px;
    font-size: 0.8rem;
  }
  .pagination-bar {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }
}
</style>
