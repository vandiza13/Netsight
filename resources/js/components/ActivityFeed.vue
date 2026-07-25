<template>
  <div class="activity-feed-container">
    <div class="feed-header">
      <h3 class="feed-title">
        <span class="icon">📜</span> System Activity
      </h3>
      <div v-if="loading" class="spinner"></div>
    </div>
    
    <div v-if="error" class="feed-error">{{ error }}</div>
    
    <div class="feed-list" v-else>
      <div class="feed-item fade-in" v-for="log in logs" :key="log.id">
        <div class="feed-icon" :class="getIconClass(log.action)">
          {{ getActionIcon(log.action) }}
        </div>
        <div class="feed-content">
          <div class="feed-desc">
            <span class="feed-user">{{ log.user?.name || 'System' }}</span> 
            {{ getActionText(log.action) }} 
            <span class="feed-target" v-if="log.target_type">
              {{ log.target_type }} <span v-if="log.target_id">#{{ log.target_id }}</span>
            </span>
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
  user_id: number | null
  action: string
  target_type: string | null
  target_id: string | null
  ip_address: string | null
  created_at: string
  user: { name: string, role: string } | null
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
  if (a.includes('login')) return '🔑'
  if (a.includes('sync')) return '🔄'
  if (a.includes('create') || a.includes('add')) return '✨'
  if (a.includes('update') || a.includes('edit')) return '✏️'
  if (a.includes('delete') || a.includes('remove')) return '🗑️'
  if (a.includes('torch')) return '🔦'
  return '⚡'
}

function getIconClass(action: string) {
  const a = action.toLowerCase()
  if (a.includes('delete') || a.includes('remove')) return 'is-danger'
  if (a.includes('sync') || a.includes('update')) return 'is-warning'
  if (a.includes('create') || a.includes('login')) return 'is-success'
  return 'is-info'
}

function getActionText(action: string) {
  const a = action.toLowerCase()
  if (a.includes('auth/complete-onboarding') || a.includes('onboarding completed')) return 'menyelesaikan pengaturan awal sistem'
  if (a.includes('auth login success') || a.includes('login')) return 'berhasil masuk ke sistem'
  if (a.includes('logout')) return 'keluar dari sistem'
  if (a.includes('torch') && a.includes('cancel')) return 'menghentikan inspeksi Torch'
  if (a.includes('torch') && (a.includes('heartbeat') || a.includes('monitor'))) return 'memantau aliran Torch'
  if (a.includes('torch')) return 'memulai inspeksi Torch'
  if (a.includes('sync')) return 'melakukan sinkronisasi router'
  if (a.includes('create') || a.includes('add')) return 'menambahkan data baru'
  if (a.includes('update') || a.includes('edit')) return 'memperbarui data'
  if (a.includes('delete') || a.includes('remove')) return 'menghapus data'
  
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

.feed-icon.is-success { background: rgba(34, 197, 94, 0.15); }
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
