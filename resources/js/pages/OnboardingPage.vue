<template>
  <div class="login-page">
    <!-- Animated gradient mesh background -->
    <div class="login-bg">
      <div class="login-bg__orb login-bg__orb--1" />
      <div class="login-bg__orb login-bg__orb--2" />
      <div class="login-bg__orb login-bg__orb--3" />
      <div class="login-bg__grid" />
    </div>

    <!-- Onboarding card -->
    <div class="login-card glass-card glass-card--elevated onboarding-card" :class="{ 'login-card--shake': shakeCard }">
      <!-- Header -->
      <div class="login-header">
        <h2 class="text-xl font-bold text-white mb-2">Welcome to Netsight</h2>
        <p class="text-sm text-gray-400">Please complete your account setup to continue.</p>
      </div>

      <!-- Error message -->
      <Transition name="error">
        <div v-if="error" class="login-error">
          <span class="login-error__icon">⚠</span>
          <span class="login-error__text">{{ error }}</span>
        </div>
      </Transition>

      <form class="login-form" @submit.prevent="handleSubmit">
        <!-- Section 1: Change Password -->
        <div class="onboarding-section">
          <h3 class="section-title"><span class="section-badge">1</span> Change Default Password</h3>
          
          <div class="input-group mt-4">
            <label class="input-label" for="password">New Password (Required)</label>
            <div class="input-wrapper" :class="{ 'input-wrapper--focus': passwordFocused }">
              <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Enter new password"
                required
                minlength="6"
                @focus="passwordFocused = true"
                @blur="passwordFocused = false"
              />
              <button
                type="button"
                class="input-toggle"
                tabindex="-1"
                @click="showPassword = !showPassword"
              >
                <span v-if="!showPassword">👁️</span>
                <span v-else>🙈</span>
              </button>
            </div>
          </div>
        </div>

        <hr class="section-divider" />

        <!-- Section 2: Setup MFA (Optional) -->
        <div class="onboarding-section mt-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="section-title"><span class="section-badge section-badge--optional">2</span> Setup MFA</h3>
            <span class="text-xs text-gray-500 uppercase tracking-wider font-bold">Optional</span>
          </div>
          
          <div v-if="loadingTotp" class="py-4 text-center text-sm text-gray-400">
            Generating Secure Key...
          </div>
          <div v-else-if="totpData" class="mt-2">
            <div class="totp-setup-box">
              <div class="qr-wrapper">
                <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(totpData.qr_code_url)}`" alt="QR Code" class="w-full h-full" />
              </div>
              <div class="totp-instructions">
                <p class="text-sm text-gray-300 mb-2">Scan with Google Authenticator, or use this setup key:</p>
                <code class="manual-key" @click="copyToClipboard(totpData.secret)" title="Click to copy">
                  {{ totpData.secret }}
                  <Transition name="fade">
                    <span v-if="copySuccess" class="copy-success">✓ Copied</span>
                  </Transition>
                </code>
              </div>
            </div>

            <div class="input-group mt-6">
              <label class="input-label" for="totp">6-Digit Code (Leave blank to skip)</label>
              <div class="input-wrapper" :class="{ 'input-wrapper--focus': totpFocused }">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <input
                  id="totp"
                  v-model="totpCode"
                  type="text"
                  inputmode="numeric"
                  maxlength="6"
                  placeholder="000000"
                  @focus="totpFocused = true"
                  @blur="totpFocused = false"
                  class="tracking-widest font-mono text-center"
                  style="text-align: center; letter-spacing: 0.5em;"
                />
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="login-btn mt-6" :disabled="loading">
          <span v-if="!loading" class="login-btn__text">Save &amp; Enter Dashboard</span>
          <span v-else class="login-btn__spinner">
            <span class="spinner" />
            <span>Processing...</span>
          </span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import api from '../utils/api'

const auth = useAuthStore()
const router = useRouter()

const password = ref('')
const showPassword = ref(false)
const passwordFocused = ref(false)

const totpData = ref<{ secret: string, qr_code_url: string } | null>(null)
const totpCode = ref('')
const totpFocused = ref(false)
const loadingTotp = ref(true)

const error = ref('')
const loading = ref(false)
const shakeCard = ref(false)
const copySuccess = ref(false)

onMounted(async () => {
  try {
    const res = await api.get('/auth/generate-totp-setup')
    totpData.value = res.data
  } catch (err) {
    console.warn('Failed to load TOTP setup', err)
  } finally {
    loadingTotp.value = false
  }
})

function copyToClipboard(text: string) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      copySuccess.value = true
      setTimeout(() => { copySuccess.value = false }, 2000)
    })
  }
}

function triggerShake() {
  shakeCard.value = true
  setTimeout(() => (shakeCard.value = false), 600)
}

async function handleSubmit() {
  error.value = ''
  if (password.value.length < 6) {
    error.value = 'Password must be at least 6 characters'
    triggerShake()
    return
  }

  loading.value = true
  try {
    const payload: any = { new_password: password.value }
    if (totpCode.value && totpData.value) {
      payload.totp_code = totpCode.value
      payload.totp_secret = totpData.value.secret
    }

    const res = await api.post('/auth/complete-onboarding', payload)
    
    // Update local user state
    if (auth.user) {
      auth.user.must_change_password = false
      localStorage.setItem('netsight_user', JSON.stringify(auth.user))
    }
    
    router.push('/dashboard')
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Failed to complete onboarding'
    triggerShake()
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* Inherit base styles from login page but adjust width */
.login-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  padding: 24px;
}
.login-bg { position: fixed; inset: 0; z-index: 0; overflow: hidden; }
.login-bg__orb { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.04; animation: orbFloat 30s ease-in-out infinite; }
.login-bg__orb--1 { width: 500px; height: 500px; background: var(--accent-cyan); top: -10%; right: -5%; animation-duration: 18s; }
.login-bg__orb--2 { width: 400px; height: 400px; background: var(--accent-blue); bottom: -10%; left: -5%; animation-duration: 22s; animation-delay: -5s; }
.login-bg__orb--3 { width: 300px; height: 300px; background: var(--accent-green); top: 50%; left: 50%; transform: translate(-50%, -50%); animation-duration: 40s; animation-delay: -10s; opacity: 0.02; }
@keyframes orbFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 25% { transform: translate(30px, -40px) scale(1.05); } 50% { transform: translate(-20px, 20px) scale(0.95); } 75% { transform: translate(10px, 30px) scale(1.02); } }
.login-bg__grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(6, 182, 212, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(6, 182, 212, 0.03) 1px, transparent 1px); background-size: 60px 60px; }

.onboarding-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 500px; /* slightly wider than login */
  padding: 40px 36px;
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.login-card--shake { animation: shake 0.5s ease-in-out; }

.login-header { text-align: center; margin-bottom: 24px; }
.login-error { display: flex; align-items: center; gap: 8px; padding: 10px 14px; background: var(--accent-red-dim); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-sm); margin-bottom: 20px; animation: slideUp 0.3s ease-out both; }
.login-error__icon { font-size: 0.9rem; flex-shrink: 0; }
.login-error__text { font-size: 0.78rem; color: #fca5a5; font-weight: 500; }
.error-enter-active, .error-leave-active { transition: all 0.3s ease; }
.error-enter-from, .error-leave-to { opacity: 0; transform: translateY(-8px); }

.section-title { font-size: 0.9rem; font-weight: 600; color: #fff; display: flex; align-items: center; gap: 8px; }
.section-badge { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--accent-blue); color: #fff; font-size: 0.75rem; font-weight: bold; }
.section-badge--optional { background: #4b5563; }
.section-divider { border-color: rgba(255,255,255,0.05); margin: 24px 0; }

.input-group { display: flex; flex-direction: column; gap: 6px; }
.input-label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; }
.input-wrapper { display: flex; align-items: center; gap: 10px; padding: 0 14px; height: 48px; background: rgba(0, 0, 0, 0.3); border: 1px solid var(--glass-border); border-radius: var(--radius-sm); transition: all var(--transition-base); }
.input-wrapper--focus { border-color: var(--accent-cyan); box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1), var(--shadow-glow-cyan); }
.input-icon { color: var(--text-muted); flex-shrink: 0; transition: color var(--transition-fast); }
.input-wrapper--focus .input-icon { color: var(--accent-cyan); }
.input-wrapper input { flex: 1; background: none; border: none; color: var(--text-primary); font-size: 0.9rem; height: 100%; outline: none; }
.input-wrapper input::placeholder { color: var(--text-muted); }
.input-toggle { display: flex; align-items: center; color: var(--text-muted); padding: 4px; transition: color var(--transition-fast); }

.totp-setup-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  border-radius: var(--radius-md);
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid var(--glass-border);
}
@media (min-width: 480px) {
  .totp-setup-box {
    flex-direction: row;
    align-items: center;
    text-align: left;
  }
}
.qr-wrapper {
  flex-shrink: 0;
  background: #fff;
  padding: 0.5rem;
  border-radius: 8px;
  width: 100px;
  height: 100px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.totp-instructions {
  flex: 1;
  text-align: center;
}
@media (min-width: 480px) {
  .totp-instructions {
    text-align: left;
  }
}

.manual-key { display: inline-block; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(56, 189, 248, 0.3); padding: 0.4rem 0.8rem; border-radius: 6px; color: #38bdf8; font-family: monospace; font-size: 1rem; letter-spacing: 1px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; position: relative; }
.manual-key:hover { background: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.6); }
.copy-success { color: #10b981; font-size: 0.75rem; margin-left: 8px; letter-spacing: normal; position: absolute; right: -70px; top: 8px; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.login-btn { width: 100%; height: 48px; border-radius: var(--radius-sm); background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue)); color: #fff; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; transition: all var(--transition-base); position: relative; overflow: hidden; box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 4px 16px rgba(6, 182, 212, 0.15); border: 1px solid rgba(255,255,255,0.1); cursor: pointer; }
.login-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 8px 24px rgba(6, 182, 212, 0.25); }
.login-btn:active:not(:disabled) { transform: translateY(0) scale(0.99); }
.login-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.login-btn__text, .login-btn__spinner { position: relative; z-index: 1; }
.login-btn__spinner { display: flex; align-items: center; justify-content: center; gap: 8px; }
.spinner { width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, 0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }

@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-8px); } 75% { transform: translateX(8px); } }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
