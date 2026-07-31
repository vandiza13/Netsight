<template>
  <div class="global-traffic-dashboard">
    <div class="header">
      <div>
        <h3 class="title">Live Router Traffic Monitoring</h3>
        <p class="subtitle">Real-time throughput & historical trends for monitored interfaces</p>
      </div>

      <div class="time-range-selector">
        <button 
          v-for="range in rangeOptions" 
          :key="range.value"
          :class="['range-btn', { active: selectedRange === range.value }]"
          @click="selectRange(range.value)"
        >
          {{ range.label }}
        </button>
      </div>
    </div>

    <div v-if="loading && chartsData.length === 0" class="loading-state">
      <div class="spinner"></div>
      <span>Memuat data traffic...</span>
    </div>

    <div v-else-if="chartsData.length === 0" class="empty-state">
      <svg class="empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
      </svg>
      <p>Belum ada router yang dikonfigurasi untuk monitoring SNMP.</p>
      <span class="empty-hint">Aktifkan SNMP Community dan pilih interface yang akan dipantau pada pengaturan router.</span>
    </div>

    <div v-else class="charts-grid">
      <div v-for="chart in chartsData" :key="chart.router_id" class="chart-card">
        <div class="chart-header">
          <div class="router-info">
            <h4 class="router-name">
              {{ chart.router_name }}
              <span v-if="chart.status === 'offline'" class="offline-badge">SNMP Offline</span>
            </h4>
            <span class="interface-badge">{{ chart.interface }}</span>
          </div>
          <div v-if="selectedRange === 'live'" class="current-speed">
            <span style="font-size: 10px; color: gray; margin-right: 10px;">{{ chart.chartData.labels.length }} pts</span>
            <div class="speed-rx">
              <span class="label">RX (In)</span>
              <span class="value">{{ formatSpeed(chart.latest_rx) }}</span>
            </div>
            <div class="speed-divider"></div>
            <div class="speed-tx">
              <span class="label">TX (Out)</span>
              <span class="value">{{ formatSpeed(chart.latest_tx) }}</span>
            </div>
          </div>
          
          <div v-else-if="chart.stats" class="historical-stats">
            <div class="stats-grid">
              <div class="stat-cell">
                <span class="stat-title">MAX</span>
                <span class="val-rx">↓{{ formatSpeedShort(chart.stats.maxRx) }}</span>
                <span class="val-tx">↑{{ formatSpeedShort(chart.stats.maxTx) }}</span>
              </div>
              <div class="stat-cell">
                <span class="stat-title">MIN</span>
                <span class="val-rx">↓{{ formatSpeedShort(chart.stats.minRx) }}</span>
                <span class="val-tx">↑{{ formatSpeedShort(chart.stats.minTx) }}</span>
              </div>
              <div class="stat-cell">
                <span class="stat-title">AVG</span>
                <span class="val-rx">↓{{ formatSpeedShort(chart.stats.avgRx) }}</span>
                <span class="val-tx">↑{{ formatSpeedShort(chart.stats.avgTx) }}</span>
              </div>
              <div class="stat-cell">
                <span class="stat-title">P95</span>
                <span class="val-rx">↓{{ formatSpeedShort(chart.stats.p95Rx) }}</span>
                <span class="val-tx">↑{{ formatSpeedShort(chart.stats.p95Tx) }}</span>
              </div>
            </div>
            
            <div class="stat-divider"></div>
            
            <div class="stat-column volume-col">
                <span class="stat-title">Volume ({{ chart.range.toUpperCase() }})</span>
                <div class="volume-total">{{ formatVolume(chart.stats.totalRxBytes + chart.stats.totalTxBytes) }}</div>
                <div class="volume-details">
                    <span class="val-rx">↓{{ formatVolume(chart.stats.totalRxBytes) }}</span>
                    <span class="val-tx">↑{{ formatVolume(chart.stats.totalTxBytes) }}</span>
                </div>
            </div>
          </div>
        </div>
        
        <div class="chart-body">
          <Line :data="chart.chartData" :options="getChartOptions(selectedRange)" class="canvas-chart" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'vue-chartjs';
import api from '../utils/api';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const MAX_POINTS = 60; // 10 minutes (60 * 10s)
const chartsData = ref([]);
const loading = ref(true);
const selectedRange = ref('live');
let pollInterval = null;

const rangeOptions = [
  { label: 'Live (10m)', value: 'live' },
  { label: '1H', value: '1h' },
  { label: '3H', value: '3h' },
  { label: '6H', value: '6h' },
  { label: '12H', value: '12h' },
  { label: '24H', value: '24h' },
  { label: '7D', value: '7d' },
  { label: '30D', value: '30d' },
];

const getChartOptions = (range) => {
  const isLive = range === 'live';
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
      duration: isLive ? 0 : 200
    },
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        backgroundColor: 'rgba(15, 23, 42, 0.9)',
        titleColor: '#fff',
        bodyColor: '#cbd5e1',
        borderColor: 'rgba(51, 65, 85, 0.5)',
        borderWidth: 1,
        padding: 10,
        callbacks: {
          label: function(context) {
            return `${context.dataset.label}: ${formatSpeed(context.raw)}`;
          }
        }
      }
    },
    scales: {
      x: {
        display: !isLive,
        grid: {
          color: 'rgba(255, 255, 255, 0.05)',
        },
        ticks: {
          color: '#64748b',
          maxTicksLimit: 8,
          font: { size: 10 }
        }
      },
      y: {
        display: true,
        grid: {
          color: 'rgba(255, 255, 255, 0.05)',
        },
        ticks: {
          color: '#94a3b8',
          callback: function(value) {
            return formatSpeed(value);
          },
          maxTicksLimit: 5
        },
        beginAtZero: true
      }
    }
  };
};

const formatSpeed = (bps) => {
  if (bps === 0 || !bps) return '0 bps';
  if (bps < 1000) return Math.round(bps) + ' bps';
  const k = 1000;
  const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
  const i = Math.floor(Math.log(bps) / Math.log(k));
  const sizeIndex = Math.min(Math.max(i, 0), sizes.length - 1);
  return parseFloat((bps / Math.pow(k, sizeIndex)).toFixed(1)) + ' ' + sizes[sizeIndex];
};

const formatSpeedShort = (bps) => {
  if (bps === 0 || !bps) return '0b';
  if (bps < 1000) return Math.round(bps) + 'b';
  const k = 1000;
  const sizes = ['b', 'Kb', 'Mb', 'Gb', 'Tb'];
  const i = Math.floor(Math.log(bps) / Math.log(k));
  const sizeIndex = Math.min(Math.max(i, 0), sizes.length - 1);
  return parseFloat((bps / Math.pow(k, sizeIndex)).toFixed(1)) + sizes[sizeIndex];
};

const formatVolume = (bytes) => {
  if (bytes === 0 || !bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  const sizeIndex = Math.min(Math.max(i, 0), sizes.length - 1);
  return parseFloat((bytes / Math.pow(k, sizeIndex)).toFixed(2)) + ' ' + sizes[sizeIndex];
};

const formatTime = (timestamp, range = 'live') => {
  if (!timestamp) return '';
  const date = new Date(timestamp * 1000);
  const hours = date.getHours().toString().padStart(2, '0');
  const minutes = date.getMinutes().toString().padStart(2, '0');
  const seconds = date.getSeconds().toString().padStart(2, '0');
  const day = date.getDate().toString().padStart(2, '0');
  const month = (date.getMonth() + 1).toString().padStart(2, '0');

  if (range === 'live') {
    return `${hours}:${minutes}:${seconds}`;
  } else if (range === '7d' || range === '30d') {
    return `${day}/${month} ${hours}:${minutes}`;
  } else {
    return `${hours}:${minutes}`;
  }
};

const createChartObject = (routerData, labels, rxData, txData, range) => {
  const isLive = range === 'live';
  
  let stats = null;
  if (!isLive && rxData.length > 0 && txData.length > 0) {
    const sortedRx = [...rxData].sort((a, b) => a - b);
    const sortedTx = [...txData].sort((a, b) => a - b);
    
    const maxRx = sortedRx[sortedRx.length - 1];
    const maxTx = sortedTx[sortedTx.length - 1];
    const minRx = sortedRx[0];
    const minTx = sortedTx[0];
    
    const avgRx = rxData.reduce((a, b) => a + b, 0) / rxData.length;
    const avgTx = txData.reduce((a, b) => a + b, 0) / txData.length;
    
    const p95Idx = Math.floor(rxData.length * 0.95);
    const p95Rx = sortedRx[p95Idx];
    const p95Tx = sortedTx[p95Idx];

    let totalRxBytes = 0;
    let totalTxBytes = 0;
    const points = routerData.points || [];
    
    for (let i = 1; i < points.length; i++) {
        const p0 = points[i-1];
        const p1 = points[i];
        let deltaSeconds = p1.timestamp - p0.timestamp;
        
        // Cap max interval to 10 mins (600s) to avoid massive jumps if data was missing
        if (deltaSeconds > 600) deltaSeconds = 600;
        if (deltaSeconds < 0) deltaSeconds = 0;

        totalRxBytes += (p0.rx / 8) * deltaSeconds;
        totalTxBytes += (p0.tx / 8) * deltaSeconds;
    }

    stats = {
        maxRx, maxTx, minRx, minTx, avgRx, avgTx, p95Rx, p95Tx, totalRxBytes, totalTxBytes
    };
  }

  return {
    router_id: routerData.router_id,
    router_name: routerData.router_name,
    interface: routerData.interface,
    latest_rx: routerData.rx,
    latest_tx: routerData.tx,
    status: routerData.status,
    range: range,
    stats: stats,
    chartData: {
      labels: labels,
      datasets: [
        {
          label: 'RX (Download)',
          backgroundColor: 'rgba(6, 182, 212, 0.1)',
          borderColor: '#06b6d4',
          data: rxData,
          fill: true,
          tension: 0.4,
          pointRadius: isLive ? 1 : 0,
          pointHoverRadius: 4,
          borderWidth: 2
        },
        {
          label: 'TX (Upload)',
          backgroundColor: 'rgba(245, 158, 11, 0.1)',
          borderColor: '#f59e0b',
          data: txData,
          fill: true,
          tension: 0.4,
          pointRadius: isLive ? 1 : 0,
          pointHoverRadius: 4,
          borderWidth: 2
        }
      ]
    }
  };
};

const fetchTrafficData = async () => {
  try {
    const range = selectedRange.value;
    const response = await api.get(`/traffic/dashboard?range=${range}&_t=${Date.now()}`);
    const newData = response.data.data;

    if (range === 'live') {
      newData.forEach(routerData => {
        let existingChart = chartsData.value.find(c => c.router_id === routerData.router_id);
        
        const points = routerData.points || [];
        const labels = points.map(p => formatTime(p.timestamp, 'live'));
        const rxData = points.map(p => p.rx);
        const txData = points.map(p => p.tx);

        if (!existingChart || existingChart.range !== 'live') {
          const newChart = createChartObject(routerData, labels, rxData, txData, 'live');
          const idx = chartsData.value.findIndex(c => c.router_id === routerData.router_id);
          if (idx !== -1) {
            chartsData.value[idx] = newChart;
          } else {
            chartsData.value.push(newChart);
          }
        } else {
          // It's already running, just append the LATEST point to keep it live
          const lastPoint = points.length > 0 ? points[points.length - 1] : { rx: 0, tx: 0, timestamp: Date.now() / 1000 };
          const timeStr = formatTime(lastPoint.timestamp, 'live');

          // Only push if it's a new timestamp
          const lastLabel = existingChart.chartData.labels[existingChart.chartData.labels.length - 1];
          if (lastLabel !== timeStr) {
            const currentLabels = [...existingChart.chartData.labels, timeStr];
            const currentRx = [...existingChart.chartData.datasets[0].data, lastPoint.rx];
            const currentTx = [...existingChart.chartData.datasets[1].data, lastPoint.tx];

            if (currentLabels.length > MAX_POINTS) {
              currentLabels.shift();
              currentRx.shift();
              currentTx.shift();
            }

            const newChart = createChartObject(routerData, currentLabels, currentRx, currentTx, 'live');
            const idx = chartsData.value.findIndex(c => c.router_id === routerData.router_id);
            if (idx !== -1) {
              chartsData.value[idx] = newChart;
            }
          }
        }
      });
    } else {
      // Historical mode logic (full replace)
      chartsData.value = newData.map(routerData => {
        const points = routerData.points || [];
        const labels = points.map(p => formatTime(p.timestamp, range));
        const rxData = points.map(p => p.rx);
        const txData = points.map(p => p.tx);

        return createChartObject(routerData, labels, rxData, txData, range);
      });
    }
  } catch (error) {
    console.error("Failed to fetch traffic dashboard data:", error);
  } finally {
    loading.value = false;
  }
};

const selectRange = (rangeValue) => {
  if (selectedRange.value === rangeValue) return;
  selectedRange.value = rangeValue;
  loading.value = true;

  if (pollInterval) {
    clearInterval(pollInterval);
    pollInterval = null;
  }

  fetchTrafficData();

  if (rangeValue === 'live') {
    pollInterval = setInterval(fetchTrafficData, 10000);
  }
};

onMounted(() => {
  fetchTrafficData();
  pollInterval = setInterval(fetchTrafficData, 10000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<style scoped>
.global-traffic-dashboard {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 1rem;
  padding: 1.5rem;
  transition: background var(--transition-base), border-color var(--transition-base);
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.title {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-1);
  margin: 0 0 0.25rem 0;
}

.subtitle {
  font-size: 0.85rem;
  color: var(--text-2);
  margin: 0;
}

.time-range-selector {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  background: var(--surface-2);
  padding: 0.25rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
}

.range-btn {
  background: transparent;
  border: none;
  color: var(--text-2);
  padding: 0.25rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 500;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.range-btn:hover {
  color: var(--text-1);
  background: var(--surface-3);
}

.range-btn.active {
  background: var(--accent);
  color: #fff;
  font-weight: 600;
  box-shadow: 0 2px 4px rgba(99, 102, 241, 0.3);
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  color: var(--text-2);
  text-align: center;
}

.spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid var(--border);
  border-radius: 50%;
  border-top-color: var(--accent);
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  width: 3rem;
  height: 3rem;
  color: var(--text-3);
  margin-bottom: 1rem;
}

.empty-hint {
  font-size: 0.75rem;
  color: var(--text-3);
  margin-top: 0.5rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.25rem;
}

.chart-card {
  background: var(--surface-1);
  border: 1px solid var(--border);
  border-radius: 0.75rem;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  transition: background var(--transition-base), border-color var(--transition-base);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.router-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.router-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-1);
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}

.offline-badge {
  font-size: 0.7rem;
  background-color: var(--danger);
  color: white;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-weight: bold;
}

.interface-badge {
  font-size: 0.75rem;
  background: var(--accent-dim);
  color: var(--accent);
  padding: 0.125rem 0.5rem;
  border-radius: 1rem;
  display: inline-block;
  width: fit-content;
  white-space: nowrap;
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.current-speed {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: var(--surface-2);
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  white-space: nowrap;
  border: 1px solid var(--border);
}

.speed-rx, .speed-tx {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}

.speed-rx .label {
  color: #06b6d4;
}

.speed-tx .label {
  color: #f59e0b;
}

.value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-1);
  font-family: var(--font-mono);
}

.speed-divider {
  width: 1px;
  height: 24px;
  background: var(--border);
}

.chart-body {
  height: 200px;
  position: relative;
}

.historical-stats {
  display: flex;
  gap: 1.25rem;
  align-items: stretch;
  background: var(--surface-2);
  padding: 0.5rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--border);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.25rem;
}

.stat-cell {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
  font-size: 0.75rem;
  font-family: var(--font-mono);
}

.stat-column {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.15rem;
}

.stat-title {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  color: var(--text-2);
  margin-bottom: 0.1rem;
}

.stat-divider {
  width: 1px;
  background: var(--border);
}

.volume-col {
  align-items: flex-end;
}

.volume-total {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-1);
  line-height: 1;
}

.volume-details {
  display: flex;
  gap: 0.75rem;
  font-size: 0.7rem;
  font-family: var(--font-mono);
  margin-top: 0.1rem;
}
</style>
