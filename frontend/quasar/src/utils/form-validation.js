export function maxLength (limit) {
  return value => value === null || value === undefined || String(value).length <= limit || `Use no máximo ${limit} caracteres`
}
