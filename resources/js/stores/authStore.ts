import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../utils/api'

export interface AuthUser {
  name: string
  email: string
  role: 'ADMIN' | 'TIER_2' | 'TIER_1'
  must_change_password?: boolean
  mfa_enabled?: boolean
}

export const useAuthStore = defineStore('auth', () => {
  // ── State ──────────────────────────────────────────────────────
  const token = ref<string | null>(localStorage.getItem('netsight_token'))
  const user = ref<AuthUser | null>(
    (() => {
      try {
        const raw = localStorage.getItem('netsight_user')
        return raw ? (JSON.parse(raw) as AuthUser) : null
      } catch {
        return null
      }
    })()
  )
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const totpRequired = ref(false)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Temporary email held between step 1 → step 2
  const _pendingEmail = ref<string | null>(null)
  const _pendingPassword = ref<string | null>(null)
  const _challengeToken = ref<string | null>(null)

  // ── Getters ────────────────────────────────────────────────────
  const isAdmin = computed(() => user.value?.role === 'ADMIN')
  const isTier2 = computed(() => user.value?.role === 'TIER_2' || isAdmin.value)
  const isTier1 = computed(() => user.value?.role === 'TIER_1')
  const canAccessTorch = computed(() => isTier2.value)

  const roleBadge = computed(() => {
    switch (user.value?.role) {
      case 'ADMIN':
        return { label: 'Admin', color: '#ef4444' }
      case 'TIER_2':
        return { label: 'Tier 2', color: '#f59e0b' }
      case 'TIER_1':
        return { label: 'Tier 1', color: '#06b6d4' }
      default:
        return { label: 'Unknown', color: '#64748b' }
    }
  })

  // ── Actions ────────────────────────────────────────────────────

  /** Step 1: Authenticate credentials, server responds with totp_required flag */
  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    error.value = null

    // Ensure we are querying the public schema for non-demo users
    if (email !== 'demo@netsight.id') {
      localStorage.removeItem('netsight_demo_schema')
    }

    try {
      const { data } = await api.post<{ totp_required: boolean; challenge_token?: string; token?: string; user?: AuthUser }>('/auth/login', {
        email,
        password,
      })

      if (data.totp_required && data.challenge_token) {
        totpRequired.value = true
        _pendingEmail.value = email
        _pendingPassword.value = password
        _challengeToken.value = data.challenge_token
      } else if (!data.totp_required && data.token && data.user) {
        // MFA Bypassed - Login directly
        token.value = data.token
        user.value = data.user
        localStorage.setItem('netsight_token', data.token)
        localStorage.setItem('netsight_user', JSON.stringify(data.user))
        
        totpRequired.value = false
        _challengeToken.value = null
      }
    } catch (err: any) {
      error.value =
        err.response?.data?.message || 'Authentication failed. Check your credentials.'
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Step 2: Verify TOTP code, receives JWT + user payload */
  async function verifyTotp(code: string): Promise<void> {
    loading.value = true
    error.value = null

    if (!_challengeToken.value) {
      error.value = 'Session expired. Please log in again.'
      return
    }

    try {
      const { data } = await api.post<{ token: string; user: AuthUser }>(
        '/auth/totp-verify',
        {
          totp_code: code,
        },
        {
          headers: {
            Authorization: `Bearer ${_challengeToken.value}`
          }
        }
      )

      // Persist
      token.value = data.token
      user.value = data.user
      localStorage.setItem('netsight_token', data.token)
      localStorage.setItem('netsight_user', JSON.stringify(data.user))

      // Clean up
      totpRequired.value = false
      _pendingEmail.value = null
      _pendingPassword.value = null
      _challengeToken.value = null
      
      // Ensure we purge any lingering demo state if logging in as real admin
      demoSetupData.value = null
      if (!data.user.email.startsWith('demo@')) {
        localStorage.removeItem('netsight_demo_schema')
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Invalid TOTP code. Try again.'
      throw err
    } finally {
      loading.value = false
    }
  }

  /** Fetch latest user data from /auth/me */
  async function fetchUser(): Promise<void> {
    if (!token.value) return
    try {
      const { data } = await api.get<{ user: AuthUser }>('/auth/me')
      user.value = data.user
      localStorage.setItem('netsight_user', JSON.stringify(data.user))
    } catch (err) {
      // If unauthorized, logout
      logout()
    }
  }

  /** Clear everything and kick to login */
  function logout(): void {
    token.value = null
    user.value = null
    totpRequired.value = false
    error.value = null
    _pendingEmail.value = null
    _pendingPassword.value = null
    _challengeToken.value = null
    demoSetupData.value = null
    localStorage.removeItem('netsight_token')
    localStorage.removeItem('netsight_user')
    localStorage.removeItem('netsight_demo_schema')
  }

  function clearError(): void {
    error.value = null
  }

  const demoStarting = ref(false)
  const demoSetupData = ref<any>(null)

  async function startDemo(): Promise<void> {
    demoStarting.value = true
    error.value = null
    try {
      const { data } = await api.post('/demo/start')
      localStorage.setItem('netsight_demo_schema', data.schema)
      demoSetupData.value = data.admin
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Gagal menyiapkan sandbox demo.'
      throw err
    } finally {
      demoStarting.value = false
    }
  }

  return {
    // state
    token,
    user,
    isAuthenticated,
    totpRequired,
    loading,
    error,
    demoStarting,
    demoSetupData,
    // getters
    isAdmin,
    isTier2,
    isTier1,
    canAccessTorch,
    roleBadge,
    // actions
    login,
    verifyTotp,
    logout,
    clearError,
    startDemo,
    fetchUser
  }
})
