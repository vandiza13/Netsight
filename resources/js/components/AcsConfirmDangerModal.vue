<template>
  <div v-if="show" class="danger-overlay" @click.self="close">
    <div class="danger-modal slide-in-down">
      <!-- Header -->
      <div class="danger-header">
        <div class="danger-header-left">
          <div class="danger-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <div>
            <h3 class="danger-title">{{ title }}</h3>
            <span class="danger-subtitle">Tindakan Destruktif &mdash; Memerlukan Konfirmasi Keamanan (Admin)</span>
          </div>
        </div>
        <button type="button" class="danger-close-btn" @click="close" :disabled="loading" title="Batal">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Body -->
      <form @submit.prevent="handleConfirm" class="danger-body">
        <div class="danger-alert">
          {{ warningText }}
        </div>

        <div v-if="targetSummary" class="target-summary-box">
          <div class="summary-row" v-for="(val, key) in targetSummary" :key="key">
            <span class="summary-key">{{ key }}:</span>
            <span class="summary-val font-mono">{{ val }}</span>
          </div>
        </div>

        <div class="confirm-input-block">
          <label class="confirm-label">
            {{ inputLabel }}
          </label>
          <div class="expected-token-bar">
            <code class="expected-token">{{ expectedInput }}</code>
            <span class="token-hint">Ketik persis seperti di atas</span>
          </div>
          <input
            v-model="typedValue"
            type="text"
            class="confirm-input font-mono"
            :placeholder="`Ketik ${expectedInput}...`"
            autocomplete="off"
            spellcheck="false"
            :disabled="loading"
            autofocus
          />
        </div>

        <div v-if="error" class="danger-error-msg">
          {{ error }}
        </div>

        <div class="danger-footer">
          <button type="button" class="btn btn-secondary btn-sm" @click="close" :disabled="loading">
            Batal
          </button>
          <button
            type="submit"
            class="btn btn-danger btn-sm"
            :disabled="!isMatch || loading"
          >
            <span v-if="loading" class="spinning mr-1">⟳</span>
            {{ loading ? 'Memproses...' : confirmButtonText }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Konfirmasi Aksi Berbahaya'
  },
  warningText: {
    type: String,
    default: 'Aksi ini bersifat permanen dan tidak dapat dibatalkan.'
  },
  expectedInput: {
    type: String,
    default: ''
  },
  inputLabel: {
    type: String,
    default: 'Ketik Serial Number perangkat untuk melanjutkan:'
  },
  confirmButtonText: {
    type: String,
    default: 'Eksekusi Sekarang'
  },
  targetSummary: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'confirm'])

const typedValue = ref('')

const isMatch = computed(() => {
  if (!props.expectedInput) return false
  return typedValue.value.trim() === props.expectedInput.trim()
})

const handleConfirm = () => {
  if (!isMatch.value || props.loading) return
  emit('confirm', typedValue.value.trim())
}

const close = () => {
  if (props.loading) return
  emit('close')
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    typedValue.value = ''
  }
})
</script>

<style scoped>
.danger-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(2, 6, 23, 0.82);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
}

.danger-modal {
  width: 100%;
  max-width: 500px;
  background: #0f172a;
  border: 1px solid rgba(244, 63, 94, 0.45);
  border-radius: 0.875rem;
  box-shadow: 0 25px 60px -12px rgba(244, 63, 94, 0.25);
  overflow: hidden;
}

.danger-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.125rem 1.35rem;
  background: rgba(244, 63, 94, 0.12);
  border-bottom: 1px solid rgba(244, 63, 94, 0.25);
}

.danger-header-left {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.danger-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 0.625rem;
  background: rgba(244, 63, 94, 0.2);
  color: #fb7185;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.danger-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #fff;
}

.danger-subtitle {
  font-size: 0.72rem;
  color: #fda4af;
}

.danger-close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.375rem;
}

.danger-close-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

.danger-body {
  padding: 1.35rem;
}

.danger-alert {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.28);
  color: #fecdd3;
  font-size: 0.82rem;
  line-height: 1.5;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  margin-bottom: 1rem;
}

.target-summary-box {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.5rem;
  padding: 0.75rem 1rem;
  margin-bottom: 1rem;
  font-size: 0.8rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.2rem 0;
}

.summary-key {
  color: #94a3b8;
}

.summary-val {
  color: #f8fafc;
  font-weight: 600;
}

.confirm-input-block {
  margin-bottom: 1rem;
}

.confirm-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #e2e8f0;
  margin-bottom: 0.5rem;
}

.expected-token-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(15, 23, 42, 0.9);
  border: 1px dashed rgba(244, 63, 94, 0.45);
  padding: 0.45rem 0.75rem;
  border-radius: 0.45rem;
  margin-bottom: 0.55rem;
}

.expected-token {
  font-family: monospace;
  font-size: 0.875rem;
  font-weight: 700;
  color: #fb7185;
  user-select: all;
}

.token-hint {
  font-size: 0.7rem;
  color: #64748b;
}

.confirm-input {
  width: 100%;
  padding: 0.6rem 0.85rem;
  background: #020617;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 0.5rem;
  color: #fff;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.2s;
}

.confirm-input:focus {
  border-color: #fb7185;
}

.danger-error-msg {
  background: rgba(244, 63, 94, 0.15);
  color: #fda4af;
  font-size: 0.8rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.4rem;
  margin-bottom: 1rem;
}

.danger-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.font-mono {
  font-family: monospace;
}
</style>
