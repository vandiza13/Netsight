<template>
  <div v-if="show" class="modal-backdrop" @click.self="closeModal">
    <div class="modal-container slide-up">
      <div class="modal-header">
        <h3 class="modal-title flex items-center gap-2">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          {{ isEdit ? 'Edit Node' : 'Tambah Node' }}
        </h3>
        <button class="modal-close" @click="closeModal">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      
      <div class="modal-body custom-scrollbar">
        <div class="form-group">
          <label>Tipe Perangkat *</label>
          <select v-model="form.type" class="form-input" required :disabled="isEdit">
            <option value="server">Core Router</option>
            <option value="olt">OLT (Optical Line Terminal)</option>
            <option value="odc">ODC (Optical Distribution Cabinet)</option>
            <option value="odp">ODP (Optical Distribution Point)</option>
            <option value="ont">ONT / CPE (Customer Premises Equipment)</option>
          </select>
        </div>

        <!-- Smart Link Autocomplete -->
        <div class="form-group" v-if="['server', 'olt', 'ont'].includes(form.type)">
          <label style="display: flex; align-items: center; justify-content: space-between; color: #3b82f6;">
            <span>Tautkan ke Perangkat Riil (Opsional)</span>
            <span style="font-size: 10px; background: rgba(59,130,246,0.1); padding: 2px 6px; border-radius: 4px;">Smart Sync</span>
          </label>
          
          <div style="position: relative; margin-top: 4px;">
            <div style="position: relative;">
              <input 
                v-model="searchQuery" 
                @input="onSearchInput"
                class="form-input" 
                style="border-color: rgba(59,130,246,0.5); padding-right: 30px;"
                placeholder="Ketik untuk mencari (Nama / SN)..."
                :disabled="!!selectedDeviceToLink"
              >
              <button 
                v-if="selectedDeviceToLink" 
                @click.prevent="clearDeviceSelection"
                style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #ef4444; cursor: pointer; padding: 2px;"
                title="Hapus Tautan"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>

            <!-- Dropdown List -->
            <div 
              v-if="!selectedDeviceToLink && searchQuery && availableDevicesToLink.length > 0" 
              style="position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; margin-top: 4px; max-height: 200px; overflow-y: auto; z-index: 50; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);"
            >
              <div 
                v-for="dev in availableDevicesToLink" 
                :key="dev.id"
                @click="selectDevice(dev)"
                style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color); font-size: 13px;"
                onmouseover="this.style.background='rgba(59,130,246,0.1)'"
                onmouseout="this.style.background='transparent'"
              >
                <div style="font-weight: 500;">{{ dev.name || dev.pppoe_username }}</div>
                <div style="font-size: 11px; color: var(--text-3);">{{ dev.host || dev.serial_number }}</div>
              </div>
            </div>
            
            <div 
              v-if="!selectedDeviceToLink && searchQuery && availableDevicesToLink.length === 0 && !isSearching" 
              style="position: absolute; top: 100%; left: 0; right: 0; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; margin-top: 4px; padding: 12px; text-align: center; font-size: 12px; color: var(--text-3); z-index: 50;"
            >
              Tidak ditemukan.
            </div>
          </div>

          <small v-if="selectedDeviceToLink" style="display: block; margin-top: 4px; color: var(--text-3); font-size: 11px;">
            Data akan otomatis diisi dari sistem (Read-only properties).
          </small>
        </div>

        <!-- Info Passive -->
        <div v-if="['odc', 'odp'].includes(form.type)" style="margin-bottom: 16px; padding: 12px; border-radius: 8px; background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2);">
          <div style="display: flex; align-items: center; gap: 8px; color: #d97706; margin-bottom: 4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span style="font-weight: 600; font-size: 12px;">Passive Splitter</span>
          </div>
          <p style="font-size: 11.5px; color: var(--text-2); margin: 0; line-height: 1.4;">
            Node tipe ini bersifat pasif. Anda bebas menyambungkan ONT langsung ke OLT (Bypass ODC) pada kolom Uplink di bawah jika topologi lapangan tidak menggunakan kabinet ini.
          </p>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>{{ formLabels.name }} *</label>
            <input v-model="form.name" type="text" class="form-input" required :placeholder="formLabels.namePlaceholder" :readonly="!!selectedDeviceToLink">
          </div>
          <div class="form-group">
            <label>{{ formLabels.code }}</label>
            <input v-model="form.code" type="text" class="form-input" :placeholder="formLabels.codePlaceholder" :readonly="!!selectedDeviceToLink">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status" class="form-input" :disabled="!!selectedDeviceToLink && form.type === 'ont'">
              <option v-for="st in availableStatuses" :key="st.value" :value="st.value">
                {{ st.label }}
              </option>
            </select>
          </div>
          <div class="form-group" v-if="['odc', 'odp'].includes(form.type)">
            <label>Kapasitas Port</label>
            <input v-model="form.total_ports" type="number" class="form-input" min="1" max="128">
          </div>
        </div>

        <div class="form-group" v-if="form.type !== 'server'">
          <label>Uplink (Parent Node)</label>
          <select v-model="form.parent_id" class="form-input">
            <option :value="null">-- Tidak ada (Root) --</option>
            <option v-for="n in availableParents" :key="n.id" :value="n.id">
              {{ getHierarchyLabel(n.type) }} - {{ n.name }}
            </option>
          </select>
        </div>

        <div class="form-group" v-if="form.parent_id && parentNode?.total_ports">
          <label>Posisi Slot Port di {{ parentNode?.name }}</label>
          <input v-model="form.port_on_parent" type="number" class="form-input" min="1" :max="parentNode.total_ports" placeholder="Nomor Port...">
        </div>

        <!-- ACS Sync Info Block (Only for linked ONT) -->
        <div v-if="form.type === 'ont' && selectedDeviceToLink" style="margin-bottom: 16px; padding: 12px; border-radius: 8px; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 6px; color: #3b82f6; font-weight: 600; font-size: 12px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <span>Live ACS / SNMP Data</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
              <span style="font-size: 11px; color: var(--text-2);">Redaman:</span>
              <span :style="{ color: form.metadata?.optical_power < -25 ? '#ef4444' : '#10b981', fontWeight: 600, fontSize: '13px' }">
                {{ form.metadata?.optical_power ? `${form.metadata.optical_power} dBm` : 'N/A' }}
              </span>
            </div>
          </div>
          <p style="font-size: 11px; color: var(--text-2); margin: 0; line-height: 1.4;">
            Karena perangkat ini ditautkan, nilai redaman (optical power) dikelola dan disinkronisasikan secara otomatis oleh sistem. Tidak perlu input manual.
          </p>
        </div>

        <!-- Coordinates -->
        <div class="form-group mt-2">
          <label style="display: flex; justify-content: space-between; align-items: center;">
            <span>Koordinat (Lat, Lng)</span>
            <button class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 8px;" @click="pickLocation" type="button">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
              Gunakan GPS Saat Ini
            </button>
          </label>
          <div class="grid-2">
            <input v-model="form.latitude" type="number" step="any" class="form-input" placeholder="Latitude" required>
            <input v-model="form.longitude" type="number" step="any" class="form-input" placeholder="Longitude" required>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" @click="closeModal">Batal</button>
        <button class="btn btn-primary" @click="save" :disabled="store.loading">
          <svg v-if="store.loading" class="spinning" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Simpan
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { useNetworkMapStore } from '../../stores/networkMapStore'
import type { NetworkNode } from '../../stores/networkMapStore'

const props = defineProps<{ show: boolean, editNode?: NetworkNode | null }>()
const emit = defineEmits(['close'])

const store = useNetworkMapStore()

const form = ref({
  id: null as number | null,
  name: '',
  code: '',
  type: 'odp',
  status: 'active',
  parent_id: null as number | null,
  port_on_parent: null as number | null,
  total_ports: 8,
  latitude: null as number | null,
  longitude: null as number | null,
  router_id: null as number | null,
  olt_id: null as number | null,
  acs_device_id: null as number | null,
  metadata: {
    optical_power: null as number | null
  }
})

const selectedDeviceToLink = ref<number | null>(null)
const isEdit = computed(() => !!form.value.id)

const getHierarchyLabel = (type: string) => {
  const map: Record<string, string> = {
    server: '[L1] Core Router',
    olt: '[L2] OLT',
    odc: '[L3] ODC',
    odp: '[L4] ODP'
  }
  return map[type] || ''
}

const formLabels = computed(() => {
  switch (form.value.type) {
    case 'server':
      return { name: 'Hostname', namePlaceholder: 'Contoh: Core-Router-01', code: 'Management IP', codePlaceholder: 'Contoh: 10.10.10.1' }
    case 'olt':
      return { name: 'Nama OLT', namePlaceholder: 'Contoh: ZTE-C320-01', code: 'Management IP', codePlaceholder: 'Contoh: 10.10.10.2' }
    case 'odc':
      return { name: 'Nama ODC', namePlaceholder: 'Contoh: ODC-KMY-01', code: 'Kode Lokasi', codePlaceholder: 'Contoh: KAB-01-UTARA' }
    case 'odp':
      return { name: 'Nama ODP', namePlaceholder: 'Contoh: ODP-KMY-01-08', code: 'Kode Tiang / Label', codePlaceholder: 'Contoh: TNG-123' }
    case 'ont':
      return { name: 'ID Pelanggan / PPPoE', namePlaceholder: 'Contoh: pelanggan01@isp', code: 'Serial Number', codePlaceholder: 'Contoh: ZTEGC1234567' }
    default:
      return { name: 'Nama Node', namePlaceholder: 'Contoh: Node-01', code: 'Kode / Label', codePlaceholder: 'Contoh: LBL-01' }
  }
})

const availableStatuses = computed(() => {
  if (['odc', 'odp'].includes(form.value.type)) {
    return [
      { value: 'active', label: 'Active (Normal)' },
      { value: 'warning', label: 'Warning (Redaman Tinggi / Bending)' },
      { value: 'critical', label: 'Critical (Kabel Putus / Loss)' },
      { value: 'maintenance', label: 'Maintenance (Sedang Perbaikan)' }
    ]
  }
  return [
    { value: 'active', label: 'Active (Online)' },
    { value: 'warning', label: 'Warning (High Load / Latency)' },
    { value: 'critical', label: 'Critical (LOS / Packet Loss)' },
    { value: 'offline', label: 'Offline (Down / Mati)' },
    { value: 'maintenance', label: 'Maintenance (Perbaikan)' }
  ]
})

const availableParents = computed(() => {
  return store.nodes.filter(n => n.id !== form.value.id && ['server', 'olt', 'odc', 'odp'].includes(n.type))
})

const parentNode = computed(() => {
  return store.nodes.find(n => n.id === form.value.parent_id)
})

const availableDevicesToLink = computed(() => {
  if (!store.unmappedDevices) return []
  if (form.value.type === 'server') return store.unmappedDevices.routers || []
  if (form.value.type === 'olt') return store.unmappedDevices.olts || []
  if (form.value.type === 'ont') return store.unmappedDevices.acs_devices || []
  return []
})

const searchQuery = ref('')
const isSearching = ref(false)
let searchTimeout: any = null

const onSearchInput = () => {
  isSearching.value = true
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(async () => {
    await store.fetchUnmappedDevices(searchQuery.value)
    isSearching.value = false
  }, 500)
}

const selectDevice = (dev: any) => {
  selectedDeviceToLink.value = dev.id
  searchQuery.value = dev.name || dev.pppoe_username || dev.host || dev.serial_number
}

const clearDeviceSelection = () => {
  selectedDeviceToLink.value = null
  searchQuery.value = ''
  form.value.router_id = null
  form.value.olt_id = null
  form.value.acs_device_id = null
  form.value.metadata.optical_power = null
  
  if (!isEdit.value) {
    form.value.name = ''
    form.value.code = ''
    form.value.status = 'active'
  }
}

// Autofill logic when a device is selected
watch(selectedDeviceToLink, (newId) => {
  if (!newId) return
  
  if (form.value.type === 'server') {
    const r = store.unmappedDevices.routers?.find((x: any) => x.id === newId)
    if (r) {
      form.value.name = r.name
      form.value.code = r.host
      form.value.router_id = r.id
    }
  } else if (form.value.type === 'olt') {
    const o = store.unmappedDevices.olts?.find((x: any) => x.id === newId)
    if (o) {
      form.value.name = o.name
      form.value.code = o.host
      form.value.olt_id = o.id
    }
  } else if (form.value.type === 'ont') {
    const a = store.unmappedDevices.acs_devices?.find((x: any) => x.id === newId)
    if (a) {
      form.value.name = a.pppoe_username || 'CPE'
      form.value.code = a.serial_number
      form.value.acs_device_id = a.id
      if (a.rx_power_dbm) {
        form.value.metadata.optical_power = a.rx_power_dbm
      }
      if (a.status === 'online') form.value.status = 'active'
      else form.value.status = 'offline'
    }
  }
})

// Reset link selection when type changes
watch(() => form.value.type, () => {
  if (!isEdit.value) {
    selectedDeviceToLink.value = null
    form.value.router_id = null
    form.value.olt_id = null
    form.value.acs_device_id = null
    form.value.metadata.optical_power = null
  }
})

watch(() => props.show, (newVal) => {
  if (newVal) {
    // Reset search state
    searchQuery.value = ''
    isSearching.value = false
    
    // Fetch fresh data each time modal opens
    store.fetchUnmappedDevices()
    store.fetchAllNodesForParent()
    
    if (props.editNode) {
      form.value = { 
        id: props.editNode.id,
        name: props.editNode.name,
        code: props.editNode.code || '',
        type: props.editNode.type,
        status: props.editNode.status,
        parent_id: props.editNode.parent_id,
        port_on_parent: props.editNode.port_on_parent,
        total_ports: props.editNode.total_ports,
        latitude: props.editNode.latitude,
        longitude: props.editNode.longitude,
        router_id: props.editNode.router_id || null,
        olt_id: props.editNode.olt_id || null,
        acs_device_id: props.editNode.acs_device_id || null,
        metadata: {
          optical_power: props.editNode.metadata?.optical_power || null
        }
      }
      // Determine selected link for edit mode
      if (form.value.type === 'server') {
        selectedDeviceToLink.value = form.value.router_id
        if (form.value.router_id) searchQuery.value = form.value.name
      }
      if (form.value.type === 'olt') {
        selectedDeviceToLink.value = form.value.olt_id
        if (form.value.olt_id) searchQuery.value = form.value.name
      }
      if (form.value.type === 'ont') {
        selectedDeviceToLink.value = form.value.acs_device_id
        if (form.value.acs_device_id) searchQuery.value = form.value.name
      }
      
    } else {
      form.value = {
        id: null, name: '', code: '', type: 'odp', status: 'active',
        parent_id: null, port_on_parent: null, total_ports: 8,
        latitude: null, longitude: null,
        router_id: null, olt_id: null, acs_device_id: null,
        metadata: { optical_power: null }
      }
      selectedDeviceToLink.value = null
    }
  }
})

const pickLocation = () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        form.value.latitude = pos.coords.latitude
        form.value.longitude = pos.coords.longitude
      },
      () => {
        alert('Gagal mengambil lokasi GPS.')
      }
    )
  }
}

const save = async () => {
  try {
    if (isEdit.value && form.value.id) {
      await store.updateNode(form.value.id, form.value)
    } else {
      await store.createNode(form.value)
    }
    closeModal()
  } catch (e: any) {
    alert(e.message)
  }
}

const closeModal = () => emit('close')
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.6);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-container {
  width: 100%;
  max-width: 600px;
  background: var(--surface-1);
  border-radius: 12px;
  border: 1px solid var(--border);
  box-shadow: 0 10px 40px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.modal-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-1);
}

.modal-close {
  background: transparent;
  border: none;
  color: var(--text-3);
  cursor: pointer;
  padding: 4px;
}

.modal-close:hover {
  color: var(--text-1);
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid var(--border);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  color: var(--text-2);
  margin-bottom: 6px;
}

.form-input, .form-select {
  width: 100%;
  background: var(--surface-0);
  border: 1px solid var(--border);
  padding: 10px 12px;
  border-radius: 6px;
  color: var(--text-1);
  font-family: inherit;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: var(--accent);
}

.btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 500;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-primary {
  background: var(--accent);
  color: #fff;
}

.btn-secondary {
  background: var(--surface-2);
  color: var(--text-1);
}

.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
</style>
