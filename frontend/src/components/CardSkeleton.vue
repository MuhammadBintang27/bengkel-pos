<script setup>
// Kerangka kartu form (judul + beberapa kolom isian) untuk halaman yang memuat data sebelum tampil.
defineProps({ cards: { type: Number, default: 2 }, fields: { type: Number, default: 4 } })
</script>

<template>
  <div class="cs-wrap" role="status" aria-label="Memuat">
    <section v-for="c in cards" :key="c" class="panel cs">
      <div class="cs-head">
        <Skeleton width="40px" height="40px" border-radius="10px" />
        <div class="cs-title">
          <Skeleton width="160px" height="16px" />
          <Skeleton width="260px" height="12px" />
        </div>
      </div>
      <div class="cs-grid">
        <div v-for="f in fields" :key="f" class="cs-field">
          <Skeleton width="90px" height="12px" />
          <Skeleton height="40px" border-radius="8px" />
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.cs-wrap {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.cs {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.cs-head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.cs-title {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
}
.cs-field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
