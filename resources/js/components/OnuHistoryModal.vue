<template>
  <div class="modal-backdrop" @click.self="closeModal">
    <div class="glass-card modal-content-box fade-in-up" style="max-width: 800px; width: 90%;">
      <div class="modal-header-row">
        <h3>Histori Redaman Optik (RX) - ONU {{ onu?.onu_index }}</h3>
        <button class="btn-close" @click="closeModal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>

      <div class="modal-body-scroll custom-scrollbar" style="min-height: 400px; display: flex; flex-direction: column;">
        
        <div v-if="loading" class="loading-state" style="margin: auto;">
          <svg class="spinning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;margin-right:6px;display:inline-block;vertical-align:middle;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg> Memuat data histori...
        </div>
        
        <div v-else-if="histories.length === 0" class="empty-state" style="margin: auto; text-align: center; color: var(--text-secondary);">
          Tidak ada data histori redaman untuk 7 hari terakhir.
        </div>
        
        <div v-else class="chart-container" style="flex: 1; position: relative; height: 350px;">
          <Line :data="chartData" :options="chartOptions" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
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
import { useToastStore } from '../stores/toastStore';

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

const props = defineProps<{
  onu: any;
}>();

const emit = defineEmits(['close']);
const toastStore = useToastStore();

const loading = ref(true);
const histories = ref<any[]>([]);

const closeModal = () => {
  emit('close');
};

const fetchHistory = async () => {
  try {
    loading.value = true;
    const response = await api.get(`/olts/onus/${props.onu.id}/history`);
    histories.value = response.data.data;
  } catch (err: any) {
    toastStore.error("Gagal memuat histori: " + err.message);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchHistory();
});

const chartData = computed(() => {
  return {
    labels: histories.value.map(h => {
      const d = new Date(h.created_at);
      return `${d.getDate()}/${d.getMonth()+1} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }),
    datasets: [
      {
        label: 'Rx Power (dBm)',
        data: histories.value.map(h => h.rx_power_dbm),
        borderColor: '#38bdf8',
        backgroundColor: 'rgba(56, 189, 248, 0.1)',
        borderWidth: 2,
        pointBackgroundColor: '#0f172a',
        pointBorderColor: '#38bdf8',
        pointRadius: 3,
        pointHoverRadius: 5,
        fill: true,
        tension: 0.3
      }
    ]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      labels: { color: '#94a3b8' }
    },
    tooltip: {
      mode: 'index' as const,
      intersect: false,
      backgroundColor: 'rgba(15, 23, 42, 0.9)',
      titleColor: '#f8fafc',
      bodyColor: '#cbd5e1',
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
    }
  },
  scales: {
    y: {
      grid: {
        color: 'rgba(255,255,255,0.05)',
      },
      ticks: {
        color: '#94a3b8'
      },
      title: {
        display: true,
        text: 'Redaman (dBm)',
        color: '#64748b'
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        color: '#94a3b8',
        maxRotation: 45,
        minRotation: 45
      }
    }
  },
  interaction: {
    mode: 'nearest' as const,
    axis: 'x' as const,
    intersect: false
  }
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; width: 100vw; height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal-content-box {
  background: var(--surface-1);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.4);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.modal-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border-color);
}
.modal-header-row h3 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--text-primary);
  font-weight: 600;
}
.btn-close {
  background: transparent;
  border: none;
  color: var(--text-secondary);
  cursor: pointer;
  padding: 4px;
  border-radius: 8px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
}
.modal-body-scroll {
  padding: 24px;
  overflow-y: auto;
}
</style>
