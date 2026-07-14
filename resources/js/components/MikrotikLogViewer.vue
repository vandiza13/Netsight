<template>
  <div class="log-viewer glass-card">
    <div class="log-header">
      <div class="log-title">
        <span class="icon">📜</span> System Logs
      </div>
    </div>
    
    <div class="log-content">
      <div v-if="!logs || logs.length === 0" class="empty-state">
        Tidak ada log terbaru yang tersedia.
      </div>
      
      <div v-else class="log-list">
        <div v-for="(log, idx) in logs" :key="idx" class="log-item" :class="getLogClass(log.topics)">
          <div class="log-time">{{ formatTime(log.time) }}</div>
          <div class="log-topics">[{{ log.topics }}]</div>
          <div class="log-message">{{ log.message }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  logs: any[]
}>()

const getLogClass = (topics: string) => {
  if (!topics) return ''
  const t = topics.toLowerCase()
  if (t.includes('error') || t.includes('critical')) return 'log-error'
  if (t.includes('warning')) return 'log-warning'
  if (t.includes('pppoe') || t.includes('radius')) return 'log-pppoe'
  return 'log-info'
}

const formatTime = (timeStr: string) => {
  if (!timeStr) return '-'
  return timeStr
}
</script>

<style scoped>
.log-viewer {
  display: flex;
  flex-direction: column;
  height: 300px;
  background: var(--bg-card);
  border: 1px solid var(--glass-border);
  border-radius: 12px;
  overflow: hidden;
  margin-top: 20px;
}

.log-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: rgba(0, 0, 0, 0.2);
  border-bottom: 1px solid var(--glass-border);
}

.log-title {
  font-weight: 600;
  font-size: 0.9rem;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-refresh {
  background: transparent;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.btn-refresh:hover:not(:disabled) {
  color: var(--text-primary);
  background: rgba(255, 255, 255, 0.1);
}

.btn-refresh:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spin {
  display: inline-block;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  100% { transform: rotate(360deg); }
}

.log-content {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.8rem;
}

.loading-state, .empty-state {
  padding: 20px;
  text-align: center;
  color: var(--text-muted);
}

.log-list {
  display: flex;
  flex-direction: column;
}

.log-item {
  display: flex;
  padding: 6px 16px;
  gap: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.02);
}

.log-item:hover {
  background: rgba(255, 255, 255, 0.03);
}

.log-time {
  color: var(--text-muted);
  white-space: nowrap;
  min-width: 90px;
}

.log-topics {
  color: var(--accent-blue);
  white-space: nowrap;
}

.log-message {
  color: var(--text-secondary);
  word-break: break-word;
}

.log-error .log-message {
  color: var(--accent-red);
}
.log-error .log-topics {
  color: var(--accent-red);
}

.log-warning .log-message {
  color: var(--accent-amber);
}

.log-pppoe .log-topics {
  color: var(--accent-cyan);
}
</style>
