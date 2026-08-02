<template>
  <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
      <div class="mb-4 sm:mb-0">
        <h1 class="text-2xl md:text-3xl text-white font-bold tracking-tight">ACS Management 🌐</h1>
        <p class="text-slate-400 mt-1">Manage TR-069 enabled modems across all your OLTs.</p>
      </div>

      <!-- Right Side Actions -->
      <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-3">
        <!-- Search -->
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input 
            v-model="searchQuery" 
            @keyup.enter="handleSearch"
            type="text" 
            placeholder="Search by SN, MAC, PPPoE..." 
            class="block w-full pl-10 pr-3 py-2 border border-slate-700 rounded-lg leading-5 bg-slate-900/50 text-slate-200 placeholder-slate-500 focus:outline-none focus:bg-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 sm:text-sm transition-colors"
          >
        </div>
        
        <button 
          @click="fetchData(1)" 
          :disabled="store.loading"
          class="btn bg-slate-800 border-slate-700 hover:bg-slate-700 text-slate-300 transition-colors flex items-center"
        >
          <svg :class="{'animate-spin': store.loading}" class="w-4 h-4 fill-current text-slate-500 shrink-0 mr-2" viewBox="0 0 16 16">
            <path d="M7.95 2A5.95 5.95 0 1013.9 7.95a5.95 5.95 0 00-5.95-5.95zm0 10A4.05 4.05 0 1112 7.95 4.05 4.05 0 017.95 12z" />
            <path d="M12.95 3L11.5 4.45 13 6h3V3l-1.55 1.55z" />
          </svg>
          Refresh
        </button>
      </div>
    </div>

    <!-- Error Alert -->
    <div v-if="store.error" class="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-lg flex items-start space-x-3">
      <svg class="w-5 h-5 text-rose-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <div>
        <h4 class="text-sm font-medium text-rose-300">Sync Error</h4>
        <p class="text-sm text-rose-400/80 mt-1">{{ store.error }}</p>
      </div>
    </div>

    <!-- Feedback Notification -->
    <div v-if="notification" class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center space-x-3 transition-all duration-300">
      <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
      <span class="text-sm font-medium text-emerald-300">{{ notification }}</span>
    </div>

    <!-- Main Table -->
    <div class="bg-slate-800/80 backdrop-blur-sm shadow-lg rounded-xl border border-slate-700/50 mb-8 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <!-- Table header -->
          <thead class="text-xs font-semibold uppercase text-slate-500 bg-slate-900/50 border-b border-slate-700/50">
            <tr>
              <th class="px-5 py-4 text-left"><div class="font-semibold text-slate-300">Status</div></th>
              <th class="px-5 py-4 text-left"><div class="font-semibold text-slate-300">Device Info</div></th>
              <th class="px-5 py-4 text-left"><div class="font-semibold text-slate-300">PPPoE</div></th>
              <th class="px-5 py-4 text-left"><div class="font-semibold text-slate-300">Optical Power</div></th>
              <th class="px-5 py-4 text-left"><div class="font-semibold text-slate-300">Wi-Fi SSID</div></th>
              <th class="px-5 py-4 text-center"><div class="font-semibold text-slate-300">Actions</div></th>
            </tr>
          </thead>
          <!-- Table body -->
          <tbody class="text-sm divide-y divide-slate-700/50">
            <tr v-if="store.loading && store.devices.length === 0">
              <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                <svg class="animate-spin w-8 h-8 mx-auto mb-3 text-blue-500" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Fetching TR-069 Devices...
              </td>
            </tr>
            <tr v-else-if="store.devices.length === 0">
              <td colspan="6" class="px-5 py-8 text-center text-slate-500">
                No ACS devices found.
              </td>
            </tr>
            <tr v-for="device in store.devices" :key="device.id" class="hover:bg-slate-700/30 transition-colors group">
              <!-- Status -->
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <span class="relative flex h-2.5 w-2.5">
                    <span :class="device.status === 'online' ? 'bg-emerald-400' : 'bg-slate-400'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
                    <span :class="device.status === 'online' ? 'bg-emerald-500' : 'bg-slate-500'" class="relative inline-flex rounded-full h-2.5 w-2.5"></span>
                  </span>
                  <span class="text-slate-300">{{ device.status === 'online' ? 'Online' : 'Offline' }}</span>
                </div>
              </td>
              <!-- Device Info -->
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="text-white font-medium mb-0.5">{{ device.serial_number || 'Unknown SN' }}</div>
                <div class="text-xs text-slate-400 font-mono">{{ device.mac_address || 'No MAC' }}</div>
                <div class="text-xs text-slate-500 mt-0.5">{{ device.vendor }} / {{ device.model }}</div>
              </td>
              <!-- PPPoE -->
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="text-blue-400 font-medium">{{ device.pppoe_username || '-' }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ device.ip_address || '-' }}</div>
              </td>
              <!-- Optical Power -->
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="flex items-baseline space-x-1">
                  <span :class="getRxPowerColor(device.rx_power_dbm)" class="font-bold">{{ device.rx_power_dbm ?? '-' }}</span>
                  <span v-if="device.rx_power_dbm" class="text-xs text-slate-500">dBm</span>
                </div>
              </td>
              <!-- WiFi -->
              <td class="px-5 py-4 whitespace-nowrap">
                <div class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-md bg-slate-900/50 border border-slate-700">
                  <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                  </svg>
                  <span class="text-slate-300 font-medium max-w-[120px] truncate" :title="device.wifi_ssid">{{ device.wifi_ssid || 'Hidden/None' }}</span>
                </div>
              </td>
              <!-- Actions -->
              <td class="px-5 py-4 whitespace-nowrap text-center">
                <div class="flex items-center justify-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button 
                    @click="refreshParams(device.id)"
                    class="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded transition-colors"
                    title="Refresh Data"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>
                  <button 
                    @click="openModal(device)"
                    class="p-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded transition-colors"
                    title="Manage Device"
                  >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="px-5 py-3 border-t border-slate-700/50 bg-slate-900/30 flex items-center justify-between">
        <span class="text-sm text-slate-400">
          Showing page <span class="font-medium text-slate-300">{{ store.pagination.current_page }}</span> of <span class="font-medium text-slate-300">{{ store.pagination.last_page }}</span>
          (Total: {{ store.pagination.total }})
        </span>
        <div class="flex space-x-2">
          <button 
            @click="fetchData(store.pagination.current_page - 1)" 
            :disabled="store.pagination.current_page <= 1"
            class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded disabled:opacity-50 transition-colors text-sm font-medium border border-slate-700"
          >
            Prev
          </button>
          <button 
            @click="fetchData(store.pagination.current_page + 1)" 
            :disabled="store.pagination.current_page >= store.pagination.last_page"
            class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded disabled:opacity-50 transition-colors text-sm font-medium border border-slate-700"
          >
            Next
          </button>
        </div>
      </div>
    </div>
    
    <!-- Modal -->
    <AcsDeviceModal 
      :show="isModalOpen" 
      :device="selectedDevice" 
      @close="isModalOpen = false"
      @updated="onDeviceUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useAcsStore } from '../stores/acsStore'
import AcsDeviceModal from '../components/AcsDeviceModal.vue'

const store = useAcsStore()
const searchQuery = ref('')
const notification = ref('')
const isModalOpen = ref(false)
const selectedDevice = ref(null)

onMounted(() => {
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

const showNotification = (msg: string) => {
  notification.value = msg
  setTimeout(() => {
    notification.value = ''
  }, 5000)
}

const onDeviceUpdated = (msg: string) => {
  showNotification(msg)
  // Optionally refresh data after a delay
  setTimeout(() => fetchData(store.pagination.current_page), 2000)
}

const refreshParams = async (deviceId: number) => {
  try {
    await store.refreshDevice(deviceId)
    showNotification('Sync command queued successfully.')
  } catch (err: any) {
    alert(err.message)
  }
}

const getRxPowerColor = (power: number | null) => {
  if (power === null) return 'text-slate-500'
  if (power >= -25 && power <= -8) return 'text-emerald-400'
  if ((power < -25 && power >= -28) || (power > -8 && power <= -3)) return 'text-amber-400'
  return 'text-rose-400'
}
</script>
