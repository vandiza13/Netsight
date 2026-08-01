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
              OID Debugger
            </button>
            <button class="btn btn-primary" @click="openAddOltModal">
              + Tambah OLT
            </button>
          </div>
        </section>

        <!-- Navigation Tabs (Segmented Control) -->
        <section class="stagger">
          <div class="segmented-control-wrapper">
            <div class="segmented-control">
              <button :class="['segmented-btn', { active: activeTab === 'olts' }]" @click="activeTab = 'olts'">
                Master OLT ({{ oltStore.olts.length }})
              </button>
              <button :class="['segmented-btn', { active: activeTab === 'onus' }]" @click="activeTab = 'onus'">
                Redaman ONU ({{ totalOnus }})
              </button>
              <button :class="['segmented-btn', { active: activeTab === 'debugger' }]" @click="activeTab = 'debugger'">
                OID Debugger Live
              </button>
            </div>
          </div>

          <!-- TAB 1: MASTER OLT LIST -->
          <div v-if="activeTab === 'olts'" class="tab-pane fade-in">
            <div v-if="oltStore.loading" class="loading-state">
              <span class="spinning">🔄</span> Memuat data OLT...
            </div>

            <div v-else-if="oltStore.olts.length === 0" class="glass-card empty-panel">
              <div class="empty-icon-wrap">⚡</div>
              <h3>Belum Ada Perangkat OLT</h3>
              <p>Tambahkan OLT (HiOSO, V-SOL, HSan, ZTE, Huawei) untuk mulai memantau jaringan optik FTTH.</p>
              <button class="btn btn-primary mt-3" @click="openAddOltModal">+ Tambah OLT Sekarang</button>
            </div>

            <div v-else class="olts-grid-container">
              <div v-for="olt in oltStore.olts" :key="olt.id" class="premium-card olt-card-item">
                <div class="olt-card-header">
                  <div>
                    <div class="olt-title-row">
                      <span :class="['status-glow', olt.status]"></span>
                      <h4 class="olt-card-name">{{ olt.name }}</h4>
                    </div>
                    <span class="vendor-badge">{{ getVendorName(olt.vendor_code) }}</span>
                  </div>
                  <div class="olt-card-actions">
                    <button class="btn-action-icon" title="Sync Redaman" @click="syncOlt(olt)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
                    </button>
                    <button class="btn-action-icon" title="Inspect ONUs" @click="inspectOlt(olt)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    </button>
                    <button class="btn-action-icon" title="Edit OLT" @click="editOlt(olt)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                    </button>
                    <button class="btn-action-icon danger" title="Hapus OLT" @click="deleteOlt(olt)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                    </button>
                  </div>
                </div>

                <div class="olt-info-body">
                  <div class="info-line">
                    <span class="lbl">IP & Port:</span>
                    <span class="val-code">{{ olt.ip_address }}:{{ olt.snmp_port }}</span>
                  </div>
                  <div class="info-line">
                    <span class="lbl">Teknologi:</span>
                    <span class="val uppercase">{{ olt.technology }} ({{ olt.total_pons }} PON)</span>
                  </div>
                  <div class="info-line">
                    <span class="lbl">Community:</span>
                    <span class="val-code">{{ olt.snmp_community }}</span>
                  </div>
                  <div v-if="olt.notes" class="notes-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px; opacity:0.7; flex-shrink:0"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    {{ olt.notes }}
                  </div>
                </div>

                <div class="onu-summary-row">
                  <div class="summary-stat green-glow">
                    <span class="num">{{ olt.onus_online || 0 }}</span>
                    <span class="label">Online</span>
                  </div>
                  <div class="summary-stat yellow-glow">
                    <span class="num">{{ olt.onus_offline || 0 }}</span>
                    <span class="label">Offline</span>
                  </div>
                  <div class="summary-stat red-glow">
                    <span class="num">{{ olt.onus_los || 0 }}</span>
                    <span class="label">LOS</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 2: ONU REDAMAN MONITORING -->
          <div v-if="activeTab === 'onus'" class="tab-pane fade-in">
            <div class="premium-card panel-full">
              <div class="filter-flex-bar">
                <div class="search-wrap">
                  <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                  <input v-model="onuSearch" type="text" placeholder="Cari User PPPoE, Nama, SN, atau MAC..." class="input-modern search-input" />
                </div>
                
                <!-- OLT Selector -->
                <select :value="oltStore.selectedOlt?.id" @change="onOltFilterChange" class="input-modern filter-select" style="max-width: 250px;">
                  <option v-for="olt in oltStore.olts" :key="olt.id" :value="olt.id">
                    {{ olt.name }}
                  </option>
                  <option v-if="oltStore.olts.length === 0" value="" disabled>Belum ada OLT</option>
                </select>

                <select v-model="onuStatusFilter" class="input-modern filter-select">
                  <option value="all">Semua Status ONU</option>
                  <option value="online">Online</option>
                  <option value="offline">Offline</option>
                  <option value="los">LOS (Kabel Putus)</option>
                </select>
              </div>

              <div class="table-container custom-scrollbar">
                <table class="premium-table">
                  <thead>
                    <tr>
                      <th style="width: 15%;">PORT / INDEX</th>
                      <th style="width: 25%;">PELANGGAN / PPPoE</th>
                      <th style="width: 20%;">SN / MAC</th>
                      <th style="width: 10%;">STATUS</th>
                      <th style="width: 15%;">REDAMAN (RX)</th>
                      <th style="width: 15%; text-align: right;">AKSI</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="filteredOnus.length === 0">
                      <td colspan="6" class="text-center text-gray-500 py-4">Tidak ada data ONU yang cocok.</td>
                    </tr>
                    <tr v-for="onu in filteredOnus" :key="onu.id" class="table-row-hover">
                      <td class="font-bold text-primary">{{ onu.pon_port }} / ONU {{ onu.onu_index }}</td>
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
                        <span :class="['status-badge-modern', statusBadgeClass(onu.status)]">
                          {{ onu.status.toUpperCase() }}
                        </span>
                      </td>
                      <td>
                        <div :class="['dbm-pill', getDbmClass(onu.rx_power_dbm)]">
                          <span class="val">{{ onu.rx_power_dbm ? onu.rx_power_dbm.toFixed(2) + ' dBm' : '-' }}</span>
                        </div>
                      </td>
                      <td class="text-right">
                        <button class="btn btn-sm btn-outline" @click="openHistoryModal(onu)" style="margin-right: 4px;">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                          Histori
                        </button>
                        <button class="btn btn-sm btn-outline" @click="linkCustomer(onu)">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                          Link User
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- TAB 3: OID DEBUGGER TOOL LIVE -->
          <div v-if="activeTab === 'debugger'" class="tab-pane fade-in">
            <div class="premium-card panel-full">
              <div class="panel-header-custom">
                <h3>OID Debugger Tool Live</h3>
                <p>Uji koneksi SNMP Walk ke IP OLT untuk menganalisa respon OID mentah secara langsung.</p>
              </div>

              <form @submit.prevent="runOidDebugger" class="debugger-form-body">
                <div class="form-grid-4">
                  <div class="form-group-modern">
                    <label>IP Address OLT</label>
                    <input v-model="debugForm.ip_address" type="text" class="input-modern" placeholder="Contoh: 10.200.0.5" required />
                  </div>
                  <div class="form-group-modern">
                    <label>SNMP Port (UDP)</label>
                    <input v-model.number="debugForm.snmp_port" type="number" class="input-modern" placeholder="161" required />
                  </div>
                  <div class="form-group-modern">
                    <label>SNMP Community</label>
                    <input v-model="debugForm.snmp_community" type="text" class="input-modern" placeholder="public" required />
                  </div>
                  <div class="form-group-modern">
                    <label>Target OID Walk</label>
                    <input v-model="debugForm.oid" type="text" class="input-modern" placeholder="1.3.6.1.4.1..." required />
                  </div>
                </div>

                <div class="preset-chips-row">
                  <span class="lbl-chips text-secondary">Preset Populer:</span>
                  <button type="button" class="chip-interactive" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.1.1.8'">HiOSO Status (EPON)</button>
                  <button type="button" class="chip-interactive" @click="debugForm.oid = '1.3.6.1.4.1.17409.2.3.4.2.1.4'">HiOSO Rx Power</button>
                  <button type="button" class="chip-interactive" @click="debugForm.oid = '1.3.6.1.4.1.37950.2.1.5.1.1.4'">V-SOL Status (EPON)</button>
                  <button type="button" class="chip-interactive" @click="debugForm.oid = '1.3.6.1.4.1.3902.1012.3.28.2.1.4'">ZTE C320 Status</button>
                  <button type="button" class="chip-interactive" @click="debugForm.oid = '1.3.6.1.2.1.1'">System Info</button>
                </div>

                <div class="form-actions-row">
                  <button type="submit" class="btn btn-primary btn-lg" :disabled="debugging">
                    <span v-if="debugging" class="spinning">🔄</span>
                    <span v-else>
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px; vertical-align:middle"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    </span>
                    {{ debugging ? 'Scanning SNMP Walk...' : 'Uji OID Sekarang' }}
                  </button>
                </div>
              </form>

              <div v-if="debugResult" class="terminal-box fade-in">
                <div class="terminal-header">
                  <div class="window-controls">
                    <span></span><span></span><span></span>
                  </div>
                  <div class="terminal-title">Status: <span :class="debugResult.success ? 'text-green' : 'text-red'">{{ debugResult.success ? '200 OK' : 'FAILED' }}</span> | Target: {{ debugResult.target }}</div>
                </div>
                <div class="terminal-body custom-scrollbar">
                  <div v-if="debugResult.error" class="text-red">
                    Error: {{ debugResult.error }}
                  </div>
                  <div v-else-if="debugResult.data">
                    <div class="text-cyan mb-2"># Ditemukan {{ debugResult.count }} OID entries:</div>
                    <pre><code>{{ JSON.stringify(debugResult.data, null, 2) }}</code></pre>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- MODAL TAMBAH / EDIT OLT -->
    <div v-if="showOltModal" class="modal-backdrop" @click.self="closeOltModal">
      <div class="glass-card modal-content-box fade-in-up">
        <div class="modal-header-row">
          <h3>{{ editingOlt ? 'Edit Perangkat OLT' : 'Tambah Perangkat OLT Baru' }}</h3>
          <button class="btn-close" @click="closeOltModal">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>

        <form @submit.prevent="saveOlt" class="modal-form">
          <div class="modal-body-scroll custom-scrollbar">
            <div class="form-group-modern mb-4">
              <label>Nama OLT / POP <span class="tooltip-icon" title="Nama lokasi atau identitas perangkat OLT">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
              </span></label>
              <input v-model="oltForm.name" type="text" class="input-modern" placeholder="Contoh: OLT POP Utama Kasihan" required />
            </div>

            <div class="form-row-2 mb-4">
              <div class="form-group-modern">
                <label>IP Address OLT / VPN <span class="tooltip-icon" title="Alamat IP yang bisa dijangkau oleh Netsight (misal: IP VPN / Tailscale)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span></label>
                <input v-model="oltForm.ip_address" type="text" class="input-modern" placeholder="10.200.0.5" required />
              </div>
              <div class="form-group-modern">
                <label>SNMP Port (UDP) <span class="tooltip-icon" title="Port SNMP, standarnya 161. Jika via VPN MikroTik bisa menggunakan port lain (contoh: 1610)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span></label>
                <input v-model.number="oltForm.snmp_port" type="number" class="input-modern" placeholder="161" required />
                <small class="hint-txt">Ubah ke 1610 / 16161 jika pakai DST-NAT MikroTik.</small>
              </div>
            </div>

            <div class="form-row-2 mb-4">
              <div class="form-group-modern">
                <label>Vendor OID Profile <span class="tooltip-icon" title="Pilih merek OLT untuk menentukan cara sistem membaca data dari perangkat tersebut">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span></label>
                <select v-model="oltForm.vendor_code" class="input-modern" required @change="onVendorChange">
                  <option v-for="p in oltStore.profiles" :key="p.code" :value="p.code">
                    {{ p.name }}
                  </option>
                </select>
              </div>
              <div class="form-group-modern">
                <label>SNMP Community <span class="tooltip-icon" title="Kata sandi SNMP untuk membaca data (ReadOnly), standar pabrik biasanya 'public'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span></label>
                <input v-model="oltForm.snmp_community" type="text" class="input-modern" placeholder="public" required />
              </div>
            </div>

            <div class="form-row-2 mb-4">
              <div class="form-group-modern">
                <label>Teknologi</label>
                <select v-model="oltForm.technology" class="input-modern">
                  <option value="epon">EPON</option>
                  <option value="gpon">GPON</option>
                </select>
              </div>
              <div class="form-group-modern">
                <label>Jumlah Port PON</label>
                <input v-model.number="oltForm.total_pons" type="number" min="1" max="64" class="input-modern" required />
              </div>
            </div>

            <div class="dstnat-info-box">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; float:left; margin-top:2px"><line x1="9" x2="15" y1="18" y2="18"/><line x1="10" x2="14" y1="22" y2="22"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg>
              <strong>Petunjuk DST-NAT MikroTik (1 IP VPN):</strong><br/>
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
    <!-- MODAL HISTORI ONU -->
    <OnuHistoryModal 
      v-if="showHistoryModal && selectedOnuForHistory"
      :onu="selectedOnuForHistory"
      @close="closeHistoryModal"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import SidebarNav from '../components/SidebarNav.vue';
import TopBar from '../components/TopBar.vue';
import OnuHistoryModal from '../components/OnuHistoryModal.vue';
import api from '../utils/api';
import { useOltStore } from '../stores/oltStore';
import { useToastStore } from '../stores/toastStore';

const sidebarOpen = ref(false);
const activeTab = ref('olts');
const oltStore = useOltStore();
const toastStore = useToastStore();

const showOltModal = ref(false);
const editingOlt = ref<any>(null);
const savingOlt = ref(false);

const showHistoryModal = ref(false);
const selectedOnuForHistory = ref<any>(null);

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

const totalOnus = computed(() => oltStore.onus.length);

const filteredOnus = computed(() => {
  return oltStore.onus.filter((onu: any) => {
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
  await oltStore.fetchOlts();
  if (oltStore.olts.length > 0 && !oltStore.selectedOlt) {
    oltStore.selectOlt(oltStore.olts[0].id);
  }
};

const syncOlt = async (olt: any) => {
  if (!confirm(`Tarik data redaman terbaru dari OLT ${olt.name} sekarang? (Membutuhkan waktu beberapa detik)`)) return;
  
  try {
    await oltStore.syncOlt(olt.id);
    toastStore.success('Sync berhasil dikirim ke background.');
  } catch (err: any) {
    toastStore.error("Gagal melakukan sync OLT: " + err.message);
  }
};

const getVendorName = (code: string) => {
  const p = oltStore.profiles.find((x: any) => x.code === code);
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
  const p = oltStore.profiles.find((x: any) => x.code === oltForm.value.vendor_code);
  if (p) {
    oltForm.value.technology = (p as any).technology || 'epon';
  }
};

const saveOlt = async () => {
  try {
    savingOlt.value = true;
    if (editingOlt.value) {
      await oltStore.updateOlt(editingOlt.value.id, oltForm.value);
      toastStore.success('OLT berhasil diperbarui.');
    } else {
      await oltStore.createOlt(oltForm.value);
      toastStore.success('OLT berhasil ditambahkan.');
    }
    closeOltModal();
  } catch (err: any) {
    toastStore.error("Gagal menyimpan OLT: " + err.message);
  } finally {
    savingOlt.value = false;
  }
};

const deleteOlt = async (olt: any) => {
  if (confirm(`Apakah Anda yakin ingin menghapus OLT "${olt.name}"?`)) {
    try {
      await oltStore.deleteOlt(olt.id);
      toastStore.success('OLT berhasil dihapus.');
    } catch (err: any) {
      toastStore.error("Gagal menghapus OLT: " + err.message);
    }
  }
};

const inspectOlt = (olt: any) => {
  oltStore.selectOlt(olt.id);
  activeTab.value = 'onus';
};

const openHistoryModal = (onu: any) => {
  selectedOnuForHistory.value = onu;
  showHistoryModal.value = true;
};

const closeHistoryModal = () => {
  showHistoryModal.value = false;
  selectedOnuForHistory.value = null;
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

const statusBadgeClass = (status: string) => {
  if (status === 'online') return 'badge-success';
  if (status === 'offline') return 'badge-warning';
  return 'badge-danger';
};

const linkCustomer = async (onu: any) => {
  const name = prompt("Masukkan User PPPoE atau Nama Pelanggan untuk ONU ini:", onu.customer_name || onu.onu_description || '');
  if (name !== null) {
    const originalName = onu.customer_name;
    try {
      onu.customer_name = name; // Optimistic update
      await oltStore.updateOnu(onu.id, { customer_name: name });
      toastStore.success('Customer name linked successfully');
    } catch (error: any) {
      onu.customer_name = originalName; // Revert
      toastStore.error(error.message);
    }
  }
};

const onOltFilterChange = (event: Event) => {
  const target = event.target as HTMLSelectElement;
  const oltId = parseInt(target.value, 10);
  if (!isNaN(oltId)) {
    oltStore.selectOlt(oltId);
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
  padding: 32px 40px;
  overflow-y: auto;
}
.dashboard__heading {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-primary);
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
  color: var(--text-secondary);
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
}

/* Animations */
.fade-in { animation: fadeIn 0.4s ease-out forwards; }
.fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.stagger > * { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
.stagger > *:nth-child(1) { animation-delay: 0.1s; }
.stagger > *:nth-child(2) { animation-delay: 0.2s; }
.stagger > *:nth-child(3) { animation-delay: 0.3s; }

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.5); }

/* Segmented Control (Tabs) */
.segmented-control-wrapper {
  margin-bottom: 24px;
}
.segmented-control {
  display: inline-flex;
  background: var(--surface-2);
  padding: 4px;
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.segmented-btn {
  background: transparent;
  color: var(--text-secondary);
  border: none;
  padding: 8px 20px;
  font-size: 0.875rem;
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.segmented-btn:hover {
  color: var(--text-primary);
}
.segmented-btn.active {
  background: var(--surface-1);
  color: var(--accent-cyan);
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  font-weight: 600;
}

.premium-card {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  border-radius: 16px;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}
.panel-full { padding: 24px; }

.empty-panel {
  text-align: center;
  padding: 60px 20px;
  background: var(--surface-2);
  border: 1px dashed var(--border-color);
  border-radius: 16px;
  max-width: 600px;
  margin: 0 auto;
}
.empty-icon-wrap {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.8;
}
.empty-panel h3 {
  font-size: 1.5rem;
  color: var(--text-primary);
  margin-bottom: 10px;
}
.empty-panel p {
  color: var(--text-secondary);
  font-size: 1rem;
  margin-bottom: 24px;
}

.olts-grid-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 1.5rem;
}
.olt-card-item {
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition: transform 0.2s, box-shadow 0.2s;
}
.olt-card-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.olt-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.25rem;
  position: relative;
}

.olt-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}
.status-glow {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-glow.online { background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.6); }
.status-glow.offline { background: #f43f5e; box-shadow: 0 0 10px rgba(244, 63, 94, 0.6); }

.olt-card-name {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.01em;
}

.vendor-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: linear-gradient(90deg, rgba(56, 189, 248, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.2);
  padding: 2px 8px;
  border-radius: 9999px;
  margin-top: 6px;
  margin-left: 20px;
}

.olt-card-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transform: translateX(10px);
  transition: all 0.3s;
}
.olt-card-item:hover .olt-card-actions {
  opacity: 1;
  transform: translateX(0);
}
.btn-action-icon {
  background: var(--surface-2);
  border: 1px solid var(--border-color);
  color: var(--text-secondary);
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
  background: var(--surface-3, rgba(255,255,255,0.1));
  color: var(--text-primary);
  border-color: rgba(255,255,255,0.2);
}
.btn-action-icon.danger:hover {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border-color: rgba(239, 68, 68, 0.3);
}

.olt-info-body {
  font-size: 0.85rem;
  color: var(--text-secondary);
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
  padding: 0 10px;
}
.info-line { display: flex; justify-content: space-between; align-items: center; }
.val-code { font-family: 'Fira Code', monospace; color: var(--text-primary); font-size: 0.8rem; }
.uppercase { text-transform: uppercase; font-weight: 500; color: var(--text-primary); }

.notes-box {
  background: rgba(255, 255, 255, 0.03);
  padding: 8px 12px;
  border-radius: 8px;
  color: var(--text-muted);
  font-size: 0.75rem;
  margin-top: 4px;
  border-left: 2px solid rgba(255,255,255,0.1);
  display: flex;
}

.onu-summary-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.summary-stat {
  flex: 1;
  background: var(--surface-2);
  padding: 12px 0;
  border-radius: 12px;
  text-align: center;
  border: 1px solid var(--border-color);
  position: relative;
  overflow: hidden;
}
.summary-stat::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  opacity: 0.7;
}
.summary-stat.green-glow::before { background: #10b981; }
.summary-stat.yellow-glow::before { background: #f59e0b; }
.summary-stat.red-glow::before { background: #f43f5e; }

.summary-stat .num { font-size: 1.25rem; font-weight: 800; display: block; color: var(--text-primary); }
.summary-stat .label { font-size: 0.7rem; color: var(--text-secondary); font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
.summary-stat.red-glow .num { color: #f43f5e; text-shadow: 0 0 10px rgba(244, 63, 94, 0.4); }

/* Table Redaman ONU */
.filter-flex-bar {
  display: flex;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.search-wrap {
  position: relative;
  flex: 1;
  min-width: 250px;
}
.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-secondary);
}
.search-input {
  width: 100%;
  padding-left: 42px !important;
}

.table-container {
  max-height: 500px;
  overflow-y: auto;
  border: 1px solid var(--border-color);
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
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-color);
  z-index: 10;
}
.premium-table td {
  padding: 16px;
  border-bottom: 1px solid rgba(255,255,255,0.03);
  font-size: 0.875rem;
  color: var(--text-primary);
  vertical-align: middle;
}
.table-row-hover { transition: background 0.2s; }
.table-row-hover:hover { background: rgba(255, 255, 255, 0.03); }
.table-row-hover:last-child td { border-bottom: none; }

.cust-title { font-weight: 600; color: var(--text-primary); display: block; }
.pppoe-tag { font-size: 0.7rem; color: #38bdf8; background: rgba(56, 189, 248, 0.1); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; }
.val-code-xs { font-size: 0.75rem; color: var(--text-secondary); font-family: 'Fira Code', monospace; }

.status-badge-modern {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 6px;
  letter-spacing: 0.05em;
  display: inline-block;
}
.badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
.badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
.badge-danger { background: rgba(244, 63, 94, 0.15); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.3); }

.dbm-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  border-radius: 9999px;
  font-weight: 700;
  font-family: 'Fira Code', monospace;
  font-size: 0.8rem;
  letter-spacing: 0.02em;
  min-width: 90px;
  border: 1px solid transparent;
}
.dbm-good { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
.dbm-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
.dbm-critical { background: rgba(244, 63, 94, 0.15); color: #f43f5e; border-color: rgba(244, 63, 94, 0.3); }
.dbm-offline { background: rgba(255,255,255,0.05); color: var(--text-muted); border-color: rgba(255,255,255,0.1); }

/* Debugger Tool */
.panel-header-custom { margin-bottom: 24px; }
.panel-header-custom h3 { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
.panel-header-custom p { color: var(--text-secondary); font-size: 0.875rem; }

.form-grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.form-group-modern { display: flex; flex-direction: column; width: 100%; }
.form-group-modern label { font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px; font-weight: 500; display: flex; align-items: center; }
.input-modern {
  width: 100%;
  box-sizing: border-box;
  display: block;
  background: var(--surface-2);
  border: 1px solid var(--border-color);
  padding: 10px 14px;
  border-radius: 8px;
  color: var(--text-primary);
  font-family: inherit;
  transition: all 0.2s;
  font-size: 0.9rem;
}
.input-modern:focus {
  outline: none;
  border-color: var(--accent-cyan);
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
  background: var(--surface-3, rgba(255,255,255,0.05));
}
.form-select.filter-select {
  width: 220px;
}

.preset-chips-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; }
.lbl-chips { font-size: 0.8rem; font-weight: 500; margin-right: 4px; }
.chip-interactive {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  color: var(--text-secondary);
  padding: 6px 12px;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.chip-interactive:hover {
  background: rgba(14, 165, 233, 0.1);
  color: var(--accent-cyan);
  border-color: rgba(14, 165, 233, 0.3);
  transform: translateY(-1px);
}

.terminal-box {
  margin-top: 24px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,0.1);
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.terminal-header {
  background: #1e293b;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid rgba(0,0,0,0.3);
}
.window-controls { display: flex; gap: 6px; margin-right: 16px; }
.window-controls span { width: 12px; height: 12px; border-radius: 50%; }
.window-controls span:nth-child(1) { background: #ff5f56; }
.window-controls span:nth-child(2) { background: #ffbd2e; }
.window-controls span:nth-child(3) { background: #27c93f; }
.terminal-title { font-family: 'Fira Code', monospace; font-size: 0.75rem; color: #94a3b8; }
.terminal-body {
  background: #0f172a;
  padding: 16px;
  max-height: 400px;
  overflow-y: auto;
  font-family: 'Fira Code', monospace;
  font-size: 0.85rem;
}
.terminal-body pre { color: #a5b4fc; margin: 0; }
.text-cyan { color: #38bdf8; }
.text-green { color: #10b981; }
.text-red { color: #f43f5e; }

/* Modal */
.modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(8px);
  display: flex; justify-content: center; align-items: center;
  z-index: 9999; padding: 1rem;
}
.modal-content-box {
  width: 100%; max-width: 600px;
  padding: 30px;
  max-height: 90vh;
  display: flex; flex-direction: column;
  background: var(--bg-primary);
  border: 1px solid var(--border-color);
  box-shadow: 0 20px 50px rgba(0,0,0,0.3);
  border-radius: 16px;
}
.modal-header-row {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 24px;
}
.modal-header-row h3 { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.btn-close {
  background: transparent; border: none; color: var(--text-secondary); cursor: pointer;
  padding: 6px; border-radius: 8px; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.btn-close:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
.modal-body-scroll {
  overflow-y: auto; padding-right: 12px; flex: 1; margin-right: -12px;
}

/* Tooltip Icon */
.tooltip-icon {
  display: inline-flex;
  align-items: center;
  margin-left: 6px;
  color: var(--text-muted);
  cursor: help;
  transition: color 0.2s;
}
.tooltip-icon:hover { color: var(--accent-cyan); }

/* Buttons */
.btn {
  display: inline-flex; align-items: center; justify-content: center;
  padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
  cursor: pointer; border: none; transition: all 0.2s; font-family: inherit;
}
.btn-primary { background: var(--accent-cyan, #0ea5e9); color: #fff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.2); }
.btn-primary:hover:not(:disabled) { background: #0284c7; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(14, 165, 233, 0.3); }
.btn-secondary { background: var(--surface-2); border: 1px solid var(--border-color); color: var(--text-primary); }
.btn-secondary:hover { background: var(--surface-3); border-color: rgba(255,255,255,0.2); }
.btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }
.btn-outline:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); border-color: rgba(255,255,255,0.2); }
.btn-sm { padding: 8px 14px; font-size: 0.85rem; }
.btn-lg { padding: 14px 28px; font-size: 1rem; }

.modal-form {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

.spinning { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.mb-4 { margin-bottom: 1.5rem; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.hint-txt { font-size: 0.75rem; color: var(--text-secondary); margin-top: 6px; display: block; }
.dstnat-info-box {
  background: rgba(14, 165, 233, 0.08);
  border: 1px solid rgba(14, 165, 233, 0.2);
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 0.8rem;
  color: var(--accent-cyan);
  margin-top: 24px;
  line-height: 1.5;
}
.dstnat-info-box code { font-family: 'Fira Code', monospace; display: block; margin-top: 6px; color: #a5b4fc; }

.modal-footer-row {
  display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;
  padding-top: 20px; border-top: 1px solid var(--border-color);
}
</style>
