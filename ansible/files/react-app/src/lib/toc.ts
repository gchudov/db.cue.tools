/**
 * TOC (Table of Contents) utilities for CD/CTDB operations
 */

// Helper to pad a hex string
function padHex(num: number, len: number): string {
  return num.toString(16).toUpperCase().padStart(len, '0')
}

/**
 * Convert TOC string to MusicBrainz disc ID
 * @param tocString - Colon-separated TOC string (e.g., "0:9938:23447:...")
 * @returns MusicBrainz disc ID (base64 with URL-safe replacements)
 */
export async function tocs2mbid(tocString: string): Promise<string> {
  const ids = tocString.split(':')
  const trackcount = ids.length - 1
  let lastaudio = trackcount
  while (lastaudio > 0 && ids[lastaudio - 1].startsWith('-')) {
    lastaudio--
  }
  const absIds = ids.map(id => Math.abs(Number(id)))

  // Build the hex string for hashing
  let mbtoc = '01' + padHex(lastaudio, 2)
  if (lastaudio === trackcount) {
    // Audio CD
    mbtoc += padHex(absIds[lastaudio] + 150, 8)
  } else {
    // Enhanced CD
    mbtoc += padHex(absIds[lastaudio] + 150 - 11400, 8)
  }
  for (let tr = 0; tr < lastaudio; tr++) {
    mbtoc += padHex(absIds[tr] + 150, 8)
  }
  // Pad to 804 characters
  mbtoc = mbtoc.padEnd(804, '0')

  // Hash the hex string as ASCII text
  const encoder = new TextEncoder()
  const data = encoder.encode(mbtoc)
  const hashBuffer = await crypto.subtle.digest('SHA-1', data)
  const hashArray = new Uint8Array(hashBuffer)

  // Base64 encode with MusicBrainz URL-safe replacements
  const base64 = btoa(String.fromCharCode(...hashArray))
  return base64.replace(/\+/g, '.').replace(/\//g, '_').replace(/=/g, '-')
}

/**
 * Convert TOC string to MusicBrainz TOC format for lookup URL
 * @param tocString - Colon-separated TOC string
 * @returns Space-separated MusicBrainz TOC format
 */
export function tocs2mbtoc(tocString: string): string {
  const ids = tocString.split(':')
  const trackcount = ids.length - 1
  let lastaudio = trackcount
  while (lastaudio > 0 && ids[lastaudio - 1].startsWith('-')) {
    lastaudio--
  }
  const absIds = ids.map(id => Math.abs(Number(id)))

  let mbtoc = '1 ' + lastaudio
  if (lastaudio === trackcount) {
    // Audio CD
    mbtoc += ' ' + (absIds[lastaudio] + 150)
  } else {
    // Enhanced CD
    mbtoc += ' ' + (absIds[lastaudio] + 150 - 11400)
  }
  for (let tr = 0; tr < lastaudio; tr++) {
    mbtoc += ' ' + (absIds[tr] + 150)
  }
  return mbtoc
}

/**
 * Convert sectors to time string (MM:SS.FF)
 * @param sectors - Number of sectors (75 sectors = 1 second)
 * @returns Formatted time string
 */
export function sectorsToTime(sectors: number): string {
  const totalSeconds = sectors / 75
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = Math.floor(totalSeconds % 60)
  const frames = sectors % 75
  return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${frames.toString().padStart(2, '0')}`
}

export interface Track {
  number: number
  name: string
  artist: string | null
  start: string
  length: string
  startSector: number
  endSector: number
  crc: string
  isDataTrack: boolean
}

/**
 * Build track list from TOC, CRCs, and metadata tracklist
 */
export function buildTracks(
  tocString: string,
  crcsString: string | null,
  tracklist: Array<{ name?: string; artist?: string }> | null,
  mainArtist: string | null
): Track[] {
  const toc = tocString.split(':')
  const crcs = crcsString ? crcsString.split(' ') : []
  const tracks: Track[] = []
  const ntracks = toc.length - 1

  let crcIndex = 0
  for (let i = 0; i < ntracks; i++) {
    const isDataTrack = toc[i].startsWith('-')
    const startOffset = 150 + Math.abs(Number(toc[i]))
    let endOffset = 149 + Math.abs(Number(toc[i + 1]))
    if (toc[i + 1].startsWith('-')) {
      endOffset -= 11400
    }

    const trackInfo = tracklist?.[i]
    const trackArtist = trackInfo?.artist
    const showArtist = trackArtist && trackArtist !== mainArtist ? trackArtist : null

    tracks.push({
      number: i + 1,
      name: trackInfo?.name || (isDataTrack ? '[data track]' : ''),
      artist: showArtist,
      start: sectorsToTime(startOffset),
      length: sectorsToTime(endOffset + 1 - startOffset),
      startSector: startOffset,
      endSector: endOffset,
      crc: !isDataTrack && crcIndex < crcs.length ? crcs[crcIndex] : '',
      isDataTrack,
    })

    if (!isDataTrack) {
      crcIndex++
    }
  }

  return tracks
}

