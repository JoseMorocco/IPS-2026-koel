import type { Ref } from 'vue'
import { ref, watch } from 'vue'
import { lrcLibService } from '@/services/lrcLibService'
import { playableStore } from '@/stores/playableStore'
import { useMessageToaster } from '@/composables/useMessageToaster'

/**
 * Automatically fetches and saves lyrics from LRCLIB when a song has no lyrics.
 *
 * Only triggers for users with edit permissions (`userCanUpdateLyrics`).
 * Uses a Set to avoid re-fetching the same song in a single session.
 *
 * @param songRef     - Reactive reference to the current song
 * @param canUpdate   - Reactive flag indicating whether the user can save lyrics
 */
export const useLyricsAutoFetch = (songRef: Ref<Song>, canUpdate: Ref<boolean>) => {
  const isFetchingLyrics = ref(false)
  const autoFetchFailed = ref(false)

  // Tracks which song IDs have already been attempted this session,
  // preventing redundant API calls when switching tabs or re-rendering.
  const attemptedSongIds = new Set<Song['id']>()

  const { toastSuccess, toastWarning } = useMessageToaster()

  watch(
    [songRef, canUpdate],
    async ([song, canUpdate]) => {
      // Only proceed if:
      // 1. We have a valid song object
      // 2. The song has no lyrics stored
      // 3. The current user has edit permissions
      // 4. We haven't already tried fetching for this song this session
      if (!song || song.lyrics || !canUpdate || attemptedSongIds.has(song.id)) {
        return
      }

      attemptedSongIds.add(song.id)
      isFetchingLyrics.value = true
      autoFetchFailed.value = false

      try {
        const lyrics = await lrcLibService.search(song)

        if (lyrics) {
          // Persist lyrics to Koel's backend via the official updateSongs channel.
          // syncWithVault inside updateSongs will update song.lyrics reactively,
          // which causes useLyrics.ts to re-parse and display the lyrics.
          await playableStore.updateSongs([song], { lyrics })
          toastSuccess('Lyrics found and saved automatically.')
        } else {
          // No match found — show normal empty state
          autoFetchFailed.value = true
        }
      } catch {
        // Network failure or unexpected error — degrade gracefully
        autoFetchFailed.value = true
        toastWarning('Could not fetch lyrics automatically.')
      } finally {
        isFetchingLyrics.value = false
      }
    },
    { immediate: true },
  )

  return {
    isFetchingLyrics,
    autoFetchFailed,
  }
}
