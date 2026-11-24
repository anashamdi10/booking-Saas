import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from './App.vue'
import router from './router'
import './style.css'

const app = createApp(App).use(createPinia()).use(router)

// Waiting for the initial navigation makes sure the first route component is
// already resolved before mounting, so the very first paint is never blocked
// by the page transition.
await router.isReady()

app.mount('#app')
