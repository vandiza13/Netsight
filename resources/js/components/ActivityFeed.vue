<template>
  <div class="activity-feed-container">
    <div class="feed-header">
      <h3 class="feed-title">
        <svg class="icon title-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;margin-right:6px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        System Activity
      </h3>
      <div v-if="loading" class="spinner"></div>
    </div>
    
    <div v-if="error" class="feed-error">{{ error }}</div>
    
    <div class="feed-list" v-else>
      <div class="feed-item fade-in" v-for="log in logs" :key="log.id">
        <div class="feed-icon" :class="getIconClass(log.action)" v-html="getActionIcon(log.action)">
        </div>
        <div class="feed-content">
          <div class="feed-desc">
            <span class="feed-user font-semibold text-primary">{{ log.staff?.name || 'Sistem' }}</span> 
            {{ getActionText(log) }} 
          </div>
          <div class="feed-time">{{ formatTimeAgo(log.created_at) }}</div>
        </div>
      </div>
      
      <div class="feed-empty" v-if="!loading && logs.length === 0">
        No recent activity found.
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../utils/api'

interface AuditLog {
  id: number
  staff_noc_id: number | null
  action: string
  target_type: string | null
  target_id: string | null
  ip_address: string | null
  created_at: string
  staff: { name: string, role: string } | null
  router: { name: string } | null
}

const logs = ref<AuditLog[]>([])
const loading = ref(true)
const error = ref('')

async function fetchLogs() {
  loading.value = true
  try {
    const { data } = await api.get('/audit-logs', { params: { page: 1 } })
    // Take top 6
    logs.value = data.data.slice(0, 6)
  } catch (err: any) {
    error.value = 'Failed to load activity.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})

function getActionIcon(action: string) {
  const a = action.toLowerCase()
  if (a.includes('login')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>'
  if (a.includes('sync')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>'
  if (a.includes('create') || a.includes('add')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
  if (a.includes('update') || a.includes('edit')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
  if (a.includes('delete') || a.includes('remove')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
  if (a.includes('torch')) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
}

function getIconClass(action: string) {
  const a = action.toLowerCase()
  if (a.includes('delete') || a.includes('remove')) return 'is-danger'
  if (a.includes('sync') || a.includes('update')) return 'is-warning'
  if (a.includes('create') || a.includes('login')) return 'is-success'
  return 'is-info'
}

function getActionText(log: AuditLog) {
  const a = log.action.toLowerCase()
  const router = log.router?.name ? ` router ${log.router.name}` : ''
  const target = log.target_type ? ` ${log.target_type.toLowerCase()}` : ' data'
  const targetId = log.target_id ? ` #${log.target_id}` : ''
  
  const targetStr = `${target}${targetId}`

  if (a.includes('auth/complete-onboarding') || a.includes('onboarding completed')) return 'menyelesaikan pengaturan awal sistem'
  if (a.includes('auth login success') || a.includes('login')) return 'berhasil masuk ke sistem'
  if (a.includes('logout')) return 'keluar dari sistem'
  if (a.includes('torch') && a.includes('cancel')) return `menghentikan inspeksi jaringan (Torch)${router}`
  if (a.includes('torch') && (a.includes('heartbeat') || a.includes('monitor'))) return `memantau aliran jaringan (Torch)${router}`
  if (a.includes('torch')) return `memulai inspeksi jaringan (Torch)${router}`
  if (a.includes('sync')) return `melakukan sinkronisasi${router}`
  
  if (a.includes('create') || a.includes('add')) return `menambahkan${targetStr} baru`
  if (a.includes('update') || a.includes('edit')) return `memperbarui${targetStr}`
  if (a.includes('delete') || a.includes('remove')) return `menghapus${targetStr}`
  if (a.includes('assign')) return `menugaskan${targetStr}`
  if (a.includes('revoke')) return `mencabut akses${targetStr}`

  // Fallback cleanup
  return 'mengakses ' + a.replace(/post api\//g, '').replace(/get api\//g, '').replace(/\//g, ' ')
}

function formatTimeAgo(dateString: string) {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.round((now.getTime() - date.getTime()) / 1000)
  
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.round(hours / 24)
  return `${days}d ago`
}
</script>

<style scoped>
.activity-feed-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.feed-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.feed-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
}

.feed-title .icon {
  font-size: 1.2rem;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-top-color: var(--cyan, #22d3ee);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.feed-error {
  color: var(--accent-red);
  font-size: 0.9rem;
}

.feed-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.feed-item {
  display: flex;
  gap: 14px;
  align-items: flex-start;
}

.feed-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  background: rgba(255,255,255,0.05);
  flex-shrink: 0;
}

.feed-icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.feed-icon.is-success {
  background: rgba(34, 197, 94, 0.1);
  color: #22c55e;
}
.feed-icon.is-warning { background: rgba(245, 166, 35, 0.15); }
.feed-icon.is-danger { background: rgba(239, 68, 68, 0.15); }
.feed-icon.is-info { background: rgba(34, 211, 238, 0.15); }

.feed-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-top: 2px;
}

.feed-desc {
  font-size: 0.9rem;
  color: var(--text-secondary);
  line-height: 1.4;
}

.feed-user {
  font-weight: 600;
  color: var(--text-primary);
}

.feed-target {
  font-family: var(--mono, monospace);
  font-size: 0.8rem;
  background: rgba(255,255,255,0.1);
  padding: 2px 6px;
  border-radius: 4px;
  color: var(--cyan, #22d3ee);
}

.feed-time {
  font-size: 0.8rem;
  color: var(--text-dim, #5c6774);
}

.feed-empty {
  color: var(--text-dim);
  font-size: 0.9rem;
  padding: 20px 0;
  text-align: center;
}

.fade-in {
  animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
