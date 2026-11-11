<template>
  <div v-if="show" class="drawer-overlay" @click.self="close">
    <div class="drawer-container slide-in-right">
      <!-- Header -->
      <div class="drawer-header">
        <div class="drawer-header-left">
          <div class="drawer-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
          </div>
          <div>
            <h3 class="drawer-title">{{ device?.serial_number || 'ACS Device Control' }}</h3>
            <span class="drawer-subtitle">{{ device?.vendor || 'Modem' }} {{ device?.model }}</span>
          </div>
        </div>
        <button class="drawer-close-btn" @click="close" title="Tutup Panel">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="drawer-body custom-scrollbar">
        <!-- Navigation Tabs -->
        <div class="drawer-tabs">
          <button type="button" :class="['tab-btn', { active: activeTab === 'config' }]" @click="activeTab = 'config'">
            Overview &amp; Optik
          </button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'wifi' }]" @click="activeTab = 'wifi'">
            Wi-Fi &amp; QR
          </button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'hosts' }]" @click="activeTab = 'hosts'">
            Klien Terhubung ({{ totalHosts }})
          </button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'diag' }]" @click="activeTab = 'diag'">
            Diagnostik
          </button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'tasks' }]" @click="activeTab = 'tasks'; loadTasks()">
            Riwayat Task
          </button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'correlation' }]" @click="activeTab = 'correlation'; loadCorrelation()">
            🔗 Korelasi Lintas Modul
          </button>
        </div>

        <!-- TAB 1: OVERVIEW & OPTIK -->
        <div v-if="activeTab === 'config'" class="tab-pane fade-in">
          <!-- Modem Info Grid -->
          <div class="info-grid">
            <div class="info-cell">
              <span class="info-label">Status Koneksi</span>
              <div class="status-cell">
                <span :class="['status-glow', device?.status === 'online' ? 'online' : 'offline']"></span>
                <span class="info-value capitalize font-bold" :class="device?.status === 'online' ? 'text-good' : 'text-muted'">
                  {{ device?.status || 'Unknown' }}
                </span>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Redaman Optik (RX)</span>
              <div class="info-value-row">
                <span :class="getRxPowerColor(device?.rx_power_dbm)" class="info-value-bold">
                  {{ device?.rx_power_dbm !== null ? device?.rx_power_dbm : 'N/A' }}
                </span>
                <span v-if="device?.rx_power_dbm !== null" class="info-unit">dBm</span>
              </div>
              <span class="info-sub text-xs" :class="getRxPowerColor(device?.rx_power_dbm)">
                {{ getRxQualityText(device?.rx_power_dbm) }}
              </span>
            </div>

            <div class="info-cell">
              <span class="info-label">Serial Number</span>
              <div class="copy-cell">
                <span class="info-value text-mono">{{ device?.serial_number || 'N/A' }}</span>
                <button v-if="device?.serial_number" class="btn-copy-tiny" @click="copyText(device.serial_number, 'Serial Number')" title="Salin SN">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">MAC Address</span>
              <div class="copy-cell">
                <span class="info-value text-mono">{{ device?.mac_address || 'N/A' }}</span>
                <button v-if="device?.mac_address" class="btn-copy-tiny" @click="copyText(device.mac_address, 'MAC Address')" title="Salin MAC">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">IP Address</span>
              <div class="copy-cell">
                <span class="info-value text-mono">{{ device?.ip_address || '0.0.0.0' }}</span>
                <button v-if="device?.ip_address && device?.ip_address !== '0.0.0.0'" class="btn-copy-tiny" @click="copyText(device.ip_address, 'IP Address')" title="Salin IP">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </button>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Firmware Software</span>
              <span class="info-value text-sm">{{ device?.software_version || '-' }}</span>
              <span class="info-sub text-xs">HW: {{ device?.hardware_version || '-' }}</span>
            </div>
          </div>

          <!-- Optical History Sparkline Chart (7 Hari) -->
          <div class="glass-card mb-4 mt-4">
            <div class="flex-between align-center mb-2">
              <h4 class="card-title mb-0">Tren Redaman Optik (7 Hari)</h4>
              <span class="text-xs text-muted" v-if="opticalHistory.length > 0">
                {{ opticalHistory.length }} data point
              </span>
            </div>

            <!-- SVG Sparkline Line Chart -->
            <div class="sparkline-wrapper" v-if="opticalHistory.length > 0">
              <svg class="sparkline-svg" viewBox="0 0 500 120" preserveAspectRatio="none">
                <defs>
                  <linearGradient id="rxGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.35"/>
                    <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.0"/>
                  </linearGradient>
                </defs>

                <!-- Threshold reference lines: -25 dBm & -8 dBm -->
                <line x1="0" y1="85" x2="500" y2="85" stroke="rgba(245, 158, 11, 0.3)" stroke-dasharray="4" stroke-width="1"/>
                <text x="6" y="81" fill="#f59e0b" font-size="9" opacity="0.7">-25 dBm (Batas Waspada)</text>

                <!-- Area fill -->
                <path :d="sparklineAreaPath" fill="url(#rxGradient)"/>
                <!-- Line stroke -->
                <path :d="sparklineLinePath" fill="none" stroke="#38bdf8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                <!-- Data point dots -->
                <circle
                  v-for="(pt, idx) in sparklinePoints"
                  :key="idx"
                  :cx="pt.x"
                  :cy="pt.y"
                  r="3.5"
                  :fill="pt.val < -27 ? '#f43f5e' : (pt.val < -25 ? '#f59e0b' : '#10b981')"
                  stroke="#0f172a"
                  stroke-width="1.5"
                >
                  <title>{{ pt.val }} dBm ({{ pt.date }})</title>
                </circle>
              </svg>

              <div class="sparkline-labels flex-between text-xs text-muted mt-1">
                <span>7 hari lalu</span>
                <span>Sekarang ({{ device?.rx_power_dbm ?? 'N/A' }} dBm)</span>
              </div>
            </div>

            <div v-else class="empty-sparkline text-center py-4 text-muted text-xs">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-1 opacity-50"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <p>Belum ada riwayat redaman tersimpan.</p>
              <span class="text-xs opacity-75">Snapshot otomatis dicatat setiap 30 menit saat sinkronisasi ACS.</span>
            </div>
          </div>

          <!-- PPPoE Form -->
          <div class="glass-card mb-4">
            <h4 class="card-title">Konfigurasi Akun PPPoE Pelanggan</h4>
            <form @submit.prevent="savePppoe" class="form-stack mt-3">
              <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <input v-model="pppoeForm.username" type="text" class="input-glass" placeholder="Username dari ISP / Mikrotik">
              </div>
              <div class="form-group">
                <label class="form-label">Password PPPoE</label>
                <div class="input-action-wrap">
                  <input v-model="pppoeForm.password" :type="showPppoePassword ? 'text' : 'password'" class="input-glass" placeholder="Biarkan kosong jika tidak ingin mengubah password">
                  <button type="button" @click="showPppoePassword = !showPppoePassword" class="input-action-btn" :title="showPppoePassword ? 'Sembunyikan password' : 'Lihat password'">
                    <svg v-if="!showPppoePassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
                </div>
              </div>
              <div class="flex-end mt-2">
                <button type="submit" :disabled="isSavingPppoe" class="btn btn-primary btn-glass">
                  <span v-if="isSavingPppoe" class="spinning mr-2">⟳</span>
                  {{ isSavingPppoe ? 'Menerapkan...' : 'Simpan Konfigurasi PPPoE' }}
                </button>
              </div>
              <div v-if="pppoeError" class="alert-box alert-box--danger mt-2">{{ pppoeError }}</div>
            </form>
          </div>

          <!-- Actions Card -->
          <div class="glass-card action-card">
            <h4 class="card-title">Aksi Remote Perangkat</h4>
            <div class="flex gap-2 mt-3">
              <button @click="reboot" :disabled="isRebooting || isResetting" class="btn btn-danger-outline flex-1">
                <span v-if="isRebooting" class="spinning mr-2">⟳</span> Reboot Modem
              </button>
              <button @click="factoryReset" :disabled="isRebooting || isResetting" class="btn btn-danger flex-1">
                <span v-if="isResetting" class="spinning mr-2">⟳</span> Factory Reset
              </button>
            </div>
          </div>
        </div>

        <!-- TAB 2: WIFI CONFIG & QR CODE -->
        <div v-if="activeTab === 'wifi'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <div>
              <h4 class="drawer-section-title mb-0">Pengaturan Wireless &amp; SSID</h4>
              <p class="text-xs text-muted mt-1">Konfigurasi nama WiFi, kata sandi, dan QR Code pemindaian cepat.</p>
            </div>
            <button @click="loadAdvancedWifi" :disabled="isLoadingWifi" class="btn btn-sm btn-glass">
              <span v-if="isLoadingWifi" class="spinning mr-2">⟳</span> Muat Ulang
            </button>
          </div>

          <div v-if="isLoadingWifi" class="skeleton-loader-container">
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
          </div>
          <div v-else-if="wifiError" class="alert-box alert-box--danger mb-3">{{ wifiError }}</div>

          <div v-else class="wifi-config-container">
            <!-- 2.4GHz Band -->
            <div class="wifi-band-card mb-4" v-if="radio2g">
              <div class="band-card-header flex-between align-center">
                <div class="band-title">
                  <span class="band-badge band-24">Radio 2.4 GHz</span>
                  <span class="band-status" :class="radio2g.wifi_enabled ? 'text-good' : 'text-muted'">
                    {{ radio2g.wifi_enabled ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
                <div class="band-actions">
                  <button v-if="!radio2g.isEditing" @click="editRadio(radio2g)" class="btn-sm btn-glass-edit">Edit Radio</button>
                  <div v-else class="flex gap-2">
                    <button @click="saveRadio(1)" :disabled="radio2g.isSaving" class="btn-sm btn-primary">Simpan</button>
                    <button @click="cancelEditRadio(radio2g)" :disabled="radio2g.isSaving" class="btn-sm btn-secondary">Batal</button>
                  </div>
                </div>
              </div>

              <div class="band-card-body grid-2-col" v-if="radio2g.isEditing || !radio2g.isEditing">
                <div class="form-group">
                  <label class="form-label">WiFi Toggle</label>
                  <label class="switch-ios">
                    <input type="checkbox" v-model="radio2g.wifi_enabled" :disabled="!radio2g.isEditing">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="form-group">
                  <label class="form-label">Channel</label>
                  <select v-model="radio2g.channel" :disabled="!radio2g.isEditing" class="input-glass">
                    <option value="0">Auto</option>
                    <option v-for="ch in 13" :key="ch" :value="String(ch)">Channel {{ ch }}</option>
                  </select>
                </div>
              </div>

              <!-- SSIDs for 2.4Ghz -->
              <div class="ssid-cards-container">
                <div v-for="ssid in ssids2g" :key="ssid.index" class="ssid-card" :class="{'ssid-disabled': !ssid.enabled}">
                  <div class="ssid-card-header flex-between align-center">
                    <div class="ssid-name-wrap">
                      <span class="ssid-index">SSID {{ ssid.index }} (2.4G)</span>
                      <h5 v-if="!ssid.isEditing" class="ssid-title">{{ ssid.name }}</h5>
                      <input v-else v-model="ssid.name" type="text" class="input-glass input-sm" placeholder="Nama SSID">
                    </div>
                    <div class="ssid-action-btns flex gap-2 align-center">
                      <!-- QR Code Button -->
                      <button
                        type="button"
                        class="btn-qr-pill"
                        @click="openQrModal(ssid)"
                        title="Tampilkan QR Code WiFi untuk discan kamera HP"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>QR WiFi</span>
                      </button>
                      <span v-if="ssid.enabled" class="badge-glass good">Aktif</span>
                      <span v-else class="badge-glass muted">Nonaktif</span>
                    </div>
                  </div>

                  <div class="ssid-card-body grid-2-col mt-3">
                    <div class="form-group">
                      <label class="form-label">Password WiFi</label>
                      <div class="input-action-wrap">
                        <input
                          v-if="ssid.isEditing"
                          v-model="ssid.passkey"
                          :type="showWifiPass[ssid.index] ? 'text' : 'password'"
                          class="input-glass input-sm"
                          placeholder="Password minimal 8 karakter"
                        >
                        <div v-else class="val-text flex align-center gap-2">
                          <span>{{ showWifiPass[ssid.index] ? (ssid.passkey || '(Kosong/Open)') : '••••••••' }}</span>
                        </div>
                        <button type="button" @click="toggleShowWifiPass(ssid.index)" class="input-action-btn" :title="showWifiPass[ssid.index] ? 'Sembunyikan' : 'Lihat password'">
                          <svg v-if="!showWifiPass[ssid.index]" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                          <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                        <button v-if="ssid.passkey" type="button" class="btn-copy-tiny" @click="copyText(ssid.passkey, 'Password WiFi')" title="Salin password">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Enkripsi / Keamanan</label>
                      <select v-if="ssid.isEditing" v-model="ssid.security_type" class="input-glass input-sm">
                        <option value="WPAand11i">WPA/WPA2-PSK (Rekomendasi)</option>
                        <option value="11i">WPA2-PSK</option>
                        <option value="WPA">WPA-PSK</option>
                        <option value="None">Open (Tanpa Password)</option>
                      </select>
                      <div v-else class="val-text">{{ ssid.security_type }}</div>
                    </div>
                  </div>

                  <div class="ssid-card-footer mt-3 flex-end gap-2">
                    <button v-if="!ssid.isEditing" @click="editSsid(ssid)" class="btn-sm btn-glass-edit">Edit SSID</button>
                    <template v-else>
                      <button @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-secondary">Batal</button>
                      <button @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-primary">
                        <span v-if="ssid.isSaving" class="spinning mr-1">⟳</span> Simpan
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <!-- 5GHz Band -->
            <div class="wifi-band-card mb-4" v-if="radio5g">
              <div class="band-card-header flex-between align-center">
                <div class="band-title">
                  <span class="band-badge band-5g">Radio 5 GHz (AC)</span>
                  <span class="band-status" :class="radio5g.wifi_enabled ? 'text-good' : 'text-muted'">
                    {{ radio5g.wifi_enabled ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </div>
                <div class="band-actions">
                  <button v-if="!radio5g.isEditing" @click="editRadio(radio5g)" class="btn-sm btn-glass-edit">Edit Radio</button>
                  <div v-else class="flex gap-2">
                    <button @click="saveRadio(5)" :disabled="radio5g.isSaving" class="btn-sm btn-primary">Simpan</button>
                    <button @click="cancelEditRadio(radio5g)" :disabled="radio5g.isSaving" class="btn-sm btn-secondary">Batal</button>
                  </div>
                </div>
              </div>

              <!-- SSIDs for 5Ghz -->
              <div class="ssid-cards-container">
                <div v-for="ssid in ssids5g" :key="ssid.index" class="ssid-card" :class="{'ssid-disabled': !ssid.enabled}">
                  <div class="ssid-card-header flex-between align-center">
                    <div class="ssid-name-wrap">
                      <span class="ssid-index">SSID {{ ssid.index }} (5G)</span>
                      <h5 v-if="!ssid.isEditing" class="ssid-title">{{ ssid.name }}</h5>
                      <input v-else v-model="ssid.name" type="text" class="input-glass input-sm" placeholder="Nama SSID 5G">
                    </div>
                    <div class="ssid-action-btns flex gap-2 align-center">
                      <button type="button" class="btn-qr-pill" @click="openQrModal(ssid)" title="Tampilkan QR Code WiFi 5G">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>QR WiFi</span>
                      </button>
                      <span v-if="ssid.enabled" class="badge-glass good">Aktif</span>
                      <span v-else class="badge-glass muted">Nonaktif</span>
                    </div>
                  </div>

                  <div class="ssid-card-body grid-2-col mt-3">
                    <div class="form-group">
                      <label class="form-label">Password WiFi 5G</label>
                      <div class="input-action-wrap">
                        <input
                          v-if="ssid.isEditing"
                          v-model="ssid.passkey"
                          :type="showWifiPass[ssid.index] ? 'text' : 'password'"
                          class="input-glass input-sm"
                          placeholder="Password minimal 8 karakter"
                        >
                        <div v-else class="val-text flex align-center gap-2">
                          <span>{{ showWifiPass[ssid.index] ? (ssid.passkey || '(Kosong/Open)') : '••••••••' }}</span>
                        </div>
                        <button type="button" @click="toggleShowWifiPass(ssid.index)" class="input-action-btn">
                          <svg v-if="!showWifiPass[ssid.index]" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                          <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                        <button v-if="ssid.passkey" type="button" class="btn-copy-tiny" @click="copyText(ssid.passkey, 'Password WiFi')" title="Salin password">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>

                  <div class="ssid-card-footer mt-3 flex-end gap-2">
                    <button v-if="!ssid.isEditing" @click="editSsid(ssid)" class="btn-sm btn-glass-edit">Edit SSID</button>
                    <template v-else>
                      <button @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-secondary">Batal</button>
                      <button @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-primary">
                        <span v-if="ssid.isSaving" class="spinning mr-1">⟳</span> Simpan
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 3: CONNECTED CLIENTS (HOSTS) -->
        <div v-if="activeTab === 'hosts'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <div>
              <h4 class="drawer-section-title mb-0">Daftar Klien Terhubung (DHCP &amp; ARP)</h4>
              <p class="text-xs text-muted mt-1">Perangkat HP, Laptop, atau Smart TV yang terhubung ke modem.</p>
            </div>
            <button @click="refreshHosts" :disabled="isRefreshingHosts" class="btn btn-sm btn-glass">
              <span v-if="isRefreshingHosts" class="spinning mr-2">⟳</span> Tarik Data Klien
            </button>
          </div>

          <div v-if="isLoadingHosts" class="skeleton-loader-container">
            <div class="skeleton-card mini" v-for="i in 3" :key="i"></div>
          </div>
          <div v-else-if="hostsError" class="alert-box alert-box--danger">{{ hostsError }}</div>
          <div v-else-if="hosts.length === 0" class="empty-glass-state">
            <p>Tidak ada perangkat klien yang sedang terhubung.</p>
          </div>

          <div v-else class="hosts-list">
            <!-- WLAN Clients -->
            <div class="hosts-section" v-if="wifiHosts.length > 0">
              <h5 class="hosts-category-title mb-2">Wireless Wi-Fi ({{ wifiHosts.length }})</h5>
              <div class="host-card" v-for="host in wifiHosts" :key="host.mac">
                <div class="host-icon-box">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
                </div>
                <div class="host-info flex-1">
                  <div class="host-name">{{ host.hostname || 'Perangkat Tanpa Nama' }}</div>
                  <div class="host-details flex gap-2 align-center mt-1">
                    <span class="badge-glass">{{ host.ip }}</span>
                    <code class="text-xs text-muted">{{ host.mac }}</code>
                    <button class="btn-copy-tiny" @click="copyText(host.mac, 'MAC Klien')" title="Salin MAC">
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                  </div>
                </div>
                <div class="host-status">
                  <span :class="['status-dot', host.active ? 'online' : 'offline']" :title="host.active ? 'Aktif' : 'Riwayat Lease'"></span>
                </div>
              </div>
            </div>

            <!-- Wired LAN Clients -->
            <div class="hosts-section mt-4" v-if="lanHosts.length > 0">
              <h5 class="hosts-category-title mb-2">Kabel LAN Ethernet ({{ lanHosts.length }})</h5>
              <div class="host-card" v-for="host in lanHosts" :key="host.mac">
                <div class="host-icon-box">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                </div>
                <div class="host-info flex-1">
                  <div class="host-name">{{ host.hostname || 'Port LAN' }}</div>
                  <div class="host-details flex gap-2 align-center mt-1">
                    <span class="badge-glass">{{ host.ip }}</span>
                    <code class="text-xs text-muted">{{ host.mac }}</code>
                  </div>
                </div>
                <div class="host-status">
                  <span :class="['status-dot', host.active ? 'online' : 'offline']"></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: DIAGNOSTICS (AUTO-POLLING TERMINAL) -->
        <div v-if="activeTab === 'diag'" class="tab-pane fade-in">
          <div class="diag-container">
            <!-- Ping Section -->
            <div class="glass-card mb-4">
              <div class="flex-between align-center mb-2">
                <h4 class="card-title mb-0">ICMP Ping Diagnostik</h4>
                <span v-if="isPinging" class="polling-indicator">
                  <span class="spinning">⟳</span> Menguji koneksi ({{ pingElapsed }}s)...
                </span>
              </div>
              <p class="text-xs text-muted mb-3">Modem menembakkan 3 paket ICMP langsung dari interface WAN.</p>

              <div class="flex gap-2">
                <input v-model="pingForm.host" type="text" class="input-glass flex-1 text-mono" placeholder="Target IP / Host e.g. 8.8.8.8">
                <button @click="triggerPing" :disabled="isPinging" class="btn btn-primary">
                  <span v-if="isPinging" class="spinning mr-2">⟳</span>
                  {{ isPinging ? 'Pinging...' : 'Mulai Ping' }}
                </button>
              </div>
              <div v-if="pingError" class="alert-box alert-box--danger mt-3">{{ pingError }}</div>

              <!-- Terminal Ping Result Box -->
              <div v-if="pingResult" class="terminal-box mt-3">
                <div class="terminal-header flex-between">
                  <span class="terminal-title">TERMINAL OUTPUT (PING {{ pingForm.host }})</span>
                  <span class="badge-glass" :class="pingResult.state === 'Complete' ? 'good' : 'warning'">{{ pingResult.state }}</span>
                </div>
                <div class="terminal-body" v-if="pingResult.state === 'Complete'">
                  <p class="terminal-line text-slate">PING {{ pingForm.host }} 56(84) bytes of data.</p>
                  <p class="terminal-line text-emerald">64 bytes from {{ pingForm.host }}: icmp_seq=1 time={{ pingResult.min_response_time || pingResult.avg_response_time }} ms</p>
                  <p class="terminal-line text-emerald">64 bytes from {{ pingForm.host }}: icmp_seq=2 time={{ pingResult.avg_response_time }} ms</p>
                  <p class="terminal-line text-emerald">64 bytes from {{ pingForm.host }}: icmp_seq=3 time={{ pingResult.max_response_time || pingResult.avg_response_time }} ms</p>
                  <p class="terminal-line text-slate mt-2">--- {{ pingForm.host }} ping statistics ---</p>
                  <p class="terminal-line text-white">
                    {{ (pingResult.success_count || 0) + (pingResult.failure_count || 0) }} packets transmitted, {{ pingResult.success_count }} received,
                    {{ pingResult.failure_count > 0 ? Math.round((pingResult.failure_count / ((pingResult.success_count || 0) + pingResult.failure_count)) * 100) : 0 }}% packet loss
                  </p>
                  <p class="terminal-line text-cyan font-bold">rtt min/avg/max = {{ pingResult.min_response_time || '-' }}/{{ pingResult.avg_response_time || '-' }}/{{ pingResult.max_response_time || '-' }} ms</p>
                </div>
                <div class="terminal-body text-muted" v-else>
                  <p class="terminal-line">Perintah dikirim ke modem. Menunggu laporan hasil eksekusi...</p>
                </div>
              </div>
            </div>

            <!-- Traceroute Section -->
            <div class="glass-card mb-4">
              <div class="flex-between align-center mb-2">
                <h4 class="card-title mb-0">Traceroute Hop Path</h4>
                <span v-if="isTracerouting" class="polling-indicator">
                  <span class="spinning">⟳</span> Menelusuri jalur hop...
                </span>
              </div>
              <div class="flex gap-2">
                <input v-model="tracerouteForm.host" type="text" class="input-glass flex-1 text-mono" placeholder="Target e.g. 1.1.1.1">
                <button @click="triggerTraceroute" :disabled="isTracerouting" class="btn btn-primary">
                  <span v-if="isTracerouting" class="spinning mr-2">⟳</span>
                  {{ isTracerouting ? 'Tracing...' : 'Mulai Trace' }}
                </button>
              </div>
              <div v-if="tracerouteError" class="alert-box alert-box--danger mt-3">{{ tracerouteError }}</div>

              <div v-if="tracerouteResult" class="terminal-box mt-3">
                <div class="terminal-header flex-between">
                  <span class="terminal-title">TRACEROUTE HOP TIMELINE</span>
                  <span class="badge-glass">{{ tracerouteResult.state }}</span>
                </div>
                <div v-if="tracerouteResult.state === 'Complete'" class="timeline-container mt-2">
                  <div v-for="(hop, index) in tracerouteResult.hops" :key="index" class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                      <span class="hop-num">#{{ index + 1 }}</span>
                      <span class="hop-ip text-mono">{{ hop.host_address || '*' }}</span>
                      <span class="hop-ms badge-glass">{{ hop.rtt ? hop.rtt[0] : '*' }} ms</span>
                    </div>
                  </div>
                </div>
                <div v-else class="p-3 text-muted text-xs">
                  Proses trace sedang berjalan di modem (bisa memakan waktu 10-15 detik)...
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 6: CORRELATION (CROSS-MODULE) -->
        <div v-if="activeTab === 'correlation'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <div>
              <h4 class="drawer-section-title mb-0">Korelasi Lintas Modul (ONT ↔ MikroTik ↔ OLT)</h4>
              <p class="text-xs text-muted mt-1">
                Pencocokan data telemetri TR-069 dengan OLT PON fisik dan sesi pelanggan MikroTik.
              </p>
            </div>
            <button @click="loadCorrelation" :disabled="isLoadingCorrelation" class="btn btn-sm btn-glass">
              <span v-if="isLoadingCorrelation" class="spinning mr-1">⟳</span> Refresh
            </button>
          </div>

          <div v-if="isLoadingCorrelation" class="loading-state text-center py-5">
            <span class="spinning text-2xl">🔄</span>
            <p class="text-xs text-muted mt-2">Menganalisis korelasi lintas modul...</p>
          </div>

          <div v-else class="correlation-grid">
            <!-- 1. OLT PON Correlation Card -->
            <div class="glass-card mb-3">
              <div class="flex align-center gap-2 mb-3">
                <span class="text-lg">📡</span>
                <div>
                  <h5 class="text-sm font-bold text-white mb-0">Infrastruktur OLT PON</h5>
                  <span class="text-xs text-muted">Data fisik optik dari SFP OLT</span>
                </div>
              </div>

              <div v-if="correlationData?.olt_onu" class="grid grid-cols-2 gap-3 text-xs">
                <div class="info-cell">
                  <span class="info-label">Nama OLT:</span>
                  <span class="info-value font-bold text-white">{{ correlationData.olt_onu.olt_name || 'OLT Terhubung' }}</span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Port PON &amp; Index:</span>
                  <span class="info-value font-mono text-cyan">{{ correlationData.olt_onu.pon_port }}:{{ correlationData.olt_onu.onu_index }}</span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Daya Terima OLT (RX):</span>
                  <span class="info-value font-bold" :class="correlationData.olt_onu.rx_power_dbm < -27 ? 'text-rose' : 'text-emerald'">
                    {{ correlationData.olt_onu.rx_power_dbm !== null ? correlationData.olt_onu.rx_power_dbm + ' dBm' : 'N/A' }}
                  </span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Estimasi Jarak Fiber:</span>
                  <span class="info-value font-bold text-white">{{ correlationData.olt_onu.distance_meters ? correlationData.olt_onu.distance_meters + ' meter' : 'N/A' }}</span>
                </div>
                <div class="info-cell col-span-2" v-if="correlationData.olt_onu.optical_discrepancy_db !== null">
                  <span class="info-label">Discrepancy (Selisih RX ONT vs OLT):</span>
                  <span class="info-value font-mono" :class="correlationData.olt_onu.optical_discrepancy_db > 3 ? 'text-rose font-bold' : 'text-good'">
                    {{ correlationData.olt_onu.optical_discrepancy_db }} dB
                    <small v-if="correlationData.olt_onu.optical_discrepancy_db <= 3">(Normal)</small>
                    <small v-else>(Selisih tinggi: periksa splitter / redaman asimetris)</small>
                  </span>
                </div>
              </div>
              <div v-else class="empty-glass-state text-xs py-3">
                Perangkat ini belum terdaftar pada modul OLT Infrastructure atau Serial Number berbeda.
              </div>
            </div>

            <!-- 2. MikroTik PPPoE Session Card -->
            <div class="glass-card mb-3">
              <div class="flex align-center gap-2 mb-3">
                <span class="text-lg">🌐</span>
                <div>
                  <h5 class="text-sm font-bold text-white mb-0">Sesi Pelanggan MikroTik</h5>
                  <span class="text-xs text-muted">Status sesi PPPoE dan router edge gateway</span>
                </div>
              </div>

              <div v-if="correlationData?.pppoe" class="grid grid-cols-2 gap-3 text-xs">
                <div class="info-cell">
                  <span class="info-label">Username PPPoE:</span>
                  <span class="info-value font-mono text-cyan">{{ correlationData.pppoe.username }}</span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Status Sesi:</span>
                  <span class="info-value font-bold" :class="correlationData.pppoe.is_active ? 'text-emerald' : 'text-slate'">
                    {{ correlationData.pppoe.is_active ? '● Aktif (Online)' : '○ Terputus' }}
                  </span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Router MikroTik:</span>
                  <span class="info-value font-bold text-white">{{ correlationData.pppoe.router_name || '-' }}</span>
                </div>
                <div class="info-cell">
                  <span class="info-label">Profil Paket:</span>
                  <span class="info-value text-white">{{ correlationData.pppoe.profile || '-' }} ({{ correlationData.pppoe.package_limit_mbps }} Mbps)</span>
                </div>
              </div>
              <div v-else class="empty-glass-state text-xs py-3">
                Tidak ada data akun PPPoE yang cocok di MikroTik router cache.
              </div>
            </div>

            <!-- 3. Network Topology Node Card -->
            <div class="glass-card">
              <div class="flex align-center gap-2 mb-3">
                <span class="text-lg">🗺️</span>
                <div>
                  <h5 class="text-sm font-bold text-white mb-0">Peta Topologi Jaringan</h5>
                  <span class="text-xs text-muted">Lokasi ODP dan distribusi jaringan</span>
                </div>
              </div>

              <div v-if="correlationData?.topology_node" class="text-xs">
                <div class="flex-between py-1">
                  <span class="text-muted">Node / Titik Distribusi:</span>
                  <strong class="text-white">{{ correlationData.topology_node.name }} ({{ correlationData.topology_node.type }})</strong>
                </div>
                <div class="flex-between py-1">
                  <span class="text-muted">Koordinat GPS:</span>
                  <span class="font-mono text-cyan">{{ correlationData.topology_node.lat }}, {{ correlationData.topology_node.lng }}</span>
                </div>
              </div>
              <div v-else class="empty-glass-state text-xs py-3">
                Node topologi untuk wilayah <strong>{{ device?.area_tag || 'ini' }}</strong> belum ditentukan pada peta jaringan.
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 5: TASKS (AUDIT TRAIL) -->
        <div v-if="activeTab === 'tasks'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <div>
              <h4 class="drawer-section-title mb-0">Riwayat Antrean Perintah (CWMP Tasks)</h4>
              <p class="text-xs text-muted mt-1">Audit log semua perintah yang dikirim dari Netsight ke modem.</p>
            </div>
            <button @click="loadTasks" :disabled="isLoadingTasks" class="btn btn-sm btn-glass">
              <span v-if="isLoadingTasks" class="spinning mr-2">⟳</span> Refresh
            </button>
          </div>

          <div v-if="isLoadingTasks" class="skeleton-loader-container">
            <div class="skeleton-card mini" v-for="i in 3" :key="i"></div>
          </div>
          <div v-else-if="tasksError" class="alert-box alert-box--danger">{{ tasksError }}</div>
          <div v-else-if="deviceTasks.length === 0" class="empty-glass-state">Belum ada perintah yang tercatat.</div>

          <div v-else class="tasks-timeline">
            <div class="host-card" v-for="task in deviceTasks" :key="task.id" :class="{'opacity-75': task.status === 'pending'}">
              <div class="host-info flex-1">
                <div class="flex align-center gap-2 mb-1">
                  <div class="host-name capitalize font-bold">{{ task.action.replace(/_/g, ' ') }}</div>
                  <span v-if="task.status === 'pending'" class="badge-glass warning text-xs">Menunggu CPE</span>
                  <span v-else-if="task.status === 'success'" class="badge-glass good text-xs">Sukses</span>
                  <span v-else class="badge-glass critical text-xs">Gagal</span>
                </div>
                <div class="text-xs text-muted mb-1">{{ new Date(task.created_at).toLocaleString('id-ID') }}</div>
                <div class="text-xs mt-1" v-if="task.error_message">
                  <span class="text-critical">{{ task.error_message }}</span>
                </div>
              </div>
              <div class="text-right">
                <div class="text-xs text-muted">oleh {{ task.creator?.name || 'Sistem' }}</div>
                <div class="text-xs text-muted mt-1 uppercase">{{ task.execution_type }}</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- QR Code Popup Modal -->
    <div v-if="qrModalOpen" class="qr-modal-overlay" @click.self="qrModalOpen = false">
      <div class="qr-modal-box fade-in">
        <div class="qr-modal-header flex-between align-center">
          <h4 class="mb-0">QR Code Wi-Fi Pelanggan</h4>
          <button class="btn-close-sm" @click="qrModalOpen = false">&times;</button>
        </div>
        <div class="qr-modal-body text-center py-3">
          <div class="qr-code-frame">
            <img :src="qrDataUrl" alt="WiFi QR Code" class="qr-image" />
          </div>
          <h5 class="mt-3 mb-1 text-white">{{ currentQrSsid }}</h5>
          <p class="text-xs text-muted">Arahkan kamera HP ke QR Code untuk langsung terhubung otomatis.</p>
          <div class="qr-pass-tag mt-2">
            <span class="text-xs text-muted">Password:</span>
            <code class="text-sm font-bold text-white ml-1">{{ currentQrPass || '(Open)' }}</code>
          </div>
        </div>
        <div class="qr-modal-footer flex gap-2 justify-center">
          <button class="btn btn-secondary btn-sm" @click="downloadQrCode">Unduh Gambar (PNG)</button>
          <button class="btn btn-primary btn-sm" @click="qrModalOpen = false">Selesai</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive, computed } from 'vue'
import QRCode from 'qrcode'
import { useAcsStore } from '../stores/acsStore'
import { useToastStore } from '../stores/toastStore'

const toastStore = useToastStore()
const store = useAcsStore()

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  device: {
    type: Object as () => any,
    default: null
  }
})

const emit = defineEmits(['close', 'updated'])

// Navigation
const activeTab = ref('config')

// Forms and UI States
const showPppoePassword = ref(false)
const pppoeError = ref('')
const isSavingPppoe = ref(false)
const error = ref('')
const wifiError = ref('')
const isLoadingWifi = ref(false)
const hostsError = ref('')
const isRebooting = ref(false)
const isResetting = ref(false)

// Optical History
const opticalHistory = ref<any[]>([])
const isLoadingMetrics = ref(false)

// Hosts / Clients
const hosts = ref<any[]>([])
const isLoadingHosts = ref(false)
const isRefreshingHosts = ref(false)

// WiFi
const radio2g = ref<any>(null)
const radio5g = ref<any>(null)
const ssids = ref<any[]>([])
const showWifiPass = ref<Record<number, boolean>>({})

// QR Code
const qrModalOpen = ref(false)
const qrDataUrl = ref('')
const currentQrSsid = ref('')
const currentQrPass = ref('')

// Diagnostics
const pingForm = reactive({ host: '8.8.8.8' })
const tracerouteForm = reactive({ host: '8.8.8.8' })
const isPinging = ref(false)
const pingError = ref('')
const pingResult = ref<any>(null)
const pingElapsed = ref(0)
let pingPollInterval: any = null

const isTracerouting = ref(false)
const tracerouteError = ref('')
const tracerouteResult = ref<any>(null)
let tracePollInterval: any = null

// Tasks
const deviceTasks = ref<any[]>([])
const isLoadingTasks = ref(false)
const tasksError = ref('')

const pppoeForm = reactive({
  username: '',
  password: ''
})

// Computeds
const totalHosts = computed(() => hosts.value.length)
const wifiHosts = computed(() => hosts.value.filter(h => String(h.type).includes('802.11')))
const lanHosts = computed(() => hosts.value.filter(h => !String(h.type).includes('802.11')))
const ssids2g = computed(() => ssids.value.filter((s: any) => s.index <= 4))
const ssids5g = computed(() => ssids.value.filter((s: any) => s.index >= 5))

// Sparkline Computeds
const sparklinePoints = computed(() => {
  if (opticalHistory.value.length === 0) return []
  const pts = opticalHistory.value
  const minVal = -32
  const maxVal = -10
  const width = 500
  const height = 120
  const padding = 10

  const count = pts.length
  return pts.map((pt, i) => {
    const x = count === 1 ? width / 2 : padding + (i / (count - 1)) * (width - 2 * padding)
    const normalized = Math.max(0, Math.min(1, (pt.rx_power_dbm - minVal) / (maxVal - minVal)))
    const y = height - padding - normalized * (height - 2 * padding)
    return {
      x: Math.round(x),
      y: Math.round(y),
      val: pt.rx_power_dbm,
      date: new Date(pt.recorded_at).toLocaleDateString('id-ID', { hour: '2-digit', minute: '2-digit' })
    }
  })
})

const sparklineLinePath = computed(() => {
  const pts = sparklinePoints.value
  if (pts.length === 0) return ''
  return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
})

const sparklineAreaPath = computed(() => {
  const pts = sparklinePoints.value
  if (pts.length === 0) return ''
  const first = pts[0]
  const last = pts[pts.length - 1]
  const line = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
  return `${line} L ${last.x} 120 L ${first.x} 120 Z`
})

// Watcher on show
watch(() => props.show, (newVal) => {
  if (newVal && props.device) {
    error.value = ''
    wifiError.value = ''
    pppoeError.value = ''
    hostsError.value = ''
    pingError.value = ''
    pingResult.value = null
    tracerouteError.value = ''
    tracerouteResult.value = null
    tasksError.value = ''
    deviceTasks.value = []
    showPppoePassword.value = false
    activeTab.value = 'config'
    hosts.value = []
    showWifiPass.value = {}
    opticalHistory.value = []

    loadHosts()
    loadAdvancedWifi()
    loadTasks()
    loadMetrics()

    pppoeForm.username = props.device.pppoe_username || ''
    pppoeForm.password = ''
  } else {
    stopPingPolling()
    stopTracePolling()
  }
})

// Metrics Loader
const loadMetrics = async () => {
  if (!props.device) return
  isLoadingMetrics.value = true
  try {
    const list = await store.fetchDeviceMetrics(props.device.id, 7)
    opticalHistory.value = list || []
  } catch (err) {
    console.error('Failed to load metrics', err)
  } finally {
    isLoadingMetrics.value = false
  }
}

const correlationData = ref<any>(null)
const isLoadingCorrelation = ref(false)

const loadCorrelation = async () => {
  if (!props.device) return
  isLoadingCorrelation.value = true
  try {
    correlationData.value = await store.fetchDeviceCorrelation(props.device.id)
  } catch (err: any) {
    console.error('Failed to load device correlation', err)
  } finally {
    isLoadingCorrelation.value = false
  }
}

// Tasks Loader
const loadTasks = async () => {
  if (!props.device) return
  isLoadingTasks.value = true
  tasksError.value = ''
  try {
    deviceTasks.value = await store.fetchDeviceTasks(props.device.id)
  } catch (err: any) {
    tasksError.value = err.message
  } finally {
    isLoadingTasks.value = false
  }
}

// Hosts Loader
const loadHosts = async () => {
  if (!props.device) return
  isLoadingHosts.value = true
  hostsError.value = ''
  try {
    hosts.value = await store.fetchDeviceHosts(props.device.id)
  } catch (err: any) {
    hostsError.value = err.message
  } finally {
    isLoadingHosts.value = false
  }
}

const refreshHosts = async () => {
  if (!props.device) return
  isRefreshingHosts.value = true
  hostsError.value = ''
  try {
    await store.refreshDeviceHosts(props.device.id)
    loadTasks()
    setTimeout(loadHosts, 3000)
    emit('updated', 'Perintah tarik klien terbaru dikirim ke modem.')
  } catch (err: any) {
    hostsError.value = err.message
  } finally {
    isRefreshingHosts.value = false
  }
}

// WiFi Advanced
const loadAdvancedWifi = async () => {
  if (!props.device) return
  isLoadingWifi.value = true
  wifiError.value = ''
  try {
    const res = await store.fetchAdvancedWifi(props.device.id)
    radio2g.value = res.radio_2g ? { ...res.radio_2g, isEditing: false, isSaving: false, _original: { ...res.radio_2g } } : null
    radio5g.value = res.radio_5g ? { ...res.radio_5g, isEditing: false, isSaving: false, _original: { ...res.radio_5g } } : null
    ssids.value = (res.ssids || []).map((item: any) => ({
      ...item,
      isSaving: false,
      isEditing: false,
      _original: { ...item }
    }))
  } catch (err: any) {
    wifiError.value = err.message
  } finally {
    isLoadingWifi.value = false
  }
}

const editRadio = (radio: any) => {
  radio._original = { ...radio }
  radio.isEditing = true
}

const cancelEditRadio = (radio: any) => {
  Object.assign(radio, radio._original)
  radio.isEditing = false
}

const saveRadio = async (radioIndex: number) => {
  if (!props.device) return
  const targetRadio = radioIndex === 1 ? radio2g.value : radio5g.value
  const band = radioIndex === 1 ? '2.4GHz' : '5GHz'
  if (!targetRadio) return

  targetRadio.isSaving = true
  try {
    const res = await store.updateRadioConfig(props.device.id, {
      radio_index: targetRadio.index,
      wifi_enabled: targetRadio.wifi_enabled,
      channel: targetRadio.channel,
      network_mode: targetRadio.network_mode,
      transmit_power: targetRadio.transmit_power,
      bandwidth: targetRadio.bandwidth
    })
    targetRadio.isEditing = false
    targetRadio._original = { ...targetRadio }
    loadTasks()
    const msg = res.message || `Pengaturan Radio ${band} disimpan.`
    toastStore.success(msg)
    emit('updated', msg)
  } catch (err: any) {
    toastStore.error(err.message || 'Gagal menyimpan radio')
  } finally {
    targetRadio.isSaving = false
  }
}

const editSsid = (ssid: any) => {
  ssid._original = { ...ssid }
  ssid.isEditing = true
}

const cancelEditSsid = (ssid: any) => {
  Object.assign(ssid, ssid._original)
  ssid.isEditing = false
}

const saveSsid = async (ssidObj: any) => {
  if (!props.device) return
  ssidObj.isSaving = true
  try {
    const res = await store.updateAdvancedWifi(props.device.id, {
      index: ssidObj.index,
      enabled: ssidObj.enabled,
      hide: ssidObj.hide,
      name: ssidObj.name,
      security_type: ssidObj.security_type,
      max_clients: ssidObj.max_clients,
      passkey: ssidObj.passkey
    })
    ssidObj.isEditing = false
    ssidObj._original = { ...ssidObj }
    loadTasks()
    const msg = res.message || `Konfigurasi SSID ${ssidObj.index} berhasil disimpan.`
    toastStore.success(msg)
    emit('updated', msg)
  } catch (err: any) {
    toastStore.error(err.message || 'Gagal menyimpan SSID')
  } finally {
    ssidObj.isSaving = false
  }
}

const toggleShowWifiPass = (idx: number) => {
  showWifiPass.value[idx] = !showWifiPass.value[idx]
}

// QR Code Popup
const openQrModal = async (ssid: any) => {
  currentQrSsid.value = ssid.name || 'WiFi'
  currentQrPass.value = ssid.passkey || ''
  const wifiString = `WIFI:T:${ssid.security_type === 'None' ? 'nopass' : 'WPA'};S:${ssid.name};P:${ssid.passkey || ''};;`
  try {
    qrDataUrl.value = await QRCode.toDataURL(wifiString, {
      width: 280,
      margin: 2,
      color: { dark: '#0f172a', light: '#ffffff' }
    })
    qrModalOpen.value = true
  } catch (err) {
    console.error('Failed to generate QR', err)
  }
}

const downloadQrCode = () => {
  if (!qrDataUrl.value) return
  const a = document.createElement('a')
  a.href = qrDataUrl.value
  a.download = `wifi_qr_${currentQrSsid.value}.png`
  a.click()
}

// PPPoE
const savePppoe = async () => {
  if (!props.device) return
  if (!pppoeForm.username && !pppoeForm.password) {
    pppoeError.value = 'Minimal isi Username atau Password PPPoE'
    return
  }

  isSavingPppoe.value = true
  pppoeError.value = ''
  try {
    const res = await store.updatePppoeConfig(props.device.id, pppoeForm)
    loadTasks()
    toastStore.success(res.message || 'Konfigurasi PPPoE dikirim ke modem.')
    if (pppoeForm.username) props.device.pppoe_username = pppoeForm.username
    pppoeForm.password = ''
  } catch (err: any) {
    pppoeError.value = err.message
  } finally {
    isSavingPppoe.value = false
  }
}

// Diagnostics: Auto-polling Ping
const triggerPing = async () => {
  if (!props.device || !pingForm.host) return
  stopPingPolling()

  isPinging.value = true
  pingError.value = ''
  pingResult.value = { state: 'Requested' }
  pingElapsed.value = 0

  try {
    await store.triggerPing(props.device.id, pingForm.host)
    loadTasks()

    // Start live auto-polling every 1.5s
    let attempts = 0
    pingPollInterval = setInterval(async () => {
      attempts++
      pingElapsed.value = attempts * 1.5
      try {
        const res = await store.fetchPingResult(props.device.id)
        if (res && res.state === 'Complete') {
          pingResult.value = res
          stopPingPolling()
          toastStore.success('Ping diagnostik selesai.')
        } else if (res && res.state === 'Error') {
          pingResult.value = res
          stopPingPolling()
          pingError.value = 'Modem melaporkan kegagalan pengujian ICMP ping.'
        }
      } catch (pollEx) {
        // continue polling
      }

      if (attempts >= 15) {
        stopPingPolling()
        if (pingResult.value?.state !== 'Complete') {
          pingError.value = 'Waktu habis. Silakan klik tombol periksa hasil kembali nanti.'
        }
      }
    }, 1500)
  } catch (err: any) {
    pingError.value = err.message
    stopPingPolling()
  }
}

const stopPingPolling = () => {
  if (pingPollInterval) {
    clearInterval(pingPollInterval)
    pingPollInterval = null
  }
  isPinging.value = false
}

// Diagnostics: Auto-polling Traceroute
const triggerTraceroute = async () => {
  if (!props.device || !tracerouteForm.host) return
  stopTracePolling()

  isTracerouting.value = true
  tracerouteError.value = ''
  tracerouteResult.value = { state: 'Requested' }

  try {
    await store.triggerTraceroute(props.device.id, tracerouteForm.host)
    loadTasks()

    let attempts = 0
    tracePollInterval = setInterval(async () => {
      attempts++
      try {
        const res = await store.fetchTracerouteResult(props.device.id)
        if (res && res.state === 'Complete') {
          tracerouteResult.value = res
          stopTracePolling()
          toastStore.success('Traceroute selesai.')
        } else if (res && res.state === 'Error') {
          tracerouteResult.value = res
          stopTracePolling()
          tracerouteError.value = 'Traceroute gagal dijalankan oleh modem.'
        }
      } catch (ex) {
        // continue
      }

      if (attempts >= 15) {
        stopTracePolling()
      }
    }, 2000)
  } catch (err: any) {
    tracerouteError.value = err.message
    stopTracePolling()
  }
}

const stopTracePolling = () => {
  if (tracePollInterval) {
    clearInterval(tracePollInterval)
    tracePollInterval = null
  }
  isTracerouting.value = false
}

// Helpers
const copyText = async (text: string, label: string) => {
  try {
    await navigator.clipboard.writeText(text)
    toastStore.info(`${label} disalin: ${text}`)
  } catch (err) {
    toastStore.error('Gagal menyalin')
  }
}

const getRxPowerColor = (power: number | null) => {
  if (power === null || power === undefined) return 'text-muted'
  if (power >= -25 && power <= -8) return 'text-good'
  if ((power < -25 && power >= -27) || (power > -8 && power <= -4)) return 'text-warning'
  return 'text-critical'
}

const getRxQualityText = (power: number | null) => {
  if (power === null) return 'Tidak ada pembacaan'
  if (power >= -25 && power <= -8) return 'Sinyal Optik Sangat Bagus'
  if (power < -25 && power >= -27) return 'Redaman Agak Tinggi (Waspada)'
  return 'Redaman Kritis (Periksa Fiber)'
}

const reboot = async () => {
  if (!confirm('Reboot modem ini sekarang? Koneksi pelanggan akan terputus selama 1-2 menit.')) return
  isRebooting.value = true
  try {
    await store.rebootDevice(props.device.id)
    toastStore.success('Perintah reboot berhasil dikirim ke modem.')
    close()
  } catch (err: any) {
    toastStore.error(err.message || 'Gagal mengirim perintah reboot.')
  } finally {
    isRebooting.value = false
  }
}

const factoryReset = async () => {
  const confirmText = prompt('PERINGATAN: Modem akan di-reset total ke setelan pabrik. Ketik "RESET" untuk konfirmasi:')
  if (confirmText !== 'RESET') return
  isResetting.value = true
  try {
    await store.factoryResetDevice(props.device.id)
    toastStore.success('Perintah factory reset dikirim.')
    close()
  } catch (err: any) {
    toastStore.error(err.message || 'Gagal mengirim perintah reset.')
  } finally {
    isResetting.value = false
  }
}

const close = () => {
  stopPingPolling()
  stopTracePolling()
  emit('close')
}
</script>

<style scoped>
/* ── Overlay & Drawer ───────────────────────────────────── */
.drawer-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  display: flex;
  justify-content: flex-end;
}
.drawer-container {
  width: 100%;
  max-width: 640px;
  height: 100%;
  background: var(--surface-1);
  border-left: 1px solid var(--border);
  box-shadow: -15px 0 50px rgba(0, 0, 0, 0.4);
  display: flex;
  flex-direction: column;
}
.slide-in-right { animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

/* ── Header ─────────────────────────────────────────────── */
.drawer-header {
  padding: 20px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  background: rgba(15, 23, 42, 0.7);
}
.drawer-header-left { display: flex; align-items: center; gap: 12px; }
.drawer-icon-wrap {
  width: 42px; height: 42px;
  border-radius: 10px;
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.2);
  display: flex; align-items: center; justify-content: center;
  color: #38bdf8;
}
.drawer-title { margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-1); }
.drawer-subtitle { font-size: 0.75rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.04em; }
.drawer-close-btn {
  background: transparent; border: none; color: var(--text-3);
  cursor: pointer; padding: 6px; border-radius: 8px; transition: all 0.15s;
}
.drawer-close-btn:hover { background: var(--surface-2); color: var(--text-1); }

/* ── Body & Tabs ────────────────────────────────────────── */
.drawer-body { flex: 1; padding: 24px; overflow-y: auto; }
.drawer-tabs {
  display: flex; gap: 6px; background: var(--surface-2);
  padding: 4px; border-radius: 10px; margin-bottom: 20px;
  overflow-x: auto;
}
.tab-btn {
  flex: 1; padding: 8px 12px; border: none; background: transparent;
  color: var(--text-3); font-size: 0.8rem; font-weight: 600;
  border-radius: 8px; cursor: pointer; transition: all 0.2s;
  white-space: nowrap; text-align: center;
}
.tab-btn:hover { color: var(--text-1); }
.tab-btn.active {
  background: var(--surface-1); color: #38bdf8;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

/* ── Info Grid ──────────────────────────────────────────── */
.info-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
}
.info-cell {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 4px;
}
.info-label { font-size: 0.7rem; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.04em; }
.info-value { font-size: 0.9rem; font-weight: 600; color: var(--text-1); }
.info-value-bold { font-size: 1.25rem; font-weight: 700; }
.info-unit { font-size: 0.8rem; font-weight: 600; color: var(--text-3); margin-left: 2px; }
.copy-cell { display: inline-flex; align-items: center; justify-content: space-between; }
.btn-copy-tiny {
  background: transparent; border: none; color: var(--text-3); cursor: pointer;
  padding: 2px; border-radius: 4px;
}
.btn-copy-tiny:hover { color: #38bdf8; }

/* ── Sparkline Chart ────────────────────────────────────── */
.sparkline-wrapper {
  background: rgba(15, 23, 42, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  padding: 10px;
}
.sparkline-svg { width: 100%; height: 110px; overflow: visible; }

/* ── Cards & Forms ──────────────────────────────────────── */
.glass-card {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 12px; padding: 16px;
}
.card-title { font-size: 0.9rem; font-weight: 700; color: var(--text-1); margin: 0; }
.drawer-section-title { font-size: 0.95rem; font-weight: 700; color: var(--text-1); }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 0.78rem; font-weight: 600; color: var(--text-2); }
.input-glass {
  background: var(--surface-1); border: 1px solid var(--border);
  border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; color: var(--text-1); width: 100%;
}
.input-action-wrap { position: relative; display: flex; align-items: center; }
.input-action-wrap input { padding-right: 60px; }
.input-action-btn {
  position: absolute; right: 8px; background: transparent; border: none;
  color: var(--text-3); cursor: pointer; padding: 4px;
}
.input-action-btn:hover { color: var(--text-1); }

/* ── WiFi Cards & QR ────────────────────────────────────── */
.wifi-band-card {
  background: var(--surface-2); border: 1px solid var(--border);
  border-radius: 12px; padding: 16px;
}
.band-badge {
  font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;
  background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.2);
}
.ssid-card {
  background: var(--surface-1); border: 1px solid var(--border);
  border-radius: 10px; padding: 12px 14px; margin-top: 12px;
}
.ssid-title { margin: 0; font-size: 0.9rem; font-weight: 700; color: var(--text-1); }
.ssid-index { font-size: 0.68rem; font-weight: 600; color: var(--text-3); text-transform: uppercase; }

.btn-qr-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8; font-size: 0.75rem; font-weight: 600; padding: 4px 10px;
  border-radius: 999px; cursor: pointer; transition: all 0.2s;
}
.btn-qr-pill:hover { background: #38bdf8; color: #0f172a; }

/* ── QR Modal ───────────────────────────────────────────── */
.qr-modal-overlay {
  position: fixed; inset: 0; z-index: 1001;
  background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.qr-modal-box {
  background: #0f172a; border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 16px; padding: 24px; max-width: 380px; width: 100%;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
}
.qr-code-frame {
  background: #ffffff; padding: 12px; border-radius: 12px;
  display: inline-block; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}
.qr-image { width: 220px; height: 220px; display: block; }
.qr-pass-tag {
  background: rgba(255, 255, 255, 0.05); padding: 4px 12px;
  border-radius: 6px; display: inline-block;
}
.btn-close-sm {
  background: transparent; border: none; color: var(--text-3);
  font-size: 1.4rem; cursor: pointer;
}

/* ── Hosts List ─────────────────────────────────────────── */
.host-card {
  background: var(--surface-1); border: 1px solid var(--border);
  border-radius: 10px; padding: 10px 14px; display: flex;
  align-items: center; gap: 12px; margin-bottom: 8px;
}
.host-icon-box {
  width: 36px; height: 36px; border-radius: 8px;
  background: rgba(56, 189, 248, 0.1); color: #38bdf8;
  display: flex; align-items: center; justify-content: center;
}
.host-name { font-size: 0.85rem; font-weight: 600; color: var(--text-1); }
.hosts-category-title { font-size: 0.78rem; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.04em; }

/* ── Terminal Console ───────────────────────────────────── */
.terminal-box {
  background: #050811; border: 1px solid rgba(56, 189, 248, 0.2);
  border-radius: 10px; overflow: hidden; font-family: var(--font-mono);
}
.terminal-header {
  background: #0b1120; padding: 8px 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.terminal-title { font-size: 0.7rem; font-weight: 700; color: #38bdf8; letter-spacing: 0.05em; }
.terminal-body { padding: 14px; font-size: 0.78rem; line-height: 1.6; }
.terminal-line { margin: 0; }
.timeline-item {
  display: flex; align-items: center; gap: 10px; padding: 4px 0; font-size: 0.78rem;
}
.timeline-dot { width: 6px; height: 6px; border-radius: 50%; background: #38bdf8; }
.hop-num { color: var(--text-3); font-weight: 700; width: 28px; }
.hop-ip { flex: 1; color: var(--text-1); }
.polling-indicator { font-size: 0.75rem; color: #38bdf8; font-weight: 600; }

/* ── General Helpers ────────────────────────────────────── */
.status-cell { display: inline-flex; align-items: center; gap: 8px; }
.status-glow { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.status-glow.online { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.8); }
.status-glow.offline { background: #6b7280; }
.status-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.status-dot.online { background: #10b981; }
.status-dot.offline { background: #6b7280; }

.badge-glass {
  background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 2px 8px; border-radius: 6px; font-size: 0.72rem; font-weight: 600;
}
.badge-glass.good { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
.badge-glass.warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-color: rgba(245, 158, 11, 0.3); }
.badge-glass.critical { background: rgba(244, 63, 94, 0.1); color: #f43f5e; border-color: rgba(244, 63, 94, 0.3); }
.badge-glass.muted { color: var(--text-3); }

.text-good { color: #10b981; }
.text-warning { color: #f59e0b; }
.text-critical { color: #f43f5e; }
.text-muted { color: var(--text-3); }
.text-mono { font-family: var(--font-mono); }
.text-white { color: #ffffff; }
.text-emerald { color: #10b981; }
.text-cyan { color: #22d3ee; }
.text-slate { color: #94a3b8; }

.btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem;
  font-weight: 600; cursor: pointer; transition: all 0.2s; border: 1px solid transparent;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #38bdf8; color: #0f172a; }
.btn-primary:hover:not(:disabled) { background: #0ea5e9; }
.btn-secondary { background: var(--surface-2); color: var(--text-2); border-color: var(--border); }
.btn-secondary:hover:not(:disabled) { background: var(--surface-3); color: var(--text-1); }
.btn-danger { background: #f43f5e; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #e11d48; }
.btn-danger-outline { background: transparent; color: #f43f5e; border-color: rgba(244, 63, 94, 0.3); }
.btn-danger-outline:hover:not(:disabled) { background: rgba(244, 63, 94, 0.1); }
.btn-sm { padding: 4px 10px; font-size: 0.78rem; border-radius: 6px; }
.btn-glass-edit {
  background: var(--surface-1); border: 1px solid var(--border);
  color: var(--text-2); padding: 4px 10px; font-size: 0.78rem; border-radius: 6px; cursor: pointer;
}
.btn-glass-edit:hover { color: var(--text-1); border-color: #38bdf8; }

.flex { display: flex; }
.flex-1 { flex: 1; }
.flex-between { display: flex; justify-content: space-between; }
.flex-end { display: flex; justify-content: flex-end; }
.align-center { align-items: center; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.grid-2-col { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
.grid-3-col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
.spinning { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.fade-in { animation: fadeIn 0.25s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
