/**
 * Utility auth client-side.
 * Token disimpan di localStorage; semua request API
 * otomatis membawa header Authorization via api.js.
 */

export const auth = {
  getToken:  ()      => localStorage.getItem('wizdam_token'),
  getUser:   ()      => {
    try { return JSON.parse(localStorage.getItem('wizdam_user') ?? 'null'); }
    catch { return null; }
  },
  isLoggedIn: ()     => !!localStorage.getItem('wizdam_token'),
  logout:    ()      => {
    localStorage.removeItem('wizdam_token');
    localStorage.removeItem('wizdam_user');
    window.location.href = '/';
  },
  setSession: (token, user) => {
    localStorage.setItem('wizdam_token', token);
    if (user) localStorage.setItem('wizdam_user', JSON.stringify(user));
  },
};

export default auth;
