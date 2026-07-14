<template>
  <div class="traffic-chart-card">
    <div class="chart-header">
      <h5 class="chart-title">Real-Time Throughput (TX/RX)</h5>
      <div class="chart-legend">
        <span class="legend-item"><span class="legend-dot tx"></span> TX (Download)</span>
        <span class="legend-item"><span class="legend-dot rx"></span> RX (Upload)</span>
      </div>
    </div>
    <div class="chart-container">
      <svg class="chart-svg" viewBox="0 0 500 100" preserveAspectRatio="none">
        <!-- Grids -->
        <line x1="0" y1="33" x2="500" y2="33" class="grid-line" />
        <line x1="0" y1="66" x2="500" y2="66" class="grid-line" />
        
        <!-- Area Fills (Gradients) -->
        <defs>
          <linearGradient id="txGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.25" />
            <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.02" />
          </linearGradient>
          <linearGradient id="rxGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#06b6d4" stop-opacity="0.25" />
            <stop offset="100%" stop-color="#06b6d4" stop-opacity="0.02" />
          </linearGradient>
        </defs>
        <path :d="txAreaPath" class="chart-area tx-area" />
        <path :d="rxAreaPath" class="chart-area rx-area" />

        <!-- Line Paths -->
        <path :d="txLinePath" class="chart-line tx-line" />
        <path :d="rxLinePath" class="chart-line rx-line" />
      </svg>
      <!-- Y-Axis Labels -->
      <div class="y-axis-labels">
        <span class="y-label">{{ formatTraffic(maxY) }}</span>
        <span class="y-label">{{ formatTraffic(maxY * 0.5) }}</span>
        <span class="y-label">0 bps</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  samples: Array<{ tx: number; rx: number }>
}>()

// Max samples to display on the chart width
const maxVisibleSamples = 30

// Normalize samples
const activeSamples = computed(() => {
  const list = props.samples || []
  return list.slice(-maxVisibleSamples)
})

// Calculate Y scale limit (at least 100 Kbps default height scale)
const maxY = computed(() => {
  let max = 100000 // 100 Kbps min scale
  activeSamples.value.forEach(s => {
    if (s.tx > max) max = s.tx
    if (s.rx > max) max = s.rx
  })
  return max * 1.1 // Add 10% headroom
})

// Helper to calculate X and Y coordinates
const getPoints = (type: 'tx' | 'rx') => {
  const list = activeSamples.value
  if (list.length === 0) return []

  const stepX = 500 / (maxVisibleSamples - 1)
  // Shift points to the right if we don't have full samples yet
  const offsetIndex = maxVisibleSamples - list.length
  
  return list.map((sample, index) => {
    const val = type === 'tx' ? sample.tx : sample.rx
    const x = (offsetIndex + index) * stepX
    // SVG y=0 is top, y=100 is bottom (viewBox height)
    const y = 100 - (val / maxY.value) * 100
    return { x, y }
  })
}

// Helper for smooth bezier curves
const getSmoothPath = (pts: {x: number, y: number}[]) => {
  if (pts.length === 0) return 'M 0 150'
  if (pts.length === 1) return `M ${pts[0].x} ${pts[0].y}`
  
  let path = `M ${pts[0].x} ${pts[0].y}`
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = i > 0 ? pts[i - 1] : pts[0]
    const p1 = pts[i]
    const p2 = pts[i + 1]
    const p3 = i !== pts.length - 2 ? pts[i + 2] : p2
    
    const tension = 0.15
    const cp1x = p1.x + (p2.x - p0.x) * tension
    const cp1y = Math.min(Math.max(p1.y + (p2.y - p0.y) * tension, 0), 100)
    const cp2x = p2.x - (p3.x - p1.x) * tension
    const cp2y = Math.min(Math.max(p2.y - (p3.y - p1.y) * tension, 0), 100)
    
    path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`
  }
  return path
}

// Generate path string for lines
const txPoints = computed(() => getPoints('tx'))
const rxPoints = computed(() => getPoints('rx'))

const txLinePath = computed(() => getSmoothPath(txPoints.value))
const rxLinePath = computed(() => getSmoothPath(rxPoints.value))

const svgH = 100 // Must match viewBox height

// Generate area path (closing it at the bottom)
const txAreaPath = computed(() => {
  const pts = txPoints.value
  if (pts.length === 0) return `M 0 ${svgH}`
  const firstX = pts[0].x
  const lastX = pts[pts.length - 1].x
  return `${txLinePath.value} L ${lastX} ${svgH} L ${firstX} ${svgH} Z`
})

const rxAreaPath = computed(() => {
  const pts = rxPoints.value
  if (pts.length === 0) return `M 0 ${svgH}`
  const firstX = pts[0].x
  const lastX = pts[pts.length - 1].x
  return `${rxLinePath.value} L ${lastX} ${svgH} L ${firstX} ${svgH} Z`
})

function formatTraffic(bps: number): string {
  if (bps === 0) return '0 bps'
  if (bps >= 1000000000) return (bps / 1000000000).toFixed(1) + ' Gbps'
  if (bps >= 1000000) return (bps / 1000000).toFixed(1) + ' Mbps'
  if (bps >= 1000) return (bps / 1000).toFixed(0) + ' Kbps'
  return bps.toFixed(0) + ' bps'
}
</script>

<style scoped>
.traffic-chart-card {
  background: rgba(17, 24, 39, 0.4);
  border: 1px solid var(--glass-border);
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 12px;
  backdrop-filter: blur(8px);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.chart-title {
  margin: 0;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.chart-legend {
  display: flex;
  gap: 12px;
  font-size: 0.75rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--text-muted);
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}
.legend-dot.tx { background: #f59e0b; }
.legend-dot.rx { background: #06b6d4; }

.chart-container {
  position: relative;
  height: 100px;
  width: 100%;
}

.chart-svg {
  width: 100%;
  height: 100%;
  overflow: visible;
}

.grid-line {
  stroke: rgba(255, 255, 255, 0.05);
  stroke-width: 0.5;
  stroke-dasharray: 4,4;
}

.chart-line {
  fill: none;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: d 0.4s ease;
}

.tx-line { stroke: #f59e0b; }
.rx-line { stroke: #06b6d4; }

.chart-area {
  stroke: none;
  transition: d 0.4s ease;
}
.tx-area { fill: url(#txGrad); }
.rx-area { fill: url(#rxGrad); }

.y-axis-labels {
  position: absolute;
  left: 8px;
  top: 0;
  bottom: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  font-size: 0.65rem;
  color: var(--text-muted);
  pointer-events: none;
  font-family: var(--font-mono, monospace);
  padding: 4px 0;
}

.y-label {
  background: rgba(17, 24, 39, 0.75);
  padding: 1px 4px;
  border-radius: 3px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
</style>
