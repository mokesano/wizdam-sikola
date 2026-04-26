/**
 * Base HTTP client untuk semua panggilan ke REST API backend.
 * Base URL dikonfigurasi via REACT_APP_API_URL di .env
 */

const BASE_URL = (process.env.REACT_APP_API_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');

class ApiError extends Error {
  constructor(message, status, data = null) {
    super(message);
    this.status = status;
    this.data   = data;
  }
}

async function request(path, options = {}) {
  const url = `${BASE_URL}${path}`;

  const headers = {
    'Content-Type': 'application/json',
    Accept:         'application/json',
    ...options.headers,
  };

  const token = localStorage.getItem('wizdam_token');
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(url, { ...options, headers });

  let json;
  try {
    json = await res.json();
  } catch {
    throw new ApiError(`Respons tidak valid dari server (${res.status})`, res.status);
  }

  if (!res.ok || json.success === false) {
    throw new ApiError(json.message || `Error ${res.status}`, res.status, json);
  }

  return json;
}

const api = {
  get:  (path, params = {}) => {
    const qs = new URLSearchParams(
      Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))
    ).toString();
    return request(`${path}${qs ? '?' + qs : ''}`);
  },

  post: (path, body = {}) =>
    request(path, { method: 'POST', body: JSON.stringify(body) }),
};

export { api as default, ApiError };

// ── Stats ──────────────────────────────────────────────────────────────────
export const statsApi = {
  getSummary: () => api.get('/stats'),
};

// ── Researchers ────────────────────────────────────────────────────────────
export const researcherApi = {
  list:   (params = {}) => api.get('/researchers', params),
  top:    (limit = 10)  => api.get('/researchers/top', { limit }),
  detail: (orcid)       => api.get(`/researchers/${encodeURIComponent(orcid)}`),
};

// ── Articles ───────────────────────────────────────────────────────────────
export const articleApi = {
  list:   (params = {}) => api.get('/articles', params),
  top:    (limit = 10)  => api.get('/articles/top', { limit }),
  trends: (params = {}) => api.get('/articles/trends', params),
  detail: (id)          => api.get(`/articles/${id}`),
};

// ── Institutions ───────────────────────────────────────────────────────────
export const institutionApi = {
  list:   (params = {}) => api.get('/institutions', params),
  map:    ()            => api.get('/institutions/map'),
  detail: (id)          => api.get(`/institutions/${id}`),
};

// ── Impact Scores ──────────────────────────────────────────────────────────
export const impactScoreApi = {
  get:       (type, id)        => api.get(`/impact-scores/${type}/${id}`),
  history:   (type, id, months = 12) => api.get(`/impact-scores/${type}/${id}/history`, { months }),
  calculate: (type, id)        => api.post(`/impact-scores/${type}/${id}/calculate`),
  averages:  (type)            => api.get(`/impact-scores/averages/${type}`),
  classifySdg: (title, abstract = '') => api.post('/sdg/classify', { title, abstract }),
};
