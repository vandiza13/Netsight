<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="settings-modal slide-in-up">
      <!-- Header -->
      <div class="modal-header">
        <div class="header-left">
          <div class="header-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </div>
          <div>
            <h3 class="modal-title">Konfigurasi Notifikasi &amp; Alert ACS</h3>
            <span class="modal-subtitle">Atur integrasi bot Telegram, gateway WhatsApp, dan ambang batas peringatan</span>
          </div>
        </div>
        <button class="close-btn" @click="close" title="Tutup">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Navigation Tabs -->
      <div class="modal-tabs">
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'telegram' }]"
          @click="activeTab = 'telegram'"
        >
          ✈️ Bot Telegram
        </button>
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'whatsapp' }]"
          @click="activeTab = 'whatsapp'"
        >
          💬 Gateway WhatsApp
        </button>
        <button
          type="button"
          :class="['tab-btn', { active: activeTab === 'rules' }]"
          @click="activeTab = 'rules'"
        >
          ⚙️ Aturan &amp; Threshold
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body custom-scrollbar">
        <!-- Toast Notification inside modal -->
        <div v-if="feedbackMsg" :class="['alert-inline', feedbackType === 'success' ? 'alert-success' : 'alert-danger']">
          {{ feedbackMsg }}
        </div>

        <!-- TAB 1: TELEGRAM -->
        <div v-if="activeTab === 'telegram'" class="tab-pane fade-in">
          <div class="channel-intro">
            <div class="channel-badge tg-badge">Telegram Bot API</div>
            <p class="channel-desc">
              Notifikasi instan dikirim ke grup Telegram NOC atau teknisi saat terjadi redaman optik anjlok, koneksi putus, atau anomali TR-069.
            </p>
          </div>

          <div class="form-grid">
            <div class="form-group col-span-2">
              <label class="toggle-card">
                <input type="checkbox" v-model="configForm.telegram_enabled" />
                <div class="toggle-info">
                  <span class="toggle-title">Aktifkan Notifikasi Telegram</span>
                  <span class="toggle-desc">Sistem akan mengirim peringatan otomatis sesuai aturan alert aktif.</span>
                </div>
              </label>
            </div>

            <div class="form-group col-span-2">
              <label>Telegram Bot Token <span class="text-rose">*</span></label>
              <input
                v-model="configForm.telegram_bot_token"
                type="text"
                class="form-control font-mono"
                placeholder="Contoh: 123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
              />
              <small class="help-text">Dapatkan dari @BotFather di Telegram.</small>
            </div>

            <div class="form-group col-span-2">
              <label>Target Chat ID / Group ID <span class="text-rose">*</span></label>
              <input
                v-model="configForm.telegram_chat_id"
                type="text"
                class="form-control font-mono"
                placeholder="Contoh: -1001234567890 atau ID user"
              />
              <small class="help-text">Gunakan ID grup (biasanya diawali -100) atau ID akun admin.</small>
            </div>
          </div>

          <div class="test-action-bar">
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="isTesting || !configForm.telegram_bot_token || !configForm.telegram_chat_id"
              @click="handleTestNotification('telegram')"
            >
              {{ isTesting ? 'Mengirim Uji Coba...' : '🚀 Test Kirim Pesan Telegram' }}
            </button>
          </div>
        </div>

        <!-- TAB 2: WHATSAPP -->
        <div v-if="activeTab === 'whatsapp'" class="tab-pane fade-in">
          <div class="channel-intro">
            <div class="channel-badge wa-badge">WhatsApp Gateway</div>
            <p class="channel-desc">
              Dukungan gateway webhook standar REST (Fonnte, Wablas, Baileys, atau gateway kustom) untuk notifikasi WhatsApp NOC.
            </p>
          </div>

          <div class="form-grid">
            <div class="form-group col-span-2">
              <label class="toggle-card">
                <input type="checkbox" v-model="configForm.whatsapp_enabled" />
                <div class="toggle-info">
                  <span class="toggle-title">Aktifkan Notifikasi WhatsApp</span>
                  <span class="toggle-desc">Kirim peringatan otomatis ke nomor admin/grup WhatsApp.</span>
                </div>
              </label>
            </div>

            <div class="form-group col-span-2">
              <label>WhatsApp Gateway Endpoint URL</label>
              <input
                v-model="configForm.whatsapp_endpoint"
                type="text"
                class="form-control font-mono"
                placeholder="https://api.fonnte.com/send atau webhook lokal"
              />
            </div>

            <div class="form-group">
              <label>API Key / Token (Opsional)</label>
              <input
                v-model="configForm.whatsapp_api_key"
                type="password"
                class="form-control font-mono"
                placeholder="••••••••••••••••"
              />
            </div>

            <div class="form-group">
              <label>Nomor Tujuan / WhatsApp ID</label>
              <input
                v-model="configForm.whatsapp_target_number"
                type="text"
                class="form-control font-mono"
                placeholder="Contoh: 6281234567890"
              />
            </div>
          </div>

          <div class="test-action-bar">
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              :disabled="isTesting || !configForm.whatsapp_endpoint || !configForm.whatsapp_target_number"
              @click="handleTestNotification('whatsapp')"
            >
              {{ isTesting ? 'Mengirim Uji Coba...' : '🚀 Test Kirim Pesan WhatsApp' }}
            </button>
          </div>
        </div>

        <!-- TAB 3: ALERT RULES -->
        <div v-if="activeTab === 'rules'" class="tab-pane fade-in">
          <div class="rules-header">
            <p class="section-desc">
              Aturan otomatisasi evaluasi telemetri ACS. Alert dijalankan secara terjadwal setelah proses sinkronisasi modem.
            </p>
          </div>

          <div class="rules-list">
            <div
              v-for="rule in rules"
              :key="rule.id"
              class="rule-card"
              :class="{ 'rule-disabled': !rule.is_active }"
            >
              <div class="rule-top">
                <div class="rule-info">
                  <div class="flex align-center gap-2">
                    <span :class="['severity-pill', rule.severity]">{{ rule.severity }}</span>
                    <h5 class="rule-title">{{ rule.name }}</h5>
                  </div>
                  <span class="rule-sub">
                    Tipe: <code>{{ rule.rule_type }}</code> &bull; Saluran: <strong>{{ rule.channel }}</strong> &bull; Cooldown: <strong>{{ rule.cooldown_minutes }} mnt</strong>
                  </span>
                </div>
                <div class="rule-toggle">
                  <input
                    type="checkbox"
                    v-model="rule.is_active"
                    @change="handleSaveRule(rule)"
                    title="Aktifkan/Nonaktifkan Aturan"
                  />
                </div>
              </div>

              <div class="rule-controls">
                <div class="control-item">
                  <span class="ctrl-label">Ambang Batas Nilai:</span>
                  <div class="flex align-center gap-1">
                    <input
                      v-model.number="rule.threshold_value"
                      type="number"
                      step="0.5"
                      class="form-control form-control-sm font-mono w-28"
                    />
                    <button class="btn btn-primary btn-xs" @click="handleSaveRule(rule)">Simpan</button>
                  </div>
                </div>

                <div class="control-item">
                  <span class="ctrl-label">Saluran Notifikasi:</span>
                  <select v-model="rule.channel" @change="handleSaveRule(rule)" class="select-modern text-xs">
                    <option value="telegram">Telegram</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="both">Keduanya (Telegram &amp; WA)</option>
                    <option value="none">Hanya Dashboard (Tanpa Notif)</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" @click="close">Tutup</button>
        <button
          v-if="activeTab !== 'rules'"
          class="btn btn-primary btn-sm"
          :disabled="isSaving"
          @click="handleSaveConfig"
        >
          {{ isSaving ? 'Menyimpan...' : 'Simpan Pengaturan Notifikasi' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close', 'updated'])
const store = useAcsStore()

const activeTab = ref<'telegram' | 'whatsapp' | 'rules'>('telegram')
const isSaving = ref(false)
const isTesting = ref(false)
const feedbackMsg = ref('')
const feedbackType = ref<'success' | 'danger'>('success')

const configForm = reactive({
  telegram_bot_token: '',
  telegram_chat_id: '',
  telegram_enabled: false,
  whatsapp_endpoint: '',
  whatsapp_api_key: '',
  whatsapp_target_number: '',
  whatsapp_enabled: false
})

const rules = ref<any[]>([])

const loadData = async () => {
  const conf = await store.fetchNotificationConfig()
  if (conf) {
    Object.assign(configForm, {
      telegram_bot_token: conf.telegram_bot_token || '',
      telegram_chat_id: conf.telegram_chat_id || '',
      telegram_enabled: Boolean(conf.telegram_enabled),
      whatsapp_endpoint: conf.whatsapp_endpoint || '',
      whatsapp_api_key: conf.whatsapp_api_key || '',
      whatsapp_target_number: conf.whatsapp_target_number || '',
      whatsapp_enabled: Boolean(conf.whatsapp_enabled)
    })
  }

  rules.value = await store.fetchAlertRules()
}

const showFeedback = (msg: string, type: 'success' | 'danger' = 'success') => {
  feedbackMsg.value = msg
  feedbackType.value = type
  setTimeout(() => {
    feedbackMsg.value = ''
  }, 4000)
}

const handleSaveConfig = async () => {
  isSaving.value = true
  try {
    const res = await store.saveNotificationConfig(configForm)
    showFeedback(res.message || 'Pengaturan notifikasi berhasil disimpan.')
    emit('updated')
  } catch (err: any) {
    showFeedback(err.message || 'Gagal menyimpan pengaturan.', 'danger')
  } finally {
    isSaving.value = false
  }
}

const handleTestNotification = async (channel: string) => {
  isTesting.value = true
  try {
    const payload: any = { channel }
    if (channel === 'telegram') {
      payload.telegram_bot_token = configForm.telegram_bot_token
      payload.telegram_chat_id = configForm.telegram_chat_id
    } else {
      payload.whatsapp_endpoint = configForm.whatsapp_endpoint
      payload.whatsapp_api_key = configForm.whatsapp_api_key
      payload.whatsapp_target_number = configForm.whatsapp_target_number
    }

    const res = await store.testNotification(payload)
    if (res.status === 'success') {
      showFeedback(res.message, 'success')
    } else {
      showFeedback(res.message, 'danger')
    }
  } catch (err: any) {
    showFeedback(err.message || 'Gagal mengirim notifikasi uji coba.', 'danger')
  } finally {
    isTesting.value = false
  }
}

const handleSaveRule = async (rule: any) => {
  try {
    await store.saveAlertRule(rule)
    showFeedback(`Aturan "${rule.name}" berhasil disimpan.`)
  } catch (err: any) {
    showFeedback(err.message || 'Gagal menyimpan aturan.', 'danger')
  }
}

const close = () => {
  emit('close')
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    loadData()
  }
})
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
  padding: 1.5rem;
}

.settings-modal {
  width: 100%;
  max-width: 760px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  max-height: 85vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.5);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.header-icon {
  width: 42px;
  height: 42px;
  border-radius: 0.625rem;
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(59, 130, 246, 0.25);
}

.modal-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #f8fafc;
  margin: 0;
}

.modal-subtitle {
  font-size: 0.8125rem;
  color: #94a3b8;
}

.close-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.35rem;
  border-radius: 0.375rem;
  transition: all 0.2s;
}

.close-btn:hover {
  color: #f8fafc;
  background: rgba(255, 255, 255, 0.1);
}

.modal-tabs {
  display: flex;
  background: rgba(15, 23, 42, 0.6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding: 0 1.5rem;
}

.tab-btn {
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  padding: 0.875rem 1.25rem;
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.tab-btn:hover {
  color: #f8fafc;
}

.tab-btn.active {
  color: #38bdf8;
  border-bottom-color: #38bdf8;
}

.modal-body {
  padding: 1.5rem;
  overflow-y: auto;
}

.alert-inline {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  margin-bottom: 1.25rem;
}

.alert-success {
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #34d399;
}

.alert-danger {
  background: rgba(244, 63, 94, 0.15);
  border: 1px solid rgba(244, 63, 94, 0.3);
  color: #fb7185;
}

.channel-intro {
  margin-bottom: 1.25rem;
}

.channel-badge {
  display: inline-block;
  padding: 0.2rem 0.6rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.tg-badge {
  background: rgba(56, 189, 248, 0.15);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.3);
}

.wa-badge {
  background: rgba(16, 185, 129, 0.15);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.channel-desc {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin: 0;
  line-height: 1.4;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.col-span-2 {
  grid-column: span 2;
}

.form-group label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #cbd5e1;
  margin-bottom: 0.35rem;
}

.form-control {
  width: 100%;
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.5rem;
  padding: 0.625rem 0.875rem;
  color: #f8fafc;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.form-control-sm {
  padding: 0.35rem 0.6rem;
  font-size: 0.8125rem;
}

.form-control:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
}

.toggle-card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  background: rgba(30, 41, 59, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.5rem;
  padding: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.toggle-card:hover {
  background: rgba(30, 41, 59, 0.7);
}

.toggle-card input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: #38bdf8;
  margin-top: 0.15rem;
}

.toggle-info {
  display: flex;
  flex-direction: column;
}

.toggle-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #f8fafc;
}

.toggle-desc {
  font-size: 0.75rem;
  color: #94a3b8;
}

.test-action-bar {
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

/* Rules Tab */
.rules-list {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}

.rule-card {
  background: rgba(30, 41, 59, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.625rem;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.rule-disabled {
  opacity: 0.55;
}

.rule-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.rule-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: #f8fafc;
}

.rule-sub {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-top: 0.25rem;
  display: block;
}

.rule-sub code {
  color: #38bdf8;
  background: rgba(0, 0, 0, 0.3);
  padding: 0.1rem 0.3rem;
  border-radius: 0.2rem;
}

.severity-pill {
  font-size: 0.625rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
}

.severity-pill.critical {
  background: rgba(244, 63, 94, 0.2);
  color: #fb7185;
  border: 1px solid rgba(244, 63, 94, 0.4);
}

.severity-pill.warning {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.4);
}

.severity-pill.info {
  background: rgba(56, 189, 248, 0.2);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.4);
}

.rule-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.control-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.ctrl-label {
  font-size: 0.75rem;
  color: #94a3b8;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.4);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
}

.font-mono {
  font-family: monospace;
}
</style>
