import { screen } from '@testing-library/vue'
import { describe, it } from 'vite-plus/test'
import { createHarness } from '@/__tests__/TestHarness'
import { listeningStatisticsStore } from '@/stores/listeningStatisticsStore'
import Component from './ListeningStatisticsScreen.vue'

describe('listeningStatisticsScreen', () => {
  const h = createHarness()

  it('renders monthly totals, the chart, and the Top 10', () => {
    listeningStatisticsStore.state.statistics = {
      month: '2026-07',
      total_seconds: 300,
      total_minutes: 5,
      total_plays: 2,
      daily: [{ date: '2026-07-01', seconds: 300, minutes: 5 }],
      top_songs: [
        {
          song: h.factory('song').make({
            title: 'Billie Jean',
            artist_name: 'Michael Jackson',
            album_cover: null,
          }),
          play_count: 2,
          listened_seconds: 300,
        },
      ],
    }

    h.render(Component)

    screen.getByText('Listening Statistics')
    screen.getByText('Listening this month')
    screen.getByText('Top 10 most played')
    screen.getByText('Billie Jean')
    screen.getByRole('button', { name: 'Play' })
    screen.getByRole('img', { name: /Daily listening minutes/ })
  })
})
