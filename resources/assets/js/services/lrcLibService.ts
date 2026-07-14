/**
 * Service for fetching synchronized lyrics from the public LRCLIB API.
 * @see https://lrclib.net/docs
 */

interface LrcLibResult {
  id: number
  trackName: string
  artistName: string
  albumName: string
  duration: number
  syncedLyrics: string | null
  plainLyrics: string | null
}

export const lrcLibService = {
  /**
   * Search for lyrics on LRCLIB for the given song.
   *
   * Returns syncedLyrics (LRC format) if available, falling back to plainLyrics.
   * Returns null if no result is found or the request fails.
   */
  async search(song: Song): Promise<string | null> {
    const params = new URLSearchParams({
      track_name: song.title,
      artist_name: song.artist_name,
    })

    if (song.album_name) {
      params.set('album_name', song.album_name)
    }

    try {
      const response = await fetch(`https://lrclib.net/api/search?${params}`, {
        signal: AbortSignal.timeout(8_000),
        headers: {
          'Lrclib-Client': 'Koel (https://koel.dev)',
        },
      })

      if (!response.ok) {
        return null
      }

      const results: LrcLibResult[] = await response.json()

      if (!results.length) {
        return null
      }

      // LRCLIB orders results by relevance; prefer the first match.
      // Prefer syncedLyrics (LRC format) over plain text.
      const best = results[0]
      return best.syncedLyrics || best.plainLyrics || null
    } catch {
      // Network error, timeout, or parse failure — fail silently
      return null
    }
  },
}
