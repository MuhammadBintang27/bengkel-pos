<script setup>
import { ref } from 'vue'
import GantiPasswordDialog from '@/components/GantiPasswordDialog.vue'
import PageHeader from '@/components/PageHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { ROLE_LABEL } from '@/utils/format'

const auth = useAuthStore()
const ganti = ref(false)
</script>

<template>
  <PageHeader title="Akun Saya" subtitle="Informasi login Anda" />
  <div class="panel p-6 max-w-xl">
    <dl class="grid grid-cols-[140px_1fr] gap-y-3 m-0">
      <dt class="muted">Nama</dt>
      <dd class="m-0 font-semibold">{{ auth.user.nama }}</dd>
      <dt class="muted">Username</dt>
      <dd class="m-0">{{ auth.user.username }}</dd>
      <dt class="muted">Peran</dt>
      <dd class="m-0"><Tag :value="ROLE_LABEL[auth.role]" /></dd>
      <template v-if="auth.user.mekanik">
        <dt class="muted">Data mekanik</dt>
        <dd class="m-0">{{ auth.user.mekanik }}</dd>
      </template>
    </dl>
    <Divider />
    <Button label="Ganti Password" icon="pi pi-key" outlined @click="ganti = true" />
  </div>
  <GantiPasswordDialog v-if="ganti" v-model:visible="ganti" />
</template>
