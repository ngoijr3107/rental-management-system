/**
 * Client-side CSV export. The report JSON is already in the browser, so
 * there's no reason to round-trip to the server for a download — and it
 * keeps working if the API is briefly unreachable.
 */

function cell(value) {
  if (value === null || value === undefined) return ''
  const text = String(value)
  // Quote anything that would otherwise break the row, and escape quotes.
  return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
}

/**
 * @param {string} filename  without extension
 * @param {Array<{key: string, label: string}>} columns
 * @param {Array<object>} rows
 * @param {Array<Array>} [extra]  leading lines, e.g. a title and totals
 */
export function downloadCsv(filename, columns, rows, extra = []) {
  const lines = [
    ...extra.map((line) => line.map(cell).join(',')),
    columns.map((c) => cell(c.label)).join(','),
    ...rows.map((row) => columns.map((c) => cell(row[c.key])).join(',')),
  ]

  // BOM keeps Excel from mangling non-ASCII names.
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = `${filename}-${new Date().toISOString().slice(0, 10)}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

export function printReport() {
  window.print()
}
