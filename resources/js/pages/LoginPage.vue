<template>
  <div class="login-page">
    <!-- Animated gradient mesh background -->
    <div class="login-bg">
      <div class="login-bg__orb login-bg__orb--1" />
      <div class="login-bg__orb login-bg__orb--2" />
      <div class="login-bg__orb login-bg__orb--3" />
      <div class="login-bg__grid" />
    </div>

    <!-- Login card -->
    <div class="login-card glass-card glass-card--elevated" :class="{ 'login-card--shake': shakeCard }">
      <!-- Header -->
      <div class="login-header">
        <div class="login-logo">
          <svg viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" class="login-logo__svg">
            <defs>
              <linearGradient id="loginBlueGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#06b6d4" />
                <stop offset="100%" stop-color="#3b82f6" />
              </linearGradient>
            </defs>
            <!-- Netsight Text -->
            <text x="200" y="75" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="800" font-size="76" fill="var(--text-primary, #ffffff)" text-anchor="middle" letter-spacing="-1.5">
              Nets<tspan fill="url(#loginBlueGrad)">i</tspan>ght
            </text>
            <!-- Tagline -->
            <g transform="translate(200, 104)">
              <line x1="-190" y1="-4" x2="-125" y2="-4" stroke="url(#loginBlueGrad)" stroke-width="2.5" />
              <text x="0" y="0" font-family="'Montserrat', 'Inter', system-ui, sans-serif" font-weight="700" font-size="12" fill="var(--text-secondary, #9ca3af)" text-anchor="middle" letter-spacing="2.5">
                INSPECT TRAFFIC. <tspan fill="url(#loginBlueGrad)">SOLVE FASTER.</tspan>
              </text>
              <line x1="125" y1="-4" x2="190" y2="-4" stroke="url(#loginBlueGrad)" stroke-width="2.5" />
            </g>
          </svg>
        </div>
      </div>

      <!-- Step indicator -->
      <div class="login-steps">
        <div class="login-steps__track">
          <div class="login-steps__fill" :style="{ width: auth.totpRequired ? '100%' : '50%' }" />
        </div>
        <div class="login-steps__labels">
          <span class="login-steps__label" :class="{ 'login-steps__label--active': !auth.totpRequired }">
            Credentials
          </span>
          <span class="login-steps__label" :class="{ 'login-steps__label--active': auth.totpRequired }">
            Verify TOTP
          </span>
        </div>
      </div>

      <!-- Error message -->
      <Transition name="error">
        <div v-if="auth.error" class="login-error">
          <span class="login-error__icon">⚠</span>
          <span class="login-error__text">{{ auth.error }}</span>
        </div>
      </Transition>

      <!-- Step 1: Credentials -->
      <Transition name="step" mode="out-in">
        <form
          v-if="!auth.totpRequired"
          key="credentials"
          class="login-form"
          @submit.prevent="handleLogin"
        >
          <div class="input-group">
            <label class="input-label" for="email">Email</label>
            <div class="input-wrapper" :class="{ 'input-wrapper--focus': emailFocused }">
              <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="2" y="4" width="20" height="16" rx="3" stroke="currentColor" stroke-width="1.5"/>
                <path d="M2 7l10 6 10-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <input
                id="email"
                v-model="email"
                type="email"
                placeholder="operator@netsight.io"
                autocomplete="email"
                required
                @focus="emailFocused = true"
                @blur="emailFocused = false"
              />
            </div>
          </div>

          <div class="input-group">
            <label class="input-label" for="password">Password</label>
            <div class="input-wrapper" :class="{ 'input-wrapper--focus': passwordFocused }">
              <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.5"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="1" fill="currentColor"/>
              </svg>
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••••"
                autocomplete="current-password"
                required
                @focus="passwordFocused = true"
                @blur="passwordFocused = false"
              />
              <button
                type="button"
                class="input-toggle"
                tabindex="-1"
                @click="showPassword = !showPassword"
                :aria-label="showPassword ? 'Hide password' : 'Show password'"
              >
                <svg v-if="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z" stroke="currentColor" stroke-width="1.5"/>
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
          </div>

          <button type="submit" class="login-btn" :disabled="auth.loading">
            <span v-if="!auth.loading" class="login-btn__text">Authenticate</span>
            <span v-else class="login-btn__spinner">
              <span class="spinner" />
              <span>Verifying...</span>
            </span>
          </button>
        </form>

        <!-- Step 2: TOTP -->
        <form
          v-else
          key="totp"
          class="login-form login-form--totp"
          @submit.prevent="handleTotp"
        >
          <div class="totp-header">
            <div class="totp-icon">🔐</div>
            <p class="totp-description">
              Enter the 6-digit code from your authenticator app
            </p>
          </div>

          <div class="totp-inputs" @paste="handlePaste">
            <input
              v-for="(_, i) in 6"
              :key="i"
              :ref="(el) => setTotpRef(el as HTMLInputElement | null, i)"
              type="text"
              inputmode="numeric"
              maxlength="1"
              class="totp-digit"
              :class="{
                'totp-digit--filled': totpDigits[i] !== '',
                'totp-digit--active': totpActiveIndex === i,
              }"
              :value="totpDigits[i]"
              @input="handleTotpInput($event, i)"
              @keydown="handleTotpKeydown($event, i)"
              @focus="totpActiveIndex = i"
              @blur="totpActiveIndex = -1"
              autocomplete="one-time-code"
            />
          </div>

          <button type="submit" class="login-btn" :disabled="auth.loading || totpCode.length !== 6">
            <span v-if="!auth.loading" class="login-btn__text">Verify &amp; Login</span>
            <span v-else class="login-btn__spinner">
              <span class="spinner" />
              <span>Verifying...</span>
            </span>
          </button>

          <button type="button" class="login-back" @click="handleBack">
            ← Back to credentials
          </button>
        </form>
      </Transition>

      <!-- Demo Section -->
      <div v-if="!auth.totpRequired && !auth.demoSetupData && (window.APP_CONFIG?.env === 'local' || window.APP_CONFIG?.showDemoButton)" class="demo-section">
        <div class="demo-divider"><span>OR</span></div>
        <button type="button" class="demo-btn" @click="handleStartDemo" :disabled="auth.demoStarting">
          <span v-if="!auth.demoStarting">🚀 Try Demo Sandbox</span>
          <span v-else class="login-btn__spinner">
            <span class="spinner" />
            <span>Creating Sandbox...</span>
          </span>
        </button>
      </div>

      <!-- Demo Setup Info -->
      <div v-if="auth.demoSetupData" class="demo-setup-box">
        <h3 class="demo-setup-title">🎉 Sandbox Created!</h3>
        <p class="demo-setup-desc">Scan this QR code with Google Authenticator or Authy:</p>
        <div class="demo-qr-container">
          <img :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(auth.demoSetupData.totp_qr_url)}`" alt="TOTP QR Code" class="demo-qr" />
        </div>
        <div class="demo-totp-manual">
          <p class="text-sm text-muted mt-2 mb-1">Mobile Users? Copy manual setup key:</p>
          <code class="manual-key" @click="copyToClipboard(auth.demoSetupData.totp_secret)" title="Click to copy">
            {{ auth.demoSetupData.totp_secret }}
            <span v-if="copySuccess" class="copy-success">✓ Copied</span>
            <span v-else class="copy-hint">📋</span>
          </code>
        </div>
        <div class="demo-credentials">
          <p><strong>Email:</strong> {{ auth.demoSetupData.email }}</p>
          <p><strong>Password:</strong> {{ auth.demoSetupData.password }}</p>
        </div>
        <button type="button" class="login-btn mt-4" @click="fillDemoCredentials">
          Login to Sandbox
        </button>
      </div>

    </div>

    <!-- Footer -->
    <p class="login-footer">Netsight By Vandiza Tech &middot; Secure NOC Operations</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()
const router = useRouter()

// ── Step 1 State ─────────────────────────────────────────────────
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const emailFocused = ref(false)
const passwordFocused = ref(false)
const shakeCard = ref(false)
const copySuccess = ref(false)

function copyToClipboard(text: string) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      copySuccess.value = true
      setTimeout(() => { copySuccess.value = false }, 2000)
    }).catch(() => {
      alert('Failed to copy: ' + text)
    })
  } else {
    // Fallback if clipboard API not available
    alert('Setup Key: ' + text)
  }
}

async function handleStartDemo() {
  try {
    await auth.startDemo()
  } catch (err) {
    triggerShake()
  }
}

function fillDemoCredentials() {
  if (auth.demoSetupData) {
    email.value = auth.demoSetupData.email
    password.value = auth.demoSetupData.password
    // Clear demo setup data to show login form again, or just trigger login
    auth.demoSetupData = null
    handleLogin()
  }
}

// ── Step 2 State ─────────────────────────────────────────────────
const totpDigits = ref<string[]>(['', '', '', '', '', ''])
const totpActiveIndex = ref(-1)
const totpRefs: (HTMLInputElement | null)[] = []

const totpCode = computed(() => totpDigits.value.join(''))

function setTotpRef(el: HTMLInputElement | null, index: number) {
  totpRefs[index] = el
}

// ── Handlers ─────────────────────────────────────────────────────
async function handleLogin() {
  auth.clearError()
  try {
    await auth.login(email.value, password.value)
    // If totp required, focus first digit after transition
    if (auth.totpRequired) {
      nextTick(() => {
        setTimeout(() => totpRefs[0]?.focus(), 350)
      })
    }
  } catch {
    triggerShake()
  }
}

async function handleTotp() {
  auth.clearError()
  try {
    await auth.verifyTotp(totpCode.value)
    router.push('/dashboard')
  } catch {
    totpDigits.value = ['', '', '', '', '', '']
    nextTick(() => totpRefs[0]?.focus())
    triggerShake()
  }
}

function handleTotpInput(event: Event, index: number) {
  const target = event.target as HTMLInputElement
  const value = target.value.replace(/\D/g, '')

  totpDigits.value[index] = value.charAt(0) || ''

  if (value && index < 5) {
    nextTick(() => totpRefs[index + 1]?.focus())
  }
}

function handleTotpKeydown(event: KeyboardEvent, index: number) {
  if (event.key === 'Backspace') {
    if (!totpDigits.value[index] && index > 0) {
      totpDigits.value[index - 1] = ''
      nextTick(() => totpRefs[index - 1]?.focus())
    }
  }
  if (event.key === 'ArrowLeft' && index > 0) {
    totpRefs[index - 1]?.focus()
  }
  if (event.key === 'ArrowRight' && index < 5) {
    totpRefs[index + 1]?.focus()
  }
}

function handlePaste(event: ClipboardEvent) {
  event.preventDefault()
  const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6)
  for (let i = 0; i < 6; i++) {
    totpDigits.value[i] = pasted[i] || ''
  }
  const focusIndex = Math.min(pasted.length, 5)
  nextTick(() => totpRefs[focusIndex]?.focus())
}

function handleBack() {
  auth.totpRequired = false
  auth.clearError()
  totpDigits.value = ['', '', '', '', '', '']
}

function triggerShake() {
  shakeCard.value = true
  setTimeout(() => (shakeCard.value = false), 600)
}
</script>

<style scoped>
/* ── Page Layout ───────────────────────────────────────────────── */
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

/* ── Animated Background ───────────────────────────────────────── */
.login-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}

.login-bg__orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(120px);
  opacity: 0.04;
  animation: orbFloat 30s ease-in-out infinite;
}

.login-bg__orb--1 {
  width: 500px;
  height: 500px;
  background: var(--accent-cyan);
  top: -10%;
  right: -5%;
  animation-duration: 18s;
}

.login-bg__orb--2 {
  width: 400px;
  height: 400px;
  background: var(--accent-blue);
  bottom: -10%;
  left: -5%;
  animation-duration: 22s;
  animation-delay: -5s;
}

.login-bg__orb--3 {
  width: 300px;
  height: 300px;
  background: var(--accent-green);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation-duration: 40s;
  animation-delay: -10s;
  opacity: 0.02;
}

@keyframes orbFloat {
  0%, 100% { transform: translate(0, 0) scale(1); }
  25%      { transform: translate(30px, -40px) scale(1.05); }
  50%      { transform: translate(-20px, 20px) scale(0.95); }
  75%      { transform: translate(10px, 30px) scale(1.02); }
}

.login-bg__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(6, 182, 212, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(6, 182, 212, 0.03) 1px, transparent 1px);
  background-size: 60px 60px;
}

/* ── Card ──────────────────────────────────────────────────────── */
.login-card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 420px;
  padding: 40px 36px;
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.login-card--shake {
  animation: shake 0.5s ease-in-out;
}

/* ── Header ────────────────────────────────────────────────────── */
.login-header {
  text-align: center;
  margin-bottom: 32px;
}

.login-logo {
  display: flex;
  justify-content: center;
  margin-bottom: 8px;
  padding: 0 16px;
}

.login-logo__svg {
  width: 100%;
  max-width: 300px;
  height: auto;
  display: block;
  filter: drop-shadow(0 8px 24px rgba(6, 182, 212, 0.25));
  animation: logoFloat 6s ease-in-out infinite;
}

@keyframes logoFloat {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}

.login-subtitle {
  font-size: 0.72rem;
  color: var(--text-muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  font-weight: 500;
}

/* ── Steps Indicator ───────────────────────────────────────────── */
.login-steps {
  margin-bottom: 24px;
}

.login-steps__track {
  height: 2px;
  background: var(--bg-tertiary);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 8px;
}

.login-steps__fill {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-cyan), var(--accent-blue));
  border-radius: 2px;
  transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.login-steps__labels {
  display: flex;
  justify-content: space-between;
}

.login-steps__label {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  transition: color var(--transition-base);
}

.login-steps__label--active {
  color: var(--accent-cyan);
}

/* ── Error ─────────────────────────────────────────────────────── */
.login-error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  background: var(--accent-red-dim);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: var(--radius-sm);
  margin-bottom: 20px;
  animation: slideUp 0.3s ease-out both;
}

.login-error__icon {
  font-size: 0.9rem;
  flex-shrink: 0;
}

.login-error__text {
  font-size: 0.78rem;
  color: #fca5a5;
  font-weight: 500;
}

.error-enter-active,
.error-leave-active {
  transition: all 0.3s ease;
}
.error-enter-from,
.error-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}

/* ── Form ──────────────────────────────────────────────────────── */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.input-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.input-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 14px;
  height: 48px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  transition: all var(--transition-base);
}

.input-wrapper--focus {
  border-color: var(--accent-cyan);
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1), var(--shadow-glow-cyan);
}

.input-icon {
  color: var(--text-muted);
  flex-shrink: 0;
  transition: color var(--transition-fast);
}

.input-wrapper--focus .input-icon {
  color: var(--accent-cyan);
}

.input-wrapper input {
  flex: 1;
  background: none;
  border: none;
  color: var(--text-primary);
  font-size: 0.9rem;
  height: 100%;
}

.input-wrapper input::placeholder {
  color: var(--text-muted);
}

.input-toggle {
  display: flex;
  align-items: center;
  color: var(--text-muted);
  padding: 4px;
  transition: color var(--transition-fast);
}
.input-toggle:hover {
  color: var(--text-secondary);
}

/* ── Submit Button ─────────────────────────────────────────────── */
.demo-qr {
  width: 100%;
  height: 100%;
  border-radius: 8px;
}

.demo-totp-manual {
  text-align: center;
  margin-top: 1rem;
}

.manual-key {
  display: inline-block;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(56, 189, 248, 0.3);
  padding: 0.5rem 1rem;
  border-radius: 8px;
  color: #38bdf8;
  font-family: monospace;
  font-size: 1.1rem;
  letter-spacing: 2px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.manual-key:hover {
  background: rgba(56, 189, 248, 0.1);
  border-color: rgba(56, 189, 248, 0.6);
}

.copy-success {
  color: #10b981;
  font-size: 0.8rem;
  margin-left: 8px;
  letter-spacing: normal;
}

.copy-hint {
  font-size: 0.9rem;
  margin-left: 8px;
  opacity: 0.7;
}

.login-btn {
  width: 100%;
  height: 48px;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));
  color: #fff;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  transition: all var(--transition-base);
  position: relative;
  overflow: hidden;
  margin-top: 4px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 4px 16px rgba(6, 182, 212, 0.15);
  border: 1px solid rgba(255,255,255,0.1);
}

.login-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.1), transparent);
  opacity: 0;
  transition: opacity var(--transition-base);
}

.login-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 8px 24px rgba(6, 182, 212, 0.25);
}
.login-btn:hover:not(:disabled)::before {
  opacity: 1;
}

.login-btn:active:not(:disabled) {
  transform: translateY(0) scale(0.99);
}

.login-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.login-btn__text {
  position: relative;
  z-index: 1;
}

.login-btn__spinner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
  z-index: 1;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

/* ── TOTP ──────────────────────────────────────────────────────── */
.totp-header {
  text-align: center;
}

.totp-icon {
  font-size: 2rem;
  margin-bottom: 8px;
}

.totp-description {
  font-size: 0.8rem;
  color: var(--text-secondary);
  line-height: 1.5;
}

.totp-inputs {
  display: flex;
  justify-content: center;
  gap: 8px;
}

.totp-digit {
  width: 48px;
  height: 56px;
  text-align: center;
  font-size: 1.4rem;
  font-weight: 700;
  font-family: var(--font-mono);
  color: var(--text-primary);
  background: rgba(0, 0, 0, 0.3);
  border: 1.5px solid var(--glass-border);
  border-radius: var(--radius-sm);
  transition: all var(--transition-base);
  caret-color: var(--accent-cyan);
}

.totp-digit:focus {
  outline: none;
  border-color: var(--accent-cyan);
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1), 0 0 12px rgba(6, 182, 212, 0.2);
}

.totp-inputs:hover .totp-digit:not(.totp-digit--filled):not(.totp-digit--active) {
  border-color: rgba(255, 255, 255, 0.2);
}

/* --- Demo Styles --- */
.demo-section {
  margin-top: 24px;
  text-align: center;
}
.demo-divider {
  display: flex;
  align-items: center;
  color: var(--text-muted);
  font-size: 0.75rem;
  margin-bottom: 16px;
}
.demo-divider::before, .demo-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--glass-border);
}
.demo-divider span {
  padding: 0 12px;
}
.demo-btn {
  background: rgba(6, 182, 212, 0.1);
  color: var(--accent-cyan);
  border: 1px solid rgba(6, 182, 212, 0.3);
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s;
}
.demo-btn:hover {
  background: rgba(6, 182, 212, 0.2);
  box-shadow: 0 0 12px rgba(6, 182, 212, 0.2);
}
.demo-setup-box {
  margin-top: 24px;
  padding: 16px;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 12px;
  border: 1px solid rgba(16, 185, 129, 0.3);
  text-align: center;
}
.demo-setup-title {
  color: var(--accent-green, #10b981);
  margin-bottom: 8px;
  font-size: 1.1rem;
}
.demo-setup-desc {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin-bottom: 16px;
}
.demo-qr-container {
  background: white;
  padding: 12px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 16px;
}
.demo-qr {
  width: 150px;
  height: 150px;
  display: block;
}
.demo-credentials p {
  font-size: 0.85rem;
  color: var(--text-primary);
  margin-bottom: 4px;
}
.mt-4 {
  margin-top: 16px;
}

.totp-digit--filled {
  border-color: rgba(6, 182, 212, 0.3);
  background: rgba(6, 182, 212, 0.05);
}

.totp-digit--active {
  border-color: var(--accent-cyan);
}

.login-back {
  display: inline-flex;
  align-self: center;
  color: var(--text-muted);
  font-size: 0.78rem;
  font-weight: 500;
  transition: color var(--transition-fast);
  padding: 4px 8px;
}
.login-back:hover {
  color: var(--text-secondary);
}

/* ── Step Transitions ──────────────────────────────────────────── */
.step-enter-active,
.step-leave-active {
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.step-enter-from {
  opacity: 0;
  transform: translateX(30px);
}

.step-leave-to {
  opacity: 0;
  transform: translateX(-30px);
}

/* ── Footer ────────────────────────────────────────────────────── */
.login-footer {
  position: relative;
  z-index: 1;
  margin-top: 32px;
  font-size: 0.65rem;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.6;
}

/* ── Responsive ────────────────────────────────────────────────── */
@media (max-width: 480px) {
  .login-card {
    padding: 28px 24px;
  }

  .login-logo {
    font-size: 1.8rem;
  }

  .totp-digit {
    width: 42px;
    height: 48px;
    font-size: 1.2rem;
  }

  .totp-inputs {
    gap: 6px;
  }
}
</style>
