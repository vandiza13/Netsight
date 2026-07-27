<template>
  <div class="empty-state">
    <div class="empty-state__illustration">
      <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Abstract network illustration -->
        <circle cx="60" cy="60" r="48" stroke="var(--border)" stroke-width="1.5" stroke-dasharray="4 4" />
        <circle cx="60" cy="60" r="28" stroke="var(--border-hover)" stroke-width="1" />
        
        <!-- Nodes -->
        <circle cx="60" cy="32" r="6" fill="var(--accent-dim)" stroke="var(--accent)" stroke-width="1.5" />
        <circle cx="36" cy="72" r="6" fill="var(--surface-2)" stroke="var(--border-hover)" stroke-width="1.5" />
        <circle cx="84" cy="72" r="6" fill="var(--surface-2)" stroke="var(--border-hover)" stroke-width="1.5" />
        <circle cx="60" cy="60" r="8" fill="var(--accent-dim)" stroke="var(--accent)" stroke-width="1.5" />
        
        <!-- Links -->
        <line x1="60" y1="38" x2="60" y2="52" stroke="var(--accent)" stroke-width="1" opacity="0.5" />
        <line x1="54" y1="64" x2="42" y2="68" stroke="var(--border-hover)" stroke-width="1" />
        <line x1="66" y1="64" x2="78" y2="68" stroke="var(--border-hover)" stroke-width="1" />
        
        <!-- Pulse rings on center -->
        <circle cx="60" cy="60" r="14" stroke="var(--accent)" stroke-width="0.5" opacity="0.3">
          <animate attributeName="r" values="14;22" dur="2s" repeatCount="indefinite" />
          <animate attributeName="opacity" values="0.3;0" dur="2s" repeatCount="indefinite" />
        </circle>
      </svg>
    </div>
    <h3 class="empty-state__title">{{ title }}</h3>
    <p class="empty-state__description">{{ description }}</p>
    <slot name="action" />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  description?: string
}>()
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
  animation: fadeIn 0.4s ease-out;
}

.empty-state__illustration {
  margin-bottom: 20px;
  opacity: 0.8;
}

.empty-state__title {
  font-size: 1rem;
  font-weight: 600;
  color: var(--text-1);
  margin-bottom: 6px;
}

.empty-state__description {
  font-size: 0.8125rem;
  color: var(--text-3);
  max-width: 280px;
  line-height: 1.5;
}
</style>
