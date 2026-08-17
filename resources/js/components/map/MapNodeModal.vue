<template>
  <div v-if="show" class="modal-backdrop">
    <div class="modal-card modal-card--md slide-up">
      <div class="modal-header">
        <h3 class="modal-title">
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
          <select v-model="form.type" class="input-modern" required :disabled="isEdit">
            <option value="server">Server / NOC</option>
            <option value="olt">OLT</option>
            <option value="odc">ODC (Distribution Cabinet)</option>
            <option value="odp">ODP (Distribution Point)</option>
            <option value="ont">ONT / Modem Pelanggan</option>
          </select>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Nama Node *</label>
            <input v-model="form.name" type="text" class="input-modern" required placeholder="Contoh: ODP-KMY-01">
          </div>
          <div class="form-group">
            <label>Kode / Label</label>
            <input v-model="form.code" type="text" class="input-modern" placeholder="Contoh: Tiang-08">
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Status</label>
            <select v-model="form.status" class="input-modern">
              <option value="active">Active</option>
              <option value="warning">Warning</option>
              <option value="critical">Critical</option>
              <option value="offline">Offline</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="form-group" v-if="['odc', 'odp'].includes(form.type)">
            <label>Kapasitas Port</label>
            <input v-model="form.total_ports" type="number" class="input-modern" min="1" max="128">
          </div>
        </div>

        <div class="form-group" v-if="form.type !== 'server'">
          <label>Uplink (Parent Node)</label>
          <select v-model="form.parent_id" class="input-modern">
            <option :value="null">-- Tidak ada (Root) --</option>
            <option v-for="n in availableParents" :key="n.id" :value="n.id">
              {{ n.name }} ({{ n.type.toUpperCase() }})
            </option>
          </select>
        </div>

        <div class="form-group" v-if="form.parent_id && parentNode?.total_ports">
          <label>Posisi Slot Port di {{ parentNode?.name }}</label>
          <input v-model="form.port_on_parent" type="number" class="input-modern" min="1" :max="parentNode.total_ports" placeholder="Nomor Port...">
        </div>

        <!-- Coordinates -->
        <div class="form-group mt-2">
          <label class="flex items-center justify-between">
            <span>Koordinat (Lat, Lng)</span>
            <button class="btn btn-secondary btn-sm" @click="pickLocation" type="button">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
              Gunakan GPS Saat Ini
            </button>
          </label>
          <div class="grid-2">
            <input v-model="form.latitude" type="number" step="any" class="input-modern" placeholder="Latitude" required>
            <input v-model="form.longitude" type="number" step="any" class="input-modern" placeholder="Longitude" required>
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
import { ref, watch, computed } from 'vue'
import { useNetworkMapStore, NetworkNode } from '../../stores/networkMapStore'

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
  longitude: null as number | null
})

const isEdit = computed(() => !!form.value.id)

const availableParents = computed(() => {
  return store.nodes.filter(n => n.id !== form.value.id && ['server', 'olt', 'odc', 'odp'].includes(n.type))
})

const parentNode = computed(() => {
  return store.nodes.find(n => n.id === form.value.parent_id)
})

watch(() => props.show, (newVal) => {
  if (newVal) {
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
        longitude: props.editNode.longitude
      }
    } else {
      form.value = {
        id: null, name: '', code: '', type: 'odp', status: 'active',
        parent_id: null, port_on_parent: null, total_ports: 8,
        latitude: null, longitude: null
      }
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
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
</style>
