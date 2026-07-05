<template>
  <HomeScreenBlock>
    <template #header>
      <Icon :icon="faYoutube" class="text-red-500 mr-2" />
      Download Music from YouTube
    </template>

    <div class="yt-downloader">
      <form id="yt-download-form" class="input-row" @submit.prevent="handleDownload">
        <input
          id="yt-url-input"
          v-model="url"
          :disabled="isDownloading"
          autocomplete="off"
          class="url-input"
          placeholder="https://www.youtube.com/watch?v=..."
          type="url"
        />
        <Btn
          id="yt-download-btn"
          :disabled="isDownloading || !url.trim()"
          class="download-btn"
          type="submit"
          variant="highlight"
        >
          <Icon v-if="isDownloading" :icon="faSpinner" spin />
          <Icon v-else :icon="faDownload" />
          {{ isDownloading ? 'Downloading…' : 'Download' }}
        </Btn>
      </form>

      <div v-if="technicalError" class="error-panel">
        <button class="details-toggle" type="button" @click="showDetails = !showDetails">
          <Icon :icon="showDetails ? faChevronUp : faChevronDown" class="mr-1" />
          {{ showDetails ? 'Ocultar detalles del error' : 'Ver detalles del error' }}
        </button>
        <Transition name="slide">
          <pre v-if="showDetails" class="error-log">{{ technicalError }}</pre>
        </Transition>
      </div>
    </div>
  </HomeScreenBlock>
</template>

<script lang="ts" setup>
import { faYoutube } from '@fortawesome/free-brands-svg-icons'
import { faChevronDown, faChevronUp, faDownload, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { computed, defineAsyncComponent, ref } from 'vue'
import { albumStore } from '@/stores/albumStore'
import { commonStore } from '@/stores/commonStore'
import { playableStore } from '@/stores/playableStore'
import { eventBus } from '@/utils/eventBus'
import { youTubeDownloadService } from '@/services/youTubeDownloadService'
import { useMessageToaster } from '@/composables/useMessageToaster'

import HomeScreenBlock from '@/components/screens/home/HomeScreenBlock.vue'

const Btn = defineAsyncComponent(() => import('@/components/ui/form/Btn.vue'))

const { toastSuccess, toastError } = useMessageToaster()

const url = ref('')
const technicalError = ref('')
const showDetails = ref(false)
const isDownloading = computed(() => youTubeDownloadService.state.status === 'downloading')

const handleDownload = async () => {
  const trimmedUrl = url.value.trim()
  if (!trimmedUrl) {
    return
  }

  technicalError.value = ''
  showDetails.value = false

  const result = await youTubeDownloadService.download(trimmedUrl)

  if (result?.song && result?.album) {
    playableStore.syncWithVault(result.song)
    albumStore.syncWithVault(result.album)
    commonStore.state.song_length += 1
    eventBus.emit('SONG_UPLOADED', result.song)
    toastSuccess('Track downloaded and added to your library!')
    url.value = ''
  } else {
    technicalError.value = youTubeDownloadService.state.message || 'Download failed. Please check the URL and try again.'
    toastError('Error en la descarga. Revisa los detalles locales.')
  }
}
</script>

<style lang="postcss" scoped>
.yt-downloader {
  @apply flex flex-col gap-3;
}

.input-row {
  @apply flex gap-3 items-stretch;
}

.url-input {
  @apply flex-1 bg-k-bg-input border border-k-fg-20 rounded px-3 py-2
         text-k-fg placeholder-k-fg-40
         focus:outline-none focus:border-k-highlight
         disabled:opacity-50 disabled:cursor-not-allowed
         transition-colors duration-200;
}

.download-btn {
  @apply whitespace-nowrap flex items-center gap-2;
}

.error-panel {
  @apply flex flex-col gap-2;
}

.details-toggle {
  @apply inline-flex items-center text-xs text-k-danger cursor-pointer
         hover:opacity-80 transition-opacity duration-150 self-start;
}

.error-log {
  @apply text-xs font-mono whitespace-pre-wrap break-all
         bg-k-bg border border-k-danger/40 text-k-danger/80
         rounded p-3 max-h-40 overflow-y-auto;
}

.slide-enter-active,
.slide-leave-active {
  @apply transition-all duration-200 overflow-hidden;
  max-height: 10rem;
}

.slide-enter-from,
.slide-leave-to {
  max-height: 0;
  @apply opacity-0;
}
</style>
