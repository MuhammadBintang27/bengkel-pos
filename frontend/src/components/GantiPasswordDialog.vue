<script setup>
import { reactive, ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors, post } from '@/api/http'
import { useAuthStore } from '@/stores/auth'

// wajib = akun baru / password bawaan: dialog tidak bisa ditutup sebelum password diganti.
const props = defineProps({ wajib: Boolean })
const visible = defineModel('visible', { default: true })

const auth = useAuthStore()
const toast = useToast()
const form = reactive({ password_lama: '', password: '', password_confirmation: '' })
const errors = ref({})
const saving = ref(false)

async function submit() {
  saving.value = true
  errors.value = {}
  try {
    const res = await post('/ganti-password', form)
    auth.user.harus_ganti_password = false
    toast.add({ severity: 'success', summary: 'Berhasil', detail: res.message, life: 3000 })
    Object.assign(form, { password_lama: '', password: '', password_confirmation: '' })
    visible.value = false
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: 'Gagal', detail: errMsg(e), life: 5000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Ganti Password"
    :closable="!props.wajib"
    :close-on-escape="!props.wajib"
    :style="{ width: '26rem' }"
    :breakpoints="{ '600px': '92vw' }"
  >
    <Message v-if="props.wajib" severity="info" class="mb-4">
      Demi keamanan, ganti password bawaan sebelum mulai memakai aplikasi.
    </Message>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="field">
        <label for="pl">Password lama</label>
        <Password v-model="form.password_lama" input-id="pl" :feedback="false" toggle-mask fluid :invalid="!!errors.password_lama" autocomplete="current-password" />
        <small v-if="errors.password_lama" class="err">{{ errors.password_lama }}</small>
      </div>
      <div class="field">
        <label for="pb">Password baru</label>
        <Password v-model="form.password" input-id="pb" toggle-mask fluid :invalid="!!errors.password" autocomplete="new-password" />
        <small v-if="errors.password" class="err">{{ errors.password }}</small>
        <small v-else class="hint">Minimal 6 karakter.</small>
      </div>
      <div class="field">
        <label for="pk">Ulangi password baru</label>
        <Password v-model="form.password_confirmation" input-id="pk" :feedback="false" toggle-mask fluid autocomplete="new-password" />
      </div>
      <div class="flex justify-end gap-2 mt-2">
        <Button v-if="!props.wajib" type="button" label="Batal" severity="secondary" outlined @click="visible = false" />
        <Button type="submit" label="Simpan Password" icon="pi pi-check" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
