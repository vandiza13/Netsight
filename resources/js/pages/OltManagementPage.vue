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
              OLT & <span class="dashboard__heading--accent">FTTH Management</span>
            </h2>
            <p class="dashboard__heading-sub">
              Monitoring Perangkat OLT (HiOSO, V-SOL, HSan, ZTE, Huawei) & Redaman Optik Pelanggan
            </p>
          </div>

          <div class="header-action-buttons">
            <button class="btn btn-secondary" @click="activeTab = 'debugger'">
              🛠️ OID Debugger Tool
            </button>
            <button class="btn btn-primary" @click="openAddOltModal">
              ➕ Tambah OLT
            </button>
          </div>
        </section>

        <!-- Navigation Tabs -->
        <section class="stagger">
          <div class="tabs-header-bar">
            <button :class="['tab-btn-pill', { active: activeTab === 'olts' }]" @click="activeTab = 'olts'">
              📡 Master OLT ({{ olts.length }})
            </button>
            <button :class="['tab-btn-pill', { active: activeTab === 'onus' }]" @click="activeTab = 'onus'">
              📊 Redaman ONU ({{ totalOnus }})
            </button>
            <button :class="['tab-btn-pill', { active: activeTab === 'debugger' }]" @click="activeTab = 'debugger'">
              🛠️ OID Debugger Live
            </button>
          </div>

          <!-- TAB 1: MASTER OLT LIST -->
          <div v-if="activeTab === 'olts'" class="tab-pane">
            <div v-if="loading" class="loading-state">
              <span class="spinning">🔄</span> Memuat data OLT...
            </div>

            <div v-else-if="olts.length === 0" class="glass-card empty-panel">
              <div class="empty-icon-wrap">⚡</div>
              <h3>Belum Ada Perangkat OLT</h3>
              <p>Tambahkan OLT (HiOSO, V-SOL, HSan, ZTE, Huawei) untuk mulai memantau jaringan optik FTTH.</p>
              <button class="btn btn-primary mt-3" @click="openAddOltModal">➕ Tambah OLT Sekarang</button>
            </div>

            <div v-else class="olts-grid-container">
              <div v-for="olt in olts" :key="olt.id" class="glass-card olt-card-item">
                <div class="olt-card-header">
                  <div>
                    <div class="olt-title-row">
                      <span :class="['status-indicator', olt.status]"></span>
                      <h4 class="olt-card-name">{{ olt.name }}</h4>
                    </div>
                    <span class="vendor-badge">{{ getVendorName(olt.vendor_code) }}</span>
                  </div>
                  <div class="olt-card-actions">
                    <button class="btn btn-sm btn-icon" title="Sync Redaman Sekarang" @click="syncOlt(olt)">🔄</button>
                    <button class="btn btn-sm btn-icon" title="Inspect ONUs" @click="inspectOlt(olt)">🔍</button>
                    <button class="btn btn-sm btn-icon" title="Edit OLT" @click="editOlt(olt)">✏️</button>
                    <button class="btn btn-sm btn-icon btn-danger" title="Hapus OLT" @click="deleteOlt(olt)">🗑️</button>
                  </div>
                </div>

                <div class="olt-info-body">
                  <div class="info-line">
                    <span class="lbl">IP & Port:</span>
                    <code class="val-code">{{ olt.ip_address }}:{{ olt.snmp_port }}</code>
                  </div>
                  <div class="info-line">
                    <span class="lbl">Teknologi:</span>
                    <span class="val uppercase">{{ olt.technology }} ({{ olt.total_pons }} PON Port)</span>
                  </div>
                  <div class="info-line">
                    <span class="lbl">Community:</span>
                    <code class="val-code">{{ olt.snmp_community }}</code>
                  </div>
                  <div v-if="olt.notes" class="notes-box">
                    📌 {{ olt.notes }}
                  </div>
                </div>

                <div class="onu-summary-row">
                  <div class="summary-pill green">
                    <span class="num">{{ olt.onus_online || 0 }}</span>
                    <span class="label">Online</span>
                  </div>
                  <div class="summary-pill yellow">
                    <span class="num">{{ olt.onus_offline || 0 }}</span>
                    <span class="label">Offline</span>
                  </div>
                  <div class="summary-pill red">
                    <span class="num">{{ olt.onus_los || 0 }}</span>
                    <span class="label">LOS</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: ONU REDAMAN MONITORING -->
          <div v-if="activeTab === 'onus'" class="tab-pane">
            <div class="glass-card panel">
              <div class="filter-flex-bar">
                <input v-model="onuSearch" type="text" placeholder="🔍 Cari User PPPoE, Nama, SN, atau MAC..." class="form-input search-input" />
                <select v-model="onuStatusFilter" class="form-select filter-select">
                  <option value="all">Semua Status ONU</option>
                  <option value="online">Online 🟢</option>
                  <option value="offline">Offline 🟡</option>
                  <option value="los">LOS (Kabel Putus) 🔴</option>
                </select>
              </div>

              <div class="table-responsive">
                <table class="data-table" style="table-layout: fixed; width: 100%;">
                  <thead>
                    <tr>
                      <th style="width: 15%;">PORT / INDEX</th>
                      <th style="width: 25%;">PELANGGAN / USER PPPoE</th>
                      <th style="width: 20%;">SN / MAC</th>
                      <th style="width: 10%;">STATUS</th>
                      <th style="width: 15%;">REDAMAN (RX)</th>
                      <th style="width: 15%; text-align: right;">AKSI</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="filteredOnus.length === 0">
                      <td colspan="6" class="text-center text-gray-500 py-4">Tidak ada data ONU yang cocok dengan filter.</td>
                    </tr>
                    <tr v-for="onu in filteredOnus" :key="onu.id">
                      <td class="font-bold">{{ onu.pon_port }} / ONU {{ onu.onu_index }}</td>
                      <td>
                        <div class="user-info-col">
                          <span class="cust-title">{{ onu.customer_name || onu.onu_description || 'Unlinked' }}</span>
                          <span v-if="onu.pppoe_username" class="pppoe-tag">pppoe: {{ onu.pppoe_username }}</span>
                        </div>
                      </td>
                      <td>
                        <code class="val-code-xs">{{ onu.serial_number || onu.mac_address || '-' }}</code>
                      </td>
                      <td>
                        <span :class="['badge', statusBadgeClass(onu.status)]">
                          {{ onu.status.toUpperCase() }}
                        </span>
                      </td>
                      <td>
                        <div :class="['dbm-box', getDbmClass(onu.rx_power_dbm)]">
                          <span class="val">{{ onu.rx_power_dbm ? onu.rx_power_dbm + ' dBm' : '-' }}</span>
                          <span class="lbl">{{ getDbmStatusText(onu.rx_power_dbm) }}</span>
                        </div>
                      </td>
                      <td class="text-right">
                        <button class="btn btn-sm btn-secondary" @click="linkCustomer(onu)">🔗 Link User</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- TAB 3: OID DEBUGGER TOOL LIVE -->
          <div v-if="activeTab === 'debugger'" class="tab-pane">
            <div class="glass-card panel">
              <div class="panel-header-custom">
                <h3>🛠️ OID Debugger Tool Live</h3>
                <p>Uji koneksi SNMP Walk ke IP OLT untuk menganalisa respon OID mentah secara langsung dari browser.</p>
              </div>

              <form @submit.prevent="runOidDebugger" class="debugger-form-body">
                <div class="form-grid-4">
                  <div class="form-group">
                    <label>IP Address OLT</label>
                    <input v-model="debugForm.ip_address" type="text" class="form-input" placeholder="10.200.0.5 atau 192.168.88.250" required />
                  </div>
                  <div class="form-group">
                    <label>SNMP Port (UDP)</label>
                    <input v-model.number="debugForm.snmp_port" type="number" class="form-input" placeholder="161 atau 1610 (DST-NAT)" required />
                  </div>
                  <div class="form-group">
                    <label>SNMP Community</label>
                    <input v-model="debugForm.snmp_community" type="text" class="form-input" placeholder="public" required />
                  </div>
                  <div class="form-group">
                    <label>Target OID Walk</label>
                    <input v-model="debugForm.oid" type="text" class="form-input" placeholder="1.3.6.1.4.1.17409.2.3.4.1.1.8" required />
                  </div>
                </div>

                <div class="preset-chips-row">
                  <span class="lbl">Preset OID Populer:</span>
                  <button type="button" class="btn-chip" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.1.1.8'">HiOSO Status (EPON)</button>
                  <button type="button" class="btn-chip" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.2.1.4'">HiOSO Rx Power</button>
                  <button type="button" class="btn-chip" @click="debugForm.oid = '1.3.6.1.4.1.37950.2.1.5.1.1.4'">V-SOL Status (EPON)</button>
                  <button type="button" class="btn-chip" @click="debugForm.oid = '1.3.6.1.4.1.3902.1012.3.28.2.1.4'">ZTE C320 Status</button>
                  <button type="button" class="btn-chip" @click="debugForm.oid = '1.3.6.1.2.1.1'">System Info</button>
                </div>

                <div class="form-actions-row">
                  <button type="submit" class="btn btn-primary" :disabled="debugging">
                    <span v-if="debugging" class="spinning">🔄</span>
                    {{ debugging ? 'Scanning SNMP Walk...' : '🚀 Uji OID Sekarang' }}
                  </button>
                </div>
              </form>

              <div v-if="debugResult" class="debug-result-panel">
                <div :class="['result-banner', debugResult.success ? 'success' : 'error']">
                  <span><strong>Status:</strong> {{ debugResult.success ? 'BERHASIL (200 OK)' : 'GAGAL / TIMEOUT' }}</span>
                  <span><strong>Target:</strong> {{ debugResult.target }}</span>
                </div>
                <div v-if="debugResult.error" class="error-msg-box">
                  ❌ {{ debugResult.error }}
                </div>
                <div v-else-if="debugResult.data" class="code-box font-mono">
                  <div class="code-header">Ditemukan {{ debugResult.count }} OID entries:</div>
                  <pre><code>{{ JSON.stringify(debugResult.data, null, 2) }}</code></pre>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- MODAL TAMBAH / EDIT OLT -->
    <div v-if="showOltModal" class="modal-backdrop" @click.self="closeOltModal">
      <div class="glass-card modal-content-box">
        <div class="modal-header-row">
          <h3>{{ editingOlt ? '✏️ Edit Perangkat OLT' : '➕ Tambah Perangkat OLT Baru' }}</h3>
          <button class="btn-close" @click="closeOltModal">&times;</button>
        </div>

        <form @submit.prevent="saveOlt">
          <div class="modal-body-scroll">
            <div class="form-group mb-3">
              <label>Nama OLT / POP</label>
              <input v-model="oltForm.name" type="text" class="form-input" placeholder="Contoh: OLT POP Utama Kasihan" required />
            </div>

            <div class="form-row-2 mb-3">
              <div class="form-group">
                <label>IP Address OLT / VPN</label>
                <input v-model="oltForm.ip_address" type="text" class="form-input" placeholder="10.200.0.5" required />
              </div>
              <div class="form-group">
                <label>SNMP Port (UDP)</label>
                <input v-model.number="oltForm.snmp_port" type="number" class="form-input" placeholder="161" required />
                <small class="hint-txt">Ubah ke 1610 / 16161 jika pakai DST-NAT MikroTik.</small>
              </div>
            </div>

            <div class="form-row-2 mb-3">
              <div class="form-group">
                <label>Vendor OID Profile Preset</label>
                <select v-model="oltForm.vendor_code" class="form-select" required @change="onVendorChange">
                  <option v-for="p in profiles" :key="p.code" :value="p.code">
                    {{ p.name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>SNMP Community</label>
                <input v-model="oltForm.snmp_community" type="text" class="form-input" placeholder="public" required />
              </div>
            </div>

            <div class="form-row-2 mb-3">
              <div class="form-group">
                <label>Teknologi</label>
                <select v-model="oltForm.technology" class="form-select">
                  <option value="epon">EPON</option>
                  <option value="gpon">GPON</option>
                </select>
              </div>
              <div class="form-group">
                <label>Jumlah Port PON</label>
                <input v-model.number="oltForm.total_pons" type="number" min="1" max="64" class="form-input" required />
              </div>
            </div>

            <div class="dstnat-info-box">
              💡 <strong>Petunjuk DST-NAT MikroTik (1 IP VPN):</strong><br/>
              <code>/ip firewall nat add chain=dstnat action=dst-nat protocol=udp dst-port={{ oltForm.snmp_port || 1610 }} to-addresses={{ oltForm.ip_address || '192.168.88.250' }} to-ports=161</code>
            </div>
          </div>

          <div class="modal-footer-row">
            <button type="button" class="btn btn-secondary" @click="closeOltModal">Batal</button>
            <button type="submit" class="btn btn-primary" :disabled="savingOlt">
              {{ savingOlt ? 'Menyimpan...' : 'Simpan OLT' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import SidebarNav from '../components/SidebarNav.vue';
import TopBar from '../components/TopBar.vue';
import api from '../utils/api';

const sidebarOpen = ref(false);
const activeTab = ref('olts');
const loading = ref(true);
const olts = ref<any[]>([]);
const profiles = ref<any[]>([]);
const onusList = ref<any[]>([]);

const showOltModal = ref(false);
const editingOlt = ref<any>(null);
const savingOlt = ref(false);

const oltForm = ref({
  name: '',
  ip_address: '',
  snmp_port: 161,
  snmp_community: 'public',
  technology: 'epon',
  vendor_code: 'hioso',
  total_pons: 2,
  notes: '',
});

const onuSearch = ref('');
const onuStatusFilter = ref('all');

const debugForm = ref({
  ip_address: '10.200.0.5',
  snmp_port: 1610,
  snmp_community: 'public',
  oid: '1.3.6.1.4.1.17409.2.3.4.1.1.8',
});

const debugging = ref(false);
const debugResult = ref<any>(null);

const totalOnus = computed(() => onusList.value.length);

const filteredOnus = computed(() => {
  return onusList.value.filter((onu: any) => {
    const matchStatus = onuStatusFilter.value === 'all' || onu.status === onuStatusFilter.value;
    const q = onuSearch.value.toLowerCase();
    const matchSearch = !q ||
      (onu.customer_name && onu.customer_name.toLowerCase().includes(q)) ||
      (onu.pppoe_username && onu.pppoe_username.toLowerCase().includes(q)) ||
      (onu.serial_number && onu.serial_number.toLowerCase().includes(q)) ||
      (onu.mac_address && onu.mac_address.toLowerCase().includes(q));
    return matchStatus && matchSearch;
  });
});

const fetchOlts = async () => {
  try {
    loading.value = true;
    const response = await api.get('/olts');
    olts.value = response.data.data || [];
    profiles.value = response.data.profiles || [];

    if (olts.value.length > 0) {
      fetchOnus(olts.value[0].id);
    }
  } catch (err) {
    console.error("Failed to fetch OLTs:", err);
  } finally {
    loading.value = false;
  }
};

const fetchOnus = async (oltId: number) => {
  try {
    const response = await api.get(`/olts/${oltId}/onus`);
    onusList.value = response.data.data || [];
  } catch (err) {
    console.error("Failed to fetch ONUs:", err);
  }
};

const syncOlt = async (olt: any) => {
  if (!confirm(`Tarik data redaman terbaru dari OLT ${olt.name} sekarang? (Membutuhkan waktu beberapa detik)`)) return;
  
  try {
    const response = await api.post(`/olts/${olt.id}/sync`);
    alert(response.data.message || 'Sync berhasil dikirim ke background.');
    // Beri jeda sebentar sebelum me-refresh list OLT (biasanya background job butuh 1-3 detik)
    setTimeout(() => {
      fetchOlts();
    }, 2000);
  } catch (err) {
    alert("Gagal melakukan sync OLT.");
    console.error(err);
  }
};

const getVendorName = (code: string) => {
  const p = profiles.value.find((x: any) => x.code === code);
  return p ? p.name : code;
};

const openAddOltModal = () => {
  editingOlt.value = null;
  oltForm.value = {
    name: '',
    ip_address: '',
    snmp_port: 161,
    snmp_community: 'public',
    technology: 'epon',
    vendor_code: 'hioso',
    total_pons: 2,
    notes: '',
  };
  showOltModal.value = true;
};

const editOlt = (olt: any) => {
  editingOlt.value = olt;
  oltForm.value = { ...olt };
  showOltModal.value = true;
};

const closeOltModal = () => {
  showOltModal.value = false;
};

const onVendorChange = () => {
  const p = profiles.value.find((x: any) => x.code === oltForm.value.vendor_code);
  if (p) {
    oltForm.value.technology = p.technology;
  }
};

const saveOlt = async () => {
  try {
    savingOlt.value = true;
    if (editingOlt.value) {
      await api.put(`/olts/${editingOlt.value.id}`, oltForm.value);
    } else {
      await api.post('/olts', oltForm.value);
    }
    closeOltModal();
    fetchOlts();
  } catch (err: any) {
    alert("Gagal menyimpan OLT: " + (err.response?.data?.message || err.message));
  } finally {
    savingOlt.value = false;
  }
};

const deleteOlt = async (olt: any) => {
  if (confirm(`Apakah Anda yakin ingin menghapus OLT "${olt.name}"?`)) {
    try {
      await api.delete(`/olts/${olt.id}`);
      fetchOlts();
    } catch (err) {
      alert("Gagal menghapus OLT");
    }
  }
};

const inspectOlt = (olt: any) => {
  fetchOnus(olt.id);
  activeTab.value = 'onus';
};

const runOidDebugger = async () => {
  try {
    debugging.value = true;
    debugResult.value = null;
    const response = await api.post('/olts/debug-oid', debugForm.value);
    debugResult.value = response.data;
  } catch (err: any) {
    debugResult.value = {
      success: false,
      target: debugForm.value.ip_address,
      error: err.response?.data?.error || err.message
    };
  } finally {
    debugging.value = false;
  }
};

const getDbmClass = (dbm: number) => {
  if (!dbm || dbm === 0) return 'dbm-offline';
  if (dbm > -24.0) return 'dbm-good';
  if (dbm >= -27.0) return 'dbm-warning';
  return 'dbm-critical';
};

const getDbmStatusText = (dbm: number) => {
  if (!dbm || dbm === 0) return 'Offline';
  if (dbm > -24.0) return 'Bagus 🟢';
  if (dbm >= -27.0) return 'Waspada 🟡';
  return 'Kritis/LOS 🔴';
};

const statusBadgeClass = (status: string) => {
  if (status === 'online') return 'badge-success';
  if (status === 'offline') return 'badge-warning';
  return 'badge-danger';
};

const linkCustomer = (onu: any) => {
  const name = prompt("Masukkan User PPPoE atau Nama Pelanggan untuk ONU ini:", onu.customer_name || onu.onu_description || '');
  if (name !== null) {
    onu.customer_name = name;
  }
};

onMounted(() => {
  fetchOlts();
});
</script>

<style scoped>
.dashboard {
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
}

.tabs-header-bar {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 0.5rem;
}

.tab-btn-pill {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #94a3b8;
  padding: 0.5rem 1.25rem;
  font-weight: 600;
  border-radius: 9999px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.875rem;
}

.tab-btn-pill.active {
  background: #3b82f6;
  color: #ffffff;
  border-color: #3b82f6;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.empty-panel {
  text-align: center;
  padding: 3rem 1.5rem;
}
.empty-icon-wrap {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.olts-grid-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.olt-card-item {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.olt-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.olt-title-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.olt-card-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-indicator.online { background: #10b981; box-shadow: 0 0 8px #10b981; }
.status-indicator.offline { background: #ef4444; box-shadow: 0 0 8px #ef4444; }

.vendor-badge {
  display: inline-block;
  font-size: 0.75rem;
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.3);
  padding: 0.15rem 0.6rem;
  border-radius: 0.25rem;
  margin-top: 0.35rem;
}

.olt-card-actions {
  display: flex;
  gap: 0.25rem;
}

.olt-info-body {
  font-size: 0.85rem;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 1rem;
}

.info-line {
  display: flex;
  justify-content: space-between;
}
.val-code {
  font-family: monospace;
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.4rem;
  border-radius: 0.25rem;
  color: #38bdf8;
}

.notes-box {
  background: rgba(0, 0, 0, 0.25);
  padding: 0.5rem;
  border-radius: 0.375rem;
  color: #94a3b8;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.onu-summary-row {
  display: flex;
  justify-content: space-around;
  background: rgba(15, 23, 42, 0.5);
  padding: 0.75rem;
  border-radius: 0.5rem;
  text-align: center;
}

.summary-pill .num { font-size: 1.1rem; font-weight: 700; display: block; }
.summary-pill .label { font-size: 0.7rem; color: #94a3b8; }
.summary-pill.green .num { color: #34d399; }
.summary-pill.yellow .num { color: #fbbf24; }
.summary-pill.red .num { color: #f87171; }

/* Filter & Tables */
.filter-flex-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.search-input { flex: 1; min-width: 250px; }
.filter-select { width: 200px; }

.user-info-col {
  display: flex;
  flex-direction: column;
}
.cust-title { font-weight: 600; color: #f1f5f9; }
.pppoe-tag { font-size: 0.75rem; color: #38bdf8; font-family: monospace; }
.val-code-xs { font-size: 0.75rem; color: #cbd5e1; }

.dbm-box { display: flex; flex-direction: column; }
.dbm-box .val { font-weight: 700; font-family: monospace; }
.dbm-box .lbl { font-size: 0.7rem; }
.dbm-good { color: #34d399; }
.dbm-warning { color: #fbbf24; }
.dbm-critical { color: #f87171; }

/* Debugger Tool */
.panel-header-custom { margin-bottom: 1.5rem; }
.panel-header-custom h3 { font-size: 1.2rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem; }
.panel-header-custom p { color: #94a3b8; font-size: 0.85rem; }

.form-grid-4 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.preset-chips-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}

.btn-chip {
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #cbd5e1;
  padding: 0.25rem 0.6rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  cursor: pointer;
  transition: background 0.2s;
}
.btn-chip:hover { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }

.debug-result-panel { margin-top: 1.5rem; }
.result-banner {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}
.result-banner.success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; }
.result-banner.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; }

.code-box pre {
  background: #0f172a;
  padding: 1rem;
  border-radius: 0.5rem;
  max-height: 350px;
  overflow-y: auto;
  color: #38bdf8;
  font-size: 0.8rem;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

/* Modals */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  padding: 1rem;
}

.modal-content-box {
  width: 100%;
  max-width: 580px;
  padding: 1.5rem;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 0.75rem;
}

.modal-header-row h3 { font-size: 1.15rem; font-weight: 700; color: #f8fafc; margin: 0; }
.btn-close { background: transparent; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; }

/* Buttons & Forms (Copied from base) */
.btn {
  padding: 8px 16px;
  border-radius: var(--radius-sm, 6px);
  font-weight: 500;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-family: inherit;
}
.btn-primary { background: var(--accent-cyan, #0ea5e9); color: #fff; }
.btn-primary:hover { opacity: 0.9; }
.btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-primary, #f1f5f9); }
.btn-secondary:hover { background: rgba(255,255,255,0.15); }
.btn-danger { color: #ef4444 !important; background: rgba(239, 68, 68, 0.1); }
.btn-danger:hover { background: rgba(239, 68, 68, 0.2); }
.btn-sm { padding: 4px 8px; font-size: 0.8rem; }
.btn-icon { background: transparent; border: 1px solid rgba(255,255,255,0.1); margin-right: 4px; }
.btn-icon:hover { background: rgba(255,255,255,0.05); }

.form-group { margin-bottom: 0; display: flex; flex-direction: column; }
.form-group label {
  font-size: 0.85rem;
  color: var(--text-secondary, #94a3b8);
  margin-bottom: 6px;
}
.form-input, .form-select {
  width: 100%;
  background: rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.1);
  padding: 10px 12px;
  border-radius: var(--radius-sm, 6px);
  color: var(--text-primary, #f8fafc);
  font-family: inherit;
}
.form-input:focus, .form-select:focus {
  outline: none;
  border-color: var(--accent-cyan, #0ea5e9);
}

.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.hint-txt { font-size: 0.7rem; color: #94a3b8; margin-top: 0.4rem; display: block; }
.mb-3 { margin-bottom: 1rem; }
.mt-3 { margin-top: 1rem; }

.dstnat-info-box {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.25);
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  color: #93c5fd;
  margin-top: 1rem;
}

.modal-footer-row {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.uppercase { text-transform: uppercase; }
.spinning { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
