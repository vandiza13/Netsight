<template>
  <div v-if="show" class="modal-overlay" @click.self="close">
    <div class="modal-container fade-in-up">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-header-left">
          <div class="modal-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
          </div>
          <h3 class="modal-title">ACS Device Control</h3>
        </div>
        <button class="modal-close-btn" @click="close">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body">
        
        <div class="modal-tabs">
          <button type="button" :class="['tab-btn', { active: activeTab === 'config' }]" @click="activeTab = 'config'">Overview & PPPoE</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'wifi' }]" @click="activeTab = 'wifi'">WiFi Configuration</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'hosts' }]" @click="activeTab = 'hosts'">Connected Devices</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'diag' }]" @click="activeTab = 'diag'">Diagnostics</button>
        </div>

        <!-- Config Tab -->
        <div v-if="activeTab === 'config'" class="tab-pane fade-in">
          <!-- Modem Info Grid -->
          <div class="info-grid">
            <div class="info-cell">
              <span class="info-label">Status</span>
              <div class="status-cell">
                <span :class="['status-glow', device?.status === 'online' ? 'online' : 'offline']"></span>
                <span class="info-value capitalize">{{ device?.status || 'unknown' }}</span>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Optical Power</span>
              <div class="info-value-row">
                <span :class="getRxPowerColor(device?.rx_power_dbm)" class="info-value-bold">{{ device?.rx_power_dbm ?? 'N/A' }}</span>
                <span v-if="device?.rx_power_dbm" class="info-unit">dBm</span>
              </div>
            </div>

            <div class="info-cell">
              <span class="info-label">Model / Vendor</span>
              <span class="info-value">{{ device?.model || 'Unknown' }}</span>
              <span class="info-sub">{{ device?.vendor }}</span>
            </div>

            <div class="info-cell">
              <span class="info-label">IP Address</span>
              <span class="info-value">{{ device?.ip_address || 'N/A' }}</span>
            </div>
          </div>

          <hr class="modal-divider" />

          <!-- PPPoE Config Form -->
          <form @submit.prevent="savePppoe" class="mb-4">
            <h4 class="form-section-title">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              PPPoE Configuration
            </h4>

            <div class="form-stack">
              <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <div class="input-icon-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <input v-model="pppoeForm.username" type="text" class="input-modern input-has-icon" placeholder="Username dari ISP">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Password PPPoE</label>
                <div class="input-icon-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  <input v-model="pppoeForm.password" :type="showPppoePassword ? 'text' : 'password'" class="input-modern input-has-icon input-has-action" placeholder="Biarkan kosong jika tidak diubah">
                  <button type="button" @click="showPppoePassword = !showPppoePassword" class="input-action-btn">
                    <svg v-if="!showPppoePassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
                </div>
              </div>

              <div class="form-group" style="display: flex; justify-content: flex-end;">
                <button type="submit" :disabled="isSavingPppoe" class="btn btn-primary btn-sm btn-icon-text">
                  <span v-if="isSavingPppoe" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                  <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>{{ isSavingPppoe ? 'Applying...' : 'Simpan PPPoE' }}</span>
                </button>
              </div>
            </div>

            <div v-if="pppoeError" class="alert-box alert-box--danger mt-2 mb-0">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span>{{ pppoeError }}</span>
            </div>
          </form>

          <hr class="modal-divider" />

            <!-- Global Footer Actions (Reboot / Reset) -->
            <div class="modal-footer">
              <div class="modal-footer-left">
                <button type="button" @click="reboot" :disabled="isRebooting || isResetting" class="btn btn-danger-outline btn-icon-text">
                  <svg v-if="!isRebooting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
                  <span v-if="isRebooting" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                  <span>{{ isRebooting ? 'Rebooting...' : 'Reboot' }}</span>
                </button>
                
                <button type="button" @click="factoryReset" :disabled="isRebooting || isResetting" class="btn btn-danger btn-icon-text">
                  <svg v-if="!isResetting" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 12v.01"/></svg>
                  <span v-if="isResetting" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                  <span>{{ isResetting ? 'Resetting...' : 'Factory Reset' }}</span>
                </button>
              </div>

              <div class="modal-footer-right">
                <button type="button" @click="close" class="btn btn-secondary">Tutup</button>
              </div>
            </div>
          </div>

        <!-- WiFi Configuration Tab -->
        <div v-if="activeTab === 'wifi'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-3">
            <h4 class="form-section-title mb-0">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
              WiFi Configuration
            </h4>
            <button type="button" @click="loadAdvancedWifi" :disabled="isLoadingWifi" class="btn btn-sm btn-secondary btn-icon-text">
              <span v-if="isLoadingWifi" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              <span>Reload</span>
            </button>
          </div>

          <div v-if="isLoadingWifi" class="loading-state text-center py-5">
            <div class="spinning mb-2" style="font-size: 28px;"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></div>
            <p class="text-muted">Memuat Konfigurasi WiFi TR-069...</p>
          </div>

          <div v-else-if="wifiError" class="alert-box alert-box--danger mb-3">
            <span>{{ wifiError }}</span>
          </div>

          <div v-else class="wifi-config-container">
            <!-- 2.4GHz Panel -->
            <div class="wifi-band-panel mb-4" v-if="radio2g">
              <div class="band-header flex-between align-center">
                <span>2.4Ghz</span>
                <div class="band-actions" style="margin-right: 12px;">
                  <div v-if="!radio2g.isEditing">
                    <button type="button" @click="editRadio(radio2g)" class="btn-icon-edit" style="color: var(--accent)" title="Edit Pengaturan 2.4Ghz"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit</button>
                  </div>
                  <div v-else class="flex align-center gap-2">
                    <button type="button" @click="saveRadio(1)" :disabled="radio2g.isSaving" class="btn-icon-edit color-good" title="Simpan">
                      <span v-if="radio2g.isSaving" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                      <span v-else><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Simpan</span>
                    </button>
                    <button type="button" @click="cancelEditRadio(radio2g)" :disabled="radio2g.isSaving" class="btn-icon-edit color-critical" title="Batal"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Batal</button>
                  </div>
                </div>
              </div>
              
              <!-- Radio Settings Table -->
              <table class="wifi-radio-table">
                <tbody>
                  <tr>
                    <td class="lbl">Wifi Enabled</td>
                    <td class="val">
                      <input type="checkbox" v-model="radio2g.wifi_enabled" :disabled="!radio2g.isEditing" class="checkbox-modern">
                    </td>
                    <td class="lbl">Network Mode</td>
                    <td class="val">
                      <select v-model="radio2g.network_mode" :disabled="!radio2g.isEditing" class="select-sm">
                        <option value="b,g,n">b,g,n</option>
                        <option value="b,g">b,g</option>
                        <option value="n">n only</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class="lbl">Possible Channel</td>
                    <td class="val text-muted text-xs">{{ radio2g.possible_channels }}</td>
                    <td class="lbl">Transmit Power Control</td>
                    <td class="val">
                      <select v-model="radio2g.transmit_power" :disabled="!radio2g.isEditing" class="select-sm">
                        <option value="100">100 %</option>
                        <option value="80">80 %</option>
                        <option value="50">50 %</option>
                        <option value="20">20 %</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class="lbl">Channel In Use</td>
                    <td class="val">
                      <select v-model="radio2g.channel" :disabled="!radio2g.isEditing" class="select-sm">
                        <option value="0">Auto</option>
                        <option v-for="ch in [1,2,3,4,5,6,7,8,9,10,11,12,13]" :key="ch" :value="String(ch)">{{ ch }}</option>
                      </select>
                    </td>
                    <td class="lbl">BandWidth</td>
                    <td class="val">
                      <select v-model="radio2g.bandwidth" :disabled="!radio2g.isEditing" class="select-sm">
                        <option value="20 MHz">20 MHz</option>
                        <option value="40 MHz">40 MHz</option>
                        <option value="Auto">Auto</option>
                      </select>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- SSID Table 2.4G -->
              <div class="table-responsive mt-3">
                <table class="ssid-table">
                  <thead>
                    <tr>
                      <th style="width: 8%;">SSID</th>
                      <th style="width: 7%;">Enabled</th>
                      <th style="width: 6%;">Hide</th>
                      <th style="width: 6%;">Status</th>
                      <th style="width: 22%;">Name</th>
                      <th style="width: 18%;">Security Type</th>
                      <th style="width: 10%;">Max Clients</th>
                      <th style="width: 17%;">Passkey</th>
                      <th style="width: 6%; text-align: center;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="ssid in ssids2g" :key="ssid.index" :class="{'row-active': ssid.enabled}">
                      <td class="bold">{{ 'SSID' + ssid.index }}</td>
                      <td><input type="checkbox" v-model="ssid.enabled" :disabled="!ssid.isEditing" class="checkbox-modern"></td>
                      <td><input type="checkbox" v-model="ssid.hide" :disabled="!ssid.isEditing" class="checkbox-modern"></td>
                      <td class="text-center">
                        <span class="status-icon" :class="ssid.enabled ? 'icon-up' : 'icon-down'" v-html="ssid.enabled ? '<svg width=\'1em\' height=\'1em\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'20 6 9 17 4 12\'/></svg>' : '<svg width=\'1em\' height=\'1em\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><line x1=\'18\' y1=\'6\' x2=\'6\' y2=\'18\'/><line x1=\'6\' y1=\'6\' x2=\'18\' y2=\'18\'/></svg>'"></span>
                      </td>
                      <td><input type="text" v-model="ssid.name" :disabled="!ssid.isEditing" class="input-table" placeholder="SSID Name"></td>
                      <td>
                        <select v-model="ssid.security_type" :disabled="!ssid.isEditing" class="select-table">
                          <option value="WPA2-PSK">WPA2-PSK</option>
                          <option value="WPA/WPA2-PSK">WPA/WPA2-PSK</option>
                          <option value="Open">Open (No Security)</option>
                        </select>
                      </td>
                      <td>
                        <select v-model="ssid.max_clients" :disabled="!ssid.isEditing" class="select-table">
                          <option :value="16">16</option>
                          <option :value="32">32</option>
                          <option :value="64">64</option>
                        </select>
                      </td>
                      <td><input type="password" v-model="ssid.passkey" :disabled="!ssid.isEditing" class="input-table" placeholder="••••••••"></td>
                      <td class="text-center">
                        <div v-if="!ssid.isEditing">
                          <button type="button" @click="editSsid(ssid)" class="btn-icon-edit" title="Edit SSID"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        </div>
                        <div v-else class="flex gap-1 justify-center align-center">
                          <button type="button" @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-icon-edit color-good" title="Simpan">
                            <span v-if="ssid.isSaving" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                            <span v-else><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                          </button>
                          <button type="button" @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-icon-edit color-critical" title="Batal"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- 5GHz Panel -->
            <div class="wifi-band-panel mb-4" v-if="radio5g">
              <div class="band-header flex-between align-center">
                <span>5Ghz</span>
                <div class="band-actions" style="margin-right: 12px;">
                  <div v-if="!radio5g.isEditing">
                    <button type="button" @click="editRadio(radio5g)" class="btn-icon-edit" style="color: var(--accent)" title="Edit Pengaturan 5Ghz"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit</button>
                  </div>
                  <div v-else class="flex align-center gap-2">
                    <button type="button" @click="saveRadio(5)" :disabled="radio5g.isSaving" class="btn-icon-edit color-good" title="Simpan">
                      <span v-if="radio5g.isSaving" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                      <span v-else><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Simpan</span>
                    </button>
                    <button type="button" @click="cancelEditRadio(radio5g)" :disabled="radio5g.isSaving" class="btn-icon-edit color-critical" title="Batal"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Batal</button>
                  </div>
                </div>
              </div>
              
              <!-- Radio Settings Table -->
              <table class="wifi-radio-table">
                <tbody>
                  <tr>
                    <td class="lbl">Wifi Enabled</td>
                    <td class="val">
                      <input type="checkbox" v-model="radio5g.wifi_enabled" :disabled="!radio5g.isEditing" class="checkbox-modern">
                    </td>
                    <td class="lbl">Network Mode</td>
                    <td class="val">
                      <select v-model="radio5g.network_mode" :disabled="!radio5g.isEditing" class="select-sm">
                        <option value="a,n,ac">a,n,ac</option>
                        <option value="n,ac">n,ac</option>
                        <option value="ac">ac only</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class="lbl">Possible Channel</td>
                    <td class="val text-muted text-xs">{{ radio5g.possible_channels }}</td>
                    <td class="lbl">Transmit Power Control</td>
                    <td class="val">
                      <select v-model="radio5g.transmit_power" :disabled="!radio5g.isEditing" class="select-sm">
                        <option value="100">100 %</option>
                        <option value="80">80 %</option>
                        <option value="50">50 %</option>
                        <option value="20">20 %</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class="lbl">Channel In Use</td>
                    <td class="val">
                      <select v-model="radio5g.channel" :disabled="!radio5g.isEditing" class="select-sm">
                        <option value="0">Auto</option>
                        <option v-for="ch in [36,40,44,48,52,56,60,64,149,153,157,161]" :key="ch" :value="String(ch)">{{ ch }}</option>
                      </select>
                    </td>
                    <td class="lbl">BandWidth</td>
                    <td class="val">
                      <select v-model="radio5g.bandwidth" :disabled="!radio5g.isEditing" class="select-sm">
                        <option value="20 MHz">20 MHz</option>
                        <option value="40 MHz">40 MHz</option>
                        <option value="80 MHz">80 MHz</option>
                        <option value="Auto">Auto</option>
                      </select>
                    </td>
                  </tr>
                </tbody>
              </table>

              <!-- SSID Table 5G -->
              <div class="table-responsive mt-3">
                <table class="ssid-table">
                  <thead>
                    <tr>
                      <th style="width: 8%;">SSID</th>
                      <th style="width: 7%;">Enabled</th>
                      <th style="width: 6%;">Hide</th>
                      <th style="width: 6%;">Status</th>
                      <th style="width: 22%;">Name</th>
                      <th style="width: 18%;">Security Type</th>
                      <th style="width: 10%;">Max Clients</th>
                      <th style="width: 17%;">Passkey</th>
                      <th style="width: 6%; text-align: center;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="ssid in ssids5g" :key="ssid.index" :class="{'row-active': ssid.enabled}">
                      <td class="bold">{{ 'SSID' + ssid.index }}</td>
                      <td><input type="checkbox" v-model="ssid.enabled" :disabled="!ssid.isEditing" class="checkbox-modern"></td>
                      <td><input type="checkbox" v-model="ssid.hide" :disabled="!ssid.isEditing" class="checkbox-modern"></td>
                      <td class="text-center">
                        <span class="status-icon" :class="ssid.enabled ? 'icon-up' : 'icon-down'" v-html="ssid.enabled ? '<svg width=\'1em\' height=\'1em\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><polyline points=\'20 6 9 17 4 12\'/></svg>' : '<svg width=\'1em\' height=\'1em\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'><line x1=\'18\' y1=\'6\' x2=\'6\' y2=\'18\'/><line x1=\'6\' y1=\'6\' x2=\'18\' y2=\'18\'/></svg>'"></span>
                      </td>
                      <td><input type="text" v-model="ssid.name" :disabled="!ssid.isEditing" class="input-table" placeholder="SSID Name"></td>
                      <td>
                        <select v-model="ssid.security_type" :disabled="!ssid.isEditing" class="select-table">
                          <option value="WPA2-PSK">WPA2-PSK</option>
                          <option value="WPA/WPA2-PSK">WPA/WPA2-PSK</option>
                          <option value="Open">Open (No Security)</option>
                        </select>
                      </td>
                      <td>
                        <select v-model="ssid.max_clients" :disabled="!ssid.isEditing" class="select-table">
                          <option :value="16">16</option>
                          <option :value="32">32</option>
                          <option :value="64">64</option>
                        </select>
                      </td>
                      <td><input type="password" v-model="ssid.passkey" :disabled="!ssid.isEditing" class="input-table" placeholder="••••••••"></td>
                      <td class="text-center">
                        <div v-if="!ssid.isEditing">
                          <button type="button" @click="editSsid(ssid)" class="btn-icon-edit" title="Edit SSID"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                        </div>
                        <div v-else class="flex gap-1 justify-center align-center">
                          <button type="button" @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-icon-edit color-good" title="Simpan">
                            <span v-if="ssid.isSaving" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                            <span v-else><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                          </button>
                          <button type="button" @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-icon-edit color-critical" title="Batal"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Hosts Tab -->
        <div v-if="activeTab === 'hosts'" class="tab-pane fade-in">
          <div class="hosts-header">
            <h4 class="form-section-title mb-0">Connected Devices <span class="badge badge-primary ml-2">{{ totalHosts }} Total</span></h4>
            <button type="button" @click="refreshHosts" :disabled="isRefreshingHosts" class="btn btn-primary-outline btn-sm btn-icon-text">
              <span v-if="isRefreshingHosts" class="spinning"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              <span>{{ isRefreshingHosts ? 'Refreshing...' : 'Refresh dari Modem' }}</span>
            </button>
          </div>

          <div v-if="isLoadingHosts" class="alert-box alert-box--warning mt-3">
            <span class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> Mengambil data klien...
          </div>
          <div v-else-if="hostsError" class="alert-box alert-box--danger mt-3">
            <span>{{ hostsError }}</span>
          </div>
          <div v-else-if="hosts.length === 0" class="empty-state mt-3">
            Belum ada data perangkat yang terhubung.
          </div>
          
          <div v-else class="hosts-split-view mt-3">
            <!-- WLAN Devices -->
            <div class="hosts-section mb-4">
              <h5 class="hosts-section-title"><span class="badge badge-primary mr-2">WiFi</span> Klien Nirkabel ({{ wifiHosts.length }})</h5>
              <div v-if="wifiHosts.length === 0" class="empty-state small">Tidak ada klien WiFi.</div>
              <div v-else class="table-responsive custom-scrollbar">
                <table class="table hosts-table">
                  <thead>
                    <tr>
                      <th>Hostname</th>
                      <th>IP Address</th>
                      <th>MAC Address</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="host in wifiHosts" :key="host.mac">
                      <td><strong>{{ host.hostname }}</strong></td>
                      <td><span class="badge badge-outline">{{ host.ip }}</span></td>
                      <td class="color-muted">{{ host.mac }}</td>
                      <td>
                        <span class="status-cell">
                          <span :class="['status-glow', host.active ? 'online' : 'offline']"></span>
                          {{ host.active ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- LAN Devices -->
            <div class="hosts-section">
              <h5 class="hosts-section-title"><span class="badge badge-secondary mr-2">LAN</span> Klien Kabel ({{ lanHosts.length }})</h5>
              <div v-if="lanHosts.length === 0" class="empty-state small">Tidak ada klien kabel.</div>
              <div v-else class="table-responsive custom-scrollbar">
                <table class="table hosts-table">
                  <thead>
                    <tr>
                      <th>Hostname</th>
                      <th>IP Address</th>
                      <th>MAC Address</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="host in lanHosts" :key="host.mac">
                      <td><strong>{{ host.hostname }}</strong></td>
                      <td><span class="badge badge-outline">{{ host.ip }}</span></td>
                      <td class="color-muted">{{ host.mac }}</td>
                      <td>
                        <span class="status-cell">
                          <span :class="['status-glow', host.active ? 'online' : 'offline']"></span>
                          {{ host.active ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Diagnostics Tab -->
        <div v-if="activeTab === 'diag'" class="tab-pane fade-in">
          <div class="hosts-header mb-4">
            <h4 class="form-section-title mb-0">Diagnostic Tools</h4>
          </div>

          <div class="hosts-split-view">
            <!-- PING SECTION -->
            <div class="hosts-section mb-4">
              <h5 class="hosts-section-title"><span class="badge badge-primary mr-2">1</span> IP Ping</h5>
              <div class="p-3">
                <div class="form-group mb-0">
                  <div class="ping-input-row">
                    <input v-model="pingForm.host" type="text" class="input-modern" placeholder="e.g. 8.8.8.8">
                    <button type="button" @click="triggerPing" :disabled="isPinging" class="btn btn-primary">
                      <span v-if="isPinging" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                      {{ isPinging ? 'Pinging...' : 'Ping' }}
                    </button>
                  </div>
                  <span class="form-hint mt-2">Tekan Ping untuk mengirim perintah ke modem. Proses memakan waktu sekitar 10 detik.</span>
                </div>

                <div v-if="pingError" class="alert-box alert-box--danger mt-3 mb-0">
                  <span>{{ pingError }}</span>
                </div>

                <div v-if="pingResult" class="ping-result-box mt-3">
                  <div class="ping-result-header">
                    <strong class="color-text-1">Hasil Ping (State: {{ pingResult.state }})</strong>
                    <button v-if="pingResult.state === 'Requested' || pingResult.state === 'None'" type="button" @click="fetchPingResult" class="btn btn-secondary btn-sm" :disabled="isFetchingPing">
                      <span v-if="isFetchingPing" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> Cek Hasil
                    </button>
                  </div>
                  
                  <div class="ping-stats" v-if="pingResult.state === 'Complete'">
                    <div class="ping-stat">
                      <span class="ping-label">Success</span>
                      <span class="ping-value color-good">{{ pingResult.success_count }}</span>
                    </div>
                    <div class="ping-stat">
                      <span class="ping-label">Failure</span>
                      <span class="ping-value color-critical">{{ pingResult.failure_count }}</span>
                    </div>
                    <div class="ping-stat">
                      <span class="ping-label">Avg. MS</span>
                      <span class="ping-value">{{ pingResult.avg_response_time }} ms</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- TRACEROUTE SECTION -->
            <div class="hosts-section mb-4">
              <h5 class="hosts-section-title"><span class="badge badge-primary mr-2">2</span> Traceroute</h5>
              <div class="p-3">
                <div class="form-group mb-0">
                  <div class="ping-input-row">
                    <input v-model="tracerouteForm.host" type="text" class="input-modern" placeholder="e.g. 8.8.8.8">
                    <button type="button" @click="triggerTraceroute" :disabled="isTracerouting" class="btn btn-primary">
                      <span v-if="isTracerouting" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                      {{ isTracerouting ? 'Tracerouting...' : 'Trace' }}
                    </button>
                  </div>
                </div>

                <div v-if="tracerouteError" class="alert-box alert-box--danger mt-3 mb-0">
                  <span>{{ tracerouteError }}</span>
                </div>

                <div v-if="tracerouteResult" class="ping-result-box mt-3">
                  <div class="ping-result-header">
                    <strong class="color-text-1">Hasil Traceroute (State: {{ tracerouteResult.state }})</strong>
                    <button v-if="tracerouteResult.state === 'Requested' || tracerouteResult.state === 'None'" type="button" @click="fetchTracerouteResult" class="btn btn-secondary btn-sm" :disabled="isFetchingTraceroute">
                      <span v-if="isFetchingTraceroute" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> Cek Hasil
                    </button>
                  </div>
                  
                  <div class="table-responsive mt-2" v-if="tracerouteResult.state === 'Complete'">
                    <table class="table hosts-table">
                      <thead>
                        <tr>
                          <th>Hop</th>
                          <th>Host</th>
                          <th>IP Address</th>
                          <th>RTT (ms)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(hop, index) in tracerouteResult.hops" :key="index">
                          <td>{{ index + 1 }}</td>
                          <td>{{ hop.host || '-' }}</td>
                          <td>{{ hop.host_address || '*' }}</td>
                          <td>{{ hop.rtt[0] || '*' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- SPEEDTEST SECTION -->
            <div class="hosts-section">
              <h5 class="hosts-section-title"><span class="badge badge-primary mr-2">3</span> Speedtest</h5>
              <div class="p-3">
                <div class="form-group mb-0">
                  <div class="band-selector mb-3" style="width: 100%; max-width: 300px;">
                    <label class="radio-label">
                      <input type="radio" v-model="speedtestForm.type" value="download"> Download
                    </label>
                    <label class="radio-label">
                      <input type="radio" v-model="speedtestForm.type" value="upload"> Upload
                    </label>
                  </div>
                  <label class="form-label">{{ speedtestForm.type === 'download' ? 'Download' : 'Upload' }} URL Target</label>
                  <div class="ping-input-row">
                    <input v-model="speedtestForm.url" type="text" class="input-modern" :placeholder="speedtestForm.type === 'download' ? 'http://speedtest.isp.net/100MB.bin' : 'http://speedtest.isp.net/upload.php'">
                    <button type="button" @click="triggerSpeedtest" :disabled="isSpeedtesting" class="btn btn-primary">
                      <span v-if="isSpeedtesting" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span>
                      {{ isSpeedtesting ? 'Testing...' : 'Test' }}
                    </button>
                  </div>
                  <span class="form-hint mt-2">Gunakan URL file langsung (bukan speedtest.net). Contoh: <code>http://speedtest.tele2.net/10MB.zip</code></span>
                </div>

                <div v-if="speedtestError" class="alert-box alert-box--danger mt-3 mb-0">
                  <span>{{ speedtestError }}</span>
                </div>

                <div v-if="speedtestResult" class="ping-result-box mt-3">
                  <div class="ping-result-header">
                    <strong class="color-text-1">Hasil Speedtest (State: {{ speedtestResult.state }})</strong>
                    <button v-if="speedtestResult.state === 'Requested' || speedtestResult.state === 'None'" type="button" @click="fetchSpeedtestResult" class="btn btn-secondary btn-sm" :disabled="isFetchingSpeedtest">
                      <span v-if="isFetchingSpeedtest" class="spinning mr-2"><svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></span> Cek Hasil
                    </button>
                  </div>
                  
                  <div class="ping-stats" v-if="speedtestResult.state === 'Complete'">
                    <div class="ping-stat">
                      <span class="ping-label">{{ speedtestForm.type === 'download' ? 'Total Received' : 'Total Sent' }}</span>
                      <span class="ping-value color-good" v-if="speedtestForm.type === 'download'">{{ (speedtestResult.total_bytes_received / 1024 / 1024).toFixed(2) }} MB</span>
                      <span class="ping-value color-good" v-else>{{ (speedtestResult.total_bytes_sent / 1024 / 1024).toFixed(2) }} MB</span>
                    </div>
                    <div class="ping-stat">
                      <span class="ping-label">BOM Time</span>
                      <span class="ping-value color-text-2">{{ speedtestResult.bom_time ? new Date(speedtestResult.bom_time).toLocaleTimeString() : '-' }}</span>
                    </div>
                    <div class="ping-stat">
                      <span class="ping-label">EOM Time</span>
                      <span class="ping-value color-text-2">{{ speedtestResult.eom_time ? new Date(speedtestResult.eom_time).toLocaleTimeString() : '-' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive, computed } from 'vue'
import { useAcsStore } from '../stores/acsStore'

const props = defineProps<{
  show: boolean
  device: any
}>()

const emit = defineEmits(['close', 'updated'])

const store = useAcsStore()

const showPassword = ref(false)
const showPppoePassword = ref(false)
const pppoeError = ref('')
const isSavingPppoe = ref(false)
const error = ref('')
const wifiError = ref('')
const isLoadingWifi = ref(false)
const advancedWifiList = ref<any[]>([])
const hostsError = ref('')
const isSaving = ref(false)
const isRebooting = ref(false)
const isResetting = ref(false)

const activeTab = ref('config')
const hosts = ref<any[]>([])
const isLoadingHosts = ref(false)
const isRefreshingHosts = ref(false)

const pingForm = reactive({ host: '8.8.8.8' })
const tracerouteForm = reactive({ host: '8.8.8.8' })
const speedtestForm = reactive({ url: 'http://speedtest.tele2.net/10MB.zip', type: 'download' as 'download' | 'upload' })

const isPinging = ref(false)
const isFetchingPing = ref(false)
const pingError = ref('')
const pingResult = ref<any>(null)

const isTracerouting = ref(false)
const isFetchingTraceroute = ref(false)
const tracerouteError = ref('')
const tracerouteResult = ref<any>(null)

const isSpeedtesting = ref(false)
const isFetchingSpeedtest = ref(false)
const speedtestError = ref('')
const speedtestResult = ref<any>(null)



const pppoeForm = reactive({
  username: '',
  password: ''
})

const totalHosts = computed(() => hosts.value.length)
const wifiHosts = computed(() => hosts.value.filter(h => String(h.type).includes('802.11')))
const lanHosts = computed(() => hosts.value.filter(h => !String(h.type).includes('802.11')))

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
    speedtestError.value = ''
    speedtestResult.value = null
    showPppoePassword.value = false
    activeTab.value = 'config'
    hosts.value = []
    advancedWifiList.value = []
    
    // Auto load data
    loadHosts()
    loadAdvancedWifi()

    // PPPoE Pre-fill
    pppoeForm.username = props.device.pppoe_username || ''
    pppoeForm.password = ''
  }
})

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
    // Re-load hosts after refresh task is sent
    // Note: TR-069 tasks are async. Data might not change instantly.
    setTimeout(loadHosts, 3000)
    emit('updated', 'Perintah refresh klien dikirim ke modem.')
  } catch (err: any) {
    hostsError.value = err.message
  } finally {
    isRefreshingHosts.value = false
  }
}

const getRxPowerColor = (power: number | null) => {
  if (power === null || power === undefined) return 'color-muted'
  if (power >= -25 && power <= -8) return 'color-good'
  if ((power < -25 && power >= -28) || (power > -8 && power <= -3)) return 'color-warning'
  return 'color-critical'
}

const close = () => {
  emit('close')
}

const radio2g = ref<any>(null)
const radio5g = ref<any>(null)
const ssids = ref<any[]>([])

const ssids2g = computed(() => ssids.value.filter((s: any) => s.index <= 4))
const ssids5g = computed(() => ssids.value.filter((s: any) => s.index >= 5))

const loadAdvancedWifi = async () => {
  if (!props.device) return
  isLoadingWifi.value = true
  wifiError.value = ''
  try {
    const res = await store.fetchAdvancedWifi(props.device.id)
    if (res.radio_2g) {
      radio2g.value = { ...res.radio_2g, isEditing: false, isSaving: false, _original: { ...res.radio_2g } }
    } else {
      radio2g.value = null
    }
    
    if (res.radio_5g) {
      radio5g.value = { ...res.radio_5g, isEditing: false, isSaving: false, _original: { ...res.radio_5g } }
    } else {
      radio5g.value = null
    }
    
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
  radio._original = { 
    wifi_enabled: radio.wifi_enabled, 
    channel: radio.channel, 
    network_mode: radio.network_mode, 
    transmit_power: radio.transmit_power, 
    bandwidth: radio.bandwidth 
  }
  radio.isEditing = true
}

const cancelEditRadio = (radio: any) => {
  Object.assign(radio, radio._original)
  radio.isEditing = false
}

const saveRadio = async (radioIndex: number) => {
  if (!props.device) return
  const targetRadio = radioIndex === 1 ? radio2g.value : radio5g.value
  if (!targetRadio) return
  
  wifiError.value = ''
  targetRadio.isSaving = true
  try {
    await store.updateRadioConfig(props.device.id, {
      radio_index: radioIndex,
      wifi_enabled: targetRadio.wifi_enabled,
      channel: targetRadio.channel,
      network_mode: targetRadio.network_mode,
      transmit_power: targetRadio.transmit_power,
      bandwidth: targetRadio.bandwidth
    })
    targetRadio.isEditing = false
    targetRadio._original = { 
      wifi_enabled: targetRadio.wifi_enabled, 
      channel: targetRadio.channel, 
      network_mode: targetRadio.network_mode, 
      transmit_power: targetRadio.transmit_power, 
      bandwidth: targetRadio.bandwidth 
    }
    emit('updated', `Pengaturan Radio ${radioIndex === 1 ? '2.4GHz' : '5GHz'} telah dikirim ke modem.`)
    alert(`Pengaturan Radio ${radioIndex === 1 ? '2.4GHz' : '5GHz'} telah dikirim ke modem.`)
  } catch (err: any) {
    wifiError.value = err.message
  } finally {
    targetRadio.isSaving = false
  }
}

const editSsid = (ssid: any) => {
  ssid._original = { 
    enabled: ssid.enabled,
    hide: ssid.hide,
    name: ssid.name,
    security_type: ssid.security_type,
    max_clients: ssid.max_clients,
    passkey: ssid.passkey
  }
  ssid.isEditing = true
}

const cancelEditSsid = (ssid: any) => {
  Object.assign(ssid, ssid._original)
  ssid.isEditing = false
}

const saveSsid = async (ssidObj: any) => {
  if (!props.device) return
  ssidObj.isSaving = true
  wifiError.value = ''
  
  try {
    await store.updateAdvancedWifi(props.device.id, {
      index: ssidObj.index,
      enabled: ssidObj.enabled,
      hide: ssidObj.hide,
      name: ssidObj.name,
      security_type: ssidObj.security_type,
      max_clients: ssidObj.max_clients,
      passkey: ssidObj.passkey
    })
    ssidObj.isEditing = false
    ssidObj._original = { 
      enabled: ssidObj.enabled,
      hide: ssidObj.hide,
      name: ssidObj.name,
      security_type: ssidObj.security_type,
      max_clients: ssidObj.max_clients,
      passkey: ssidObj.passkey
    }
    emit('updated', `Perintah simpan SSID ${ssidObj.index} (${ssidObj.name}) telah dikirim ke modem.`)
    alert(`Perintah simpan SSID ${ssidObj.index} (${ssidObj.name}) telah dikirim ke modem.`)
  } catch (err: any) {
    wifiError.value = err.message
  } finally {
    ssidObj.isSaving = false
  }
}

const savePppoe = async () => {
  if (!props.device) return
  
  if (!pppoeForm.username && !pppoeForm.password) {
    pppoeError.value = 'Minimal isi Username atau Password PPPoE'
    return
  }

  if (pppoeForm.username && pppoeForm.username.length < 3) {
    pppoeError.value = 'Username PPPoE terlalu pendek'
    return
  }

  pppoeError.value = ''
  isSavingPppoe.value = true
  try {
    await store.updatePppoeConfig(props.device.id, pppoeForm)
    emit('updated', 'PPPoE config update requested.')
    
    // Optional: Update local modal prop data directly
    if (pppoeForm.username) {
      props.device.pppoe_username = pppoeForm.username
    }
    
    // Reset password field after submit
    pppoeForm.password = ''
    alert('Perintah ubah PPPoE telah dikirim ke modem. Modem mungkin akan terputus sesaat.')
  } catch (err: any) {
    pppoeError.value = err.message
  } finally {
    isSavingPppoe.value = false
  }
}

const triggerPing = async () => {
  if (!props.device) return
  if (!pingForm.host) {
    pingError.value = 'Target host tidak boleh kosong.'
    return
  }
  
  isPinging.value = true
  pingError.value = ''
  pingResult.value = { state: 'Requested' }
  
  try {
    await store.triggerPing(props.device.id, pingForm.host)
    // Auto fetch result after 10 seconds
    setTimeout(() => {
      fetchPingResult()
    }, 10000)
  } catch (err: any) {
    pingError.value = err.message
  } finally {
    isPinging.value = false
  }
}

const fetchPingResult = async () => {
  if (!props.device) return
  isFetchingPing.value = true
  pingError.value = ''
  try {
    const res = await store.fetchPingResult(props.device.id)
    if (res) {
      pingResult.value = res
    }
  } catch (err: any) {
    pingError.value = err.message
  } finally {
    isFetchingPing.value = false
  }
}

const triggerTraceroute = async () => {
  if (!props.device) return
  if (!tracerouteForm.host) {
    tracerouteError.value = 'Target host tidak boleh kosong.'
    return
  }
  
  isTracerouting.value = true
  tracerouteError.value = ''
  tracerouteResult.value = { state: 'Requested' }
  
  try {
    await store.triggerTraceroute(props.device.id, tracerouteForm.host)
    setTimeout(() => {
      fetchTracerouteResult()
    }, 15000)
  } catch (err: any) {
    tracerouteError.value = err.message
  } finally {
    isTracerouting.value = false
  }
}

const fetchTracerouteResult = async () => {
  if (!props.device) return
  isFetchingTraceroute.value = true
  tracerouteError.value = ''
  try {
    const res = await store.fetchTracerouteResult(props.device.id)
    if (res) {
      tracerouteResult.value = res
    }
  } catch (err: any) {
    tracerouteError.value = err.message
  } finally {
    isFetchingTraceroute.value = false
  }
}

const triggerSpeedtest = async () => {
  if (!props.device) return
  if (!speedtestForm.url) {
    speedtestError.value = 'URL Target tidak boleh kosong.'
    return
  }
  
  isSpeedtesting.value = true
  speedtestError.value = ''
  speedtestResult.value = { state: 'Requested' }
  
  try {
    await store.triggerSpeedtest(props.device.id, speedtestForm.url, speedtestForm.type)
    setTimeout(() => {
      fetchSpeedtestResult()
    }, 15000)
  } catch (err: any) {
    speedtestError.value = err.message
  } finally {
    isSpeedtesting.value = false
  }
}

const fetchSpeedtestResult = async () => {
  if (!props.device) return
  isFetchingSpeedtest.value = true
  speedtestError.value = ''
  try {
    const res = await store.fetchSpeedtestResult(props.device.id, speedtestForm.type)
    if (res) {
      speedtestResult.value = res
    }
  } catch (err: any) {
    speedtestError.value = err.message
  } finally {
    isFetchingSpeedtest.value = false
  }
}

const reboot = async () => {
  if (!confirm('Yakin ingin mereboot modem ini? Koneksi pelanggan akan terputus sementara.')) {
    return
  }

  error.value = ''
  isRebooting.value = true
  try {
    await store.rebootDevice(props.device.id)
    emit('updated', 'Reboot command sent to modem.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isRebooting.value = false
  }
}

const factoryReset = async () => {
  const confirmText = prompt('BAHAYA: Modem akan di-reset ke setelan pabrik dan pelanggan akan kehilangan koneksi internet. Ketik "RESET" untuk melanjutkan:')
  if (confirmText !== 'RESET') {
    if (confirmText !== null) alert('Konfirmasi dibatalkan. Anda harus mengetik "RESET".')
    return
  }

  error.value = ''
  isResetting.value = true
  try {
    await store.factoryResetDevice(props.device.id)
    emit('updated', 'Factory Reset command sent to modem.')
    close()
  } catch (err: any) {
    error.value = err.message
  } finally {
    isResetting.value = false
  }
}
</script>

<style scoped>
/* ── Modal Overlay ──────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  padding: 16px;
}

/* ── Modal Container ────────────────────────────────────── */
.modal-container {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 16px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.04);
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
}

/* ── Header ─────────────────────────────────────────────── */
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}
.modal-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}
.modal-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--accent-dim);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--accent);
}
.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-1);
  margin: 0;
}
.modal-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-3);
  transition: all 0.2s;
  cursor: pointer;
  background: none;
  border: none;
}
.modal-close-btn:hover {
  background: var(--surface-2);
  color: var(--text-1);
}

/* ── Body ───────────────────────────────────────────────── */
.modal-body {
  padding: 24px;
}

/* ── Info Grid ──────────────────────────────────────────── */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}
.info-cell {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
}
.info-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-3);
  margin-bottom: 6px;
}
.info-value {
  color: var(--text-1);
  font-weight: 500;
  display: block;
}
.info-sub {
  display: block;
  font-size: 0.8rem;
  color: var(--text-3);
  margin-top: 2px;
}
.info-value-row {
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.info-value-bold {
  font-size: 1.25rem;
  font-weight: 700;
}
.info-unit {
  font-size: 0.8rem;
  color: var(--text-3);
}
.capitalize { text-transform: capitalize; }

/* ── Color helpers for rx_power ─────────────────────────── */
.color-good { color: #10b981; }
.color-warning { color: #f59e0b; }
.color-critical { color: #f43f5e; }
.color-muted { color: var(--text-3); }

/* ── Status in info grid ────────────────────────────────── */
.status-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}
.status-glow {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-glow.online { background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.6); }
.status-glow.offline { background: #f43f5e; box-shadow: 0 0 10px rgba(244, 63, 94, 0.6); }

/* ── Divider ────────────────────────────────────────────── */
.modal-divider {
  border: none;
  border-top: 1px solid var(--border);
  margin: 0 0 20px 0;
}

/* ── Form ───────────────────────────────────────────────── */
.form-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--accent);
  margin: 0 0 16px 0;
}
.form-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.form-group {
  display: flex;
  flex-direction: column;
}
.form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-2);
  margin-bottom: 6px;
}
.form-hint {
  font-size: 0.75rem;
  color: var(--text-3);
  margin-top: 4px;
}

/* ── Input ──────────────────────────────────────────────── */
.input-icon-wrap {
  position: relative;
}
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  pointer-events: none;
}
.input-modern {
  width: 100%;
  box-sizing: border-box;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 0.875rem;
  color: var(--text-1);
  transition: all 0.2s;
}
.input-modern:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-dim);
}
.input-modern::placeholder {
  color: var(--text-3);
}
.input-has-icon {
  padding-left: 42px;
}
.input-has-action {
  padding-right: 42px;
}
.input-action-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  cursor: pointer;
  background: none;
  border: none;
  padding: 4px;
  border-radius: 4px;
  transition: color 0.2s;
}
.input-action-btn:hover {
  color: var(--text-1);
}

/* ── Alert ──────────────────────────────────────────────── */
.alert-box {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 10px;
  margin-top: 14px;
  font-size: 0.85rem;
  font-weight: 500;
}
.alert-box svg { flex-shrink: 0; }
.alert-box--danger {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.2);
  color: #f43f5e;
}

/* ── Buttons ────────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border: 1px solid transparent;
}
.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.btn-primary {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
}
.btn-primary:hover:not(:disabled) {
  background: var(--accent-hover);
  box-shadow: 0 0 20px var(--accent-glow);
}
.btn-secondary {
  background: var(--surface-2);
  color: var(--text-2);
  border-color: var(--border);
}
.btn-secondary:hover:not(:disabled) {
  background: var(--surface-3);
  color: var(--text-1);
}
.btn-danger-outline {
  background: rgba(245, 158, 11, 0.06);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.2);
}
.btn-danger-outline:hover:not(:disabled) {
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}
.btn-danger {
  background: #f43f5e;
  color: #fff;
  border: 1px solid #f43f5e;
}
.btn-danger:hover:not(:disabled) {
  background: #e11d48;
  box-shadow: 0 0 20px rgba(244, 63, 94, 0.4);
}
.btn-icon-text svg { flex-shrink: 0; }

/* ── Tabs ───────────────────────────────────────────────── */
.modal-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 12px;
}
.tab-btn {
  background: none;
  border: none;
  color: var(--text-3);
  font-size: 0.95rem;
  font-weight: 500;
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn:hover {
  background: var(--surface-2);
  color: var(--text-2);
}
.tab-btn.active {
  background: var(--surface-3);
  color: var(--text-1);
}
.tab-pane {
  animation: fadeIn 0.3s ease;
}

/* ── Hosts Table ────────────────────────────────────────── */
.hosts-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.hosts-section {
  background: var(--surface-2);
  border-radius: 12px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.hosts-section-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-1);
  padding: 12px 16px;
  margin: 0;
  border-bottom: 1px solid var(--border);
  background: rgba(255, 255, 255, 0.02);
  display: flex;
  align-items: center;
}
.mb-0 { margin-bottom: 0 !important; }
.mb-4 { margin-bottom: 24px !important; }
.mt-3 { margin-top: 16px; }
.mr-2 { margin-right: 8px; }
.ml-2 { margin-left: 8px; }
.empty-state.small {
  padding: 24px;
  font-size: 0.9rem;
}
.hosts-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.hosts-table th {
  text-align: left;
  padding: 12px;
  color: var(--text-3);
  border-bottom: 1px solid var(--border);
  font-weight: 500;
}
.hosts-table td {
  padding: 12px;
  border-bottom: 1px solid rgba(255,255,255,0.02);
  color: var(--text-2);
}
.hosts-table tr:hover td {
  background: var(--surface-2);
}

/* ── Form Extensions ────────────────────────────────────── */
.band-selector {
  display: flex;
  gap: 16px;
  margin-top: 8px;
}
.radio-label {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-2);
  cursor: pointer;
  font-size: 0.95rem;
}
.ping-input-row {
  display: flex;
  gap: 8px;
}
.ping-input-row input {
  flex: 1;
}

/* ── Ping Diagnostics Box ───────────────────────────────── */
.ping-result-box {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
}
.ping-result-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.ping-stats {
  display: flex;
  gap: 24px;
  background: var(--surface-3);
  padding: 12px;
  border-radius: 6px;
}
.ping-stat {
  display: flex;
  flex-direction: column;
}
.ping-label {
  font-size: 0.8rem;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.ping-value {
  font-size: 1.1rem;
  font-weight: 600;
  margin-top: 4px;
}

/* ── Footer ─────────────────────────────────────────────── */
.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}
.modal-footer-left {
  display: flex;
  gap: 10px;
}
.modal-footer-right {
  display: flex;
  gap: 10px;
}

/* ── Animations ─────────────────────────────────────────── */
.fade-in-up {
  animation: fadeInUp 0.3s ease-out forwards;
}
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(16px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Mobile ─────────────────────────────────────────────── */
@media (max-width: 640px) {
  .modal-container {
    max-height: 95vh;
  }
  .info-grid {
    grid-template-columns: 1fr;
  }
  .modal-footer {
    flex-direction: column;
    gap: 12px;
  }
  .modal-footer-right {
    width: 100%;
    justify-content: flex-end;
  }
}
/* ── WiFi Config Tab Styling ───────────────────────────── */
.wifi-band-panel {
  background: var(--surface-1, #121624);
  border: 1px solid var(--border, rgba(255,255,255,0.08));
  border-radius: 12px;
  overflow: hidden;
}
.band-header {
  background: var(--surface-2, #1a2035);
  padding: 10px 16px;
  font-weight: 700;
  font-size: 0.95rem;
  color: var(--text-1, #f8fafc);
  text-align: center;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border, rgba(255,255,255,0.08));
}
.wifi-radio-table {
  width: 100%;
  border-collapse: collapse;
}
.wifi-radio-table td {
  padding: 8px 12px;
  border: 1px solid var(--border, rgba(255,255,255,0.06));
  font-size: 0.85rem;
}
.wifi-radio-table td.lbl {
  font-weight: 600;
  color: var(--text-2, #94a3b8);
  width: 20%;
  background: rgba(255,255,255,0.02);
}
.wifi-radio-table td.val {
  width: 30%;
}
.ssid-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.ssid-table th {
  background: var(--surface-2, #1a2035);
  color: var(--text-2, #94a3b8);
  padding: 10px;
  text-align: left;
  font-size: 0.78rem;
  font-weight: 600;
  border: 1px solid var(--border, rgba(255,255,255,0.08));
}
.ssid-table td {
  padding: 8px;
  border: 1px solid var(--border, rgba(255,255,255,0.06));
  vertical-align: middle;
}
.ssid-table tr.row-active {
  background: rgba(16, 185, 129, 0.08);
}
.ssid-table tr.row-active td.bold {
  color: #10b981;
  font-weight: 700;
}
.checkbox-modern {
  width: 18px;
  height: 18px;
  accent-color: #10b981;
  cursor: pointer;
}
.btn-icon-edit {
  background: var(--surface-2, #1e293b);
  border: 1px solid var(--border, rgba(255,255,255,0.1));
  border-radius: 6px;
  padding: 4px 8px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: background 0.2s;
}
.btn-icon-edit:hover {
  background: var(--accent, #3b82f6);
  color: #fff;
}
.select-sm, .select-table {
  background: var(--surface-2, #1e293b);
  color: var(--text-1, #f8fafc);
  border: 1px solid var(--border, rgba(255,255,255,0.12));
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 0.8rem;
  width: 100%;
}
.input-table {
  background: var(--surface-2, #1e293b);
  color: var(--text-1, #f8fafc);
  border: 1px solid var(--border, rgba(255,255,255,0.12));
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 0.8rem;
  width: 100%;
}
.status-icon {
  display: inline-block;
  width: 20px;
  height: 20px;
  line-height: 20px;
  border-radius: 50%;
  font-size: 11px;
  font-weight: bold;
}
.icon-up {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
}
.icon-down {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
}
</style>
