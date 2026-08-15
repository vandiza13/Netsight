<template>
  <transition name="fade">
    <div v-if="isVisible" class="license-modal-overlay">
      <div class="license-modal-content">
        <svg viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" class="brand-logo">
          <defs>
            <linearGradient id="loginBlueGrad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="#06b6d4" />
              <stop offset="100%" stop-color="#3b82f6" />
            </linearGradient>
          </defs>
          <!-- Netsight Text -->
          <text x="200" y="75" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="800" font-size="76" fill="#ffffff" text-anchor="middle" letter-spacing="-1.5">
            Nets<tspan fill="url(#loginBlueGrad)">i</tspan>ght
          </text>
          <!-- Tagline -->
          <g transform="translate(200, 104)">
            <line x1="-190" y1="-4" x2="-125" y2="-4" stroke="url(#loginBlueGrad)" stroke-width="2.5" />
            <text x="0" y="0" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="700" font-size="12" fill="#9ca3af" text-anchor="middle" letter-spacing="2.5">
              INSPECT TRAFFIC. <tspan fill="url(#loginBlueGrad)">SOLVE FASTER.</tspan>
            </text>
            <line x1="125" y1="-4" x2="190" y2="-4" stroke="url(#loginBlueGrad)" stroke-width="2.5" />
          </g>
        </svg>
        
        <h2 class="title">Lisensi Tidak Valid</h2>
        <p class="message">{{ errorMessage }}</p>
        
        <div class="input-group">
          <label for="license-key">Kunci Lisensi (License Key)</label>
          <input 
            type="text" 
            id="license-key" 
            v-model="licenseKey" 
            placeholder="Masukkan kunci lisensi"
            :disabled="isLoading"
          />
        </div>
        
        <button class="btn-activate" @click="submitLicense" :disabled="!licenseKey || isLoading">
          <span v-if="!isLoading">Verifikasi & Aktifkan</span>
          <span v-else>Memverifikasi...</span>
        </button>
        
        <p v-if="submitError" class="submit-error">{{ submitError }}</p>

        <div class="footer-contact">
          <p>Membutuhkan bantuan teknis? Hubungi <strong>Vandiza Tech Support</strong></p>
          <div class="contact-links">
            <a href="mailto:support@vandiza.com">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              support@vandiza.com
            </a>
            <span class="divider">|</span>
            <a href="https://wa.me/6287758611756" target="_blank" rel="noopener noreferrer">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              087758611756
            </a>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const isVisible = ref(false)
const errorMessage = ref('Sistem tidak memiliki lisensi yang valid. Silakan hubungi Administrator.')
const licenseKey = ref('')
const isLoading = ref(false)
const submitError = ref('')

const handleLicenseExpired = (event: Event) => {
  const customEvent = event as CustomEvent
  if (customEvent.detail) {
    errorMessage.value = customEvent.detail
  }
  isVisible.value = true
}

onMounted(() => {
  window.addEventListener('netsight-license-expired', handleLicenseExpired)
})

onUnmounted(() => {
  window.removeEventListener('netsight-license-expired', handleLicenseExpired)
})

const submitLicense = async () => {
  isLoading.value = true
  submitError.value = ''
  
  try {
    const response = await axios.post('/api/settings/license', {
      license_key: licenseKey.value
    })
    
    if (response.data.success) {
      // Reload the page completely to reset all states
      window.location.reload()
    }
  } catch (error: any) {
    submitError.value = error.response?.data?.message || 'Gagal menyimpan lisensi. Pastikan lisensi valid.'
  } finally {
    isLoading.value = false
  }
}
</script>

<style scoped>
.license-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(15, 23, 42, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.license-modal-content {
  background: var(--bg-secondary, #1e293b);
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 40px;
  border-radius: 16px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  text-align: center;
  color: var(--text-primary, #f8fafc);
  position: relative;
  overflow: hidden;
  animation: modalEnter 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes modalEnter {
  0% {
    opacity: 0;
    transform: translateY(40px) scale(0.95);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.license-modal-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, #06b6d4, #3b82f6);
}

.icon-container {
  display: inline-flex;
  padding: 16px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  border-radius: 50%;
  margin-bottom: 20px;
}

.title {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 12px;
  margin-top: 0;
}

.message {
  color: var(--text-secondary, #94a3b8);
  margin-bottom: 30px;
  line-height: 1.5;
}

.input-group {
  text-align: left;
  margin-bottom: 24px;
}

.input-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 8px;
  color: var(--text-secondary, #94a3b8);
}

.input-group input {
  width: 100%;
  padding: 12px 16px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-size: 16px;
  outline: none;
  transition: all 0.3s;
  box-sizing: border-box;
}

.input-group input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}

.btn-activate {
  width: 100%;
  padding: 14px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-activate:hover:not(:disabled) {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
}

.btn-activate:disabled {
  background: #475569;
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.7;
  transform: none;
}

.submit-error {
  color: #ef4444;
  font-size: 14px;
  margin-top: 16px;
  animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
  10%, 90% { transform: translate3d(-1px, 0, 0); }
  20%, 80% { transform: translate3d(2px, 0, 0); }
  30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
  40%, 60% { transform: translate3d(3px, 0, 0); }
}

.brand-logo {
  height: 52px;
  margin: 0 auto 24px auto;
  display: block;
  animation: logoFloat 6s ease-in-out infinite;
}

@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-8px); }
}

.footer-contact {
  margin-top: 32px;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  font-size: 13px;
  color: var(--text-secondary, #94a3b8);
}

.footer-contact p {
  margin: 0 0 12px 0;
}

.footer-contact strong {
  color: #e2e8f0;
}

.contact-links {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 12px;
}

.contact-links a {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #3b82f6;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.2s;
}

.contact-links a:hover {
  color: #60a5fa;
  transform: translateY(-1px);
}

.divider {
  color: rgba(255, 255, 255, 0.1);
  font-size: 12px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
