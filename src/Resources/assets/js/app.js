// Import Vue and Axios
window.Vue = require('vue');
window.axios = require('axios');

// Set Axios as a default HTTP client for Vue
Vue.prototype.$http = axios;

// Create a global event bus
window.eventBus = new Vue();
