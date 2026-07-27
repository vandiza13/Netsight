<template>
  <div id="netsight-app">
    <router-view v-slot="{ Component, route }">
      <transition :name="(route.meta.transition || 'fade')" mode="out-in">
        <component :is="Component" :key="route.path" />
      </transition>
    </router-view>
    <ToastNotification />
  </div>
</template>

<script setup lang="ts">
import ToastNotification from './components/ToastNotification.vue'
</script>

<style scoped>
#netsight-app {
  min-height: 100vh;
  background: var(--bg-primary);
  color: var(--text-primary);
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-enter-from {
  opacity: 0;
  transform: translateX(24px);
}
.slide-leave-to {
  opacity: 0;
  transform: translateX(-24px);
}
</style>
