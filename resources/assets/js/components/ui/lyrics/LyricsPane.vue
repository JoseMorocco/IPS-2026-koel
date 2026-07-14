<template>
  <div v-if="hasLyrics" class="group relative h-full">
    <LrcLyricsPane v-if="isLrc" :font-size="fontSize" :lyrics="lrcLyrics" class="absolute inset-0 px-6 py-8" />

    <div v-else class="lyrics px-6 py-8 whitespace-pre-wrap leading-relaxed" data-testid="plain-text-lyrics">
      {{ plainTextLyrics }}
    </div>

    <Magnifier
      class="absolute top-4 right-4 opacity-0 group-hover:opacity-50 hover:!opacity-100 transition-opacity no-hover:!opacity-100"
      @in="zoomIn"
      @out="zoomOut"
    />
  </div>

  <!-- Auto-fetch loading skeleton -->
  <div v-else-if="isFetchingLyrics" class="px-6 py-8 space-y-3" data-testid="lyrics-auto-fetch-loading" aria-busy="true" aria-label="Searching for lyrics">
    <p class="text-k-fg-50 text-sm flex items-center gap-2">
      <span class="inline-block h-3 w-3 rounded-full border-2 border-k-highlight border-t-transparent animate-spin" aria-hidden="true" />
      Searching for lyrics automatically&hellip;
    </p>
    <div class="space-y-2 mt-4 animate-pulse">
      <div class="h-3 rounded-full bg-k-fg-10 w-3/4" />
      <div class="h-3 rounded-full bg-k-fg-10 w-full" />
      <div class="h-3 rounded-full bg-k-fg-10 w-5/6" />
      <div class="h-3 rounded-full bg-k-fg-10 w-2/3" />
      <div class="h-3 rounded-full bg-k-fg-10 w-4/5" />
    </div>
  </div>

  <p v-else class="px-6 py-8">
    <template v-if="userCanUpdateLyrics">
      No lyrics found.
      <a role="button" @click.prevent="showEditSongForm">Click here</a>
      to add lyrics.
    </template>
    <span v-else>No lyrics available. Are you listening to Bach?</span>
  </p>
</template>

<script lang="ts" setup>
import { computed, ref, toRefs, watch } from 'vue'
import { preferenceStore as preferences } from '@/stores/preferenceStore'
import { defineAsyncComponent } from '@/utils/helpers'
import { useLyrics } from '@/composables/useLyrics'
import { useModal } from '@/composables/useModal'
import { useLyricsAutoFetch } from '@/composables/useLyricsAutoFetch'

const props = defineProps<{ song: Song }>()
const LrcLyricsPane = defineAsyncComponent(() => import('@/components/ui/lyrics/LrcLyricsPane.vue'))
const Magnifier = defineAsyncComponent(() => import('@/components/ui/Magnifier.vue'))
const EditSongForm = defineAsyncComponent(() => import('@/components/playable/EditSongForm.vue'))

const { openModal } = useModal()
const { song } = toRefs(props)
const zoomLevel = ref(preferences.lyrics_zoom_level || 1)

const { plainTextLyrics, lrcLyrics, isLrc, hasLyrics, userCanUpdateLyrics } = useLyrics(song)
const { isFetchingLyrics } = useLyricsAutoFetch(song, userCanUpdateLyrics)

const fontSize = computed(() => `${1 + (zoomLevel.value - 1) * 0.2}rem`)

const zoomIn = () => (zoomLevel.value = Math.min(zoomLevel.value + 1, 8))
const zoomOut = () => (zoomLevel.value = Math.max(zoomLevel.value - 1, -2))
const showEditSongForm = () => openModal<'EDIT_SONG_FORM'>(EditSongForm, { songs: [song.value], initialTab: 'lyrics' })

watch(zoomLevel, level => (preferences.lyrics_zoom_level = level), { immediate: true })
</script>

<style lang="postcss" scoped>
.lyrics {
  font-size: v-bind(fontSize);
  line-height: 1.85;
  color: color-mix(in srgb, var(--color-fg), transparent 20%); /* k-fg-80 equivalent */
}
</style>
