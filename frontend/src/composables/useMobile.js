import { onBeforeUnmount, ref } from 'vue'

export function useMobile(query = '(max-width: 900px)') {
  const mq = window.matchMedia(query)
  const mobile = ref(mq.matches)
  const update = (e) => (mobile.value = e.matches)
  mq.addEventListener('change', update)
  onBeforeUnmount(() => mq.removeEventListener('change', update))
  return mobile
}
