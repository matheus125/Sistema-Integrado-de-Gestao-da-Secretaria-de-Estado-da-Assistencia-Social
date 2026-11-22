import assert from 'node:assert/strict'
import { readdir, readFile } from 'node:fs/promises'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'

const sourceRoot = fileURLToPath(new URL('../src', import.meta.url))

async function vueFiles (directory) {
  const entries = await readdir(directory, { withFileTypes: true })
  const nested = await Promise.all(entries.map(async entry => {
    const file = path.join(directory, entry.name)

    if (entry.isDirectory()) return vueFiles(file)

    return entry.name.endsWith('.vue') ? [file] : []
  }))

  return nested.flat()
}

test('todo botao Quasar com icone possui nome acessivel explicito', async () => {
  const files = await vueFiles(sourceRoot)
  const unnamed = []
  let iconButtons = 0

  for (const file of files) {
    const source = await readFile(file, 'utf8')

    for (const match of source.matchAll(/<q-btn\b([^>]*?)>/g)) {
      const attributes = match[1]
      const hasIcon = /(?:^|\s)(?::)?icon\s*=/.test(attributes) || /(?:^|\s)round(?:\s|=|$)/.test(attributes)

      if (!hasIcon) continue

      iconButtons += 1

      const accessibleName = attributes.match(/(?:^|\s)(?::)?(?:aria-label|label|title)\s*=\s*"([^"]+)"/)

      if (!accessibleName) {
        const line = source.slice(0, match.index).split('\n').length
        unnamed.push(`${path.relative(sourceRoot, file)}:${line}`)
      }
    }
  }

  assert.ok(iconButtons >= 90, `Inventario inesperadamente pequeno: ${iconButtons}`)
  assert.deepEqual(unnamed, [])
})
