<template>
  <div class="stat-card glass-card" :style="cardStyle">
    <!-- Accent glow line -->
    <div class="stat-card__glow" :style="{ background: accentColor }" />

    <div class="stat-card__header">
      <span class="stat-card__icon" v-html="icon"></span>
      <span class="stat-card__title">{{ title }}</span>
    </div>

    <div class="stat-card__value" :style="{ color: accentColor }">
      {{ formattedDisplayValue }}
    </div>

    <div v-if="subtitle" class="stat-card__subtitle">
      {{ subtitle }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    title: string
    value: string | number
    icon?: string
    accentColor?: string
    subtitle?: string
  }>(),
  {
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>',
    accentColor: 'var(--accent)',
    subtitle: '',
  }
)

const displayValue = ref(props.value)

watch(() => props.value, (newVal, oldVal) => {
  if (typeof newVal === 'number' && typeof oldVal === 'number') {
    const start = oldVal
    const end = newVal
    const duration = 400
    const startTime = performance.now()

    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime
      const progress = Math.min(elapsed / duration, 1)
      const easeOut = (t: number) => 1 - Math.pow(1 - t, 3)
      
      displayValue.value = Math.round(start + (end - start) * easeOut(progress))
      
      if (progress < 1) {
        requestAnimationFrame(animate)
      } else {
        displayValue.value = end
      }
    }
    requestAnimationFrame(animate)
  } else {
    displayValue.value = newVal
  }
}, { immediate: true })

const formattedDisplayValue = computed(() =>
  typeof displayValue.value === 'number' ? displayValue.value.toLocaleString() : displayValue.value
)

const cardStyle = computed(() => ({
  '--card-accent': props.accentColor,
  '--card-accent-dim': 'color-mix(in srgb, var(--card-accent) 20%, transparent)',
}))
</script>

<style scoped>
.stat-card {
  position: relative;
  padding: 24px;
  overflow: hidden;
  transition: transform var(--transition-base), box-shadow var(--transition-base);
  cursor: default;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow:
    var(--shadow-elevated),
    0 0 30px var(--card-accent-dim);
}

/* Top glow accent bar */
.stat-card__glow {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  opacity: 0.8;
  transition: opacity var(--transition-base), height var(--transition-base);
}
.stat-card:hover .stat-card__glow {
  height: 3px;
  opacity: 1;
}

.stat-card__header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 16px;
}

.stat-card__icon {
  font-size: 1.25rem;
  line-height: 1;
}

.stat-card__title {
  font-size: 0.8rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-2);
}

.stat-card__value {
  font-size: 2.25rem;
  font-weight: 700;
  font-family: var(--font-mono);
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.stat-card__subtitle {
  margin-top: 8px;
  font-size: 0.75rem;
  color: var(--text-3);
}
</style>
