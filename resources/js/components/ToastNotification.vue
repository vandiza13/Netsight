<template>
  <Teleport to="body">
    <TransitionGroup name="toast" tag="div" class="toast-container">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="toast"
        :class="`toast--${toast.type}`"
        @click="remove(toast.id)"
      >
        <span class="toast__icon" v-html="iconFor(toast.type)"></span>
        <div class="toast__content">
          <span class="toast__title" v-if="toast.title">{{ toast.title }}</span>
          <span class="toast__message">{{ toast.message }}</span>
        </div>
        <button class="toast__close" @click.stop="remove(toast.id)" aria-label="Close">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { useToastStore } from '../stores/toastStore'
import { storeToRefs } from 'pinia'

const toastStore = useToastStore()
const { toasts } = storeToRefs(toastStore)

function remove(id: number) {
  toastStore.remove(id)
}

function iconFor(type: string): string {
  const icons: Record<string, string> = {
    success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>',
    error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  }
  return icons[type] || icons.info
}
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 8px;
  pointer-events: none;
}

.toast {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  min-width: 280px;
  max-width: 380px;
  background: var(--elevated-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  box-shadow: var(--elevated-shadow);
  pointer-events: auto;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.toast:hover {
  border-color: var(--border-hover);
}

.toast__icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  margin-top: 1px;
}

.toast--success .toast__icon { color: var(--success); }
.toast--error .toast__icon   { color: var(--danger); }
.toast--warning .toast__icon { color: var(--warning); }
.toast--info .toast__icon    { color: var(--info); }

.toast--success { border-left: 3px solid var(--success); }
.toast--error   { border-left: 3px solid var(--danger); }
.toast--warning { border-left: 3px solid var(--warning); }
.toast--info    { border-left: 3px solid var(--info); }

.toast__content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.toast__title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-1);
}

.toast__message {
  font-size: 0.75rem;
  color: var(--text-2);
  line-height: 1.4;
}

.toast__close {
  flex-shrink: 0;
  color: var(--text-3);
  transition: color var(--transition-fast);
  display: flex;
  align-items: center;
}
.toast__close:hover {
  color: var(--text-1);
}

/* Toast animations */
.toast-enter-active {
  transition: all 300ms var(--ease-spring);
}
.toast-leave-active {
  transition: all 200ms var(--ease);
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
.toast-move {
  transition: transform 300ms var(--ease);
}
</style>
