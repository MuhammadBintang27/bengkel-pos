<script setup>
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="panel p-10 text-center max-w-lg mx-auto mt-10">
    <p class="text-5xl font-bold muted">404</p>
    <h1 class="mt-2 text-xl">Halaman tidak ditemukan</h1>
    <p class="muted mt-2">Alamat yang Anda buka tidak ada atau sudah dipindah.</p>
    <RouterLink :to="auth.home"><Button label="Ke halaman utama" icon="pi pi-home" class="mt-6" /></RouterLink>
  </div>
</template>
