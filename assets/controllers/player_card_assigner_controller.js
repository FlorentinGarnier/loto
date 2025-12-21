import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['feedback', 'list', 'hint']
  static values = { playerId: Number }

  connect() {
    this.pending = false
    this.createIntent = false
    this.debouncedLookup = this.debounce(this.lookupNow.bind(this), 250)
    // autofocus robuste même après navigation Turbo
    setTimeout(() => { this.element?.focus?.() }, 0)
  }

  get input() { return this.element }

  setFeedback(msg, type = 'neutral') {
    if (!this.hasFeedbackTarget) return
    this.feedbackTarget.textContent = msg || ''
    this.feedbackTarget.className = `text-sm mt-1 feedback ${type === 'success' ? 'text-green-700' : type === 'error' ? 'text-red-700' : type === 'pending' ? 'text-gray-500' : 'text-gray-700'}`
  }

  setHint(text) {
    if (!this.hasHintTarget) return
    this.hintTarget.textContent = text || ''
  }

  reset() {
    this.createIntent = false
    this.input.value = ''
    this.setHint('')
    this.setFeedback('')
    this.input.focus()
  }

  async submit(event) {
    event.preventDefault()
    if (this.pending) return

    const ref = this.input.value.trim()
    if (!ref) return

    const createIfMissing = this.createIntent === true

    try {
      this.pending = true
      this.setFeedback('…', 'pending')
      const res = await fetch(`/admin/players/${this.playerIdValue}/cards/assign`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ref, createIfMissing })
      })
      let data = {}
      const isJson = res.headers.get('content-type')?.includes('json')
      if (isJson) data = await res.json()

      if (res.status === 200 || res.status === 201) {
        this.prependCard(ref)
        this.setFeedback(res.status === 201 ? 'Carton créé et ajouté' : 'Carton ajouté', 'success')
        this.input.value = ''
        this.createIntent = false
        this.input.focus()
        return
      }
      if (res.status === 409) {
        this.setFeedback(data.message || 'Déjà attribué à un autre joueur', 'error')
      } else if (res.status === 422) {
        this.setFeedback(data.message || 'Format invalide', 'error')
      } else if (res.status === 404) {
        // Première validation: proposer la création
        this.createIntent = true
        this.setHint(`Aucun carton « ${ref} ». Entrée pour créer et attribuer`)
        this.setFeedback('', 'neutral')
      } else {
        this.setFeedback('Erreur. Réessayez.', 'error')
      }
    } catch (e) {
      this.setFeedback('Réseau indisponible', 'error')
    } finally {
      this.pending = false
      if (!this.createIntent) this.input.value = ''
      this.input.focus()
    }
  }

  lookup() { this.debouncedLookup() }

  async lookupNow() {
    const ref = this.input.value.trim()
    this.createIntent = false
    this.setHint('')
    if (!ref) return

    try {
      const res = await fetch(`/admin/cards/lookup?ref=${encodeURIComponent(ref)}`)
      if (res.status === 200) {
        const data = await res.json()
        if (data.assignedTo) {
          this.setHint(`Déjà attribué à ${data.assignedTo.name}`)
        } else {
          this.setHint('Disponible')
        }
      } else if (res.status === 404) {
        this.setHint(`Aucun carton « ${ref} ». Entrée pour créer`)
      }
    } catch (_) {
      // silencieux
    }
  }

  async remove(event) {
    const ref = event.currentTarget.dataset.refValue
    const li = this.listTarget.querySelector(`li[data-ref="${CSS.escape(ref)}"]`)
    if (!ref || !li) return

    const backup = li.cloneNode(true)
    try {
      const res = await fetch(`/admin/players/${this.playerIdValue}/cards/${encodeURIComponent(ref)}`, { method: 'DELETE' })
      if (res.status === 204) {
        li.remove()
        this.toastUndo(ref, backup)
      } else {
        this.setFeedback('Retrait impossible', 'error')
      }
    } catch (_) {
      this.setFeedback('Réseau indisponible', 'error')
    }
  }

  toastUndo(ref, backupNode) {
    const undo = document.createElement('div')
    undo.className = 'fixed bottom-4 left-1/2 -translate-x-1/2 bg-gray-900 text-white px-3 py-2 rounded shadow'
    undo.textContent = 'Retiré. '
    const btn = document.createElement('button')
    btn.className = 'underline ml-1'
    btn.textContent = 'Annuler'
    btn.onclick = async () => {
      try {
        const res = await fetch(`/admin/players/${this.playerIdValue}/cards/assign`, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ ref, createIfMissing: false })
        })
        if (res.ok) {
          this.listTarget.prepend(backupNode)
          undo.remove()
        }
      } catch (_) {}
    }
    undo.appendChild(btn)
    document.body.appendChild(undo)
    setTimeout(() => undo.remove(), 5000)
  }

  prependCard(ref) {
    const existing = this.listTarget.querySelector(`li[data-ref="${CSS.escape(ref)}"]`)
    if (existing) return // idempotent
    const li = document.createElement('li')
    li.className = 'py-1 flex items-center justify-between'
    li.dataset.ref = ref
    li.innerHTML = `<strong>${ref}</strong> <button type="button" class="text-sm text-red-700 hover:underline" data-action="player-card-assigner#remove" data-ref-value="${ref}">Retirer</button>`
    this.listTarget.prepend(li)
  }

  debounce(fn, delay) {
    let t
    return (...args) => {
      clearTimeout(t)
      t = setTimeout(() => fn(...args), delay)
    }
  }
}
