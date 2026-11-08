import { useToast } from 'primevue/usetoast'
import { errMsg, fieldErrors } from '@/api/http'

/** Jalankan aksi ke server; gagal → toast pesan yang jelas. Mengembalikan true bila berhasil. */
export function useRun() {
  const toast = useToast()
  return async (fn, sukses) => {
    try {
      await fn()
      if (sukses) toast.add({ severity: 'success', summary: sukses, life: 1800 })
      return true
    } catch (e) {
      const detail = Object.values(fieldErrors(e))[0] || errMsg(e)
      toast.add({ severity: 'error', summary: 'Tidak bisa diproses', detail, life: 6000 })
      return false
    }
  }
}
