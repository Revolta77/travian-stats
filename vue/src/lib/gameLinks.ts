export function trimGameBaseUrl(raw: string | null | undefined): string | null {
  if (raw == null || typeof raw !== 'string') {
    return null
  }
  const t = raw.trim()
  return t === '' ? null : t.replace(/\/$/, '')
}

export function gameKarteUrl(base: string, x: number, y: number): string {
  return `${base}/karte.php?x=${x}&y=${y}`
}

export function gameProfileUrl(base: string, playerExternalId: number): string {
  return `${base}/profile/${playerExternalId}`
}

export function gameAllianceUrl(base: string, allianceExternalId: number): string {
  return `${base}/alliance/${allianceExternalId}`
}
