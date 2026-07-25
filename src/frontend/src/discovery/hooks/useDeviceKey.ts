const STORAGE_KEY = 'discovery_device_key'

function createDeviceKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID()
  }
  return `dk_${Date.now()}_${Math.random().toString(36).slice(2, 12)}`
}

export function getDiscoveryDeviceKey(): string {
  try {
    const existing = localStorage.getItem(STORAGE_KEY)
    if (existing && existing.length <= 64) {
      return existing
    }
    const next = createDeviceKey()
    localStorage.setItem(STORAGE_KEY, next)
    return next
  } catch {
    return createDeviceKey()
  }
}

export function discoveryDeviceHeaders(): HeadersInit {
  return {
    'X-Discovery-Device-Key': getDiscoveryDeviceKey(),
  }
}
