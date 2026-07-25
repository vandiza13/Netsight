<template>
  <div class="distribution-chart-container">
    <div class="chart-header">
      <h3 class="chart-title">
        <span class="icon">📊</span> Status Ringkas Router
      </h3>
      <span class="chart-sub">Data Real Server</span>
    </div>

    <div class="chart-wrapper" v-if="routers.length > 0">
      <Bar :data="chartData" :options="chartOptions" />
    </div>
    <div class="empty-state" v-else>
      Tidak ada data router.
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouterStore } from '../stores/routerStore'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'
import { Bar } from 'vue-chartjs'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

const routerStore = useRouterStore()
const { routers } = storeToRefs(routerStore)

const chartData = computed(() => {
  const labels = routers.value.map(r => r.name)
  const backgroundColors = routers.value.map(r => {
    if (r.status === 'HEALTHY') return 'rgba(34, 197, 94, 0.5)'
    if (r.status === 'DEGRADED') return 'rgba(245, 166, 35, 0.5)'
    return 'rgba(239, 68, 68, 0.5)'
  })
  const borderColors = routers.value.map(r => {
    if (r.status === 'HEALTHY') return '#22c55e'
    if (r.status === 'DEGRADED') return '#f5a623'
    return '#ef4444'
  })

  // Value representing health score (Healthy = 100%, Degraded = 50%, Unreachable = 10%)
  const dataValues = routers.value.map(r => {
    if (r.status === 'HEALTHY') return 100
    if (r.status === 'DEGRADED') return 50
    return 10
  })

  return {
    labels,
    datasets: [
      {
        label: 'Kesehatan Router (%)',
        backgroundColor: backgroundColors,
        borderColor: borderColors,
        borderWidth: 1.5,
        borderRadius: 8,
        maxBarThickness: 45,
        data: dataValues
      }
    ]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(10, 14, 20, 0.9)',
      titleColor: '#8b96a5',
      bodyColor: '#e8ecf1',
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      padding: 12,
      cornerRadius: 8,
      callbacks: {
        label: function(context: any) {
          const router = routers.value[context.dataIndex]
          return `Status: ${router?.status || 'Unknown'} (${router?.host})`
        }
      }
    }
  },
  scales: {
    x: {
      grid: { color: 'rgba(255, 255, 255, 0.03)' },
      ticks: { color: '#8b96a5' }
    },
    y: {
      grid: { color: 'rgba(255, 255, 255, 0.05)' },
      ticks: {
        color: '#5c6774',
        callback: function(val: any) {
          return val + '%'
        }
      },
      min: 0,
      max: 100
    }
  }
}
</script>

<style scoped>
.distribution-chart-container {
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

.chart-title .icon {
  font-size: 1.2rem;
}

.chart-sub {
  font-size: 0.8rem;
  color: var(--cyan, #22d3ee);
  background: rgba(34, 211, 238, 0.1);
  padding: 3px 8px;
  border-radius: 4px;
  font-weight: 500;
}

.chart-wrapper {
  position: relative;
  flex: 1;
  min-height: 250px;
}

.empty-state {
  color: var(--text-dim);
  font-size: 0.9rem;
  padding: 40px 0;
  text-align: center;
}
</style>
