window.Vue = require("vue");
window.axios = require("axios");

Vue.prototype.$http = axios;

window.eventBus = new Vue();
