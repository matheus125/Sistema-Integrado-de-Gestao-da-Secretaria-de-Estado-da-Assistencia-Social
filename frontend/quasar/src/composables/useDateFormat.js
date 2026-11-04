export function formatDateTime(value) {
  return value ? String(value).replace('T', ' ').slice(0, 16) : '-'
}

export function formatDate(value) {
  return value ? formatDateTime(value).slice(0, 10).split('-').reverse().join('/') : '-'
}
