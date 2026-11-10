<template>
  <div class="dashboard">
    <SidebarNav :is-open="sidebarOpen" @close="sidebarOpen = false" />

    <div class="dashboard__main">
      <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

      <main class="dashboard__content">
        <!-- Page Header -->
        <section class="dashboard__welcome fade-in flex-header">
          <div>
            <h2 class="dashboard__heading">
              TR-069 <span class="dashboard__heading--accent">ACS Management</span>
            </h2>
            <p class="dashboard__heading-sub">
              Monitoring telemetri & konfigurasi jarak jauh modem pelanggan via Auto Configuration Server (GenieACS)
              <span v-if="store.syncStatus?.last_sync_at" class="sync-badge">
                <span class="sync-dot" :class="{ 'sync-dot--ok': store.syncStatus.status === 'success', 'sync-dot--err': store.syncStatus.status === 'error' }"></span>
                {{ store.syncStatus.status === 'success' ? `Sinkron: ${formatRelativeTime(store.syncStatus.last_sync_at)} (${store.syncStatus.device_count ?? store.stats?.total ?? 0} modem)` : 'Sinkronisasi bermasalah' }}
              </span>
            </p>
          </div>

          <div class="header-action-buttons">
            <button class="btn btn-secondary" @click="isProfilesModalOpen = true" title="Kelola Profil Provisioning TR-069 & Zero-Touch">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Profil ACS
            </button>
            <button class="btn btn-outline" @click="exportCsv" :disabled="store.devices.length === 0" title="Ekspor data tabel ke file CSV">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Ekspor CSV
            </button>
            <button class="btn btn-primary" @click="handleManualSync" :disabled="store.syncing || store.loading" :title="store.syncing ? 'Menyinkronkan data dari GenieACS NBI...' : 'Tarik data terbaru dari GenieACS NBI'">
              <svg :class="{ spinning: store.syncing || store.loading }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
              <span>{{ store.syncing ? 'Menyinkronkan...' : 'Sinkronkan' }}</span>
            </button>
          </div>
        </section>

        <!-- Sync Error Alert Banner -->
        <div v-if="store.syncStatus?.status === 'error'" class="alert-box alert-box--warning fade-in">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <div>
            <strong>Peringatan Sinkronisasi GenieACS</strong>
            <p>{{ store.syncStatus.error }}. Data terakhir berhasil disinkronkan: {{ formatRelativeTime(store.syncStatus.last_sync_at) }}</p>
          </div>
        </div>

        <!-- Success/Error Toast Notification -->
        <div v-if="notification" :class="['alert-box', `alert-box--${notificationType}`, 'fade-in']">
          <svg v-if="notificationType === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span>{{ notification }}</span>
        </div>

        <!-- Interactive Analytics Cards (Click to Filter) -->
        <section class="analytics-grid" v-if="store.stats">
          <!-- Total Card -->
          <div
            :class="['stat-card', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'all' }]"
            @click="setStatusFilter('all')"
            title="Klik untuk tampilkan semua ONT"
          >
            <div class="stat-icon-wrap stat-icon--all">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Total ONT</span>
              <span class="stat-value">{{ store.stats.total }}</span>
            </div>
            <span v-if="activeStatusFilter === 'all'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Online Card -->
          <div
            :class="['stat-card', 'stat-online', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'online' }]"
            @click="setStatusFilter('online')"
            title="Klik untuk filter ONT Online"
          >
            <div class="stat-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Online</span>
              <span class="stat-value text-emerald">{{ store.stats.online }}</span>
            </div>
            <span v-if="activeStatusFilter === 'online'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Offline Card -->
          <div
            :class="['stat-card', 'stat-offline', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'offline' }]"
            @click="setStatusFilter('offline')"
            title="Klik untuk filter ONT Offline"
          >
            <div class="stat-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Offline</span>
              <span class="stat-value text-slate">{{ store.stats.offline }}</span>
            </div>
            <span v-if="activeStatusFilter === 'offline'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Critical RX Card -->
          <div
            :class="['stat-card', 'stat-critical', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'critical_rx' }]"
            @click="setStatusFilter('critical_rx')"
            title="Klik untuk filter Redaman Kritis (< -25 dBm / > -8 dBm)"
          >
            <div class="stat-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Redaman Kritis</span>
              <span class="stat-value text-rose">{{ store.stats.critical_rx }}</span>
            </div>
            <span v-if="activeStatusFilter === 'critical_rx'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Unlinked Card -->
          <div
            :class="['stat-card', 'stat-warning', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'unlinked' }]"
            @click="setStatusFilter('unlinked')"
            title="Klik untuk filter ONT Tanpa Akun PPPoE"
          >
            <div class="stat-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Tanpa PPPoE</span>
              <span class="stat-value text-amber">{{ store.stats.unlinked ?? 0 }}</span>
            </div>
            <span v-if="activeStatusFilter === 'unlinked'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Stale Inform Card -->
          <div
            :class="['stat-card', 'stat-stale', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'stale' }]"
            @click="setStatusFilter('stale')"
            title="Klik untuk filter ONT tidak melapor > 24 jam"
          >
            <div class="stat-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Stale (>24 Jam)</span>
              <span class="stat-value text-purple">{{ store.stats.stale ?? 0 }}</span>
            </div>
            <span v-if="activeStatusFilter === 'stale'" class="filter-active-pill">Aktif</span>
          </div>

          <!-- Unprovisioned Card -->
          <div
            :class="['stat-card', 'stat-unprovisioned', 'stat-card--clickable', { 'stat-card--active': activeStatusFilter === 'unprovisioned' }]"
            @click="setStatusFilter('unprovisioned')"
            title="Klik untuk filter ONT Baru yang belum diprovisioning"
          >
            <div class="stat-icon-wrap" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <div class="stat-info">
              <span class="stat-title">Belum Provision</span>
              <span class="stat-value text-amber">{{ store.stats.unprovisioned ?? 0 }}</span>
            </div>
            <span v-if="activeStatusFilter === 'unprovisioned'" class="filter-active-pill">Aktif</span>
          </div>
        </section>

        <!-- Filter Control Bar -->
        <section class="filter-bar-card fade-in">
          <div class="filter-bar-left">
            <!-- Search Input -->
            <div class="search-wrap">
              <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                v-model="searchQuery"
                @keyup.enter="handleSearch"
                type="text"
                placeholder="Cari SN, MAC, PPPoE username, SSID, IP..."
                class="input-modern search-input"
              >
              <button v-if="searchQuery" class="search-clear-btn" @click="clearSearch" title="Hapus pencarian">×</button>
            </div>

            <!-- Status Dropdown -->
            <div class="filter-select-wrap">
              <select v-model="activeStatusFilter" @change="handleFilterChange" class="select-modern">
                <option value="all">Semua Status</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="critical_rx">Redaman Kritis (&lt; -25 / &gt; -8 dBm)</option>
                <option value="warning_rx">Redaman Peringatan (-25 s/d -27 dBm)</option>
                <option value="good_rx">Redaman Bagus (-8 s/d -25 dBm)</option>
                <option value="unlinked">Tanpa Akun PPPoE</option>
                <option value="stale">Stale (Tidak Melapor &gt; 24 Jam)</option>
                <option value="new_ont">ONT Baru (24 Jam Terakhir)</option>
                <option value="unprovisioned">Belum Provisioning</option>
                <option value="provisioned">Sudah Provisioning</option>
              </select>
            </div>

            <!-- Vendor Dropdown -->
            <div class="filter-select-wrap">
              <select v-model="activeVendorFilter" @change="handleFilterChange" class="select-modern">
                <option value="all">Semua Vendor</option>
                <option v-for="v in vendorList" :key="v.name" :value="v.name">
                  {{ v.name }} ({{ v.count }})
                </option>
              </select>
            </div>

            <!-- Reset Filters -->
            <button
              v-if="isFiltered"
              class="btn btn-outline btn-sm reset-filter-btn"
              @click="resetAllFilters"
              title="Reset semua filter ke default"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
              Reset Filter
            </button>
          </div>

          <div class="filter-bar-right">
            <!-- Per Page Selector -->
            <div class="per-page-wrap">
              <span class="per-page-label">Tampilkan:</span>
              <select v-model="perPage" @change="handleFilterChange" class="select-modern select-compact">
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
          </div>
        </section>

        <!-- Main Table Section -->
        <section class="stagger">
          <div class="premium-card panel-full">
            <div class="table-container custom-scrollbar">
              <table class="premium-table">
                <thead>
                  <tr>
                    <th class="th-checkbox">
                      <input type="checkbox" @change="toggleAll" :checked="isAllSelected" class="checkbox-glass" title="Pilih Semua di Halaman Ini">
                    </th>
                    <th class="th-status">STATUS</th>
                    <th class="th-device">PERANGKAT & IDENTITAS</th>
                    <th class="th-pppoe">PELANGGAN / PPPoE</th>
                    <th class="th-rx">REDAMAN (RX)</th>
                    <th class="th-wifi">WI-FI (SSID)</th>
                    <th class="th-inform">LAST INFORM</th>
                    <th class="th-actions text-right">AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Loading State -->
                  <tr v-if="store.loading && store.devices.length === 0">
                    <td colspan="8" class="loading-state">
                      <div class="loading-state-content">
                        <span class="spinning">🔄</span>
                        <p>Memuat data perangkat TR-069...</p>
                      </div>
                    </td>
                  </tr>

                  <!-- Empty State -->
                  <tr v-else-if="store.devices.length === 0">
                    <td colspan="8" class="empty-state">
                      <div class="empty-state-content">
                        <div class="empty-state-icon">📡</div>
                        <h4>Tidak Ada Perangkat yang Cocok</h4>
                        <p v-if="isFiltered">Coba sesuaikan filter pencarian atau klik Reset Filter.</p>
                        <p v-else>Belum ada perangkat ACS yang terhubung atau tersinkronkan dari GenieACS.</p>
                        <button v-if="isFiltered" class="btn btn-secondary btn-sm mt-3" @click="resetAllFilters">Reset Filter</button>
                        <button v-else class="btn btn-primary btn-sm mt-3" @click="handleManualSync">Sinkronkan dari GenieACS</button>
                      </div>
                    </td>
                  </tr>

                  <!-- Data Rows -->
                  <tr
                    v-for="device in store.devices"
                    :key="device.id"
                    class="table-row-hover"
                    :class="{ 'row-selected': selectedDevices.includes(device.id) }"
                  >
                    <!-- Checkbox -->
                    <td class="td-checkbox">
                      <input type="checkbox" :value="device.id" v-model="selectedDevices" class="checkbox-glass">
                    </td>

                    <!-- Status -->
                    <td class="td-status">
                      <div class="status-cell">
                        <span :class="['status-glow', device.status === 'online' ? 'online' : 'offline']"></span>
                        <span class="status-label" :class="device.status === 'online' ? 'text-emerald' : 'text-slate'">
                          {{ device.status === 'online' ? 'Online' : 'Offline' }}
                        </span>
                      </div>
                    </td>

                    <!-- Device Info -->
                    <td class="td-device">
                      <div class="device-cell">
                        <!-- Serial Number with Quick Copy -->
                        <div class="code-copy-row">
                          <span class="sn-text" :title="device.serial_number">{{ device.serial_number || 'Unknown SN' }}</span>
                          <button
                            v-if="device.serial_number"
                            class="btn-copy-tiny"
                            @click="copyText(device.serial_number, 'Serial Number')"
                            title="Salin Serial Number"
                          >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                          </button>
                        </div>

                        <!-- MAC Address with Quick Copy -->
                        <div class="code-copy-row">
                          <code class="mac-code">{{ device.mac_address || 'No MAC' }}</code>
                          <button
                            v-if="device.mac_address"
                            class="btn-copy-tiny"
                            @click="copyText(device.mac_address, 'MAC Address')"
                            title="Salin MAC Address"
                          >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                          </button>
                        </div>

                        <!-- Vendor & Model Pill -->
                        <div class="vendor-model-tag">
                          <span class="vendor-name">{{ device.vendor || 'Generic' }}</span>
                          <span class="separator">/</span>
                          <span class="model-name">{{ device.model || 'ONT' }}</span>
                        </div>

                        <!-- Area Tag & Provision Badge -->
                        <div class="device-meta-pills">
                          <span v-if="device.area_tag" class="pill-area" :title="'Wilayah: ' + device.area_tag">
                            📍 {{ device.area_tag }}
                          </span>
                          <span v-if="device.provisioned_at" class="pill-prov" :title="'Diprovisioning: ' + formatRelativeTime(device.provisioned_at)">
                            ⚡ {{ device.profile?.name || 'Provisioned' }}
                          </span>
                          <span v-else class="pill-unprov" title="Belum diprovisioning">
                            ⏳ Belum Provision
                          </span>
                        </div>
                      </div>
                    </td>

                    <!-- PPPoE & IP -->
                    <td class="td-pppoe">
                      <div class="pppoe-cell">
                        <!-- PPPoE Username -->
                        <div v-if="device.pppoe_username" class="code-copy-row">
                          <span class="pppoe-badge" :title="device.pppoe_username">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="link-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            {{ device.pppoe_username }}
                          </span>
                          <button
                            class="btn-copy-tiny"
                            @click="copyText(device.pppoe_username, 'PPPoE Username')"
                            title="Salin PPPoE Username"
                          >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                          </button>
                        </div>
                        <div v-else class="unlinked-badge">
                          <span class="dot-warn">●</span> Belum Terhubung
                        </div>

                        <!-- IP Address -->
                        <div class="code-copy-row">
                          <span class="ip-code">{{ device.ip_address || '0.0.0.0' }}</span>
                          <button
                            v-if="device.ip_address && device.ip_address !== '0.0.0.0'"
                            class="btn-copy-tiny"
                            @click="copyText(device.ip_address, 'IP Address')"
                            title="Salin IP"
                          >
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                          </button>
                        </div>
                      </div>
                    </td>

                    <!-- Optical Power (RX) -->
                    <td class="td-rx">
                      <div :class="['dbm-pill', getDbmClass(device.rx_power_dbm)]" :title="getDbmTooltip(device.rx_power_dbm)">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="dbm-icon"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        <span class="dbm-val">{{ device.rx_power_dbm !== null ? device.rx_power_dbm + ' dBm' : 'N/A' }}</span>
                      </div>
                    </td>

                    <!-- WiFi SSID -->
                    <td class="td-wifi">
                      <div class="wifi-cell">
                        <div v-if="device.wifi_ssid" class="wifi-badge" :title="device.wifi_ssid">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>
                          <span class="wifi-name">{{ device.wifi_ssid }}</span>
                        </div>
                        <span v-else class="text-muted text-xs">Nonaktif / Tersembunyi</span>

                        <span v-if="device.has_5g" class="band-tag band-tag--5g" title="Modem mendukung Dual-Band 2.4GHz & 5GHz">
                          Dual-Band
                        </span>
                        <span v-else class="band-tag band-tag--24g" title="Modem Single-Band 2.4GHz">
                          2.4 GHz
                        </span>
                      </div>
                    </td>

                    <!-- Last Inform -->
                    <td class="td-inform">
                      <div class="inform-cell" :title="formatFullDate(device.last_inform_at)">
                        <span :class="['inform-text', getInformClass(device.last_inform_at)]">
                          {{ formatRelativeTime(device.last_inform_at) }}
                        </span>
                        <span class="inform-sub-time" v-if="device.last_inform_at">
                          {{ formatShortTime(device.last_inform_at) }}
                        </span>
                      </div>
                    </td>

                    <!-- Actions -->
                    <td class="td-actions text-right">
                      <div class="action-buttons-row">
                        <button
                          v-if="!device.provisioned_at"
                          class="btn-action-icon btn-action-icon--amber"
                          @click="openQuickProvision(device)"
                          title="Terapkan Profil Provisioning Cepat"
                        >
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </button>
                        <button
                          class="btn-action-icon btn-action-icon--primary"
                          @click="openModal(device)"
                          title="Kelola & Diagnostik Modem"
                        >
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button
                          class="btn-action-icon btn-action-icon--cyan"
                          @click="refreshParams(device.id)"
                          title="Tarik Parameter Terbaru dari Modem (Connection Request)"
                        >
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21v-5h5"/></svg>
                        </button>
                        <button
                          class="btn-action-icon btn-action-icon--danger"
                          @click="deleteDeviceConfirm(device.id, device.serial_number)"
                          title="Hapus Modem dari ACS dan Database"
                        >
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination Bar -->
            <div class="pagination-bar">
              <span class="pagination-info">
                Menampilkan <strong>{{ store.devices.length }}</strong> dari <strong>{{ store.pagination.total }}</strong> perangkat
                (Halaman <strong>{{ store.pagination.current_page }}</strong> dari <strong>{{ store.pagination.last_page }}</strong>)
              </span>
              <div class="pagination-actions">
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(1)"
                  :disabled="store.pagination.current_page <= 1"
                  title="Halaman Pertama"
                >
                  &laquo;
                </button>
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(store.pagination.current_page - 1)"
                  :disabled="store.pagination.current_page <= 1"
                >
                  Prev
                </button>
                <span class="page-number-pill">Hal. {{ store.pagination.current_page }}</span>
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(store.pagination.current_page + 1)"
                  :disabled="store.pagination.current_page >= store.pagination.last_page"
                >
                  Next
                </button>
                <button
                  class="btn btn-sm btn-outline"
                  @click="fetchData(store.pagination.last_page)"
                  :disabled="store.pagination.current_page >= store.pagination.last_page"
                  title="Halaman Terakhir"
                >
                  &raquo;
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Device Modal Component -->
        <AcsDeviceModal
          :show="isModalOpen"
          :device="selectedDevice"
          @close="isModalOpen = false"
          @updated="onDeviceUpdated"
        />

        <!-- Bulk Action Floating Bar -->
        <div v-if="selectedDevices.length > 0" class="bulk-action-bar fade-in-up">
          <div class="bulk-info">
            <span class="bulk-count">{{ selectAllMatching ? store.pagination.total : selectedDevices.length }}</span> modem terpilih
            <span v-if="selectAllMatching" class="badge-all-matching">(Semua yang cocok filter)</span>
          </div>

          <div class="bulk-select-matching-banner" v-if="!selectAllMatching && selectedDevices.length === store.devices.length && store.pagination.total > store.devices.length">
            <button class="btn-link text-xs" @click="selectAllMatching = true">
              Pilih semua <strong>{{ store.pagination.total }}</strong> modem yang cocok dengan filter saat ini
            </button>
          </div>

          <div class="bulk-actions">
            <button class="btn btn-secondary btn-sm" @click="handleBulkAction('refresh')" title="Tarik parameter terbaru dari perangkat terpilih">
              🔄 Sync
            </button>
            <button class="btn btn-info-outline btn-sm" @click="openBulkPrompt('apply_profile')" title="Terapkan profil provisioning TR-069 massal">
              📑 Terapkan Profil
            </button>
            <button class="btn btn-info-outline btn-sm" @click="openBulkPrompt('set_admin_password')" title="Ubah kata sandi web admin modem massal">
              🔒 Ganti Sandi Admin
            </button>
            <button class="btn btn-info-outline btn-sm" @click="openBulkPrompt('set_inform_interval')" title="Ubah interval inform CWMP berkala">
              ⏱️ Atur Inform
            </button>
            <button class="btn btn-danger-outline btn-sm" @click="handleBulkAction('reboot')" title="Reboot perangkat terpilih">
              ⚡ Reboot Massal
            </button>
            <button class="btn btn-danger btn-sm" @click="handleBulkAction('factory_reset')" title="Reset ke setelan pabrik">
              ⚠️ Factory Reset
            </button>
            <button class="btn btn-outline btn-sm" @click="cancelBulkSelection">Batal</button>
          </div>
        </div>

        <!-- Modals: Profiles & Batch Progress -->
        <AcsProfilesModal
          :show="isProfilesModalOpen"
          @close="isProfilesModalOpen = false"
          @updated="onProfilesUpdated"
        />

        <AcsBatchModal
          :show="isBatchModalOpen"
          :batch-id="activeBatchId"
          :action="activeBatchAction"
          @close="isBatchModalOpen = false"
          @finished="onBatchFinished"
        />

        <!-- Parameter Prompt Modal for Bulk Operations -->
        <div v-if="bulkPromptModal.open" class="prompt-overlay" @click.self="bulkPromptModal.open = false">
          <div class="prompt-modal slide-in-down">
            <div class="prompt-header">
              <h4>{{ bulkPromptModal.title }}</h4>
              <button class="close-btn" @click="bulkPromptModal.open = false">×</button>
            </div>
            <div class="prompt-body">
              <p class="prompt-desc">{{ bulkPromptModal.desc }}</p>

              <!-- Admin Password Input -->
              <div v-if="bulkPromptModal.action === 'set_admin_password'" class="form-group mb-3">
                <label class="block text-xs font-bold text-slate mb-1">Kata Sandi Web Admin Baru <span class="text-rose">*</span></label>
                <input v-model="bulkPromptModal.inputValue" type="text" class="input-modern font-mono w-full" placeholder="Masukkan password baru..." required />
              </div>

              <!-- Inform Interval Input -->
              <div v-if="bulkPromptModal.action === 'set_inform_interval'" class="form-group mb-3">
                <label class="block text-xs font-bold text-slate mb-1">Interval Inform (Detik) <span class="text-rose">*</span></label>
                <input v-model.number="bulkPromptModal.inputValue" type="number" min="30" max="86400" class="input-modern w-full" placeholder="300" required />
                <small class="text-xs text-muted mt-1 block">Rekomendasi: 300 detik (5 menit).</small>
              </div>

              <!-- Select Provisioning Profile -->
              <div v-if="bulkPromptModal.action === 'apply_profile'" class="form-group mb-3">
                <label class="block text-xs font-bold text-slate mb-1">Pilih Profil Provisioning <span class="text-rose">*</span></label>
                <select v-model="bulkPromptModal.selectedProfileId" class="select-modern w-full" required>
                  <option :value="null" disabled>-- Pilih Profil --</option>
                  <option v-for="p in store.profiles" :key="p.id" :value="p.id">
                    {{ p.name }} ({{ p.vendor || 'Semua Vendor' }})
                  </option>
                </select>
                <p v-if="store.profiles.length === 0" class="text-xs text-rose mt-2">
                  Belum ada profil provisioning. Silakan buat di menu Profil ACS terlebih dahulu.
                </p>
              </div>
            </div>
            <div class="prompt-footer">
              <button class="btn btn-secondary btn-sm" @click="bulkPromptModal.open = false">Batal</button>
              <button
                class="btn btn-primary btn-sm"
                :disabled="bulkPromptModal.action === 'apply_profile' && !bulkPromptModal.selectedProfileId"
                @click="executeBulkPrompt"
              >
                Eksekusi Massal
              </button>
            </div>
          </div>
        </div>

        <!-- Quick Single Device Provision Modal -->
        <div v-if="quickProvisionModal.open" class="prompt-overlay" @click.self="quickProvisionModal.open = false">
          <div class="prompt-modal slide-in-down">
            <div class="prompt-header">
              <h4>⚡ Provisioning Cepat: {{ quickProvisionModal.device?.serial_number }}</h4>
              <button class="close-btn" @click="quickProvisionModal.open = false">×</button>
            </div>
            <div class="prompt-body">
              <p class="prompt-desc">
                Pilih profil provisioning untuk dikirimkan ke modem <strong>{{ quickProvisionModal.device?.vendor }} {{ quickProvisionModal.device?.model }}</strong>:
              </p>
              <div class="form-group mb-3">
                <label class="block text-xs font-bold text-slate mb-1">Pilih Profil Provisioning</label>
                <select v-model="quickProvisionModal.selectedProfileId" class="select-modern w-full">
                  <option :value="null" disabled>-- Pilih Profil --</option>
                  <option v-for="p in store.profiles" :key="p.id" :value="p.id">
                    {{ p.name }} ({{ p.vendor || 'Semua' }})
                  </option>
                </select>
              </div>
            </div>
            <div class="prompt-footer">
              <button class="btn btn-secondary btn-sm" @click="quickProvisionModal.open = false">Batal</button>
              <button class="btn btn-primary btn-sm" :disabled="!quickProvisionModal.selectedProfileId" @click="executeQuickProvision">
                Kirim Perintah
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAcsStore } from '../stores/acsStore'
import SidebarNav from '../components/SidebarNav.vue'
import TopBar from '../components/TopBar.vue'
import AcsDeviceModal from '../components/AcsDeviceModal.vue'
import AcsProfilesModal from '../components/AcsProfilesModal.vue'
import AcsBatchModal from '../components/AcsBatchModal.vue'
import { reactive } from 'vue'

const store = useAcsStore()
const sidebarOpen = ref(false)
const searchQuery = ref('')
const activeStatusFilter = ref('all')
const activeVendorFilter = ref('all')
const perPage = ref(20)

const notification = ref('')
const notificationType = ref('success')
const isModalOpen = ref(false)
const selectedDevice = ref<any>(null)
const selectedDevices = ref<number[]>([])
const selectAllMatching = ref(false)

const isProfilesModalOpen = ref(false)
const isBatchModalOpen = ref(false)
const activeBatchId = ref('')
const activeBatchAction = ref('')

const bulkPromptModal = reactive({
  open: false,
  action: '',
  title: '',
  desc: '',
  inputValue: '' as any,
  selectedProfileId: null as number | null
})

const quickProvisionModal = reactive({
  open: false,
  device: null as any,
  selectedProfileId: null as number | null
})

// Check if any filter is active
const isFiltered = computed(() => {
  return searchQuery.value.trim() !== '' || activeStatusFilter.value !== 'all' || activeVendorFilter.value !== 'all'
})

// Vendor list formatted from stats
const vendorList = computed(() => {
  const vendors = store.stats?.vendors
  if (!vendors || !Array.isArray(vendors)) return []
  return vendors.map((v: any) => ({
    name: v.vendor,
    count: v.count
  }))
})

// Bulk selection logic
const isAllSelected = computed(() => {
  if (store.devices.length === 0) return false
  return selectedDevices.value.length === store.devices.length
})

const toggleAll = (e: Event) => {
  const target = e.target as HTMLInputElement
  if (target.checked) {
    selectedDevices.value = store.devices.map((d: any) => d.id)
  } else {
    selectedDevices.value = []
  }
}

// Data fetching helper
const fetchData = (page: number = 1) => {
  store.fetchDevices(page, searchQuery.value, {
    status: activeStatusFilter.value,
    vendor: activeVendorFilter.value,
    per_page: perPage.value
  })
}

const handleSearch = () => {
  fetchData(1)
}

const clearSearch = () => {
  searchQuery.value = ''
  fetchData(1)
}

const handleFilterChange = () => {
  fetchData(1)
}

const setStatusFilter = (status: string) => {
  activeStatusFilter.value = status
  fetchData(1)
}

const resetAllFilters = () => {
  searchQuery.value = ''
  activeStatusFilter.value = 'all'
  activeVendorFilter.value = 'all'
  fetchData(1)
}

// Manual NBI Sync trigger
const handleManualSync = async () => {
  try {
    const res = await store.triggerSync()
    showNotification(res?.message || 'Sinkronisasi dengan GenieACS berhasil.')
  } catch (err: any) {
    showNotification(err?.message || 'Gagal melakukan sinkronisasi dengan GenieACS.', 'error')
  }
}

// Copy to Clipboard with toast
const copyText = async (text: string, label: string) => {
  try {
    await navigator.clipboard.writeText(text)
    showNotification(`${label} (${text}) berhasil disalin!`)
  } catch (err) {
    showNotification(`Gagal menyalin ${label}`, 'error')
  }
}

// Modal handling
const openModal = (device: any) => {
  selectedDevice.value = device
  isModalOpen.value = true
}

const onDeviceUpdated = (msg: string) => {
  showNotification(msg)
  setTimeout(() => fetchData(store.pagination.current_page), 1500)
}

// Row Actions
const refreshParams = async (deviceId: number) => {
  try {
    const res = await store.refreshDevice(deviceId)
    showNotification(res?.message || 'Permintaan sinkronisasi berhasil dikirim ke modem.')
    setTimeout(() => fetchData(store.pagination.current_page), 1500)
  } catch (err: any) {
    showNotification(err?.message || 'Gagal menghubungi modem.', 'error')
  }
}

const deleteDeviceConfirm = async (deviceId: number, serialNumber: string) => {
  if (confirm(`Hapus perangkat ${serialNumber}? Data akan dihapus dari ACS dan database lokal.`)) {
    try {
      await store.deleteDevice(deviceId)
      showNotification('Perangkat berhasil dihapus dari ACS dan database.')
      fetchData(1)
      store.fetchStats()
    } catch (err: any) {
      showNotification(err?.message || 'Gagal menghapus perangkat.', 'error')
    }
  }
}

// Cancel bulk selection
const cancelBulkSelection = () => {
  selectedDevices.value = []
  selectAllMatching.value = false
}

// Open Prompt for bulk operations requiring user input
const openBulkPrompt = (action: string) => {
  bulkPromptModal.action = action
  bulkPromptModal.inputValue = ''
  bulkPromptModal.selectedProfileId = store.profiles[0]?.id || null

  const targetCount = selectAllMatching.value ? store.pagination.total : selectedDevices.value.length

  if (action === 'set_admin_password') {
    bulkPromptModal.title = '🔒 Ganti Sandi Web Admin Massal'
    bulkPromptModal.desc = `Masukkan kata sandi baru untuk ${targetCount} modem terpilih:`
  } else if (action === 'set_inform_interval') {
    bulkPromptModal.title = '⏱️ Atur Interval Inform CWMP'
    bulkPromptModal.desc = `Atur interval lapor berkala untuk ${targetCount} modem (dalam detik):`
    bulkPromptModal.inputValue = 300
  } else if (action === 'apply_profile') {
    bulkPromptModal.title = '📑 Terapkan Profil Provisioning'
    bulkPromptModal.desc = `Pilih profil yang akan dipush ke ${targetCount} modem terpilih:`
  }

  bulkPromptModal.open = true
}

// Execute prompt modal action
const executeBulkPrompt = async () => {
  const action = bulkPromptModal.action
  const params: any = {}

  if (action === 'set_admin_password') {
    if (!bulkPromptModal.inputValue.trim()) {
      alert('Kata sandi baru wajib diisi.')
      return
    }
    params.admin_password = bulkPromptModal.inputValue.trim()
  } else if (action === 'set_inform_interval') {
    params.inform_interval = Number(bulkPromptModal.inputValue) || 300
  } else if (action === 'apply_profile') {
    if (!bulkPromptModal.selectedProfileId) {
      alert('Pilih profil provisioning terlebih dahulu.')
      return
    }
    params.profile_id = bulkPromptModal.selectedProfileId
  }

  bulkPromptModal.open = false

  try {
    const res = await store.bulkAction({
      deviceIds: selectAllMatching.value ? [] : selectedDevices.value,
      action: action,
      params: params,
      selectAllMatching: selectAllMatching.value,
      filterSearch: searchQuery.value,
      filterVendor: activeVendorFilter.value,
      filterStatus: activeStatusFilter.value
    })

    if (res.batch_id) {
      activeBatchId.value = res.batch_id
      activeBatchAction.value = action
      isBatchModalOpen.value = true
    }

    showNotification(res.message || 'Operasi massal berhasil dimasukkan ke antrean.')
    cancelBulkSelection()
  } catch (err: any) {
    showNotification(err.message || 'Gagal menjalankan aksi massal.', 'error')
  }
}

// Direct bulk actions (reboot, refresh, factory_reset)
const handleBulkAction = async (action: string) => {
  let actionName = ''
  if (action === 'reboot') actionName = 'Reboot'
  if (action === 'refresh') actionName = 'Tarik Parameter (Sync)'
  if (action === 'factory_reset') actionName = 'Factory Reset'

  const targetCount = selectAllMatching.value ? store.pagination.total : selectedDevices.value.length

  if (confirm(`Apakah Anda yakin ingin melakukan ${actionName} pada ${targetCount} perangkat?`)) {
    try {
      const res = await store.bulkAction({
        deviceIds: selectAllMatching.value ? [] : selectedDevices.value,
        action: action,
        selectAllMatching: selectAllMatching.value,
        filterSearch: searchQuery.value,
        filterVendor: activeVendorFilter.value,
        filterStatus: activeStatusFilter.value
      })

      if (res.batch_id) {
        activeBatchId.value = res.batch_id
        activeBatchAction.value = action
        isBatchModalOpen.value = true
      }

      showNotification(res.message || `${targetCount} perangkat dimasukkan ke antrean ${actionName}.`)
      cancelBulkSelection()
    } catch (err: any) {
      showNotification(err.message || 'Gagal menjalankan aksi massal.', 'error')
    }
  }
}

// Quick single device provision modal
const openQuickProvision = (device: any) => {
  quickProvisionModal.device = device
  quickProvisionModal.selectedProfileId = device.acs_profile_id || store.profiles[0]?.id || null
  quickProvisionModal.open = true
}

const executeQuickProvision = async () => {
  if (!quickProvisionModal.device || !quickProvisionModal.selectedProfileId) return

  try {
    const res = await store.provisionDevice(quickProvisionModal.device.id, quickProvisionModal.selectedProfileId)
    showNotification(res.message || 'Profil berhasil diterapkan.')
    quickProvisionModal.open = false
    setTimeout(() => fetchData(store.pagination.current_page), 1500)
    store.fetchStats()
  } catch (err: any) {
    showNotification(err.message || 'Gagal menerapkan profil.', 'error')
  }
}

const onProfilesUpdated = () => {
  store.fetchProfiles()
  store.fetchStats()
}

const onBatchFinished = () => {
  fetchData(store.pagination.current_page)
  store.fetchStats()
}

// CSV Export
const exportCsv = () => {
  if (store.devices.length === 0) return

  const headers = ['ID', 'Serial Number', 'MAC Address', 'Vendor', 'Model', 'Status', 'PPPoE Username', 'IP Address', 'RX Power (dBm)', 'WiFi SSID', '5G Capable', 'Last Inform']
  const rows = store.devices.map((d: any) => [
    d.id,
    `"${d.serial_number || ''}"`,
    `"${d.mac_address || ''}"`,
    `"${d.vendor || ''}"`,
    `"${d.model || ''}"`,
    d.status,
    `"${d.pppoe_username || ''}"`,
    d.ip_address || '',
    d.rx_power_dbm !== null ? d.rx_power_dbm : '',
    `"${d.wifi_ssid || ''}"`,
    d.has_5g ? 'Yes' : 'No',
    `"${d.last_inform_at || ''}"`
  ])

  const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const encodedUri = encodeURI(csvContent)
  const link = document.createElement('a')
  link.setAttribute('href', encodedUri)
  const nowStr = new Date().toISOString().slice(0, 10)
  link.setAttribute('download', `acs_devices_${nowStr}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  showNotification('Data modem berhasil diekspor ke file CSV.')
}

// Formatters
const showNotification = (msg: string, type: string = 'success') => {
  notification.value = msg
  notificationType.value = type
  setTimeout(() => {
    notification.value = ''
  }, 4500)
}

const getDbmClass = (power: number | null) => {
  if (power === null || power === undefined) return 'dbm-offline'
  if (power >= -25 && power <= -8) return 'dbm-good'
  if ((power < -25 && power >= -27) || (power > -8 && power <= -4)) return 'dbm-warning'
  return 'dbm-critical'
}

const getDbmTooltip = (power: number | null) => {
  if (power === null) return 'Redaman optik tidak terdeteksi / modem offline'
  if (power >= -25 && power <= -8) return 'Kualitas sinyal optik sangat baik'
  if (power < -25 && power >= -27) return 'Sinyal optik mulai menurun (warning)'
  return 'Sinyal optik kritis (berpotensi gangguan / RTO)'
}

const formatRelativeTime = (dateStr: string | null) => {
  if (!dateStr) return 'Belum pernah melapor'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  if (diffMs < 0) return 'Baru saja'

  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'Baru saja'
  if (diffMins < 60) return `${diffMins} menit lalu`

  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours} jam lalu`

  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays} hari lalu`
}

const formatShortTime = (dateStr: string | null) => {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

const formatFullDate = (dateStr: string | null) => {
  if (!dateStr) return 'Tidak ada data laporan'
  const d = new Date(dateStr)
  return d.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'medium' })
}

const getInformClass = (dateStr: string | null) => {
  if (!dateStr) return 'inform-dead'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)

  if (diffMins <= 15) return 'inform-fresh'
  if (diffMins <= 24 * 60) return 'inform-stale'
  return 'inform-dead'
}

onMounted(() => {
  store.fetchStats()
  fetchData(1)
  store.fetchProfiles()
})
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.dashboard {
  display: flex;
  min-height: 100vh;
  background: var(--surface-0);
}
.dashboard__main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.dashboard__content {
  flex: 1;
  padding: 32px 40px;
  overflow-y: auto;
}
.dashboard__heading {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-1);
  margin-bottom: 6px;
  letter-spacing: -0.02em;
}
.dashboard__heading--accent {
  background: linear-gradient(135deg, #38bdf8 0%, #3b82f6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.dashboard__heading-sub {
  font-size: 0.875rem;
  color: var(--text-2);
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.sync-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  color: var(--text-2);
}
.sync-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #9ca3af;
}
.sync-dot--ok {
  background: #10b981;
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.6);
}
.sync-dot--err {
  background: #f59e0b;
  box-shadow: 0 0 6px rgba(245, 158, 11, 0.6);
}

.dashboard__welcome { margin-bottom: 24px; }
.flex-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.header-action-buttons {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

/* ── Animations ─────────────────────────────────────────── */
.fade-in { animation: fadeIn 0.3s ease-out forwards; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.spinning {
  display: inline-block;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Interactive Analytics Cards ────────────────────────── */
.analytics-grid {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 16px;
  margin-bottom: 20px;
}
.stat-card {
  position: relative;
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 14px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  cursor: pointer;
  user-select: none;
}
.stat-card:hover {
  transform: translateY(-2px);
  border-color: rgba(56, 189, 248, 0.3);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}
.stat-card--active {
  border-color: #38bdf8 !important;
  background: rgba(56, 189, 248, 0.06);
  box-shadow: 0 0 20px rgba(56, 189, 248, 0.15);
}
.filter-active-pill {
  position: absolute;
  top: 8px;
  right: 10px;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 1px 6px;
  border-radius: 6px;
  background: #38bdf8;
  color: #0f172a;
}
.stat-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--surface-2);
  color: var(--text-2);
  flex-shrink: 0;
}
.stat-icon--all { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
.stat-online .stat-icon-wrap { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.stat-offline .stat-icon-wrap { background: rgba(107, 114, 128, 0.1); color: #9ca3af; }
.stat-critical .stat-icon-wrap { background: rgba(244, 63, 94, 0.1); color: #f43f5e; }
.stat-warning .stat-icon-wrap { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.stat-stale .stat-icon-wrap { background: rgba(168, 85, 247, 0.1); color: #a855f7; }

.stat-info { display: flex; flex-direction: column; }
.stat-title {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 2px;
}
.stat-value {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text-1);
}
.text-emerald { color: #10b981; }
.text-slate { color: #9ca3af; }
.text-rose { color: #f43f5e; }
.text-amber { color: #f59e0b; }
.text-purple { color: #a855f7; }

/* ── Filter Bar ─────────────────────────────────────────── */
.filter-bar-card {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 14px;
  padding: 12px 18px;
  margin-bottom: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.filter-bar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  flex: 1;
}
.filter-bar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.search-wrap {
  position: relative;
  min-width: 280px;
  flex: 1;
  max-width: 400px;
}
.search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
}
.search-clear-btn {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: var(--text-3);
  font-size: 1.1rem;
  cursor: pointer;
  padding: 0 4px;
}
.search-clear-btn:hover { color: var(--text-1); }

.search-input {
  width: 100%;
  padding-left: 38px !important;
  padding-right: 28px !important;
}
.input-modern, .select-modern {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 8px 14px;
  font-size: 0.85rem;
  color: var(--text-1);
  transition: all 0.2s;
}
.input-modern:focus, .select-modern:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-dim);
}
.select-compact { padding: 6px 10px; }
.per-page-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.82rem;
  color: var(--text-3);
}
.reset-filter-btn {
  color: #f43f5e !important;
  border-color: rgba(244, 63, 94, 0.3) !important;
}
.reset-filter-btn:hover {
  background: rgba(244, 63, 94, 0.1) !important;
}

/* ── Alert Boxes ────────────────────────────────────────── */
.alert-box {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 0.85rem;
}
.alert-box strong { display: block; margin-bottom: 2px; }
.alert-box p { margin: 0; opacity: 0.85; }
.alert-box--warning {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.25);
  color: #f59e0b;
}
.alert-box--success {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: #10b981;
}
.alert-box--error {
  background: rgba(244, 63, 94, 0.08);
  border: 1px solid rgba(244, 63, 94, 0.25);
  color: #f43f5e;
}

/* ── Premium Table ──────────────────────────────────────── */
.premium-card {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.05);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
  border-radius: 16px;
}
.panel-full { padding: 20px; }

.table-container {
  max-height: 580px;
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface-1);
}
.premium-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
  min-width: 950px;
}
.premium-table th {
  position: sticky;
  top: 0;
  background: rgba(15, 23, 42, 0.96);
  backdrop-filter: blur(8px);
  padding: 12px 16px;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--text-2);
  border-bottom: 1px solid var(--border);
  z-index: 10;
}
.premium-table td {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
  font-size: 0.85rem;
  color: var(--text-1);
  vertical-align: middle;
}
.table-row-hover { transition: background 0.15s; }
.table-row-hover:hover { background: rgba(255, 255, 255, 0.025); }
.row-selected { background: rgba(56, 189, 248, 0.04) !important; }

/* ── Columns Width & Cells ──────────────────────────────── */
.th-checkbox, .td-checkbox { width: 44px; text-align: center; }
.th-status, .td-status { width: 95px; }
.th-device, .td-device { width: 230px; }
.th-pppoe, .td-pppoe { width: 200px; }
.th-rx, .td-rx { width: 130px; }
.th-wifi, .td-wifi { width: 180px; }
.th-inform, .td-inform { width: 135px; }
.th-actions, .td-actions { width: 130px; }

/* Status Cell */
.status-cell {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.status-glow {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}
.status-glow.online { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.8); }
.status-glow.offline { background: #6b7280; box-shadow: 0 0 6px rgba(107, 114, 128, 0.4); }
.status-label { font-size: 0.78rem; font-weight: 600; }

/* Device Cell */
.device-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.code-copy-row {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.sn-text {
  font-weight: 600;
  color: var(--text-1);
  font-size: 0.85rem;
  letter-spacing: 0.02em;
}
.mac-code {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  color: var(--text-2);
  background: var(--surface-2);
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}
.vendor-model-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.7rem;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.vendor-name { font-weight: 600; color: #38bdf8; }
.separator { opacity: 0.4; }

.btn-copy-tiny {
  background: transparent;
  border: none;
  color: var(--text-3);
  cursor: pointer;
  padding: 2px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}
.btn-copy-tiny:hover {
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.1);
}

/* PPPoE Cell */
.pppoe-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.pppoe-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.82rem;
  font-weight: 600;
  color: #38bdf8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 170px;
}
.link-icon { opacity: 0.7; flex-shrink: 0; }
.unlinked-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  padding: 1px 8px;
  border-radius: 6px;
  width: fit-content;
}
.dot-warn { font-size: 0.65rem; }
.ip-code {
  font-family: var(--font-mono);
  font-size: 0.75rem;
  color: var(--text-3);
}

/* RX Optical Pill */
.dbm-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 600;
  font-family: var(--font-mono);
  font-size: 0.76rem;
  min-width: 90px;
  border: 1px solid transparent;
}
.dbm-good { background: rgba(52, 211, 153, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.2); }
.dbm-warning { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.2); }
.dbm-critical { background: rgba(251, 113, 133, 0.1); color: #fb7185; border-color: rgba(251, 113, 133, 0.25); }
.dbm-offline { background: rgba(255, 255, 255, 0.03); color: var(--text-3); border-color: rgba(255, 255, 255, 0.05); }
.dbm-icon { flex-shrink: 0; opacity: 0.8; }

/* WiFi Cell */
.wifi-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.wifi-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 170px;
}
.wifi-badge svg { color: var(--text-2); flex-shrink: 0; }
.wifi-name {
  font-size: 0.82rem;
  color: var(--text-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.band-tag {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  width: fit-content;
}
.band-tag--5g { background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.2); }
.band-tag--24g { background: rgba(255, 255, 255, 0.04); color: var(--text-3); }

/* Inform Cell */
.inform-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.inform-text {
  font-size: 0.8rem;
  font-weight: 500;
}
.inform-fresh { color: #34d399; }
.inform-stale { color: #fbbf24; }
.inform-dead { color: #fb7185; }
.inform-sub-time { font-size: 0.7rem; color: var(--text-3); }

/* Action Buttons */
.action-buttons-row {
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}
.btn-action-icon {
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text-2);
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-action-icon:hover {
  background: var(--surface-3);
  color: var(--text-1);
  border-color: var(--border-hover);
}
.btn-action-icon--primary {
  color: #38bdf8;
  background: rgba(56, 189, 248, 0.05);
  border-color: rgba(56, 189, 248, 0.2);
}
.btn-action-icon--primary:hover {
  background: #38bdf8;
  color: #0f172a;
}
.btn-action-icon--cyan {
  color: #22d3ee;
  background: rgba(34, 211, 238, 0.05);
  border-color: rgba(34, 211, 238, 0.2);
}
.btn-action-icon--cyan:hover {
  background: #22d3ee;
  color: #0f172a;
}
.btn-action-icon--danger:hover {
  background: rgba(244, 63, 94, 0.12);
  color: #f43f5e;
  border-color: rgba(244, 63, 94, 0.35);
}

/* Pagination */
.pagination-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}
.pagination-info { font-size: 0.82rem; color: var(--text-2); }
.pagination-info strong { color: var(--text-1); }
.pagination-actions { display: flex; align-items: center; gap: 6px; }
.page-number-pill {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-2);
  padding: 4px 10px;
  background: var(--surface-2);
  border-radius: 6px;
  border: 1px solid var(--border);
}

/* Empty & Loading States */
.loading-state, .empty-state {
  text-align: center;
  padding: 48px 16px;
}
.loading-state-content, .empty-state-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.empty-state-icon { font-size: 2.2rem; margin-bottom: 4px; }
.empty-state h4 { margin: 0; color: var(--text-1); font-size: 1.1rem; }
.empty-state p { margin: 0; color: var(--text-3); font-size: 0.85rem; }

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  border: 1px solid transparent;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
.btn-primary:hover:not(:disabled) { background: var(--accent-hover); box-shadow: 0 0 16px var(--accent-glow); }
.btn-secondary { background: var(--surface-2); color: var(--text-2); border-color: var(--border); }
.btn-secondary:hover:not(:disabled) { background: var(--surface-3); color: var(--text-1); }
.btn-outline { background: transparent; color: var(--text-2); border: 1px solid var(--border); }
.btn-outline:hover:not(:disabled) { background: var(--surface-2); color: var(--text-1); }
.btn-sm { padding: 5px 10px; font-size: 0.78rem; }
.btn-danger { background: #f43f5e; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #e11d48; }
.btn-danger-outline { background: transparent; color: #f43f5e; border-color: rgba(244, 63, 94, 0.3); }
.btn-danger-outline:hover:not(:disabled) { background: rgba(244, 63, 94, 0.1); }

/* Bulk Action Bar */
.bulk-action-bar {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(56, 189, 248, 0.3);
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5), 0 0 20px rgba(56, 189, 248, 0.1);
  padding: 10px 20px;
  border-radius: 100px;
  display: flex;
  align-items: center;
  gap: 20px;
  z-index: 100;
}
.bulk-info { font-size: 0.85rem; color: var(--text-2); }
.bulk-count {
  font-weight: 700;
  color: #fff;
  background: var(--accent);
  padding: 2px 8px;
  border-radius: 10px;
  margin-right: 4px;
}
.bulk-actions { display: flex; gap: 8px; }

.fade-in-up { animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fadeInUp {
  from { opacity: 0; transform: translate(-50%, 16px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}

/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.5); }

/* Phase 3 Styles */
.stat-unprovisioned .stat-icon-wrap {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}

.device-meta-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.35rem;
}

.pill-area {
  font-size: 0.6875rem;
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
  border: 1px solid rgba(56, 189, 248, 0.25);
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-weight: 500;
}

.pill-prov {
  font-size: 0.6875rem;
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.25);
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-weight: 600;
}

.pill-unprov {
  font-size: 0.6875rem;
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.25);
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-weight: 600;
}

.btn-action-icon--amber {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
}

.btn-action-icon--amber:hover {
  background: #f59e0b;
  color: #0f172a;
}

.btn-info-outline {
  background: transparent;
  border: 1px solid rgba(56, 189, 248, 0.4);
  color: #38bdf8;
}

.btn-info-outline:hover {
  background: rgba(56, 189, 248, 0.15);
  color: #fff;
}

.badge-all-matching {
  font-size: 0.75rem;
  color: #38bdf8;
  font-weight: 600;
  margin-left: 0.35rem;
}

.bulk-select-matching-banner {
  padding: 0.35rem 0.75rem;
  background: rgba(56, 189, 248, 0.12);
  border: 1px dashed rgba(56, 189, 248, 0.3);
  border-radius: 0.375rem;
  margin-right: 0.75rem;
}

.btn-link {
  background: transparent;
  border: none;
  color: #38bdf8;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
  font-size: 0.75rem;
}

.btn-link:hover {
  color: #7dd3fc;
}

.prompt-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1060;
  padding: 1.5rem;
}

.prompt-modal {
  width: 100%;
  max-width: 480px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.75rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  overflow: hidden;
}

.prompt-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.5);
}

.prompt-header h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #f8fafc;
}

.prompt-body {
  padding: 1.25rem;
}

.prompt-desc {
  font-size: 0.8125rem;
  color: #94a3b8;
  margin-bottom: 1rem;
}

.prompt-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.875rem 1.25rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(30, 41, 59, 0.4);
}

/* Responsive */
@media (max-width: 1200px) {
  .analytics-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
  .dashboard__content { padding: 16px; }
  .analytics-grid { grid-template-columns: repeat(2, 1fr); }
  .filter-bar-card { flex-direction: column; align-items: stretch; }
  .filter-bar-left { flex-direction: column; align-items: stretch; }
  .search-wrap { max-width: none; min-width: auto; }
}
</style>
