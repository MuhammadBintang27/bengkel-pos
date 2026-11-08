<script setup>
defineProps({ title: String, subtitle: String, back: String })
</script>

<template>
  <header class="ph">
    <div class="ph-text">
      <RouterLink v-if="back" :to="back" class="back"><i class="pi pi-arrow-left" /> Kembali</RouterLink>
      <h1>{{ title }}</h1>
      <p v-if="subtitle" class="muted">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.default" class="ph-actions"><slot /></div>
  </header>
</template>

<style scoped>
.ph {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 12px 16px;
  margin-bottom: 20px;
}
.ph-text {
  min-width: 0;
}
h1 {
  font-size: 1.5rem;
  line-height: 1.25;
}
p {
  margin: 4px 0 0;
}
.back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 0.9rem;
  color: var(--accent-text);
  font-weight: 500;
}
.ph-actions {
  margin-left: auto;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
