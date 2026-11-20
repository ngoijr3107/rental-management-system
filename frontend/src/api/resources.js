import api from './client'

/** Strip empty filter values so they never reach the query string. */
function clean(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined),
  )
}

export const auth = {
  login: (payload) => api.post('/login', payload),
  register: (payload) => api.post('/register', payload),
  logout: () => api.post('/logout'),
  me: () => api.get('/me'),
}

export const profile = {
  show: () => api.get('/profile'),
  update: (payload) => api.put('/profile', payload),
  changePassword: (payload) => api.put('/profile/password', payload),
}

export const dashboard = {
  load: () => api.get('/dashboard'),
}

export const properties = {
  list: (params) => api.get('/properties', { params: clean(params) }),
  get: (id) => api.get(`/properties/${id}`),
  create: (payload) => api.post('/properties', payload),
  update: (id, payload) => api.put(`/properties/${id}`, payload),
  remove: (id) => api.delete(`/properties/${id}`),
}

export const units = {
  list: (params) => api.get('/units', { params: clean(params) }),
  get: (id) => api.get(`/units/${id}`),
  create: (payload) => api.post('/units', payload),
  update: (id, payload) => api.put(`/units/${id}`, payload),
  remove: (id) => api.delete(`/units/${id}`),
}

export const unitPhotos = {
  list: (unitId) => api.get(`/units/${unitId}/photos`),
  upload: (unitId, files) => {
    const body = new FormData()
    for (const file of files) body.append('photos[]', file)
    return api.post(`/units/${unitId}/photos`, body)
  },
  setCover: (unitId, photoId) => api.post(`/units/${unitId}/photos/${photoId}/cover`),
  remove: (unitId, photoId) => api.delete(`/units/${unitId}/photos/${photoId}`),
}

export const alerts = {
  list: () => api.get('/alerts'),
  read: (id) => api.post(`/alerts/${id}/read`),
  readAll: () => api.post('/alerts/read-all'),
  outbox: (params) => api.get('/outbox', { params: clean(params) }),
}

export const tenants = {
  list: (params) => api.get('/tenants', { params: clean(params) }),
  get: (id) => api.get(`/tenants/${id}`),
  create: (payload) => api.post('/tenants', payload),
  update: (id, payload) => api.put(`/tenants/${id}`, payload),
  remove: (id) => api.delete(`/tenants/${id}`),
}

export const leases = {
  list: (params) => api.get('/leases', { params: clean(params) }),
  get: (id) => api.get(`/leases/${id}`),
  create: (payload) => api.post('/leases', payload),
  update: (id, payload) => api.put(`/leases/${id}`, payload),
  terminate: (id, payload) => api.post(`/leases/${id}/terminate`, payload),
  bill: (id, payload) => api.post(`/leases/${id}/invoices`, payload),
}

export const invoices = {
  list: (params) => api.get('/invoices', { params: clean(params) }),
  get: (id) => api.get(`/invoices/${id}`),
  generate: (payload) => api.post('/invoices/generate', payload),
  cancel: (id) => api.post(`/invoices/${id}/cancel`),
  pay: (id, payload) => api.post(`/invoices/${id}/payments`, payload),
}

export const payments = {
  list: (params) => api.get('/payments', { params: clean(params) }),
  get: (id) => api.get(`/payments/${id}`),
  remove: (id) => api.delete(`/payments/${id}`),
}

export const team = {
  list: () => api.get('/team'),
  roles: () => api.get('/team/roles'),
  create: (payload) => api.post('/team', payload),
  update: (id, payload) => api.put(`/team/${id}`, payload),
  remove: (id) => api.delete(`/team/${id}`),
}

export const settings = {
  show: () => api.get('/settings'),
  update: (payload) => api.put('/settings', payload),

  /** Multipart — the browser sets the boundary, so no Content-Type here. */
  uploadLogo: (file) => {
    const body = new FormData()
    body.append('logo', file)
    return api.post('/settings/logo', body)
  },

  deleteLogo: () => api.delete('/settings/logo'),
}

export const activity = {
  list: (params) => api.get('/activity', { params: clean(params) }),
}

export const reports = {
  rentRoll: () => api.get('/reports/rent-roll'),
  arrears: () => api.get('/reports/arrears'),
  collections: (params) => api.get('/reports/collections', { params: clean(params) }),
  vacancy: () => api.get('/reports/vacancy'),
  renewals: (params) => api.get('/reports/renewals', { params: clean(params) }),
  propertyPerformance: (params) => api.get('/reports/property-performance', { params: clean(params) }),
  maintenance: (params) => api.get('/reports/maintenance', { params: clean(params) }),
  tenantStatement: (id) => api.get(`/reports/tenant-statement/${id}`),
}

export const maintenance = {
  list: (params) => api.get('/maintenance-requests', { params: clean(params) }),
  get: (id) => api.get(`/maintenance-requests/${id}`),
  create: (payload) => api.post('/maintenance-requests', payload),
  update: (id, payload) => api.put(`/maintenance-requests/${id}`, payload),
  remove: (id) => api.delete(`/maintenance-requests/${id}`),
}
