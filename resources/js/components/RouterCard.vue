<template>
  <div class="router-card glass-card" :class="{'router-card--selected': isSelected}" @click="$emit('select')">
    <div class="router-card__header">
      <div class="router-card__title-group">
        <h4 class="router-card__title">{{ router.name }}</h4>
        <span class="router-card__host">{{ router.host }}:{{ router.api_port }}</span>
      </div>
      <div class="router-card__status" :class="`status--${router.status.toLowerCase()}`">
        <span class="status-dot"></span>
        {{ router.status }}
      </div>
    </div>
    <div class="router-card__footer">
      <div class="router-card__info">
        <span class="info-label">Version:</span>
        <span class="info-value">{{ router.routeros_version || 'Unknown' }}</span>
      </div>
      <div class="router-card__info">
        <span class="info-label">Last Sync:</span>
        <span class="info-value" :title="router.last_synced_at || 'Never'">
          {{ timeAgo(router.last_synced_at) }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { MikroTikRouter } from '../stores/routerStore'

const props = defineProps<{
  router: MikroTikRouter
  isSelected: boolean
}>()

defineEmits<{
  (e: 'select'): void
}>()

function timeAgo(dateString: string | null): string {
  if (!dateString) return 'Never'
  
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`
  return `${Math.floor(diffInSeconds / 86400)}d ago`
}
</script>

<style scoped>
.router-card {
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 1px solid var(--glass-border);
}

.router-card:hover {
  transform: translateY(-2px);
  border-color: rgba(255, 255, 255, 0.15);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.router-card--selected {
  border-color: var(--accent-cyan);
  background: rgba(6, 182, 212, 0.05);
  box-shadow: 0 0 0 1px var(--accent-cyan);
}

.router-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}

.router-card__title {
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 4px 0;
  color: var(--text-primary);
}

.router-card__host {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-family: var(--font-mono, monospace);
}

.router-card__status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 12px;
  letter-spacing: 0.05em;
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.status--healthy {
  background: rgba(16, 185, 129, 0.1);
  color: var(--accent-green);
}
.status--healthy .status-dot { background: var(--accent-green); }

.status--degraded {
  background: rgba(245, 158, 11, 0.1);
  color: var(--accent-amber);
}
.status--degraded .status-dot { background: var(--accent-amber); }

.status--unreachable {
  background: rgba(239, 68, 68, 0.1);
  color: var(--accent-red);
}
.status--unreachable .status-dot { background: var(--accent-red); }

.router-card__footer {
  display: flex;
  justify-content: space-between;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding-top: 12px;
}

.router-card__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  color: var(--text-muted);
  letter-spacing: 0.05em;
}

.info-value {
  font-size: 0.8rem;
  color: var(--text-secondary);
}
</style>
