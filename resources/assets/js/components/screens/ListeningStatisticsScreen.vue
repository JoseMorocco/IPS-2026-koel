<template>
  <ScreenBase>
    <template #header>
      <ScreenHeader layout="collapsed">
        Listening Statistics

        <template #meta>
          <span>Your monthly listening activity</span>
        </template>
      </ScreenHeader>
    </template>

    <div class="flex flex-col gap-6">
      <label class="flex items-center gap-3 self-end">
        <span class="text-k-fg-70">Month</span>
        <input
          v-model="selectedMonth"
          class="rounded-md border border-k-fg-10 bg-k-bg px-3 py-2 text-k-fg"
          type="month"
          :max="currentMonth"
          @change="fetchStatistics"
        />
      </label>

      <div v-if="loading" class="flex min-h-64 items-center justify-center" role="status">
        Loading listening statistics…
      </div>

      <template v-else>
        <section class="summary-grid">
          <article class="stat-card">
            <span class="text-k-fg-70">Minutes listened</span>
            <strong class="text-4xl">{{ statistics.total_minutes.toLocaleString() }}</strong>
            <span class="text-k-fg-50">{{ formattedMonth }}</span>
          </article>

          <article class="stat-card">
            <span class="text-k-fg-70">Songs started</span>
            <strong class="text-4xl">{{ statistics.total_plays.toLocaleString() }}</strong>
            <span class="text-k-fg-50">{{ formattedMonth }}</span>
          </article>
        </section>

        <section class="panel">
          <header class="flex flex-col gap-1">
            <h2 class="text-2xl font-semibold">Listening this month</h2>
            <p class="text-k-fg-60">Minutes listened each day</p>
          </header>

          <div v-if="hasListeningTime" class="mt-6 overflow-x-auto">
            <svg
              class="h-72 min-w-[720px] w-full"
              viewBox="0 0 900 290"
              role="img"
              :aria-label="`Daily listening minutes for ${formattedMonth}`"
            >
              <text class="axis-title" x="18" y="20">Minutes</text>

              <g v-for="tick in chartTicks" :key="tick.value">
                <line class="chart-grid" x1="65" :y1="tick.y" x2="880" :y2="tick.y" />
                <text class="axis-label" x="52" :y="tick.y + 4" text-anchor="end">{{ tick.value }}</text>
              </g>

              <g v-for="bar in chartBars" :key="bar.date">
                <rect
                  class="chart-bar"
                  :class="{ empty: bar.minutes === 0 }"
                  :x="bar.x"
                  :y="bar.y"
                  :width="bar.width"
                  :height="bar.height"
                  rx="5"
                >
                  <title>{{ bar.label }}</title>
                </rect>
                <text v-if="bar.showDay" class="axis-label" :x="bar.x + bar.width / 2" y="251" text-anchor="middle">
                  {{ bar.day }}
                </text>
              </g>

              <text class="axis-title" x="472" y="278" text-anchor="middle">Day of month</text>
            </svg>

            <p class="text-center text-sm text-k-fg-50">
              Each bar represents the minutes listened on one day. Point at a bar to see its exact value.
            </p>
          </div>

          <p v-else class="py-20 text-center text-k-fg-60">Play some music to start building your chart.</p>
        </section>

        <section class="panel">
          <header class="flex flex-col gap-1">
            <h2 class="text-2xl font-semibold">Top 10 most played</h2>
            <p class="text-k-fg-60">Your favorite songs for {{ formattedMonth }}</p>
          </header>

          <ol v-if="statistics.top_songs.length" class="mt-5 flex flex-col gap-2">
            <li v-for="(entry, index) in statistics.top_songs" :key="entry.song.id" class="ranking-row">
              <span class="w-8 text-center text-lg font-semibold text-k-fg-50">{{ index + 1 }}</span>
              <PlayableThumbnail :playable="entry.song" @clicked="play(entry.song)" />
              <span class="min-w-0 flex-1">
                <strong class="block truncate">{{ entry.song.title }}</strong>
                <span class="block truncate text-sm text-k-fg-60">{{ entry.song.artist_name }}</span>
              </span>
              <span class="text-right">
                <strong class="block">{{ entry.play_count }}</strong>
                <span class="text-sm text-k-fg-60">{{ entry.play_count === 1 ? 'play' : 'plays' }}</span>
              </span>
            </li>
          </ol>

          <p v-else class="py-20 text-center text-k-fg-60">No songs played during this month yet.</p>
        </section>
      </template>
    </div>
  </ScreenBase>
</template>

<script lang="ts" setup>
import { computed, ref, toRef } from 'vue'
import { useRouter } from '@/composables/useRouter'
import { playback } from '@/services/playbackManager'
import { listeningStatisticsStore } from '@/stores/listeningStatisticsStore'
import PlayableThumbnail from '@/components/playable/PlayableThumbnail.vue'
import ScreenBase from '@/components/screens/ScreenBase.vue'
import ScreenHeader from '@/components/ui/ScreenHeader.vue'

const currentMonth = new Date().toISOString().slice(0, 7)
const selectedMonth = ref(currentMonth)
const loading = ref(false)
const statistics = toRef(listeningStatisticsStore.state, 'statistics')

const formattedMonth = computed(() => {
  const [year, month] = selectedMonth.value.split('-').map(Number)

  return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(new Date(year, month - 1))
})

const hasListeningTime = computed(() => statistics.value.daily.some(day => day.seconds > 0))
const currentDate = new Date().toISOString().slice(0, 10)
const chartDays = computed(() =>
  selectedMonth.value === currentMonth
    ? statistics.value.daily.filter(day => day.date <= currentDate)
    : statistics.value.daily,
)
const maximumMinutes = computed(() => Math.max(...chartDays.value.map(day => day.minutes), 1))
const chartTicks = computed(() =>
  [maximumMinutes.value, Math.ceil(maximumMinutes.value / 2), 0]
    .filter((value, index, values) => values.indexOf(value) === index)
    .map(value => ({ value, y: 220 - (value / maximumMinutes.value) * 180 })),
)

const chartBars = computed(() => {
  const availableWidth = 815
  const slotWidth = availableWidth / Math.max(chartDays.value.length, 1)
  const width = Math.min(slotWidth * 0.68, 42)

  return chartDays.value.map((day, index, days) => {
    const height = day.minutes ? Math.max((day.minutes / maximumMinutes.value) * 180, 6) : 3
    const date = new Date(`${day.date}T00:00:00`)

    return {
      date: day.date,
      day: date.getDate(),
      minutes: day.minutes,
      label: `${date.toLocaleDateString()}: ${day.minutes} ${day.minutes === 1 ? 'minute' : 'minutes'}`,
      x: 65 + index * slotWidth + (slotWidth - width) / 2,
      y: 220 - height,
      width,
      height,
      showDay: days.length <= 10 || index === 0 || index === days.length - 1 || date.getDate() % 5 === 0,
    }
  })
})
const play = (song: Song) => playback().play(song)

const fetchStatistics = async () => {
  loading.value = true

  try {
    await listeningStatisticsStore.fetch(selectedMonth.value)
  } finally {
    loading.value = false
  }
}

useRouter().onScreenActivated('Statistics', fetchStatistics)
</script>

<style lang="postcss" scoped>
.summary-grid {
  @apply grid gap-4 md:grid-cols-2;
}

.stat-card,
.panel {
  @apply rounded-xl border border-k-fg-10 bg-k-bg p-5;
}

.stat-card {
  @apply flex flex-col gap-2;
}

.ranking-row {
  @apply flex items-center gap-4 rounded-lg bg-k-fg-5 px-3 py-2 transition-colors hover:bg-k-fg-10;
}

.chart-grid {
  stroke: var(--color-fg);
  stroke-opacity: 0.12;
  stroke-width: 1;
}

.chart-bar {
  fill: var(--color-highlight);
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
  transform-box: fill-box;
  transform-origin: bottom;

  &:hover {
    opacity: 0.8;
    transform: scaleY(1.03);
  }

  &.empty {
    opacity: 0.2;
  }
}

.axis-label,
.axis-title {
  fill: var(--color-fg);
  font-size: 13px;
  opacity: 0.6;
}

.axis-title {
  font-size: 14px;
  font-weight: 600;
}
</style>
