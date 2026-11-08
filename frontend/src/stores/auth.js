import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { csrf, get, post } from '@/api/http'
import { applyTheme } from '@/composables/useAppTheme'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const pengaturan = ref({})
  const checked = ref(false)

  const role = computed(() => user.value?.role || '')
  const isOwner = computed(() => role.value === 'owner')
  const can = (roles) => !roles?.length || isOwner.value || roles.includes(role.value)
  const home = computed(() => (role.value === 'mekanik' ? '/mekanik/pekerjaan' : '/'))

  function set(res) {
    user.value = res.data.user
    pengaturan.value = res.data.pengaturan
    applyTheme(res.data.pengaturan)
  }

  /** Cek session yang sudah ada (sekali saat app dibuka). */
  async function fetchMe() {
    try {
      set(await get('/me'))
    } catch {
      user.value = null
    } finally {
      checked.value = true
    }
  }

  async function login(username, password) {
    await csrf()
    set(await post('/login', { username, password }))
  }

  async function logout() {
    try {
      await post('/logout')
    } finally {
      clear()
    }
  }

  function clear() {
    user.value = null
  }

  return { user, pengaturan, checked, role, isOwner, home, can, fetchMe, login, logout, clear, set }
})
