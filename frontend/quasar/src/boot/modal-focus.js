import { boot } from 'quasar/wrappers'

const focusableSelector = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])'
].join(',')

function isVisible (element) {
  const style = window.getComputedStyle(element)
  return style.visibility !== 'hidden' && style.display !== 'none' && element.getClientRects().length > 0
}

function trapModalFocus (event) {
  if (event.key !== 'Tab' || event.defaultPrevented) return

  const dialogs = [...document.querySelectorAll('.q-dialog')].filter(isVisible)
  const active = document.activeElement
  const dialog = dialogs.find(candidate => candidate.contains(active)) || dialogs.at(-1)
  if (!dialog) return

  const focusable = [...dialog.querySelectorAll(focusableSelector)].filter(isVisible)
  if (!focusable.length) {
    event.preventDefault()
    dialog.querySelector('.q-dialog__inner')?.focus({ preventScroll: true })
    return
  }

  const first = focusable[0]
  const last = focusable.at(-1)

  if (event.shiftKey && (active === first || !dialog.contains(active))) {
    event.preventDefault()
    last.focus({ preventScroll: true })
  } else if (!event.shiftKey && (active === last || !dialog.contains(active))) {
    event.preventDefault()
    first.focus({ preventScroll: true })
  }
}

export default boot(() => {
  document.addEventListener('keydown', trapModalFocus, true)
})
