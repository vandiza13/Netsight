<template>
  <div class="olt-management-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">OLT & FTTH Management</h1>
        <p class="page-subtitle">Monitoring Perangkat OLT (HiOSO, V-SOL, HSan, ZTE, Huawei) & Redaman Optik Pelanggan</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="activeTab = 'debugger'">
          <svg class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          OID Debugger Tool
        </button>
        <button class="btn btn-primary" @click="openAddOltModal">
          <svg class="btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Tambah OLT
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-header">
      <button :class="['tab-btn', { active: activeTab === 'olts' }]" @click="activeTab = 'olts'">
        Master Perangkat OLT ({{ olts.length }})
      </button>
      <button :class="['tab-btn', { active: activeTab === 'onus' }]" @click="activeTab = 'onus'">
        Monitoring Redaman ONU ({{ totalOnus }})
      </button>
      <button :class="['tab-btn', { active: activeTab === 'debugger' }]" @click="activeTab = 'debugger'">
        🛠️ OID Debugger Tool Live
      </button>
    </div>

    <!-- TAB 1: MASTER OLT LIST -->
    <div v-if="activeTab === 'olts'" class="tab-content">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <span>Memuat data OLT...</span>
      </div>

      <div v-else-if="olts.length === 0" class="empty-card">
        <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <h3>Belum Ada Perangkat OLT</h3>
        <p>Tambahkan OLT (HiOSO, V-SOL, HSan, ZTE, Huawei) untuk mulai memantau jaringan optik FTTH.</p>
        <button class="btn btn-primary" @click="openAddOltModal">Tambah OLT Sekarang</button>
      </div>

      <div v-else class="olts-grid">
        <div v-for="olt in olts" :key="olt.id" class="olt-card">
          <div class="olt-card-header">
            <div>
              <span :class="['status-dot', olt.status]"></span>
              <h3 class="olt-name">{{ olt.name }}</h3>
              <span class="vendor-tag">{{ getVendorName(olt.vendor_code) }}</span>
            </div>
            <div class="olt-card-actions">
              <button class="icon-btn" title="Inspect ONUs" @click="inspectOlt(olt)">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
              <button class="icon-btn" title="Edit OLT" @click="editOlt(olt)">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
              <button class="icon-btn text-danger" title="Hapus OLT" @click="deleteOlt(olt)">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>

          <div class="olt-info-list">
            <div class="info-row">
              <span class="label">IP Address & Port:</span>
              <span class="value font-mono">{{ olt.ip_address }}:{{ olt.snmp_port }}</span>
            </div>
            <div class="info-row">
              <span class="label">Teknologi / PON:</span>
              <span class="value uppercase">{{ olt.technology }} ({{ olt.total_pons }} PON Port)</span>
            </div>
            <div class="info-row">
              <span class="label">Community SNMP:</span>
              <span class="value font-mono">{{ olt.snmp_community }}</span>
            </div>
            <div v-if="olt.notes" class="info-notes">
              📌 {{ olt.notes }}
            </div>
          </div>

          <div class="onu-summary-bar">
            <div class="onu-stat green">
              <span class="num">{{ olt.onus_online || 0 }}</span>
              <span class="txt">Online</span>
            </div>
            <div class="onu-stat yellow">
              <span class="num">{{ olt.onus_offline || 0 }}</span>
              <span class="txt">Offline</span>
            </div>
            <div class="onu-stat red">
              <span class="num">{{ olt.onus_los || 0 }}</span>
              <span class="txt">LOS</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: ONU REDAMAN MONITORING -->
    <div v-if="activeTab === 'onus'" class="tab-content">
      <div class="filter-bar">
        <div class="search-box">
          <input v-model="onuSearch" type="text" placeholder="Cari User PPPoE, Nama, SN, atau MAC..." class="form-control" />
        </div>
        <select v-model="onuStatusFilter" class="form-control select-filter">
          <option value="all">Semua Status</option>
          <option value="online">Online 🟢</option>
          <option value="offline">Offline 🟡</option>
          <option value="los">LOS (Kabel Putus) 🔴</option>
        </select>
      </div>

      <div class="table-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>PORT / INDEX</th>
              <th>PELANGGAN / USER PPPoE</th>
              <th>GPON SN / EPON MAC</th>
              <th>STATUS</th>
              <th>REDAMAN OPTIK (RX POWER)</th>
              <th>JARAK MODEM</th>
              <th>AKSI</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredOnus.length === 0">
              <td colspan="7" class="text-center py-4 text-muted">Tidak ada data ONU yang cocok dengan filter.</td>
            </tr>
            <tr v-for="onu in filteredOnus" :key="onu.id">
              <td class="font-bold">{{ onu.pon_port }} / ONU {{ onu.onu_index }}</td>
              <td>
                <div class="user-cell">
                  <span class="cust-name">{{ onu.customer_name || onu.onu_description || 'Unlinked' }}</span>
                  <span v-if="onu.pppoe_username" class="pppoe-badge">pppoe: {{ onu.pppoe_username }}</span>
                </div>
              </td>
              <td class="font-mono text-xs">
                {{ onu.serial_number || onu.mac_address || '-' }}
              </td>
              <td>
                <span :class="['badge-status', onu.status]">
                  {{ onu.status.toUpperCase() }}
                </span>
              </td>
              <td>
                <div :class="['dbm-badge', getDbmClass(onu.rx_power_dbm)]">
                  <span class="dbm-val">{{ onu.rx_power_dbm ? onu.rx_power_dbm + ' dBm' : '-' }}</span>
                  <span class="dbm-label">{{ getDbmStatusText(onu.rx_power_dbm) }}</span>
                </div>
              </td>
              <td class="text-sm">
                {{ onu.distance_meters ? (onu.distance_meters / 1000).toFixed(2) + ' KM' : '-' }}
              </td>
              <td>
                <button class="btn btn-xs btn-outline" @click="linkCustomer(onu)">Link User</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 3: OID DEBUGGER TOOL LIVE -->
    <div v-if="activeTab === 'debugger'" class="tab-content">
      <div class="debugger-card">
        <div class="debugger-header">
          <h3>🛠️ OID Debugger Tool Live</h3>
          <p>Uji koneksi SNMP Walk ke IP OLT untuk menganalisa respon OID mentah secara langsung dari browser.</p>
        </div>

        <form @submit.prevent="runOidDebugger" class="debugger-form">
          <div class="form-grid">
            <div class="form-group">
              <label>IP Address OLT</label>
              <input v-model="debugForm.ip_address" type="text" class="form-control" placeholder="10.200.0.5 atau 192.168.88.250" required />
            </div>
            <div class="form-group">
              <label>SNMP Port (UDP)</label>
              <input v-model.number="debugForm.snmp_port" type="number" class="form-control" placeholder="161 atau 1610 (DST-NAT)" required />
            </div>
            <div class="form-group">
              <label>SNMP Community</label>
              <input v-model="debugForm.snmp_community" type="text" class="form-control" placeholder="public" required />
            </div>
            <div class="form-group">
              <label>Target OID Walk</label>
              <input v-model="debugForm.oid" type="text" class="form-control" placeholder="1.3.6.1.4.1.17409.2.3.4.1.1.8" required />
            </div>
          </div>

          <div class="quick-oid-presets">
            <span class="txt">Preset OID Populer:</span>
            <button type="button" class="chip" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.1.1.8'">HiOSO Status (EPON)</button>
            <button type="button" class="chip" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.2.1.4'">HiOSO Rx Power</button>
            <button type="button" class="chip" @click="debugForm.oid = '1.3.6.1.4.1.37950.2.1.5.1.1.4'">V-SOL Status (EPON)</button>
            <button type="button" class="chip" @click="debugForm.oid = '1.3.6.1.4.1.3902.1012.3.28.2.1.4'">ZTE C320 Status</button>
            <button type="button" class="chip" @click="debugForm.oid = '1.3.6.1.2.1.1'">System Info</button>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" :disabled="debugging">
              <span v-if="debugging" class="spinner-sm"></span>
              {{ debugging ? 'Scanning SNMP Walk...' : '🚀 Uji OID Sekarang' }}
            </button>
          </div>
        </form>

        <div v-if="debugResult" class="debug-result-box">
          <div :class="['result-header', debugResult.success ? 'success' : 'error']">
            <span>Status: {{ debugResult.success ? 'BERHASIL (200 OK)' : 'GAGAL / TIMEOUT' }}</span>
            <span>Target: {{ debugResult.target }}</span>
          </div>
          <div v-if="debugResult.error" class="error-msg">
            ❌ {{ debugResult.error }}
          </div>
          <div v-else-if="debugResult.data" class="code-preview">
            <div class="code-title">Ditemukan {{ debugResult.count }} OID entries:</div>
            <pre><code>{{ JSON.stringify(debugResult.data, null, 2) }}</code></pre>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL TAMBAH / EDIT OLT -->
    <div v-if="showOltModal" class="modal-overlay" @click.self="closeOltModal">
      <div class="modal-card">
        <div class="modal-header">
          <h3>{{ editingOlt ? 'Edit Perangkat OLT' : 'Tambah Perangkat OLT Baru' }}</h3>
          <button class="close-btn" @click="closeOltModal">&times;</button>
        </div>

        <form @submit.prevent="saveOlt">
          <div class="modal-body">
            <div class="form-group">
              <label>Nama OLT / POP</label>
              <input v-model="oltForm.name" type="text" class="form-control" placeholder="Contoh: OLT POP Utama Kasihan" required />
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>IP Address OLT / VPN</label>
                <input v-model="oltForm.ip_address" type="text" class="form-control" placeholder="10.200.0.5" required />
              </div>
              <div class="form-group">
                <label>SNMP Port (UDP)</label>
                <input v-model.number="oltForm.snmp_port" type="number" class="form-control" placeholder="161" required />
                <small class="form-hint">Ubah ke 1610 / 16161 jika menggunakan DST-NAT MikroTik.</small>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Vendor OID Profile Preset</label>
                <select v-model="oltForm.vendor_code" class="form-control" required @change="onVendorChange">
                  <option v-for="p in profiles" :key="p.code" :value="p.code">
                    {{ p.name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>SNMP Community</label>
                <input v-model="oltForm.snmp_community" type="text" class="form-control" placeholder="public" required />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Teknologi</label>
                <select v-model="oltForm.technology" class="form-control">
                  <option value="epon">EPON</option>
                  <option value="gpon">GPON</option>
                </select>
              </div>
              <div class="form-group">
                <label>Jumlah Port PON</label>
                <input v-model.number="oltForm.total_pons" type="number" min="1" max="64" class="form-control" required />
              </div>
            </div>

            <div class="dstnat-banner">
              💡 <strong>Tips DST-NAT MikroTik (1 IP VPN):</strong> Jika OLT berada di belakang MikroTik, buat baris NAT:
              <code>/ip firewall nat add chain=dstnat action=dst-nat protocol=udp dst-port={{ oltForm.snmp_port || 1610 }} to-addresses={{ oltForm.ip_address || '192.168.88.250' }} to-ports=161</code>
            </div>
          </div>

          <div class="modal-footer">
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

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../utils/api';

const activeTab = ref('olts');
const loading = ref(true);
const olts = ref([]);
const profiles = ref([]);
const onusList = ref([]);

const showOltModal = ref(false);
const editingOlt = ref(null);
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
const debugResult = ref(null);

const totalOnus = computed(() => onusList.value.length);

const filteredOnus = computed(() => {
  return onusList.value.filter(onu => {
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
    olts.value = response.data.data;
    profiles.value = response.data.profiles || [];
    
    // Load ONUs for first OLT if exists
    if (olts.value.length > 0) {
      fetchOnus(olts.value[0].id);
    }
  } catch (err) {
    console.error("Failed to fetch OLTs:", err);
  } finally {
    loading.value = false;
  }
};

const fetchOnus = async (oltId) => {
  try {
    const response = await api.get(`/olts/${oltId}/onus`);
    onusList.value = response.data.data;
  } catch (err) {
    console.error("Failed to fetch ONUs:", err);
  }
};

const getVendorName = (code) => {
  const p = profiles.value.find(x => x.code === code);
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

const editOlt = (olt) => {
  editingOlt.value = olt;
  oltForm.value = { ...olt };
  showOltModal.value = true;
};

const closeOltModal = () => {
  showOltModal.value = false;
};

const onVendorChange = () => {
  const p = profiles.value.find(x => x.code === oltForm.value.vendor_code);
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
  } catch (err) {
    alert("Gagal menyimpan OLT: " + (err.response?.data?.message || err.message));
  } finally {
    savingOlt.value = false;
  }
};

const deleteOlt = async (olt) => {
  if (confirm(`Apakah Anda yakin ingin menghapus OLT "${olt.name}"?`)) {
    try {
      await api.delete(`/olts/${olt.id}`);
      fetchOlts();
    } catch (err) {
      alert("Gagal menghapus OLT");
    }
  }
};

const inspectOlt = (olt) => {
  fetchOnus(olt.id);
  activeTab.value = 'onus';
};

const runOidDebugger = async () => {
  try {
    debugging.value = true;
    debugResult.value = null;
    const response = await api.post('/olts/debug-oid', debugForm.value);
    debugResult.value = response.data;
  } catch (err) {
    debugResult.value = {
      success: false,
      target: debugForm.value.ip_address,
      error: err.response?.data?.error || err.message
    };
  } finally {
    debugging.value = false;
  }
};

const getDbmClass = (dbm) => {
  if (!dbm || dbm === 0) return 'dbm-offline';
  if (dbm > -24.0) return 'dbm-good';
  if (dbm >= -27.0) return 'dbm-warning';
  return 'dbm-critical';
};

const getDbmStatusText = (dbm) => {
  if (!dbm || dbm === 0) return 'Offline';
  if (dbm > -24.0) return 'Bagus 🟢';
  if (dbm >= -27.0) return 'Waspada 🟡';
  return 'Kritis/LOS 🔴';
};

const linkCustomer = (onu) => {
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
.olt-management-page {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #f8fafc;
}

.page-subtitle {
  color: #94a3b8;
  font-size: 0.875rem;
}

.tabs-header {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 0.5rem;
}

.tab-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  padding: 0.5rem 1rem;
  font-weight: 600;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn.active {
  background: #3b82f6;
  color: #fff;
}

.olts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}

.olt-card {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 1rem;
  padding: 1.25rem;
}

.olt-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.olt-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #f1f5f9;
}

.vendor-tag {
  display: inline-block;
  font-size: 0.75rem;
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  padding: 0.1rem 0.5rem;
  border-radius: 0.25rem;
  margin-top: 0.25rem;
}

.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 6px;
}
.status-dot.online { background: #10b981; }
.status-dot.offline { background: #ef4444; }

.olt-info-list {
  font-size: 0.85rem;
  color: #cbd5e1;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 1rem;
}

.info-row {
  display: flex;
  justify-content: space-between;
}

.info-notes {
  background: rgba(0, 0, 0, 0.2);
  padding: 0.5rem;
  border-radius: 0.375rem;
  color: #94a3b8;
  font-size: 0.75rem;
}

.onu-summary-bar {
  display: flex;
  justify-content: space-around;
  background: rgba(15, 23, 42, 0.6);
  padding: 0.75rem;
  border-radius: 0.5rem;
  text-align: center;
}

.onu-stat .num { font-size: 1.2rem; font-weight: 700; display: block; }
.onu-stat .txt { font-size: 0.7rem; color: #94a3b8; }
.onu-stat.green .num { color: #10b981; }
.onu-stat.yellow .num { color: #f59e0b; }
.onu-stat.red .num { color: #ef4444; }

/* ONU Table & Badges */
.filter-bar {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
}

.table-card {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 0.75rem;
  overflow: hidden;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.875rem;
}

.data-table th, .data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.badge-status {
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.7rem;
  font-weight: 700;
}
.badge-status.ONLINE { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.badge-status.OFFLINE { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.badge-status.LOS { background: rgba(239, 68, 68, 0.2); color: #f87171; }

.dbm-badge {
  display: flex;
  flex-direction: column;
}
.dbm-val { font-weight: 700; font-family: monospace; }
.dbm-label { font-size: 0.7rem; }
.dbm-good { color: #34d399; }
.dbm-warning { color: #fbbf24; }
.dbm-critical { color: #f87171; }

/* Debugger Tool */
.debugger-card {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 1rem;
  padding: 1.5rem;
}

.quick-oid-presets {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 1rem;
  margin-bottom: 1.5rem;
}

.chip {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #cbd5e1;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  cursor: pointer;
}

.code-preview pre {
  background: #0f172a;
  padding: 1rem;
  border-radius: 0.5rem;
  max-height: 300px;
  overflow-y: auto;
  color: #38bdf8;
  font-size: 0.8rem;
}

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-card {
  background: #1e293b;
  border-radius: 1rem;
  width: 100%;
  max-width: 550px;
  padding: 1.5rem;
}

.dstnat-banner {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.3);
  padding: 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  color: #93c5fd;
  margin-top: 1rem;
}

.btn-primary { background: #3b82f6; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.btn-secondary { background: #475569; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.form-control { width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.5rem; border-radius: 0.375rem; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.25rem; }
</style>
