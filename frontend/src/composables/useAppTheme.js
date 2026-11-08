import { definePreset } from '@primeuix/themes'
import Aura from '@primeuix/themes/aura'
import Lara from '@primeuix/themes/lara'
import Material from '@primeuix/themes/material'
import Nora from '@primeuix/themes/nora'
import { load, save } from '@/utils/storage'

export const THEMES = [
  { id: 'aura', label: 'Aura', base: Aura },
  { id: 'lara', label: 'Lara', base: Lara },
  { id: 'material', label: 'Material', base: Material },
  { id: 'nora', label: 'Nora', base: Nora },
]

export const PRIMARY_COLORS = [
  { name: 'amber', hex: '#f59e0b' },
  { name: 'orange', hex: '#f97316' },
  { name: 'red', hex: '#ef4444' },
  { name: 'rose', hex: '#f43f5e' },
  { name: 'pink', hex: '#ec4899' },
  { name: 'fuchsia', hex: '#d946ef' },
  { name: 'purple', hex: '#a855f7' },
  { name: 'violet', hex: '#8b5cf6' },
  { name: 'indigo', hex: '#6366f1' },
  { name: 'blue', hex: '#3b82f6' },
  { name: 'sky', hex: '#0ea5e9' },
  { name: 'cyan', hex: '#06b6d4' },
  { name: 'teal', hex: '#14b8a6' },
  { name: 'emerald', hex: '#10b981' },
  { name: 'green', hex: '#22c55e' },
  { name: 'lime', hex: '#84cc16' },
  { name: 'yellow', hex: '#eab308' },
  { name: 'slate', hex: '#64748b' },
]

export const MODES = [
  { label: 'Terang', value: 'light', icon: 'pi pi-sun' },
  { label: 'Gelap', value: 'dark', icon: 'pi pi-moon' },
  { label: 'Otomatis', value: 'auto', icon: 'pi pi-desktop' },
]

// Sama dengan Pengaturan::DEFAULT di backend (bagian tampilan).
export const DEFAULT_TAMPILAN = { nama_bengkel: 'Tunas Bengkel', logo: '', tema: 'aura', warna: 'blue', mode: 'light' }

const STORAGE_KEY = 'bengkelTampilan'
const STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]
const DARK_TEXT = '#1c1917'
const HEX = /^#[0-9a-f]{6}$/i

export const isHex = (s) => HEX.test(s)

const toRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))
const toHex = (rgb) => '#' + rgb.map((v) => v.toString(16).padStart(2, '0')).join('')
const mix = (a, b, t) => a.map((v, i) => Math.round(v + (b[i] - v) * t))

// shortcut: campuran RGB ke putih/hitam, bukan OKLCH; upgrade bila ada warna brand yang hasilnya kusam.
export function buildPalette(hex) {
  const base = toRgb(hex)
  const light = (t) => toHex(mix(base, [255, 255, 255], t))
  const dark = (t) => toHex(mix(base, [0, 0, 0], t))
  return {
    50: light(0.95), 100: light(0.88), 200: light(0.75), 300: light(0.55), 400: light(0.3),
    500: hex.toLowerCase(),
    600: dark(0.12), 700: dark(0.25), 800: dark(0.38), 900: dark(0.5), 950: dark(0.65),
  }
}

const luminance = (hex) => {
  const [r, g, b] = toRgb(hex).map((v) => {
    const c = v / 255
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
  })
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/** Warna teks di atas warna primer: putih atau gelap, mana yang kontrasnya lebih tinggi. */
export function contrastFor(hex) {
  const l = luminance(hex)
  return 1.05 / (l + 0.05) >= (l + 0.05) / (luminance(DARK_TEXT) + 0.05) ? '#ffffff' : DARK_TEXT
}

const themeById = (id) => THEMES.find((t) => t.id === id) || THEMES[0]
const named = (w) => PRIMARY_COLORS.find((c) => c.name === w)
export const hexOf = (warna) => (isHex(warna) ? warna : (named(warna) || named('blue')).hex)

function build(s) {
  const palette = isHex(s.warna)
    ? buildPalette(s.warna)
    : Object.fromEntries(STEPS.map((n) => [n, `{${named(s.warna) ? s.warna : 'blue'}.${n}}`]))
  return definePreset(themeById(s.tema).base, {
    semantic: {
      primary: palette,
      colorScheme: {
        light: {
          primary: {
            color: '{primary.600}',
            // Tombol memakai primary.600, jadi kontras teks dihitung dari warna 600 (perkiraan untuk warna bawaan).
            contrastColor: contrastFor(buildPalette(hexOf(s.warna))[600]),
            hoverColor: '{primary.700}',
            activeColor: '{primary.800}',
          },
        },
        dark: {
          primary: {
            color: '{primary.400}',
            contrastColor: DARK_TEXT,
            hoverColor: '{primary.300}',
            activeColor: '{primary.200}',
          },
        },
      },
    },
  })
}

let current = { ...DEFAULT_TAMPILAN }
let primeConfig = null

const prefersDark = () => typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-color-scheme: dark)').matches

function setDark(mode) {
  document.documentElement.classList.toggle('app-dark', mode === 'dark' || (mode === 'auto' && prefersDark()))
}

export const loadCachedTampilan = () => ({ ...DEFAULT_TAMPILAN, ...load(STORAGE_KEY, {}) })

/** Dipanggil sekali sebelum app di-mount; memakai salinan localStorage supaya tidak ada kedipan warna default. */
export function initTheme() {
  current = loadCachedTampilan()
  setDark(current.mode)
  document.title = current.nama_bengkel
  window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (current.mode === 'auto') setDark('auto')
  })
  return build(current)
}

export function bindConfig(config) {
  primeConfig = config
}

/** persist=false untuk pratinjau di halaman Pengaturan. */
export function applyTheme(tampilan, { persist = true } = {}) {
  const next = { ...DEFAULT_TAMPILAN, ...pick(tampilan) }
  const berubah = next.tema !== current.tema || next.warna !== current.warna
  current = next
  // lewat config.theme, bukan usePreset(): usePreset() bisa mengubah salinan @primeuix/styled yang salah
  if (primeConfig && berubah) primeConfig.theme = { ...primeConfig.theme, preset: build(current) }
  setDark(current.mode)
  document.title = current.nama_bengkel
  if (persist) save(STORAGE_KEY, current)
}

const pick = (s = {}) => Object.fromEntries(Object.keys(DEFAULT_TAMPILAN).filter((k) => s[k] != null).map((k) => [k, s[k]]))
