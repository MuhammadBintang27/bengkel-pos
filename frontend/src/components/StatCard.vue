<script setup>
import BIcon from '@/components/BIcon.vue'

defineProps({ icon: String, label: String, value: [String, Number], hint: String, tone: { type: String, default: 'primary' }, to: String })
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="panel stat" :class="[tone, { link: to }]">
    <div class="ico"><BIcon :name="icon" :size="22" /></div>
    <div class="body">
      <small>{{ label }}</small>
      <strong class="num">{{ value }}</strong>
      <span v-if="hint" class="hint">{{ hint }}</span>
    </div>
  </component>
</template>

<style scoped>
.stat {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 18px;
}
.stat.link:hover {
  border-color: var(--p-primary-color);
}
.ico {
  width: 42px;
  height: 42px;
  flex: none;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 1.15rem;
  background: color-mix(in srgb, var(--tone) 14%, transparent);
  color: var(--tone);
}
.primary { --tone: var(--p-primary-color); }
.green { --tone: var(--p-green-600); }
.orange { --tone: var(--p-orange-500); }
.red { --tone: var(--p-red-500); }
.body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
small {
  color: var(--p-text-muted-color);
  font-weight: 500;
}
strong {
  font-size: 1.4rem;
  line-height: 1.3;
}
.hint {
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}
</style>
