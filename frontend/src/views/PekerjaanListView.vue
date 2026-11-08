<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errMsg, get } from '@/api/http'
import BIcon from '@/components/BIcon.vue'
import PageHeader from '@/components/PageHeader.vue'
import { STATUS, tglJam } from '@/utils/format'

const router = useRouter()
const rows = ref(null)
const error = ref('')

async function load() {
  error.value = ''
  try {
    rows.value = (await get('/mekanik/pekerjaan')).data
  } catch (e) {
    error.value = errMsg(e)
  }
}
onMounted(load)
</script>

<template>
  <PageHeader title="Pekerjaan Saya" subtitle="Motor yang ada jasa atas nama Anda dan belum dibayar." />

  <Message v-if="error" severity="error" :closable="false">{{ error }} <a href="#" class="underline" @click.prevent="load">Coba lagi</a></Message>

  <div v-else-if="!rows" class="susun"><Skeleton v-for="i in 3" :key="i" height="110px" border-radius="12px" /></div>

  <div v-else-if="!rows.length" class="panel kosong">
    <BIcon name="servis" :size="40" />
    <strong>Tidak ada pekerjaan</strong>
    <span class="muted">Pekerjaan muncul di sini saat kasir menugaskan jasa ke Anda.</span>
  </div>

  <div v-else class="susun">
    <button v-for="t in rows" :key="t.id" type="button" class="panel kartu" @click="router.push(`/mekanik/pekerjaan/${t.id}`)">
      <div class="baris">
        <strong class="plat">{{ t.plat || '-' }}</strong>
        <Tag :value="STATUS[t.status].label" :severity="STATUS[t.status].severity" />
      </div>
      <span v-if="t.motor" class="muted">{{ t.motor }}</span>
      <span>{{ t.jasa_saya.join(', ') }}</span>
      <small v-if="t.keluhan" class="muted">Keluhan: {{ t.keluhan }}</small>
      <small class="muted">Masuk {{ tglJam(t.masuk_at) }}</small>
    </button>
  </div>
</template>

<style scoped>
.susun {
  display: grid;
  gap: 16px;
}
.kartu {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 16px 20px;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
}
.baris {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}
.plat {
  font-size: 1.25rem;
}
.kosong {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 40px 16px;
  text-align: center;
  color: var(--p-text-muted-color);
}
.kosong strong {
  color: var(--p-text-color);
  font-size: 1.05rem;
}
</style>
