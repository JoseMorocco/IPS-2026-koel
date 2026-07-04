import { describe, expect, it } from 'vite-plus/test'
import { createHarness } from '@/__tests__/TestHarness'
import { http } from '@/services/http'
import { playableStore } from '@/stores/playableStore'
import { listeningStatisticsStore } from '@/stores/listeningStatisticsStore'

describe('listeningStatisticsStore', () => {
  const h = createHarness()

  it('fetches and synchronizes monthly listening statistics', async () => {
    const song = h.factory('song').make()
    const statistics: MonthlyListeningStatistics = {
      month: '2026-07',
      total_seconds: 300,
      total_minutes: 5,
      total_plays: 2,
      daily: [{ date: '2026-07-01', seconds: 300, minutes: 5 }],
      top_songs: [{ song, play_count: 2, listened_seconds: 300 }],
    }
    const getMock = h.mock(http, 'get').mockResolvedValue(statistics)
    const synchronizedSong = h.factory('song').make({ id: song.id })
    const syncMock = h.mock(playableStore, 'syncWithVault').mockReturnValue([synchronizedSong])

    await listeningStatisticsStore.fetch('2026-07')

    expect(getMock).toHaveBeenCalledWith('statistics/listening?month=2026-07')
    expect(syncMock).toHaveBeenCalledWith(song)
    expect(listeningStatisticsStore.state.statistics.top_songs[0].song.id).toBe(synchronizedSong.id)
  })
})
