import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import LoginPage from '../pages/LoginPage.vue'
import DashboardPage from '../pages/DashboardPage.vue'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: () => {
      const auth = useAuthStore()
      return auth.isAuthenticated ? '/dashboard' : '/login'
    },
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginPage,
    meta: { requiresAuth: false, transition: 'fade' },
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true, transition: 'slide' },
  },
  {
    path: '/routers',
    name: 'Routers',
    component: () => import('../pages/RouterManagementPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, transition: 'slide' },
  },
  {
    path: '/audit',
    name: 'Audit',
    component: () => import('../pages/AuditLogPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, transition: 'slide' },
  },
  {
    path: '/staff',
    name: 'Staff',
    component: () => import('../pages/StaffManagementPage.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, transition: 'slide' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// ── Navigation Guards ──────────────────────────────────────────────
router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()

  // Protected route — kick to login if unauthenticated
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'Login', query: { redirect: to.fullPath } })
  }

  // Admin route check
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return next({ name: 'Dashboard' })
  }

  // Already authenticated — don't show login page
  if (to.name === 'Login' && auth.isAuthenticated) {
    return next({ name: 'Dashboard' })
  }

  next()
})

export default router
