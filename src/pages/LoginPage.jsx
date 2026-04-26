import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import api from '../services/api';

/**
 * Halaman Login Wizdam.
 * Mendukung dua metode:
 *   1. Email + Password (POST /api/v1/auth/login → JWT token)
 *   2. Login via ORCID OAuth2 (redirect ke PHP backend: /auth/orcid-login)
 *
 * Token disimpan di localStorage key "wizdam_token".
 * Setelah login, redirect ke halaman sebelumnya atau /dashboard.
 */

const BACKEND_URL = process.env.REACT_APP_BACKEND_URL
  || (process.env.REACT_APP_API_URL || '').replace('/api/v1', '');

const LoginPage = () => {
  const navigate  = useNavigate();
  const location  = useLocation();
  const from      = location.state?.from?.pathname ?? '/';

  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [loading,  setLoading]  = useState(false);
  const [error,    setError]    = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) { setError('Email dan password wajib diisi.'); return; }

    setLoading(true);
    setError('');
    try {
      const res = await api.post('/auth/login', { email, password });
      if (res.token) {
        localStorage.setItem('wizdam_token', res.token);
        if (res.user) localStorage.setItem('wizdam_user', JSON.stringify(res.user));
        navigate(from, { replace: true });
      } else {
        setError('Respons login tidak valid.');
      }
    } catch (err) {
      setError(err.message || 'Login gagal. Periksa email dan password Anda.');
    } finally {
      setLoading(false);
    }
  };

  const handleOrcidLogin = () => {
    // Redirect ke PHP backend untuk memulai OAuth2 ORCID flow
    window.location.href = `${BACKEND_URL}/auth/orcid-login`;
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-lg w-full max-w-md p-8">

        {/* Logo & title */}
        <div className="text-center mb-8">
          <div className="w-16 h-16 bg-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold">
            W
          </div>
          <h1 className="text-2xl font-bold text-gray-900">Wizdam Indonesia</h1>
          <p className="text-sm text-gray-500 mt-1">Platform Analisis Dampak Penelitian</p>
        </div>

        {/* Error banner */}
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-5">
            {error}
          </div>
        )}

        {/* ORCID login */}
        <button
          type="button"
          onClick={handleOrcidLogin}
          className="w-full flex items-center justify-center gap-3 border-2 border-green-600 text-green-700 font-semibold rounded-lg py-2.5 hover:bg-green-50 transition-colors mb-5"
        >
          <svg viewBox="0 0 256 256" className="w-5 h-5" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="128" cy="128" r="128" fill="#A6CE39"/>
            <circle cx="128" cy="68" r="20" fill="white"/>
            <rect x="108" y="100" width="40" height="108" rx="8" fill="white"/>
          </svg>
          Masuk dengan ORCID
        </button>

        <div className="relative mb-5">
          <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-gray-200" /></div>
          <div className="relative flex justify-center text-xs text-gray-400 bg-white px-2">atau masuk dengan email</div>
        </div>

        {/* Email/password form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="peneliti@universitas.ac.id"
              className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              autoComplete="email"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              autoComplete="current-password"
              required
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-blue-700 text-white font-semibold rounded-lg py-2.5 hover:bg-blue-800 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
          >
            {loading ? 'Masuk…' : 'Masuk'}
          </button>
        </form>

        <p className="text-center text-xs text-gray-400 mt-6">
          Belum punya akun?{' '}
          <a href={`${BACKEND_URL}/auth/register`} className="text-blue-600 hover:underline">Daftar</a>
          {' · '}
          <a href={`${BACKEND_URL}/auth/forgot-password`} className="text-blue-600 hover:underline">Lupa password</a>
        </p>
      </div>
    </div>
  );
};

export default LoginPage;
