import axios from 'axios'

// Sanctum SPA cookie: session + XSRF-TOKEN dari cookie, tanpa token di localStorage.
export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export const csrf = () => axios.get('/sanctum/csrf-cookie', { withCredentials: true })

http.interceptors.response.use(
  (res) => res,
  async (err) => {
    const { response, config } = err
    // 419 = cookie CSRF kedaluwarsa: ambil ulang sekali lalu ulangi permintaan.
    if (response?.status === 419 && !config._retry) {
      config._retry = true
      await csrf()
      return http(config)
    }
    if (response?.status === 401 && !config.url.endsWith('/login')) {
      window.dispatchEvent(new Event('auth:expired'))
    }
    return Promise.reject(err)
  },
)

const data = (res) => res.data

export const get = (url, params) => http.get(url, { params }).then(data)
export const post = (url, body) => http.post(url, body).then(data)
export const put = (url, body) => http.put(url, body).then(data)
export const del = (url) => http.delete(url).then(data)

export const errMsg = (e) => {
  if (!e?.response) return 'Tidak bisa terhubung ke server. Periksa koneksi lalu coba lagi.'
  if (e.response.status === 403) return e.response.data?.message || 'Anda tidak punya akses ke fitur ini.'
  if (e.response.status >= 500) return 'Terjadi kesalahan di server. Coba lagi, atau hubungi admin bila berulang.'
  return e.response.data?.message || 'Permintaan gagal.'
}

/** Error validasi 422 sebagai { field: 'pesan pertama' } untuk ditampilkan di bawah input. */
export const fieldErrors = (e) =>
  Object.fromEntries(Object.entries(e?.response?.data?.errors || {}).map(([k, v]) => [k, v[0]]))
