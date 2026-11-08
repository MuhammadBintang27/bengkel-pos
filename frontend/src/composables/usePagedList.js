import { onBeforeUnmount, reactive, ref } from 'vue'
import { errMsg, get } from '@/api/http'

/** Daftar server-side untuk DataTable lazy: halaman, urutan, pencarian (ditunda 300 ms), dan filter tambahan. */
export function usePagedList(url, filters = {}) {
  const rows = ref([])
  const total = ref(0)
  const loading = ref(false)
  const error = ref('')
  const last = ref(null) // respons lengkap terakhir, untuk data tambahan di luar baris
  const state = reactive({ page: 1, per_page: 15, q: '', sort: '', order: 'asc', ...filters })
  let timer
  let seq = 0

  async function load() {
    const mine = ++seq
    loading.value = true
    error.value = ''
    try {
      const params = Object.fromEntries(Object.entries(state).filter(([, v]) => v !== '' && v != null))
      const res = await get(typeof url === 'function' ? url() : url, params)
      if (mine !== seq) return // ada permintaan yang lebih baru
      rows.value = res.data
      last.value = res
      total.value = res.total
    } catch (e) {
      if (mine === seq) error.value = errMsg(e)
    } finally {
      if (mine === seq) loading.value = false
    }
  }

  const reset = () => {
    state.page = 1
    return load()
  }
  const search = () => {
    clearTimeout(timer)
    timer = setTimeout(reset, 300)
  }
  const onPage = (e) => {
    state.page = e.page + 1
    state.per_page = e.rows
    load()
  }
  const onSort = (e) => {
    state.sort = e.sortOrder ? e.sortField : ''
    state.order = e.sortOrder === -1 ? 'desc' : 'asc'
    reset()
  }
  /** Muat ulang; kalau halaman terakhir jadi kosong (mis. setelah hapus), mundur satu halaman. */
  async function refresh() {
    await load()
    if (!rows.value.length && state.page > 1) {
      state.page -= 1
      await load()
    }
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { rows, total, loading, error, last, state, load, reset, search, onPage, onSort, refresh }
}
