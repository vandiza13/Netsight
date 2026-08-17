<template>
  <div v-if="show" class="modal-backdrop">
    <div class="modal-card modal-card--md slide-up">
      <div class="modal-header">
        <h3 class="modal-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          {{ isEdit ? 'Edit Rute Kabel' : 'Tambah Rute Kabel' }}
        </h3>
        <button class="modal-close" @click="closeModal">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      
      <div class="modal-body custom-scrollbar">
        <div class="form-group">
          <label>Nama Kabel *</label>
          <input v-model="form.name" type="text" class="input-modern" required placeholder="Contoh: Kabel Feeder Utama">
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Tipe Kabel *</label>
            <select v-model="form.cable_type" class="input-modern" required>
              <option value="backbone">Backbone</option>
              <option value="feeder">Feeder</option>
              <option value="distribution">Distribusi</option>
              <option value="drop">Drop (Pelanggan)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Kapasitas Core</label>
            <select v-model="form.core_count" class="input-modern">
              <option :value="1">1 Core</option>
              <option :value="2">2 Core</option>
              <option :value="4">4 Core</option>
              <option :value="6">6 Core</option>
              <option :value="8">8 Core</option>
              <option :value="12">12 Core</option>
              <option :value="24">24 Core</option>
              <option :value="48">48 Core</option>
              <option :value="96">96 Core</option>
              <option :value="144">144 Core</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label>Titik Hulu (Source)</label>
            <select v-model="form.source_node_id" class="input-modern">
              <option :value="null">-- Tidak ada --</option>
              <option v-for="n in store.nodes" :key="'src-'+n.id" :value="n.id">
                {{ n.name }} ({{ n.type.toUpperCase() }})
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Titik Hilir (Target)</label>
            <select v-model="form.target_node_id" class="input-modern">
              <option :value="null">-- Tidak ada --</option>
              <option v-for="n in store.nodes" :key="'tgt-'+n.id" :value="n.id">
                {{ n.name }} ({{ n.type.toUpperCase() }})
              </option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Status Kabel</label>
          <select v-model="form.status" class="input-modern">
            <option value="active">Active (Normal)</option>
            <option value="damaged">Damaged (Putus/Rusak)</option>
            <option value="maintenance">Maintenance</option>
            <option value="planned">Planned (Rencana)</option>
          </select>
        </div>
        
        <div class="form-group">
          <label>Warna Garis Peta</label>
          <input v-model="form.color" type="color" class="input-modern h-10 w-full p-1 cursor-pointer">
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
import { useNetworkMapStore, FiberLine } from '../../stores/networkMapStore'

const props = defineProps<{ show: boolean, editLine?: FiberLine | null }>()
const emit = defineEmits(['close'])

const store = useNetworkMapStore()

const form = ref({
  id: null as number | null,
  name: '',
  cable_type: 'distribution',
  core_count: 12,
  source_node_id: null as number | null,
  target_node_id: null as number | null,
  status: 'active',
  color: '#8b5cf6'
})

const isEdit = computed(() => !!form.value.id)

watch(() => props.show, (newVal) => {
  if (newVal) {
    if (props.editLine) {
      form.value = { 
        id: props.editLine.id,
        name: props.editLine.name,
        cable_type: props.editLine.cable_type,
        core_count: props.editLine.core_count,
        source_node_id: props.editLine.source_node_id,
        target_node_id: props.editLine.target_node_id,
        status: props.editLine.status,
        color: props.editLine.color || '#8b5cf6'
      }
    } else {
      form.value = {
        id: null, name: '', cable_type: 'distribution', core_count: 12,
        source_node_id: null, target_node_id: null, status: 'active', color: '#8b5cf6'
      }
    }
  }
})

const save = async () => {
  try {
    // If we have auto-coord generation between two nodes, we can calculate it here
    let payload = { ...form.value } as any
    if (!isEdit.value && form.value.source_node_id && form.value.target_node_id) {
      const src = store.nodes.find(n => n.id === form.value.source_node_id)
      const tgt = store.nodes.find(n => n.id === form.value.target_node_id)
      if (src?.latitude && tgt?.latitude) {
        payload.coordinates = [
          [src.latitude, src.longitude],
          [tgt.latitude, tgt.longitude]
        ]
      }
    }

    if (isEdit.value && form.value.id) {
      // await store.updateLine(form.value.id, payload)
      alert("Fungsi edit line API belum dimplementasikan sepenuhnya di vue component ini (untuk simplifikasi).")
    } else {
      await store.createLine(payload)
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
