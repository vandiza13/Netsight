<template>
  <div class="torch-widget-container">
    <div class="widget-header">
      <h3 class="widget-title">
        <svg class="icon title-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;margin-right:6px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg> Recent Torch Inspections
      </h3>
      <div v-if="loading" class="spinner"></div>
    </div>

    <div v-if="error" class="widget-error">{{ error }}</div>

    <div class="widget-list" v-else-if="items.length > 0">
      <div class="widget-item" v-for="item in items" :key="item.id">
        <div class="item-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <div class="item-info">
          <div class="item-target">
            User <span class="username">{{ item.username }}</span>
          </div>
          <div class="item-meta">
            By <span class="staff">{{ item.initiator?.name || 'System' }}</span> • {{ formatTimeAgo(item.started_at) }}
          </div>
        </div>
        <div class="item-peak" v-if="item.peak_rx_bps || item.peak_tx_bps">
          ▲ {{ formatTraffic(item.peak_tx_bps) }} / ▼ {{ formatTraffic(item.peak_rx_bps) }}
        </div>
      </div>
    </div>

    <div class="empty-state" v-else>
      No recent diagnostic sessions recorded.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '../utils/api'

interface TorchSession {
  id: number
  username: string
  started_at: string
  peak_rx_bps: number | null
  peak_tx_bps: number | null
  initiator: { name: string } | null
}

const items = ref<TorchSession[]>([])
const loading = ref(true)
const error = ref('')

async function fetchRecent() {
  loading.value = true
  try {
    const { data } = await api.get('/torch/history', { params: { page: 1 } })
    items.value = (data.data || []).slice(0, 5)
  } catch (err: any) {
    error.value = 'Failed to load diagnostic history.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchRecent()
})

function formatTraffic(bps: number | null) {
  if (!bps) return '0 B'
  if (bps >= 1000000000) return (bps / 1000000000).toFixed(1) + ' Gbps'
  if (bps >= 1000000) return (bps / 1000000).toFixed(1) + ' Mbps'
  if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps'
  return bps + ' bps'
}

function formatTimeAgo(dateString: string) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.round((now.getTime() - date.getTime()) / 1000)
  
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.round(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.round(hours / 24)}d ago`
}
</script>

<style scoped>
.torch-widget-container {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.widget-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
}

.widget-title .icon {
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

.widget-error {
  color: var(--accent-red);
  font-size: 0.9rem;
}

.widget-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.widget-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.06));
  border-radius: 8px;
}

.item-icon {
  font-size: 1.2rem;
  opacity: 0.8;
}

.item-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.item-target {
  font-size: 0.88rem;
  color: var(--text-secondary);
}

.item-target .username {
  font-weight: 600;
  color: var(--cyan, #22d3ee);
}

.item-meta {
  font-size: 0.78rem;
  color: var(--text-dim, #5c6774);
}

.item-meta .staff {
  color: var(--text-secondary);
}

.item-peak {
  font-family: var(--font-mono, monospace);
  font-size: 0.78rem;
  color: var(--accent-amber, #f5a623);
  background: rgba(245, 166, 35, 0.1);
  padding: 3px 6px;
  border-radius: 4px;
}

.empty-state {
  color: var(--text-dim);
  font-size: 0.9rem;
  padding: 20px 0;
  text-align: center;
}
</style>
