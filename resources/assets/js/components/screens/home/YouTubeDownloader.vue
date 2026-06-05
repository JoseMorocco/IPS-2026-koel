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

      <Transition name="fade">
        <p v-if="message" :class="['status-message', status]" role="status">
          <Icon v-if="status === 'success'" :icon="faCircleCheck" />
          <Icon v-if="status === 'error'" :icon="faCircleXmark" />
          {{ message }}
        </p>
      </Transition>
    </div>
  </HomeScreenBlock>
</template>

<script lang="ts" setup>
import { faYoutube } from '@fortawesome/free-brands-svg-icons'
import { faCircleCheck, faCircleXmark, faDownload, faSpinner } from '@fortawesome/free-solid-svg-icons'
import { computed, defineAsyncComponent, ref, toRef } from 'vue'
import { albumStore } from '@/stores/albumStore'
import { commonStore } from '@/stores/commonStore'
import { playableStore } from '@/stores/playableStore'
import { eventBus } from '@/utils/eventBus'
import { youTubeDownloadService } from '@/services/youTubeDownloadService'

import HomeScreenBlock from '@/components/screens/home/HomeScreenBlock.vue'

const Btn = defineAsyncComponent(() => import('@/components/ui/form/Btn.vue'))

const url = ref('')

const status = toRef(youTubeDownloadService.state, 'status')
const message = toRef(youTubeDownloadService.state, 'message')
const isDownloading = computed(() => status.value === 'downloading')

const handleDownload = async () => {
  const trimmedUrl = url.value.trim()
  if (!trimmedUrl) {
    return
  }

  const result = await youTubeDownloadService.download(trimmedUrl)

  if (result?.song && result?.album) {
    playableStore.syncWithVault(result.song)
    albumStore.syncWithVault(result.album)
    commonStore.state.song_length += 1
    eventBus.emit('SONG_UPLOADED', result.song)
    url.value = ''
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

.status-message {
  @apply flex items-center gap-2 text-sm;

  &.success {
    @apply text-k-success;
  }

  &.error {
    @apply text-k-danger;
  }
}

.fade-enter-active,
.fade-leave-active {
  @apply transition-opacity duration-300;
}

.fade-enter-from,
.fade-leave-to {
  @apply opacity-0;
}
</style>
