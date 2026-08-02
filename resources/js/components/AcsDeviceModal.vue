<template>
  <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity" @click="close"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-2xl bg-slate-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-xl border border-slate-700">
        
        <!-- Header -->
        <div class="bg-slate-800/80 px-6 py-4 border-b border-slate-700 flex justify-between items-center">
          <div class="flex items-center space-x-3">
            <div class="p-2 bg-blue-500/10 rounded-lg">
              <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.04 4.04C10.15 2.04 13.85 2.04 15.96 4.04M10.16 6.16a4 4 0 0 1 3.68 0M12 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM4.93 11.07a10 10 0 0 1 14.14 0" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8z" />
              </svg>
            </div>
            <h3 class="text-xl font-bold text-white">ACS Device Control</h3>
          </div>
          <button @click="close" class="text-slate-400 hover:text-slate-200 transition-colors">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-5">
          <!-- Modem Info Grid -->
          <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Status</span>
              <div class="flex items-center space-x-2">
                <span class="relative flex h-3 w-3">
                  <span :class="device.status === 'online' ? 'bg-emerald-400' : 'bg-rose-400'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
                  <span :class="device.status === 'online' ? 'bg-emerald-500' : 'bg-rose-500'" class="relative inline-flex rounded-full h-3 w-3"></span>
                </span>
                <span class="text-white font-medium capitalize">{{ device.status }}</span>
              </div>
            </div>
            
            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Optical Power</span>
              <div class="flex items-baseline space-x-1">
                <span :class="getRxPowerColor(device.rx_power_dbm)" class="text-xl font-bold">{{ device.rx_power_dbm ?? 'N/A' }}</span>
                <span v-if="device.rx_power_dbm" class="text-slate-400 text-sm">dBm</span>
              </div>
            </div>

            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Model / Vendor</span>
              <span class="text-white font-medium truncate block">{{ device.model || 'Unknown' }}</span>
              <span class="text-slate-400 text-sm">{{ device.vendor }}</span>
            </div>

            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700/50">
              <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">IP Address</span>
              <span class="text-white font-medium">{{ device.ip_address || 'N/A' }}</span>
            </div>
          </div>

          <hr class="border-slate-700 mb-6" />

          <!-- Config Forms -->
          <form @submit.prevent="saveWifi">
            <h4 class="text-sm font-semibold text-blue-400 uppercase tracking-wider mb-4">Wi-Fi Configuration</h4>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">SSID Name</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                  </div>
                  <input v-model="form.ssid" type="text" required class="block w-full pl-10 pr-3 py-2.5 border border-slate-600 rounded-lg bg-slate-900/50 text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Network Name">
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Wi-Fi Password</label>
                <div class="relative">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </div>
                  <input v-model="form.password" :type="showPassword ? 'text' : 'password'" required minlength="8" class="block w-full pl-10 pr-10 py-2.5 border border-slate-600 rounded-lg bg-slate-900/50 text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Leave blank to keep current">
                  <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white">
                    <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
                <p class="text-xs text-slate-500 mt-1">Minimum 8 characters</p>
              </div>
            </div>

            <!-- Error Alert -->
            <div v-if="error" class="mt-4 p-3 bg-rose-500/10 border border-rose-500/50 rounded-lg flex items-start space-x-2">
              <svg class="w-5 h-5 text-rose-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <span class="text-sm text-rose-300 font-medium">{{ error }}</span>
            </div>

            <!-- Footer Actions -->
            <div class="mt-8 flex justify-between items-center bg-slate-900/50 -mx-6 -mb-5 px-6 py-4 border-t border-slate-700">
              <button type="button" @click="reboot" :disabled="isRebooting" class="inline-flex items-center space-x-2 px-4 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 hover:text-amber-300 font-medium rounded-lg transition-colors border border-amber-500/20 disabled:opacity-50">
                <svg v-if="!isRebooting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg v-else class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isRebooting ? 'Rebooting...' : 'Reboot Modem' }}</span>
              </button>
              
              <div class="flex space-x-3">
                <button type="button" @click="close" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium rounded-lg transition-colors">
                  Cancel
                </button>
                <button type="submit" :disabled="isSaving" class="inline-flex items-center space-x-2 px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium rounded-lg shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50">
                  <svg v-if="isSaving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <span>{{ isSaving ? 'Applying...' : 'Apply Changes' }}</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps<{
  show: boolean
  device: any
}>()

const emit = defineEmits(['close', 'updated'])

const store = useAcsStore()

const showPassword = ref(false)
const error = ref('')
const isSaving = ref(false)
const isRebooting = ref(false)

const form = reactive({
  ssid: '',
  password: ''
})

watch(() => props.show, (newVal) => {
  if (newVal && props.device) {
    form.ssid = props.device.wifi_ssid || ''
    form.password = ''
    error.value = ''
    showPassword.value = false
  }
})

const getRxPowerColor = (power: number | null) => {
  if (power === null) return 'text-slate-400'
  if (power >= -25 && power <= -8) return 'text-emerald-400'
  if ((power < -25 && power >= -28) || (power > -8 && power <= -3)) return 'text-amber-400'
  return 'text-rose-400'
}

const close = () => {
  emit('close')
}

const saveWifi = async () => {
  error.value = ''
  isSaving.value = true
  try {
    await store.updateWifi(props.device.id, form.ssid, form.password)
    emit('updated', 'WiFi config update requested.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isSaving.value = false
  }
}

const reboot = async () => {
  if (!confirm('Are you sure you want to reboot this modem? This will disconnect the customer temporarily.')) {
    return
  }
  
  error.value = ''
  isRebooting.value = true
  try {
    await store.rebootDevice(props.device.id)
    emit('updated', 'Reboot command sent to modem.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isRebooting.value = false
  }
}
</script>
