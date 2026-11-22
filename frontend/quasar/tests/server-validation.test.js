import assert from 'node:assert/strict'
import { readdir, readFile } from 'node:fs/promises'
import path from 'node:path'
import test from 'node:test'
import { fileURLToPath } from 'node:url'
import {
  captureServerValidation,
  clearServerValidationErrors,
  serverErrorMessage,
  serverErrorProps,
  serverValidationErrors,
  setServerValidationErrors
} from '../src/services/server-validation.js'

const projectRoot = fileURLToPath(new URL('..', import.meta.url))

test('preserva todas as mensagens 422 e resolve campos simples, aliases e filhos', () => {
  setServerValidationErrors({
    email: ['O email já está em uso.', 'Use um email institucional.'],
    'items.0.label': ['Informe o primeiro item.'],
    roles: 'Selecione ao menos um perfil.'
  })

  assert.equal(serverErrorMessage('email'), 'O email já está em uso. • Use um email institucional.')
  assert.equal(serverErrorMessage('items'), 'Informe o primeiro item.')
  assert.equal(serverErrorMessage('missing', 'roles'), 'Selecione ao menos um perfil.')
  assert.deepEqual(serverErrorProps('email'), { error: true, errorMessage: 'O email já está em uso. • Use um email institucional.' })
  assert.deepEqual(serverValidationErrors(), {
    email: ['O email já está em uso.', 'Use um email institucional.'],
    'items.0.label': ['Informe o primeiro item.'],
    roles: ['Selecione ao menos um perfil.']
  })
})

test('ignora respostas que não são 422 e limpa o estado entre envios', () => {
  setServerValidationErrors({ name: ['Nome inválido.'] })

  assert.equal(captureServerValidation(400, { errors: { email: ['Email inválido.'] } }), false)
  assert.equal(serverErrorMessage('name'), 'Nome inválido.')

  clearServerValidationErrors()
  assert.equal(serverErrorMessage('name'), '')
  assert.deepEqual(serverErrorProps('name'), { error: false, errorMessage: undefined })
})

test('serviço de API captura validação em JSON e upload e não reduz ao primeiro erro', async () => {
  const api = await readFile(path.join(projectRoot, 'src/services/api.js'), 'utf8')

  assert.match(api, /captureServerValidation\(response\.status, payload\)/)
  assert.equal((api.match(/captureServerValidation\(response\.status, payload\)/g) || []).length, 3)
  assert.match(api, /if \(isMutation\(options\.method\)\) clearServerValidationErrors\(\)/)
  assert.match(api, /export async function apiUpload[\s\S]*?clearServerValidationErrors\(\)/)
})

async function vueFiles (directory) {
  const entries = await readdir(directory, { withFileTypes: true })
  const nested = await Promise.all(entries.map(entry => {
    const file = path.join(directory, entry.name)
    if (entry.isDirectory()) return vueFiles(file)
    return entry.name.endsWith('.vue') ? [file] : []
  }))
  return nested.flat()
}

test('todos os controles submetidos por QForm exibem o erro 422 no campo', async () => {
  const files = await vueFiles(path.join(projectRoot, 'src'))
  const missing = []
  const missingChoices = []
  let controls = 0
  let choices = 0

  for (const file of files) {
    const source = await readFile(file, 'utf8')

    for (const form of source.matchAll(/<q-form\b[\s\S]*?<\/q-form>/g)) {
      const controlsPattern = /<(q-(?:input|select|file)|Brazilian\w+|BrlCurrencyInput)\b([^>]*?)>/g

      for (const control of form[0].matchAll(controlsPattern)) {
        const attributes = control[2]
        if (!/v-model(?:\.\w+)*=/.test(attributes) || /data-local-only/.test(attributes)) continue

        controls += 1
        if (!/serverErrorProps\(/.test(attributes)) {
          const model = attributes.match(/v-model(?:\.\w+)*="([^"]+)"/)?.[1] || 'desconhecido'
          missing.push(`${path.relative(projectRoot, file)}:${model}`)
        }
      }

      for (const choice of form[0].matchAll(/<q-(toggle|option-group|rating)\b([^>]*?)>/g)) {
        choices += 1
        const prefix = form[0].slice(Math.max(0, choice.index - 240), choice.index)
        if (!/serverErrorProps\([^)]*\)[\s\S]*?<template #control>\s*$/.test(prefix)) {
          missingChoices.push(`${path.relative(projectRoot, file)}:${choice[1]}`)
        }
      }
    }
  }

  assert.ok(controls >= 160, `Inventário inesperadamente pequeno: ${controls}`)
  assert.ok(choices >= 20, `Inventário de escolhas inesperadamente pequeno: ${choices}`)
  assert.deepEqual(missing, [])
  assert.deepEqual(missingChoices, [])
})

test('campos dinâmicos de configurações e filtros de relatórios exibem erros 422', async () => {
  const settings = await readFile(path.join(projectRoot, 'src/pages/SettingsPage.vue'), 'utf8')
  const reports = await readFile(path.join(projectRoot, 'src/pages/ReportsPage.vue'), 'utf8')

  assert.match(settings, /serverErrorProps\(`settings\.\$\{settingIndex\(setting\)\}\.value`, setting\.key\)/)
  assert.equal((reports.match(/serverErrorProps\('/g) || []).length, 5)
})
