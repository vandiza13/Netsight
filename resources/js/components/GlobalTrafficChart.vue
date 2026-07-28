<template>
  <div class="global-traffic-dashboard">
    <div class="header">
      <h3 class="title">Live Router Traffic Monitoring</h3>
      <p class="subtitle">Real-time throughput on monitored interfaces (refreshed every 10s)</p>
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
            <h4 class="router-name">{{ chart.router_name }}</h4>
            <span class="interface-badge">{{ chart.interface }}</span>
          </div>
          <div class="current-speed">
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
        </div>
        
        <div class="chart-body">
          <Line :data="chart.chartData" :options="chartOptions" class="canvas-chart" />
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
import axios from 'axios';

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
let pollInterval = null;

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  animation: {
    duration: 0 // Disable animation for smoother live updates
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
      display: false,
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

const formatSpeed = (bps) => {
  if (bps === 0 || !bps) return '0 bps';
  const k = 1000;
  const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps'];
  const i = Math.floor(Math.log(bps) / Math.log(k));
  return parseFloat((bps / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatTime = (timestamp) => {
  const date = new Date(timestamp * 1000);
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}:${date.getSeconds().toString().padStart(2, '0')}`;
};

const fetchTrafficData = async () => {
  try {
    const response = await axios.get('/api/traffic/dashboard', {
        headers: {
            'X-Demo-Schema': window.location.hostname.includes('demo') ? 'true' : ''
        }
    });
    const newData = response.data.data;
    
    // Update existing charts or create new ones
    newData.forEach(routerData => {
      let existingChart = chartsData.value.find(c => c.router_id === routerData.router_id);
      
      const timeStr = formatTime(routerData.timestamp);
      
      if (!existingChart) {
        // Initialize new chart
        existingChart = {
          router_id: routerData.router_id,
          router_name: routerData.router_name,
          interface: routerData.interface,
          latest_rx: routerData.rx,
          latest_tx: routerData.tx,
          chartData: {
            labels: [timeStr],
            datasets: [
              {
                label: 'RX (Download)',
                backgroundColor: 'rgba(6, 182, 212, 0.1)',
                borderColor: '#06b6d4',
                data: [routerData.rx],
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2
              },
              {
                label: 'TX (Upload)',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderColor: '#f59e0b',
                data: [routerData.tx],
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                borderWidth: 2
              }
            ]
          }
        };
        chartsData.value.push(existingChart);
      } else {
        // Update existing chart
        existingChart.interface = routerData.interface;
        existingChart.latest_rx = routerData.rx;
        existingChart.latest_tx = routerData.tx;
        
        existingChart.chartData.labels.push(timeStr);
        existingChart.chartData.datasets[0].data.push(routerData.rx);
        existingChart.chartData.datasets[1].data.push(routerData.tx);
        
        // Trim history
        if (existingChart.chartData.labels.length > MAX_POINTS) {
          existingChart.chartData.labels.shift();
          existingChart.chartData.datasets[0].data.shift();
          existingChart.chartData.datasets[1].data.shift();
        }
        
        // Trigger reactivity (Vue-Chartjs requires creating a new object)
        existingChart.chartData = { ...existingChart.chartData };
      }
    });
    
  } catch (error) {
    console.error("Failed to fetch traffic dashboard data:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchTrafficData();
  // Poll every 10 seconds
  pollInterval = setInterval(fetchTrafficData, 10000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<style scoped>
.global-traffic-dashboard {
  background: rgba(30, 41, 59, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
  backdrop-filter: blur(12px);
}

.header {
  margin-bottom: 1.5rem;
}

.title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #f8fafc;
  margin: 0 0 0.25rem 0;
}

.subtitle {
  font-size: 0.875rem;
  color: #94a3b8;
  margin: 0;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 3rem 0;
  color: #94a3b8;
  gap: 1rem;
}

.spinner {
  width: 1.5rem;
  height: 1.5rem;
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  background: rgba(15, 23, 42, 0.3);
  border-radius: 0.75rem;
  border: 1px dashed rgba(255, 255, 255, 0.1);
}

.empty-icon {
  width: 3rem;
  height: 3rem;
  color: #475569;
  margin: 0 auto 1rem;
}

.empty-state p {
  color: #f8fafc;
  font-weight: 500;
  margin: 0 0 0.5rem 0;
}

.empty-hint {
  display: block;
  color: #94a3b8;
  font-size: 0.875rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.25rem;
}

.chart-card {
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 0.75rem;
  overflow: hidden;
}

.chart-header {
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.router-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.router-name {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #f8fafc;
}

.interface-badge {
  font-size: 0.75rem;
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  padding: 0.125rem 0.5rem;
  border-radius: 1rem;
  display: inline-block;
  width: fit-content;
}

.current-speed {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: rgba(0, 0, 0, 0.2);
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
}

.speed-rx, .speed-tx {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.label {
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.05em;
}

.speed-rx .label { color: #06b6d4; }
.speed-tx .label { color: #f59e0b; }

.value {
  font-size: 0.875rem;
  font-weight: 600;
  color: #f8fafc;
  font-variant-numeric: tabular-nums;
}

.speed-divider {
  width: 1px;
  height: 20px;
  background: rgba(255, 255, 255, 0.1);
}

.chart-body {
  height: 150px;
  padding: 1rem;
  position: relative;
}
</style>
