<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import AppLayout from '@/layouts/AppLayout.vue'
import GantiPasswordDialog from '@/components/GantiPasswordDialog.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const blank = computed(() => route.meta.layout === 'blank')

// Session habis / akun dinonaktifkan: kembali ke login dengan pesan yang jelas.
function onExpired() {
  if (!auth.user) return
  auth.clear()
  toast.add({ severity: 'warn', summary: 'Sesi berakhir', detail: 'Silakan masuk kembali.', life: 4000 })
  router.push({ path: '/login', query: { next: route.fullPath } })
}
onMounted(() => window.addEventListener('auth:expired', onExpired))
onBeforeUnmount(() => window.removeEventListener('auth:expired', onExpired))
</script>

<template>
  <Toast position="top-right" />
  <ConfirmDialog :style="{ width: '28rem' }" :breakpoints="{ '600px': '92vw' }" />

  <RouterView v-if="blank" />
  <AppLayout v-else-if="auth.user">
    <RouterView />
  </AppLayout>

  <GantiPasswordDialog v-if="auth.user?.harus_ganti_password" wajib />
</template>
