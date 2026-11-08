<script setup>
// Logo Tunas Bengkel: tunas yang tumbuh dari roda gigi. Warna mengikuti warna utama tema.
defineProps({ size: { type: Number, default: 40 } })
</script>

<template>
  <svg :width="size" :height="size" viewBox="0 0 64 64" role="img" aria-label="Tunas Bengkel" class="tunas-logo">
    <rect width="64" height="64" rx="16" class="bg" />
    <path
      class="fg"
      fill-rule="evenodd"
      d="M28.78 31.61L29.68 27.66L34.32 27.66L35.22 31.61L36.67 32.08L39.72 29.42L43.48 32.15L41.89 35.87L42.79 37.11L46.82 36.74L48.25 41.16L44.78 43.24L44.78 44.76L48.25 46.84L46.82 51.26L42.79 50.89L41.89 52.13L43.48 55.85L39.72 58.58L36.67 55.92L35.22 56.39L34.32 60.34L29.68 60.34L28.78 56.39L27.33 55.92L24.28 58.58L20.52 55.85L22.11 52.13L21.21 50.89L17.18 51.26L15.75 46.84L19.22 44.76L19.22 43.24L15.75 41.16L17.18 36.74L21.21 37.11L22.11 35.87L20.52 32.15L24.28 29.42L27.33 32.08Z M37.5 44a5.5 5.5 0 1 0-11 0a5.5 5.5 0 1 0 11 0Z"
    />
    <path class="stem" d="M32 29V17" />
    <path class="fg" d="M32 24C27 24 20 21 17 13C25 12.5 31 17 32 24Z" />
    <path class="fg" d="M32 20C32.5 12 38 7 48 6.5C47.5 15 41 20 32 20Z" />
  </svg>
</template>

<style scoped>
.tunas-logo {
  flex: none;
  display: block;
}
.bg {
  fill: var(--p-primary-color);
}
.fg {
  fill: var(--p-primary-contrast-color);
}
.stem {
  fill: none;
  stroke: var(--p-primary-contrast-color);
  stroke-width: 3.2;
  stroke-linecap: round;
}
</style>
