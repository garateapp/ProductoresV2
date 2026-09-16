import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// La cabecera CSRF se resuelve en CADA petición, no una sola vez al cargar la página.
// La cookie XSRF-TOKEN la renueva el servidor en cada respuesta, por lo que es la
// fuente más fiable. Solo se recurre al <meta> cuando la cookie no está disponible.
window.axios.interceptors.request.use((config) => {
  const hasXSRFCookie = document.cookie.split(';').some((c) => c.trim().startsWith('XSRF-TOKEN='));
  if (!hasXSRFCookie) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) config.headers['X-CSRF-TOKEN'] = token;
  }
  return config;
});

// Si la sesión venció o el token quedó obsoleto, Laravel responde 419 "Page Expired".
// Recargamos la página para renovar meta, cookies y sesión, en vez de quedar bloqueados.
window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 419) {
      window.location.reload();
    }
    return Promise.reject(error);
  }
);
