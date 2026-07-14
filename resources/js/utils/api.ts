import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'

const api = axios.create({
  baseURL: '/api',
  timeout: 15_000,
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
    if (demoSchema && config.headers) {
      config.headers['X-Demo-Schema'] = demoSchema
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
      // Token expired or invalid — purge and redirect
      localStorage.removeItem('netsight_token')
      localStorage.removeItem('netsight_user')

      if (window.location.pathname !== '/login') {
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
