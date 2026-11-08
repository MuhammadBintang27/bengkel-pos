<script setup>
import { computed } from 'vue'
import { STATUS } from '@/utils/format'

// Penjualan langsung tidak punya tahap pengerjaan: status "selesai" berarti menunggu pembayaran.
const props = defineProps({ status: String, jenis: String })
const info = computed(() => (props.jenis === 'penjualan' && props.status === 'selesai' ? { label: 'Belum dibayar', severity: 'warn' } : STATUS[props.status] || { label: props.status, severity: 'secondary' }))
</script>

<template>
  <Tag :value="info.label" :severity="info.severity" />
</template>
