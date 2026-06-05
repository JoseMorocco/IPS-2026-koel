import { reactive } from 'vue'
import { http } from '@/services/http'

export type YouTubeDownloadStatus = 'idle' | 'downloading' | 'success' | 'error'

export interface YouTubeDownloadResult {
  song: Song
  album: Album
}

export const youTubeDownloadService = {
  state: reactive({
    status: 'idle' as YouTubeDownloadStatus,
    message: '' as string,
  }),

  async download(url: string): Promise<YouTubeDownloadResult | null> {
    this.state.status = 'downloading'
    this.state.message = ''

    try {
      const result = await http.post<YouTubeDownloadResult | null>('youtube/download', { url })

      this.state.status = 'success'
      this.state.message = 'Track downloaded and added to your library!'

      return result
    } catch (error: unknown) {
      this.state.status = 'error'

      const err = error as { responseData?: { message?: string } }
      this.state.message = err.responseData?.message ?? 'Download failed. Please check the URL and try again.'

      return null
    }
  },

  reset() {
    this.state.status = 'idle'
    this.state.message = ''
  },
}
