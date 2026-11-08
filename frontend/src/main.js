import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import 'primeicons/primeicons.css'
import '@/assets/main.css'
import App from './App.vue'
import router from './router'
import { bindConfig, initTheme } from '@/composables/useAppTheme'
import { localeId } from '@/utils/locale'

const app = createApp(App)

app.use(createPinia())
app.use(PrimeVue, {
  ripple: false,
  locale: localeId,
  theme: { preset: initTheme(), options: { darkModeSelector: '.app-dark', cssLayer: { name: 'primevue', order: 'theme, base, primevue' } } },
})
app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)
bindConfig(app.config.globalProperties.$primevue.config)
app.use(router)

app.mount('#app')
