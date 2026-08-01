import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 60_000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// ── Request Interceptor: Attach Bearer Token ──────────────────────
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('netsight_token')
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    const demoSchema = localStorage.getItem('netsight_demo_schema')
    const userRaw = localStorage.getItem('netsight_user')
    let isDemoUser = false
    if (userRaw) {
      try {
        const user = JSON.parse(userRaw)
        isDemoUser = user.email && user.email.startsWith('demo@')
      } catch (e) {}
    }

    let isLoginWithDemoEmail = false
    if (config.data) {
      try {
        const body = typeof config.data === 'string' ? JSON.parse(config.data) : config.data
        if (body?.email && typeof body.email === 'string' && body.email.startsWith('demo@')) {
          isLoginWithDemoEmail = true
        }
      } catch (e) {}
    }

    // Only attach demo schema if they are logged in as demo user or attempting demo login
    if (demoSchema && (isDemoUser || isLoginWithDemoEmail) && config.headers) {
      config.headers['X-Demo-Schema'] = demoSchema
    } else if (demoSchema && !isDemoUser && !isLoginWithDemoEmail) {
      // Clean up stale demo schema if they are not a demo user and not performing demo login
      localStorage.removeItem('netsight_demo_schema')
    }
    
    return config
  },
  (error: AxiosError) => Promise.reject(error)
)

// ── Response Interceptor: Handle 401 / 403 ────────────────────────
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    const status = error.response?.status

    if (status === 401) {
      // Token expired or invalid
      localStorage.removeItem('netsight_token')
      localStorage.removeItem('netsight_user')

      // Jika kita BUKAN berada di halaman login, baru bersihkan schema demo dan redirect
      if (window.location.pathname !== '/login') {
        localStorage.removeItem('netsight_demo_schema')
        window.location.href = '/login'
      }
    }

    if (status === 403) {
      console.error(
        '[NETSIGHT] Access forbidden — insufficient permissions for this resource.'
      )
    }

    return Promise.reject(error)
  }
)

export default api
