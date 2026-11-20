import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import i18n from './i18n'
import { usePrefsStore } from './stores/prefs'
import './assets/styles.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(i18n).use(router)

// Paint the saved theme before the first render so there's no flash.
const prefs = usePrefsStore(pinia)
prefs.apply()
prefs.watchSystem()
i18n.global.locale.value = prefs.locale

// Anything that escapes a component still reaches the console with context,
// which is what a bug report will be built from.
app.config.errorHandler = (error, _instance, info) => {
  console.error('[Rent Manager]', info, error)
}

app.mount('#app')
