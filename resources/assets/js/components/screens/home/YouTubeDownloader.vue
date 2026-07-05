<template>
  <HomeScreenBlock>
    <template #header>
      <Icon :icon="faYoutube" class="mr-2 text-red-500" />
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
        <pre v-show="showDetails" class="error-log">{{ technicalError }}</pre>
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
    technicalError.value =
      youTubeDownloadService.state.message || 'Download failed. Please check the URL and try again.'
    toastError('Download failed. Check the details below.')
  }
}
</script>

<style lang="postcss" scoped>
.yt-downloader {
  @apply flex flex-col gap-3;
}

.input-row {
  @apply flex items-stretch gap-3;
}

.url-input {
  @apply flex-1 rounded border border-k-fg-20 bg-k-bg-input px-3 py-2 text-k-fg
         transition-colors duration-200 placeholder-k-fg-40
         focus:border-k-highlight focus:outline-none
         disabled:cursor-not-allowed disabled:opacity-50;
}

.download-btn {
  @apply flex items-center gap-2 whitespace-nowrap;
}

.error-panel {
  @apply flex flex-col gap-2;
}

.details-toggle {
  @apply inline-flex cursor-pointer items-center self-start text-xs
         text-k-danger transition-opacity duration-150 hover:opacity-80;
}

.error-log {
  @apply max-h-40 overflow-y-auto break-all whitespace-pre-wrap rounded
         border border-k-danger bg-k-bg p-3 font-mono text-xs text-k-danger opacity-80;
}
</style>
