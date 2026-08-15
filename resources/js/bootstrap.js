import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Interceptor untuk menangkap error dari Backend (khususnya Lisensi)
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 403) {
            const data = error.response.data;
            if (data && data.error === 'LICENSE_EXPIRED') {
                // Trigger global event untuk memunculkan Modal License
                window.dispatchEvent(new CustomEvent('netsight-license-expired', { detail: data.message }));
                // Stop the promise chain so the app doesn't crash with unhandled rejection
                return new Promise(() => {}); 
            }
        }
        return Promise.reject(error);
    }
);
