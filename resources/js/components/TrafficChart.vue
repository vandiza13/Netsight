<template>
  <div class="traffic-chart-container">
    <div class="chart-header">
      <h3 class="chart-title">
        <span class="pulse-dot"></span>
        Global Network Traffic (Live)
      </h3>
      <div class="chart-legend">
        <div class="legend-item"><span class="color-box rx"></span> RX (Download)</div>
        <div class="legend-item"><span class="color-box tx"></span> TX (Upload)</div>
      </div>
    </div>
    
    <div class="chart-wrapper">
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, shallowRef } from 'vue'
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
} from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

// Constants
const MAX_POINTS = 30
let rxBase = 850
let txBase = 320

// State
const labels = ref<string[]>([])
const rxData = ref<number[]>([])
const txData = ref<number[]>([])
let updateInterval: ReturnType<typeof setInterval> | null = null

// Vue-chartjs expected format
const chartData = shallowRef({
  labels: [] as string[],
  datasets: [
    {
      label: 'RX (Mbps)',
      borderColor: '#22d3ee', // Cyan
      backgroundColor: 'rgba(34, 211, 238, 0.15)',
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 4,
      fill: true,
      tension: 0.4,
      data: [] as number[]
    },
    {
      label: 'TX (Mbps)',
      borderColor: '#f5a623', // Amber/Orange
      backgroundColor: 'rgba(245, 166, 35, 0.15)',
      borderWidth: 2,
      pointRadius: 0,
      pointHoverRadius: 4,
      fill: true,
      tension: 0.4,
      data: [] as number[]
    }
  ]
})

// Initialize with some mock historical data
function initData() {
  const now = new Date()
  const tempLabels = []
  const tempRx = []
  const tempTx = []
  
  for (let i = MAX_POINTS; i > 0; i--) {
    const t = new Date(now.getTime() - i * 3000)
    tempLabels.push(t.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }))
    
    // Add some random noise
    rxBase = Math.max(100, rxBase + (Math.random() * 100 - 50))
    txBase = Math.max(50, txBase + (Math.random() * 60 - 30))
    
    tempRx.push(Math.round(rxBase))
    tempTx.push(Math.round(txBase))
  }
  
  labels.value = tempLabels
  rxData.value = tempRx
  txData.value = tempTx
  
  chartData.value = {
    labels: [...labels.value],
    datasets: [
      { ...chartData.value.datasets[0], data: [...rxData.value] },
      { ...chartData.value.datasets[1], data: [...txData.value] }
    ]
  }
}

// Push a new data point
function updateData() {
  const now = new Date()
  
  // Shift arrays
  labels.value.shift()
  rxData.value.shift()
  txData.value.shift()
  
  labels.value.push(now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' }))
  
  // Random walk
  rxBase = Math.max(100, Math.min(2000, rxBase + (Math.random() * 150 - 75)))
  txBase = Math.max(50, Math.min(1000, txBase + (Math.random() * 80 - 40)))
  
  rxData.value.push(Math.round(rxBase))
  txData.value.push(Math.round(txBase))
  
  // Trigger reactivity for Chart.js
  chartData.value = {
    labels: [...labels.value],
    datasets: [
      { ...chartData.value.datasets[0], data: [...rxData.value] },
      { ...chartData.value.datasets[1], data: [...txData.value] }
    ]
  }
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  animation: {
    duration: 500, // Smooth slide
    easing: 'linear' as const
  },
  interaction: {
    mode: 'index' as const,
    intersect: false,
  },
  plugins: {
    legend: {
      display: false // We use our own custom HTML legend
    },
    tooltip: {
      backgroundColor: 'rgba(10, 14, 20, 0.9)',
      titleColor: '#8b96a5',
      bodyColor: '#e8ecf1',
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      padding: 12,
      cornerRadius: 8,
      displayColors: true,
    }
  },
  scales: {
    x: {
      grid: {
        color: 'rgba(255, 255, 255, 0.03)',
      },
      ticks: {
        color: '#5c6774',
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 6
      }
    },
    y: {
      grid: {
        color: 'rgba(255, 255, 255, 0.05)',
      },
      ticks: {
        color: '#5c6774',
        callback: function(value: any) {
          return value + ' M';
        }
      },
      beginAtZero: true
    }
  }
}

onMounted(() => {
  initData()
  // Start pushing new data every 3 seconds
  updateInterval = setInterval(updateData, 3000)
})

onBeforeUnmount(() => {
  if (updateInterval) clearInterval(updateInterval)
})
</script>

<style scoped>
.traffic-chart-container {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.chart-title {
  font-size: 1.1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-primary);
}

.pulse-dot {
  width: 10px;
  height: 10px;
  background: var(--accent-green);
  border-radius: 50%;
  box-shadow: 0 0 10px var(--accent-green);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.chart-legend {
  display: flex;
  gap: 16px;
  font-size: 0.85rem;
  color: var(--text-secondary);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.color-box {
  width: 12px;
  height: 12px;
  border-radius: 3px;
}

.color-box.rx { background: #22d3ee; }
.color-box.tx { background: #f5a623; }

.chart-wrapper {
  position: relative;
  flex: 1;
  min-height: 250px;
}
</style>
