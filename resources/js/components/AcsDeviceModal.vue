<template>
  <div v-if="show" class="drawer-overlay" @click.self="close">
    <div class="drawer-container slide-in-right">
      <!-- Header -->
      <div class="drawer-header">
        <div class="drawer-header-left">
          <div class="drawer-icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
          </div>
          <h3 class="drawer-title">ACS Device Control</h3>
        </div>
        <button class="drawer-close-btn" @click="close">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <!-- Body -->
      <div class="drawer-body">
        
        <div class="drawer-tabs">
          <button type="button" :class="['tab-btn', { active: activeTab === 'config' }]" @click="activeTab = 'config'">Overview</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'wifi' }]" @click="activeTab = 'wifi'">WiFi Config</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'hosts' }]" @click="activeTab = 'hosts'">Devices</button>
          <button type="button" :class="['tab-btn', { active: activeTab === 'diag' }]" @click="activeTab = 'diag'">Diagnostics</button>
        </div>

        <!-- TAB: OVERVIEW -->
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

          <!-- PPPoE Form -->
          <div class="glass-card mb-4 mt-4">
            <h4 class="card-title">PPPoE Configuration</h4>
            <form @submit.prevent="savePppoe" class="form-stack mt-3">
              <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <input v-model="pppoeForm.username" type="text" class="input-glass" placeholder="Username dari ISP">
              </div>
              <div class="form-group">
                <label class="form-label">Password PPPoE</label>
                <div class="input-action-wrap">
                  <input v-model="pppoeForm.password" :type="showPppoePassword ? 'text' : 'password'" class="input-glass" placeholder="Biarkan kosong jika tidak diubah">
                  <button type="button" @click="showPppoePassword = !showPppoePassword" class="input-action-btn">
                    <svg v-if="!showPppoePassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
                </div>
              </div>
              <div class="flex-end mt-2">
                <button type="submit" :disabled="isSavingPppoe" class="btn btn-primary btn-glass">
                  <span v-if="isSavingPppoe" class="spinning mr-2">⟳</span>
                  {{ isSavingPppoe ? 'Applying...' : 'Save PPPoE' }}
                </button>
              </div>
              <div v-if="pppoeError" class="alert-box alert-box--danger mt-2">{{ pppoeError }}</div>
            </form>
          </div>
          
          <!-- Actions -->
          <div class="glass-card action-card">
            <h4 class="card-title">Device Actions</h4>
            <div class="flex gap-2 mt-3">
              <button @click="reboot" :disabled="isRebooting || isResetting" class="btn btn-danger-outline flex-1">
                <span v-if="isRebooting" class="spinning mr-2">⟳</span> Reboot
              </button>
              <button @click="factoryReset" :disabled="isRebooting || isResetting" class="btn btn-danger flex-1">
                <span v-if="isResetting" class="spinning mr-2">⟳</span> Factory Reset
              </button>
            </div>
          </div>
        </div>

        <!-- TAB: WIFI CONFIG -->
        <div v-if="activeTab === 'wifi'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <h4 class="drawer-section-title mb-0">Wireless Settings</h4>
            <button @click="loadAdvancedWifi" :disabled="isLoadingWifi" class="btn btn-sm btn-glass">
              <span v-if="isLoadingWifi" class="spinning mr-2">⟳</span> Reload
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
                  <span class="band-badge">2.4Ghz</span>
                  <span class="band-status" :class="radio2g.wifi_enabled ? 'text-good' : 'text-muted'">{{ radio2g.wifi_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="band-actions">
                  <button v-if="!radio2g.isEditing" @click="editRadio(radio2g)" class="btn-sm btn-glass-edit">Edit Radio</button>
                  <div v-else class="flex gap-2">
                    <button @click="saveRadio(1)" :disabled="radio2g.isSaving" class="btn-sm btn-primary">Save</button>
                    <button @click="cancelEditRadio(radio2g)" :disabled="radio2g.isSaving" class="btn-sm btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>
              
              <div class="band-card-body grid-2-col" v-if="radio2g.isEditing || !radio2g.isEditing">
                <!-- Radio settings grid -->
                <div class="form-group">
                  <label class="form-label">WiFi Toggle</label>
                  <label class="switch-ios">
                    <input type="checkbox" v-model="radio2g.wifi_enabled" :disabled="!radio2g.isEditing">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="form-group">
                  <label class="form-label">Mode</label>
                  <select v-model="radio2g.network_mode" :disabled="!radio2g.isEditing" class="input-glass">
                    <option v-if="!['b,g,n', 'g,n', 'b,g', 'n', 'g', 'b'].includes(radio2g.network_mode)" :value="radio2g.network_mode">{{ radio2g.network_mode }}</option>
                    <option value="b,g,n">b,g,n</option>
                    <option value="g,n">g,n</option>
                    <option value="n">n</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Channel</label>
                  <select v-model="radio2g.channel" :disabled="!radio2g.isEditing" class="input-glass">
                    <option value="0">Auto</option>
                    <option v-for="ch in 13" :key="ch" :value="String(ch)">{{ ch }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Bandwidth</label>
                  <select v-model="radio2g.bandwidth" :disabled="!radio2g.isEditing" class="input-glass">
                    <option v-if="!['20MHz', '40MHz', 'Auto'].includes(radio2g.bandwidth)" :value="radio2g.bandwidth">{{ radio2g.bandwidth }}</option>
                    <option value="20MHz">20MHz</option>
                    <option value="40MHz">40MHz</option>
                    <option value="Auto">Auto</option>
                  </select>
                </div>
              </div>

              <!-- SSIDs for 2.4Ghz -->
              <div class="ssid-cards-container">
                <div v-for="ssid in ssids2g" :key="ssid.index" class="ssid-card" :class="{'ssid-disabled': !ssid.enabled}">
                  <div class="ssid-card-header flex-between">
                    <div class="ssid-name-wrap">
                      <span class="ssid-index">SSID {{ ssid.index }}</span>
                      <h5 v-if="!ssid.isEditing" class="ssid-title">{{ ssid.name }}</h5>
                      <input v-else v-model="ssid.name" type="text" class="input-glass input-sm" placeholder="SSID Name">
                    </div>
                    <div class="ssid-toggles flex gap-3 align-center" v-if="ssid.isEditing">
                       <label class="switch-ios sm"><input type="checkbox" v-model="ssid.enabled"><span class="slider round"></span></label>
                       <span class="toggle-lbl">ON</span>
                    </div>
                    <div v-else>
                      <span v-if="ssid.enabled" class="badge-glass good">Active</span>
                      <span v-else class="badge-glass muted">Disabled</span>
                    </div>
                  </div>
                  
                  <div class="ssid-card-body grid-2-col mt-3">
                    <div class="form-group">
                      <label class="form-label">Security</label>
                      <select v-if="ssid.isEditing" v-model="ssid.security_type" class="input-glass input-sm">
                        <option value="WPAand11i">WPA/WPA2-PSK</option>
                        <option value="11i">WPA2-PSK</option>
                        <option value="WPA">WPA-PSK</option>
                        <option value="None">Open (None)</option>
                      </select>
                      <div v-else class="val-text">{{ ssid.security_type }}</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Password</label>
                      <input v-if="ssid.isEditing" v-model="ssid.passkey" type="password" class="input-glass input-sm" placeholder="••••••••">
                      <div v-else class="val-text">••••••••</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Max Clients</label>
                      <select v-if="ssid.isEditing" v-model="ssid.max_clients" class="input-glass input-sm">
                        <option value="0">0 (No Limit)</option>
                        <option v-for="n in 32" :key="n" :value="n">{{ n }}</option>
                      </select>
                      <div v-else class="val-text">{{ ssid.max_clients == 0 ? 'No Limit' : ssid.max_clients }}</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Visibility</label>
                      <div v-if="ssid.isEditing" class="flex align-center gap-2 h-full">
                        <input type="checkbox" v-model="ssid.hide" class="checkbox-glass"> Hide SSID
                      </div>
                      <div v-else class="val-text">{{ ssid.hide ? 'Hidden' : 'Visible' }}</div>
                    </div>
                  </div>

                  <div class="ssid-card-footer mt-3 flex-end gap-2">
                    <button v-if="!ssid.isEditing" @click="editSsid(ssid)" class="btn-sm btn-glass-edit">Edit SSID</button>
                    <template v-else>
                      <button @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-secondary">Cancel</button>
                      <button @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-primary">
                        <span v-if="ssid.isSaving" class="spinning mr-1">⟳</span> Save
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
                  <span class="band-badge">5Ghz</span>
                  <span class="band-status" :class="radio5g.wifi_enabled ? 'text-good' : 'text-muted'">{{ radio5g.wifi_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <div class="band-actions">
                  <button v-if="!radio5g.isEditing" @click="editRadio(radio5g)" class="btn-sm btn-glass-edit">Edit Radio</button>
                  <div v-else class="flex gap-2">
                    <button @click="saveRadio(5)" :disabled="radio5g.isSaving" class="btn-sm btn-primary">Save</button>
                    <button @click="cancelEditRadio(radio5g)" :disabled="radio5g.isSaving" class="btn-sm btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>
              
              <div class="band-card-body grid-2-col" v-if="radio5g.isEditing || !radio5g.isEditing">
                <!-- Radio settings grid -->
                <div class="form-group">
                  <label class="form-label">WiFi Toggle</label>
                  <label class="switch-ios">
                    <input type="checkbox" v-model="radio5g.wifi_enabled" :disabled="!radio5g.isEditing">
                    <span class="slider round"></span>
                  </label>
                </div>
                <div class="form-group">
                  <label class="form-label">Mode</label>
                  <select v-model="radio5g.network_mode" :disabled="!radio5g.isEditing" class="input-glass">
                    <option value="a,n,ac">a,n,ac</option>
                    <option value="n,ac">n,ac</option>
                    <option value="ac">ac</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Channel</label>
                  <select v-model="radio5g.channel" :disabled="!radio5g.isEditing" class="input-glass">
                    <option value="0">Auto</option>
                    <option v-for="ch in [36,40,44,48,52,56,60,64,149,153,157,161]" :key="ch" :value="String(ch)">{{ ch }}</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Bandwidth</label>
                  <select v-model="radio5g.bandwidth" :disabled="!radio5g.isEditing" class="input-glass">
                    <option value="20MHz">20MHz</option>
                    <option value="40MHz">40MHz</option>
                    <option value="80MHz">80MHz</option>
                    <option value="Auto">Auto</option>
                  </select>
                </div>
              </div>

              <!-- SSIDs for 5Ghz -->
              <div class="ssid-cards-container">
                <div v-for="ssid in ssids5g" :key="ssid.index" class="ssid-card" :class="{'ssid-disabled': !ssid.enabled}">
                  <div class="ssid-card-header flex-between">
                    <div class="ssid-name-wrap">
                      <span class="ssid-index">SSID {{ ssid.index }}</span>
                      <h5 v-if="!ssid.isEditing" class="ssid-title">{{ ssid.name }}</h5>
                      <input v-else v-model="ssid.name" type="text" class="input-glass input-sm" placeholder="SSID Name">
                    </div>
                    <div class="ssid-toggles flex gap-3 align-center" v-if="ssid.isEditing">
                       <label class="switch-ios sm"><input type="checkbox" v-model="ssid.enabled"><span class="slider round"></span></label>
                       <span class="toggle-lbl">ON</span>
                    </div>
                    <div v-else>
                      <span v-if="ssid.enabled" class="badge-glass good">Active</span>
                      <span v-else class="badge-glass muted">Disabled</span>
                    </div>
                  </div>
                  
                  <div class="ssid-card-body grid-2-col mt-3">
                    <div class="form-group">
                      <label class="form-label">Security</label>
                      <select v-if="ssid.isEditing" v-model="ssid.security_type" class="input-glass input-sm">
                        <option value="WPAand11i">WPA/WPA2-PSK</option>
                        <option value="11i">WPA2-PSK</option>
                        <option value="WPA">WPA-PSK</option>
                        <option value="None">Open (None)</option>
                      </select>
                      <div v-else class="val-text">{{ ssid.security_type }}</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Password</label>
                      <input v-if="ssid.isEditing" v-model="ssid.passkey" type="password" class="input-glass input-sm" placeholder="••••••••">
                      <div v-else class="val-text">••••••••</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Max Clients</label>
                      <select v-if="ssid.isEditing" v-model="ssid.max_clients" class="input-glass input-sm">
                        <option value="0">0 (No Limit)</option>
                        <option v-for="n in 32" :key="n" :value="n">{{ n }}</option>
                      </select>
                      <div v-else class="val-text">{{ ssid.max_clients == 0 ? 'No Limit' : ssid.max_clients }}</div>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Visibility</label>
                      <div v-if="ssid.isEditing" class="flex align-center gap-2 h-full">
                        <input type="checkbox" v-model="ssid.hide" class="checkbox-glass"> Hide SSID
                      </div>
                      <div v-else class="val-text">{{ ssid.hide ? 'Hidden' : 'Visible' }}</div>
                    </div>
                  </div>

                  <div class="ssid-card-footer mt-3 flex-end gap-2">
                    <button v-if="!ssid.isEditing" @click="editSsid(ssid)" class="btn-sm btn-glass-edit">Edit SSID</button>
                    <template v-else>
                      <button @click="cancelEditSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-secondary">Cancel</button>
                      <button @click="saveSsid(ssid)" :disabled="ssid.isSaving" class="btn-sm btn-primary">
                        <span v-if="ssid.isSaving" class="spinning mr-1">⟳</span> Save
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- TAB: DEVICES (HOSTS) -->
        <div v-if="activeTab === 'hosts'" class="tab-pane fade-in">
          <div class="flex-between align-center mb-4">
            <h4 class="drawer-section-title mb-0">Connected Clients <span class="badge-glass ml-2">{{ totalHosts }} Total</span></h4>
            <button @click="refreshHosts" :disabled="isRefreshingHosts" class="btn btn-sm btn-glass">
              <span v-if="isRefreshingHosts" class="spinning mr-2">⟳</span> Refresh
            </button>
          </div>

          <div v-if="isLoadingHosts" class="skeleton-loader-container">
             <div class="skeleton-card mini" v-for="i in 3" :key="i"></div>
          </div>
          <div v-else-if="hostsError" class="alert-box alert-box--danger">{{ hostsError }}</div>
          <div v-else-if="hosts.length === 0" class="empty-glass-state">No devices connected.</div>
          
          <div v-else class="hosts-list">
            <!-- WLAN -->
            <h5 class="hosts-category-title mt-2 mb-3">Wireless ({{ wifiHosts.length }})</h5>
            <div class="host-card" v-for="host in wifiHosts" :key="host.mac">
              <div class="host-info">
                <div class="host-name">{{ host.hostname || 'Unknown Device' }}</div>
                <div class="host-mac">{{ host.mac }}</div>
              </div>
              <div class="host-status">
                <span class="badge-glass">{{ host.ip }}</span>
                <span :class="['status-dot', host.active ? 'online' : 'offline']" title="Status"></span>
              </div>
            </div>

            <!-- LAN -->
            <h5 class="hosts-category-title mt-4 mb-3">Wired LAN ({{ lanHosts.length }})</h5>
            <div class="host-card" v-for="host in lanHosts" :key="host.mac">
              <div class="host-info">
                <div class="host-name">{{ host.hostname || 'Unknown Device' }}</div>
                <div class="host-mac">{{ host.mac }}</div>
              </div>
              <div class="host-status">
                <span class="badge-glass">{{ host.ip }}</span>
                <span :class="['status-dot', host.active ? 'online' : 'offline']" title="Status"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: DIAGNOSTICS -->
        <div v-if="activeTab === 'diag'" class="tab-pane fade-in">
          <div class="diag-container">
            
            <!-- Ping -->
            <div class="glass-card mb-4">
              <h4 class="card-title">IP Ping</h4>
              <div class="flex gap-2 mt-3">
                <input v-model="pingForm.host" type="text" class="input-glass flex-1" placeholder="Host/IP e.g. 8.8.8.8">
                <button @click="triggerPing" :disabled="isPinging" class="btn btn-primary">
                  <span v-if="isPinging" class="spinning mr-2">⟳</span> Ping
                </button>
              </div>
              <div v-if="pingError" class="alert-box alert-box--danger mt-3">{{ pingError }}</div>
              
              <div v-if="pingResult" class="diag-result-box mt-3">
                <div class="flex-between align-center mb-2">
                  <span class="text-sm text-muted">Status: {{ pingResult.state }}</span>
                  <button v-if="['Requested', 'None'].includes(pingResult.state)" @click="fetchPingResult" class="btn-sm btn-glass-edit" :disabled="isFetchingPing">Check Result</button>
                </div>
                <div v-if="pingResult.state === 'Complete'" class="grid-3-col mt-2">
                  <div class="stat-box"><span class="stat-lbl">Success</span><span class="stat-val text-good">{{ pingResult.success_count }}</span></div>
                  <div class="stat-box"><span class="stat-lbl">Failed</span><span class="stat-val text-critical">{{ pingResult.failure_count }}</span></div>
                  <div class="stat-box"><span class="stat-lbl">Avg MS</span><span class="stat-val">{{ pingResult.avg_response_time }}</span></div>
                </div>
              </div>
            </div>

            <!-- Traceroute -->
            <div class="glass-card mb-4">
              <h4 class="card-title">Traceroute</h4>
              <div class="flex gap-2 mt-3">
                <input v-model="tracerouteForm.host" type="text" class="input-glass flex-1" placeholder="Host/IP e.g. 8.8.8.8">
                <button @click="triggerTraceroute" :disabled="isTracerouting" class="btn btn-primary">
                  <span v-if="isTracerouting" class="spinning mr-2">⟳</span> Trace
                </button>
              </div>
              <div v-if="tracerouteError" class="alert-box alert-box--danger mt-3">{{ tracerouteError }}</div>
              
              <div v-if="tracerouteResult" class="diag-result-box mt-3">
                <div class="flex-between align-center mb-2">
                  <span class="text-sm text-muted">Status: {{ tracerouteResult.state }}</span>
                  <button v-if="['Requested', 'None'].includes(tracerouteResult.state)" @click="fetchTracerouteResult" class="btn-sm btn-glass-edit" :disabled="isFetchingTraceroute">Check Result</button>
                </div>
                <div v-if="tracerouteResult.state === 'Complete'" class="timeline-container mt-3">
                  <div v-for="(hop, index) in tracerouteResult.hops" :key="index" class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                      <span class="hop-num">{{ index + 1 }}</span>
                      <span class="hop-ip">{{ hop.host_address || '*' }}</span>
                      <span class="hop-ms">{{ hop.rtt[0] || '*' }} ms</span>
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
import { useToastStore } from '../stores/toastStore';

const toastStore = useToastStore()

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
  const band = radioIndex === 1 ? '2.4GHz' : '5GHz'
  if (!targetRadio) return
  
  wifiError.value = ''
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
    targetRadio._original = { 
      wifi_enabled: targetRadio.wifi_enabled,
      channel: targetRadio.channel,
      network_mode: targetRadio.network_mode, 
      transmit_power: targetRadio.transmit_power,
      bandwidth: targetRadio.bandwidth
    }
    const successMsg = res.message || `Perintah simpan pengaturan Radio ${band} telah dikirim ke modem.`;
    emit('updated', successMsg)
    
    if (res.status === 'warning' && res.execution_type === 'queued') {
      toastStore.info(successMsg) // Biru
    } else {
      toastStore.success(successMsg) // Hijau
    }
  } catch (err: any) {
    wifiError.value = err.message
    toastStore.error(err.message || 'Gagal menyimpan pengaturan radio')
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
    ssidObj._original = { 
      enabled: ssidObj.enabled,
      hide: ssidObj.hide,
      name: ssidObj.name,
      security_type: ssidObj.security_type,
      max_clients: ssidObj.max_clients,
      passkey: ssidObj.passkey
    }
    const successMsg = res.message || `Perintah simpan SSID ${ssidObj.index} (${ssidObj.name}) telah dikirim ke modem.`;
    emit('updated', successMsg)
    
    if (res.status === 'warning' && res.execution_type === 'queued') {
      toastStore.info(successMsg)
    } else {
      toastStore.success(successMsg)
    }
  } catch (err: any) {
    wifiError.value = err.message
    toastStore.error(err.message || 'Gagal menyimpan SSID')
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
    const res = await store.updatePppoeConfig(props.device.id, pppoeForm)
    const successMsg = res.message || 'Perintah ubah PPPoE telah dikirim ke modem. Modem mungkin akan terputus sesaat.'
    emit('updated', successMsg)
    
    if (res.status === 'warning' && res.execution_type === 'queued') {
      toastStore.info(successMsg)
    } else {
      toastStore.success(successMsg)
    }
    
    // Optional: Update local modal prop data directly
    if (pppoeForm.username) {
      props.device.pppoe_username = pppoeForm.username
    }
    
    // Reset password field after submit
    pppoeForm.password = ''
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
/* ── Overlay & Slide-over Drawer ────────────────────────── */
.drawer-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(8px);
  display: flex;
  justify-content: flex-end;
}
.drawer-container {
  width: 100%;
  max-width: 600px; /* Lebar panel slide-over */
  height: 100%;
  background: rgba(18, 22, 36, 0.85);
  backdrop-filter: blur(20px);
  border-left: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: -10px 0 40px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
}
.slide-in-right {
  animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes slideInRight {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

/* ── Header ─────────────────────────────────────────────── */
.drawer-header {
  padding: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.drawer-header-left { display: flex; align-items: center; gap: 14px; }
.drawer-icon-wrap {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(59,130,246,0.2) 0%, rgba(59,130,246,0.05) 100%);
  border: 1px solid rgba(59,130,246,0.2);
  display: flex; align-items: center; justify-content: center;
  color: #3b82f6;
}
.drawer-title { font-size: 1.25rem; font-weight: 700; color: #f8fafc; margin: 0; }
.drawer-close-btn {
  background: rgba(255,255,255,0.05);
  border: none;
  width: 36px; height: 36px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #94a3b8; cursor: pointer; transition: 0.2s;
}
.drawer-close-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

/* ── Body & Tabs ────────────────────────────────────────── */
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}
.drawer-tabs {
  display: flex; gap: 8px; margin-bottom: 24px;
  background: rgba(0,0,0,0.2);
  padding: 6px; border-radius: 12px;
}
.tab-btn {
  flex: 1;
  background: transparent; border: none;
  padding: 8px 12px; border-radius: 8px;
  color: #94a3b8; font-size: 0.9rem; font-weight: 600;
  cursor: pointer; transition: 0.3s;
}
.tab-btn:hover { color: #e2e8f0; }
.tab-btn.active { background: rgba(255,255,255,0.1); color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.tab-pane { animation: fadeIn 0.3s ease; }

/* ── Generic Utils ──────────────────────────────────────── */
.fade-in { animation: fadeIn 0.3s ease forwards; }
@keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
.flex { display: flex; }
.flex-1 { flex: 1; }
.flex-between { display: flex; justify-content: space-between; }
.flex-end { display: flex; justify-content: flex-end; }
.align-center { align-items: center; }
.gap-2 { gap: 8px; } .gap-3 { gap: 12px; }
.mt-2 { margin-top: 8px; } .mt-3 { margin-top: 16px; } .mt-4 { margin-top: 24px; }
.mb-2 { margin-bottom: 8px; } .mb-3 { margin-bottom: 16px; } .mb-4 { margin-bottom: 24px; }
.mr-1 { margin-right: 4px; } .mr-2 { margin-right: 8px; } .ml-2 { margin-left: 8px; }
.text-good { color: #10b981; } .text-critical { color: #f43f5e; } .text-muted { color: #94a3b8; }
.text-sm { font-size: 0.85rem; }
.spinning { display: inline-block; animation: spin 1s linear infinite; }
@keyframes spin { 100% { transform: rotate(360deg); } }

/* ── Info Grid ──────────────────────────────────────────── */
.info-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}
.info-cell {
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.05);
  border-radius: 12px; padding: 16px;
}
.info-label { display: block; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.info-value { display: block; font-size: 1rem; color: #f8fafc; font-weight: 600; }
.info-sub { font-size: 0.8rem; color: #64748b; }
.info-value-row { display: flex; align-items: baseline; gap: 4px; }
.info-value-bold { font-size: 1.4rem; font-weight: 700; }
.info-unit { font-size: 0.8rem; color: #94a3b8; }
.status-cell { display: flex; align-items: center; gap: 8px; }
.status-glow { width: 10px; height: 10px; border-radius: 50%; }
.status-glow.online { background: #10b981; box-shadow: 0 0 10px rgba(16,185,129,0.5); }
.status-glow.offline { background: #f43f5e; box-shadow: 0 0 10px rgba(244,63,94,0.5); }
.capitalize { text-transform: capitalize; }

/* ── Cards & Inputs ─────────────────────────────────────── */
.glass-card {
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px; padding: 20px;
}
.card-title { font-size: 1.05rem; font-weight: 600; margin: 0; color: #e2e8f0; }
.form-stack { display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 0.85rem; color: #94a3b8; font-weight: 500; }

.input-glass {
  background: rgba(0,0,0,0.2);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px; padding: 10px 14px;
  color: #f8fafc; font-size: 0.9rem; transition: 0.3s;
  width: 100%; box-sizing: border-box;
}
.input-glass:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
.input-sm { padding: 8px 12px; font-size: 0.85rem; border-radius: 8px; }
.input-action-wrap { position: relative; }
.input-action-btn {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px;
}
.input-action-btn:hover { color: #fff; }

/* ── Buttons ────────────────────────────────────────────── */
.btn {
  padding: 10px 18px; border-radius: 10px; font-size: 0.9rem; font-weight: 600;
  cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center;
  transition: 0.2s;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: #3b82f6; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #2563eb; box-shadow: 0 4px 15px rgba(59,130,246,0.4); }
.btn-secondary { background: rgba(255,255,255,0.1); color: #e2e8f0; }
.btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.15); }
.btn-danger { background: #f43f5e; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #e11d48; box-shadow: 0 4px 15px rgba(244,63,94,0.4); }
.btn-danger-outline { background: rgba(244,63,94,0.1); color: #f43f5e; border: 1px solid rgba(244,63,94,0.2); }
.btn-glass { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; }
.btn-glass:hover:not(:disabled) { background: rgba(255,255,255,0.1); }
.btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; }
.btn-glass-edit { background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); color: #3b82f6; cursor: pointer; border-radius: 8px; padding: 6px 12px;}
.btn-glass-edit:hover:not(:disabled) { background: rgba(59,130,246,0.2); }

/* ── Alert ──────────────────────────────────────────────── */
.alert-box { padding: 12px 16px; border-radius: 10px; font-size: 0.85rem; }
.alert-box--danger { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.2); color: #f43f5e; }

/* ── WiFi Section (Cards instead of Tables) ─────────────── */
.drawer-section-title { font-size: 1.15rem; font-weight: 600; color: #fff; }
.wifi-band-card {
  background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px; padding: 20px;
}
.band-card-header { margin-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 16px; }
.band-title { display: flex; align-items: center; gap: 12px; }
.band-badge { background: #3b82f6; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; }
.band-status { font-size: 0.85rem; font-weight: 600; }
.grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.val-text { font-size: 0.95rem; color: #e2e8f0; padding: 8px 0; font-family: monospace; }

.ssid-cards-container { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }
.ssid-card {
  background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);
  border-radius: 12px; padding: 16px; transition: 0.3s;
}
.ssid-disabled { opacity: 0.6; }
.ssid-card-header { border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 12px; }
.ssid-name-wrap { display: flex; align-items: center; gap: 12px; }
.ssid-index { font-size: 0.75rem; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; color: #94a3b8; }
.ssid-title { font-size: 1.05rem; font-weight: 600; margin: 0; color: #fff; }
.badge-glass { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; border: 1px solid transparent; }
.badge-glass.good { background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.2); color: #10b981; }
.badge-glass.muted { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #94a3b8; }

/* ── iOS Switch ─────────────────────────────────────────── */
.switch-ios { position: relative; display: inline-block; width: 44px; height: 24px; }
.switch-ios.sm { width: 36px; height: 20px; }
.switch-ios input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; inset: 0; background-color: rgba(255,255,255,0.1); transition: .3s; border-radius: 24px; }
.slider:before {
  position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
  background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.switch-ios.sm .slider:before { height: 14px; width: 14px; }
input:checked + .slider { background-color: #10b981; }
input:checked + .slider:before { transform: translateX(20px); }
.switch-ios.sm input:checked + .slider:before { transform: translateX(16px); }
.toggle-lbl { font-size: 0.8rem; color: #10b981; font-weight: 700; }
.checkbox-glass { width: 16px; height: 16px; accent-color: #3b82f6; }

/* ── Hosts List ─────────────────────────────────────────── */
.empty-glass-state { padding: 40px; text-align: center; color: #64748b; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1); }
.hosts-category-title { font-size: 0.95rem; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px; margin: 0; }
.hosts-list { display: flex; flex-direction: column; gap: 8px; }
.host-card {
  display: flex; justify-content: space-between; align-items: center;
  background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
  padding: 12px 16px; border-radius: 12px; transition: 0.2s;
}
.host-card:hover { background: rgba(255,255,255,0.04); }
.host-name { font-weight: 600; color: #e2e8f0; font-size: 0.95rem; }
.host-mac { font-family: monospace; font-size: 0.8rem; color: #64748b; margin-top: 4px; }
.host-status { display: flex; align-items: center; gap: 12px; }
.status-dot { width: 10px; height: 10px; border-radius: 50%; }
.status-dot.online { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
.status-dot.offline { background: #64748b; }

/* ── Diagnostics ────────────────────────────────────────── */
.diag-result-box { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 16px; }
.grid-3-col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.stat-box { display: flex; flex-direction: column; align-items: center; background: rgba(255,255,255,0.03); padding: 12px; border-radius: 10px; }
.stat-lbl { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
.stat-val { font-size: 1.25rem; font-weight: 700; }

.timeline-container { display: flex; flex-direction: column; padding-left: 8px; }
.timeline-item { display: flex; gap: 16px; position: relative; padding-bottom: 16px; }
.timeline-item:last-child { padding-bottom: 0; }
.timeline-item::before {
  content: ''; position: absolute; left: 4px; top: 12px; bottom: 0;
  width: 2px; background: rgba(255,255,255,0.1);
}
.timeline-item:last-child::before { display: none; }
.timeline-dot {
  width: 10px; height: 10px; border-radius: 50%; background: #3b82f6;
  position: relative; z-index: 2; margin-top: 4px; box-shadow: 0 0 10px rgba(59,130,246,0.5);
}
.timeline-content { display: flex; gap: 16px; background: rgba(255,255,255,0.03); padding: 8px 16px; border-radius: 8px; flex: 1; align-items: center; }
.hop-num { font-size: 0.8rem; color: #64748b; width: 20px; }
.hop-ip { font-family: monospace; color: #e2e8f0; font-size: 0.9rem; flex: 1; }
.hop-ms { font-size: 0.85rem; color: #10b981; font-weight: 600; }

/* ── Skeletons ──────────────────────────────────────────── */
.skeleton-loader-container { display: flex; flex-direction: column; gap: 12px; margin-top: 16px; }
.skeleton-card { height: 180px; border-radius: 16px; background: rgba(255,255,255,0.03); animation: pulse 1.5s infinite ease-in-out; }
.skeleton-card.mini { height: 60px; border-radius: 12px; }
@keyframes pulse { 0% {opacity: 0.5;} 50% {opacity: 1;} 100% {opacity: 0.5;} }
</style>
