export function load(key, def) {
  try {
    const v = localStorage.getItem(key)
    return v === null ? def : JSON.parse(v)
  } catch {
    return def
  }
}

export function save(key, val) {
  try {
    localStorage.setItem(key, JSON.stringify(val))
  } catch {
    /* localStorage bisa diblok (mode privat): nilai tetap berlaku untuk sesi ini */
  }
}

export function remove(key) {
  try {
    localStorage.removeItem(key)
  } catch {
    /* sama seperti di atas */
  }
}
