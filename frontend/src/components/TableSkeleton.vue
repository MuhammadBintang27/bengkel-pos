<script setup>
// Kerangka tabel saat data pertama kali dimuat: lebih tenang daripada lapisan gelap + spinner.
defineProps({ rows: { type: Number, default: 6 }, cols: { type: Number, default: 5 } })
const lebar = ['60%', '85%', '45%', '70%', '55%', '75%']
</script>

<template>
  <div class="ts" role="status" aria-label="Memuat data">
    <div class="ts-row ts-head" :style="{ '--c': cols }">
      <Skeleton v-for="c in cols" :key="c" height="12px" width="50%" />
    </div>
    <div v-for="r in rows" :key="r" class="ts-row" :style="{ '--c': cols }">
      <Skeleton v-for="c in cols" :key="c" height="14px" :width="lebar[(r + c) % lebar.length]" />
    </div>
  </div>
</template>

<style scoped>
.ts-row {
  display: grid;
  grid-template-columns: repeat(var(--c), 1fr);
  gap: 24px;
  align-items: center;
  padding: 20px 16px;
  border-bottom: 1px solid var(--p-content-border-color);
}
.ts-head {
  padding: 16px;
}
</style>
