import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import en from '@/i18n/en'
import sw from '@/i18n/sw'

// vitest runs from the project root, so resolve from there rather than
// from import.meta.url, which the transform rewrites.
const SRC = join(process.cwd(), 'src')

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f)
    return statSync(p).isDirectory() ? walk(p) : [p]
  })
}
const vueFiles = walk(SRC).filter((f) => f.endsWith('.vue'))

function flatten(obj, prefix = '') {
  return Object.entries(obj).flatMap(([k, v]) =>
    v && typeof v === 'object' ? flatten(v, `${prefix}${k}.`) : [`${prefix}${k}`],
  )
}
function placeholders(value) {
  return [...String(value).matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort().join(',')
}
function comparePlaceholders(a, b, prefix = '') {
  return Object.entries(a).flatMap(([k, v]) => {
    if (v && typeof v === 'object') return comparePlaceholders(v, b?.[k] ?? {}, `${prefix}${k}.`)
    if (b?.[k] && placeholders(v) !== placeholders(b[k])) return [`${prefix}${k}`]
    return []
  })
}

describe('source conventions', () => {
  it('keeps every table inside a horizontal scroll container', () => {
    const offenders = []
    for (const file of vueFiles) {
      const src = readFileSync(file, 'utf8')
      for (const match of src.matchAll(/<table\b/g)) {
        const before = src.slice(0, match.index).trimEnd()
        if (!before.endsWith('<div class="table-scroll">') && !/table-scroll"\s+v-/.test(before.slice(-80))) {
          offenders.push(file.replace(SRC, ''))
        }
      }
    }
    expect(offenders).toEqual([])
  })

  it('uses design tokens rather than hardcoded colours', () => {
    const offenders = []
    for (const file of vueFiles) {
      const src = readFileSync(file, 'utf8')
      const style = src.slice(src.indexOf('<style'))
      // AppShell carries deliberate literal fallbacks so the rail is never
      // unstyled; the print letterhead needs true black on paper.
      if (/AppShell|ReportShell/.test(file)) continue
      for (const m of style.matchAll(/#[0-9a-fA-F]{3,8}\b/g)) {
        offenders.push(`${file.replace(SRC, '')}: ${m[0]}`)
      }
    }
    expect(offenders).toEqual([])
  })

  it('gives every row action an icon and an accessible label', () => {
    const offenders = []
    for (const file of vueFiles) {
      const src = readFileSync(file, 'utf8')
      for (const m of src.matchAll(/<RowAction\b[\s\S]*?\/>/g)) {
        const tag = m[0]
        if (!/icon="/.test(tag) || !/:label="/.test(tag)) {
          offenders.push(`${file.replace(SRC, '')}: ${tag.slice(0, 60)}`)
        }
      }
    }
    expect(offenders).toEqual([])
  })

  it('leaves no bare quiet buttons inside table rows', () => {
    const offenders = vueFiles.filter((f) =>
      readFileSync(f, 'utf8').includes('btn-quiet btn-sm'),
    )
    expect(offenders.map((f) => f.replace(SRC, ''))).toEqual([])
  })

  it('never hardcodes the list of assignable roles in a view', () => {
    // The Accountant role existed on the server for a while before it
    // appeared in this form, because the list was duplicated here.
    const offenders = []
    for (const file of vueFiles) {
      const src = readFileSync(file, 'utf8')
      if (/\[\s*'manager'\s*,|'agent'\s*,\s*'viewer'/.test(src)) {
        offenders.push(file.replace(SRC, ''))
      }
    }
    expect(offenders).toEqual([])
  })

  it('keeps both locales at identical keys and placeholders', () => {
    const a = new Set(flatten(en))
    const b = new Set(flatten(sw))
    expect([...a].filter((k) => !b.has(k))).toEqual([])
    expect([...b].filter((k) => !a.has(k))).toEqual([])
    expect(comparePlaceholders(en, sw)).toEqual([])
  })

  it('resolves every static translation key used in the source', () => {
    const get = (o, path) => path.split('.').reduce((acc, k) => acc?.[k], o)
    const missing = []
    for (const file of [...vueFiles, ...walk(SRC).filter((f) => f.endsWith('.js'))]) {
      const src = readFileSync(file, 'utf8')
      for (const m of src.matchAll(/\bt\(\s*'([a-zA-Z0-9_.]+)'/g)) {
        if (get(en, m[1]) === undefined) missing.push(`${file.replace(SRC, '')}: ${m[1]}`)
      }
    }
    expect(missing).toEqual([])
  })
})
