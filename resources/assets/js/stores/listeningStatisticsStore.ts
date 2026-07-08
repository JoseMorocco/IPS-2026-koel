import { reactive } from 'vue'
import { http } from '@/services/http'
import { playableStore } from '@/stores/playableStore'

const emptyStatistics = (month: string): MonthlyListeningStatistics => ({
  month,
  total_seconds: 0,
  total_minutes: 0,
  total_plays: 0,
  daily: [],
  top_songs: [],
})

export const listeningStatisticsStore = {
  state: reactive({
    statistics: emptyStatistics(''),
  }),

  async fetch(month: string) {
    const statistics = await http.get<MonthlyListeningStatistics>(`statistics/listening?month=${month}`)

    statistics.top_songs = statistics.top_songs.map(entry => ({
      ...entry,
      song: playableStore.syncWithVault(entry.song)[0] as Song,
    }))

    this.state.statistics = statistics

    return statistics
  },
}
