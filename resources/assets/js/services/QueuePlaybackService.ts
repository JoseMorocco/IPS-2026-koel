import type { Ref } from 'vue'
import { ref } from 'vue'
import { shuffle } from 'lodash-es'
import { commonStore } from '@/stores/commonStore'
import { preferenceStore as preferences } from '@/stores/preferenceStore'
import { queueStore } from '@/stores/queueStore'
import { recentlyPlayedStore } from '@/stores/recentlyPlayedStore'
import { playableStore } from '@/stores/playableStore'
import { userStore } from '@/stores/userStore'
import { logger } from '@/utils/logger'
import { isEpisode, isSong } from '@/utils/typeGuards'
import { arrayify, getPlayableProp } from '@/utils/helpers'
import { eventBus } from '@/utils/eventBus'
import { isAudioContextSupported } from '@/utils/supports'
import { audioService } from '@/services/audioService'
import { http } from '@/services/http'
import { socketService } from '@/services/socketService'
import { useEpisodeProgressTracking } from '@/composables/useEpisodeProgressTracking'
import { BasePlaybackService } from '@/services/BasePlaybackService'
import { crossfadeService } from '@/services/crossfadeService'
import { encyclopediaService } from '@/services/encyclopediaService'
import { volumeManager } from '@/services/volumeManager'
import { useBranding } from '@/composables/useBranding'

/**
 * The number of seconds before the current playable ends to start preloading the next one.
 */
const PRELOAD_BUFFER = 30

export class QueuePlaybackService extends BasePlaybackService {
  private repeatModes: RepeatMode[] = ['NO_REPEAT', 'REPEAT_ALL', 'REPEAT_ONE']
  private upNext: Ref<Playable | null> = ref(null)
  private activeListeningSessionId: number | null = null
  private listenedSeconds = 0
  private lastReportedSeconds = 0
  private lastPlaybackPosition = 0

  /**
   * The next item in the queue.
   * If we're in REPEAT_ALL mode and there's no next item, just get the first item.
   */
  public get next() {
    if (queueStore.next) {
      return queueStore.next
    }

    if (preferences.repeat_mode === 'REPEAT_ALL') {
      return queueStore.first
    }
  }

  /**
   * The previous item in the queue.
   * If we're in REPEAT_ALL mode and there's no prev item, get the last item.
   */
  public get previous() {
    if (queueStore.previous) {
      return queueStore.previous
    }

    if (preferences.repeat_mode === 'REPEAT_ALL') {
      return queueStore.last
    }
  }

  public async registerPlay(playable: Playable) {
    await this.flushListeningSession()
    recentlyPlayedStore.add(playable)
    playable.play_count_registered = true
    const interaction = await playableStore.registerPlay(playable)

    this.activeListeningSessionId = interaction.listening_session_id
    this.listenedSeconds = 0
    this.lastReportedSeconds = 0
    this.lastPlaybackPosition = this.media.currentTime

    if (isSong(playable) && !playable.album_cover) {
      encyclopediaService.fetchForAlbum({ id: playable.album_id } as Album).catch(logger.error)
    }
  }

  public preload(playable: Playable) {
    const audioElement = document.createElement('audio')
    audioElement.setAttribute('src', playableStore.getSourceUrl(playable))
    audioElement.setAttribute('preload', 'auto')
    audioElement.load()
    playable.preloaded = true
  }

  /**
   * Play a song. Because
   *
   * So many adventures couldn't happen today,
   * So many songs we forgot to play
   * So many dreams swinging out of the blue
   * We'll let them come true
   */
  public async play(playable: Playable, position = 0) {
    const isCrossfadeFinalization = crossfadeService.active && crossfadeService.state!.playable.id === playable.id

    // Cancel any active crossfade unless we're finalizing it
    if (!isCrossfadeFinalization) {
      this.cancelCrossfade()
    }

    if (isEpisode(playable)) {
      useEpisodeProgressTracking().trackEpisode(playable)
    }

    queueStore.queueIfNotQueued(playable, 'after-current')

    // If for any reason (most likely a bug), the requested playable has been deleted, attempt the next item in the queue.
    if (isSong(playable) && playable.deleted) {
      logger.warn('Attempted to play a deleted playable', playable)

      if (this.next && this.next.id !== playable.id) {
        await this.playNext()
      }

      return
    }

    this.markAsOnlyPlaying(playable)

    await this.setNowPlayingMeta(playable)

    if (isCrossfadeFinalization) {
      // The incoming track is already playing via the crossfade audio element.
      // Simply swap it in as the new primary — no src change, no seeking, no interruption.
      const { incomingAudio } = crossfadeService.state!

      // Stop and fully discard the old element
      this.media.pause()
      this.media.removeAttribute('src')
      this.media.load()

      // The incoming audio is already playing at the right position.
      // Just make it the new primary media element.
      this.swapMediaElement(incomingAudio)
      this.setVolume(volumeManager.get())

      // Reconnect the audio graph to the new element
      if (isAudioContextSupported && audioService.context) {
        audioService.reconnectSource(incomingAudio)
      }

      crossfadeService.state = null

      this.recordStartTime(playable)
      this.showNotification(playable)
    } else {
      // Normal playback: set src and start
      this.media.src = playableStore.getSourceUrl(playable)

      if (position === 0) {
        await this.restart()
      } else {
        this.seekTo(position)
        await this.resume()
      }
    }

    this.setMediaSessionActionHandlers()
  }

  public showNotification(playable: Playable) {
    if (!isSong(playable) && !isEpisode(playable)) {
      throw new Error('Invalid playable type.')
    }

    if (preferences.show_now_playing_notification) {
      try {
        const notification = new window.Notification(`♫ ${playable.title}`, {
          icon: getPlayableProp(playable, 'album_cover', 'episode_image'),
          body: isSong(playable) ? `${playable.album_name} – ${playable.artist_name}` : playable.title,
        })

        notification.onclick = () => window.focus()

        window.setTimeout(() => notification.close(), 5000)
      } catch (error: unknown) {
        // Notification fails.
        // @link https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification
        logger.error(error)
      }
    }

    if (!navigator.mediaSession) {
      return
    }

    navigator.mediaSession.metadata = new MediaMetadata({
      title: playable.title,
      artist: getPlayableProp(playable, 'artist_name', 'podcast_author'),
      album: getPlayableProp(playable, 'album_name', 'podcast_title'),
      artwork: [48, 64, 96, 128, 192, 256, 384, 512].map(d => ({
        src: getPlayableProp(playable, 'album_cover', 'episode_image'),
        sizes: `${d}x${d}`,
        type: 'image/png',
      })),
    })
  }

  public async restart() {
    const playable = queueStore.current!

    // Reset the "up next" value to let subscribers know that the next item is cleared
    // (because another playable, likely the "next" one, is being played)
    this.upNext.value = null

    this.recordStartTime(playable)
    socketService.broadcast('SOCKET_STREAMABLE', playable)

    try {
      http.silently.put('queue/playback-status', {
        song: playable.id,
        position: 0,
      })
    } catch (error: unknown) {
      logger.error(error)
    }

    this.media.currentTime = 0

    try {
      await this.media.play()
      navigator.mediaSession && (navigator.mediaSession.playbackState = 'playing')
      this.showNotification(playable)
      await this.registerPlay(playable)
    } catch (error: unknown) {
      // convert this into a warning to avoid breaking the app
      logger.warn(error)
    }
  }

  public rotateRepeatMode() {
    let index = this.repeatModes.indexOf(preferences.repeat_mode) + 1

    if (index >= this.repeatModes.length) {
      index = 0
    }

    preferences.repeat_mode = this.repeatModes[index]
  }

  /**
   * Play the prev item the queue, if one is found.
   * If there's no prev item and the current mode is NO_REPEAT, we stop completely.
   */
  public async playPrev() {
    // If the item's duration is greater than 5 seconds, and we've passed 5 seconds into it,
    // restart playing instead.
    if (this.media.currentTime > 5 && queueStore.current!.length > 5) {
      this.media.currentTime = 0

      return
    }

    if (!this.previous && preferences.repeat_mode === 'NO_REPEAT') {
      await this.stop()
    } else {
      this.previous && (await this.play(this.previous))
    }
  }

  /**
   * Play the next item in the queue if one is found.
   * If there's no next item and the current mode is NO_REPEAT, we stop completely.
   */
  public async playNext() {
    if (!this.next && preferences.repeat_mode === 'NO_REPEAT') {
      await this.stop() //  Nothing lasts forever, even cold November rain.
    } else {
      this.next && (await this.play(this.next))
    }
  }

  public async stop() {
    this.cancelCrossfade()
    this.flushListeningSession()
    this.activeListeningSessionId = null

    if (this.media) {
      this.media.pause()
      this.seekTo(0)
    }

    document.title = useBranding().name

    queueStore.current && (queueStore.current.playback_state = 'Stopped')

    navigator.mediaSession && (navigator.mediaSession.playbackState = 'none')

    socketService.broadcast('SOCKET_PLAYBACK_STOPPED')
  }

  public async pause() {
    this.cancelCrossfade()
    this.media.pause()
    this.flushListeningSession()

    queueStore.current!.playback_state = 'Paused'
    navigator.mediaSession && (navigator.mediaSession.playbackState = 'paused')

    socketService.broadcast('SOCKET_STREAMABLE', queueStore.current)
  }

  public async resume() {
    const playable = queueStore.current!

    if (!this.media.src) {
      // on first load when the queue is loaded from saved state, the player's src is empty
      // we need to properly set it as well as any kind of playback metadata
      this.media.src = playableStore.getSourceUrl(playable)
      this.seekTo(commonStore.state.queue_state.playback_position)

      await this.setNowPlayingMeta(queueStore.current!)
      this.recordStartTime(playable)
    }

    try {
      await this.media.play()
    } catch (error: unknown) {
      logger.error(error)
    }

    if (isSong(playable) && !this.activeListeningSessionId) {
      await this.registerPlay(playable)
    }

    queueStore.current!.playback_state = 'Playing'
    navigator.mediaSession && (navigator.mediaSession.playbackState = 'playing')

    socketService.broadcast('SOCKET_STREAMABLE', playable)
  }

  public async toggle() {
    if (!queueStore.current) {
      await this.playFirstInQueue()
      return
    }

    if (queueStore.current.playback_state !== 'Playing') {
      await this.resume()
      return
    }

    this.pause()
  }

  /**
   * Queue up playables (replace them into the queue) and start playing right away.
   */
  public async queueAndPlay(playables: MaybeArray<Playable>, shuffled = false) {
    playables = arrayify(playables)

    if (shuffled) {
      playables = shuffle(playables)
    }

    await this.stop()
    queueStore.replaceQueueWith(playables)
    await this.play(queueStore.first)
  }

  public async playFirstInQueue() {
    queueStore.all.length && (await this.play(queueStore.first))
  }

  private markAsOnlyPlaying(playable: Playable) {
    playableStore.vault.forEach((storedPlayable: Playable) => {
      storedPlayable.playback_state = storedPlayable.id === playable.id ? 'Playing' : 'Stopped'
    })

    queueStore.all.forEach((queuedPlayable: Playable) => {
      queuedPlayable.playback_state = queuedPlayable.id === playable.id ? 'Playing' : 'Stopped'
    })

    playable.playback_state = 'Playing'
  }

  private async setNowPlayingMeta(playable: Playable) {
    document.title = `${playable.title} ♫ ${useBranding().name}`
    this.media.setAttribute('title', isSong(playable) ? `${playable.artist_name} - ${playable.title}` : playable.title)

    if (isAudioContextSupported) {
      await audioService.context.resume()
    }
  }

  // Record the UNIX timestamp the playable starts playing, for scrobbling purpose
  private recordStartTime(song: Playable) {
    if (!isSong(song)) {
      return
    }

    song.play_start_time = Math.floor(Date.now() / 1000)
    song.play_count_registered = false
  }

  public forward(seconds: number): void {
    this.media.currentTime += seconds
  }

  protected onEnded(): void {
    this.flushListeningSession()

    if (
      queueStore.current &&
      isSong(queueStore.current) &&
      commonStore.state.uses_last_fm &&
      userStore.current.preferences.lastfm_session_key
    ) {
      playableStore.scrobble(queueStore.current)
    }

    // If a crossfade is active (completed or not), the outgoing track has ended — transition to the next song
    if (crossfadeService.active) {
      const { playable } = crossfadeService.state!
      this.play(playable)
      return
    }

    preferences.repeat_mode === 'REPEAT_ONE' ? this.restart() : this.playNext()
  }

  protected onError(error: ErrorEvent): void {
    logger.error(error)
    this.playNext()
  }

  protected onTimeUpdate(): void {
    const currentPlayable = queueStore.current

    if (!currentPlayable) {
      return
    }

    const media = this.media

    this.trackListenedTime(media.currentTime)

    if (Math.ceil(media.currentTime) % 5 === 0) {
      // every 5 seconds, we save the current playback position to the server
      try {
        http.silently.put('queue/playback-status', {
          song: currentPlayable.id,
          position: Math.ceil(media.currentTime),
        })
      } catch (error: unknown) {
        logger.error(error)
      }

      // if the current item is an episode, we emit an event to update the progress on the client side as well
      if (isEpisode(currentPlayable)) {
        eventBus.emit('EPISODE_PROGRESS_UPDATED', currentPlayable, Math.ceil(media.currentTime))
      }
    }

    const nextPlayable = queueStore.next

    if (!nextPlayable) {
      return
    }

    // Set the "up next" value to the next playable if we're near the end of the current playback.
    this.upNext.value = media.currentTime + 15 > media.duration ? nextPlayable : null

    // Preload the next playable if we're near the end of the current playback.
    if (media.currentTime + PRELOAD_BUFFER > media.duration && !nextPlayable.preloaded) {
      this.preload(nextPlayable)
    }

    // Initiate crossfade if enabled and near the end of the track
    const crossfadeDuration = preferences.crossfade_duration

    if (
      crossfadeDuration > 0 &&
      !crossfadeService.active &&
      preferences.repeat_mode !== 'REPEAT_ONE' &&
      media.duration > crossfadeDuration * 2 && // skip for short tracks
      media.currentTime + crossfadeDuration >= media.duration
    ) {
      if (crossfadeService.start(nextPlayable, crossfadeDuration, volumeManager.get())) {
        // Show the incoming track as "now playing" immediately
        queueStore.current!.playback_state = 'Stopped'
        nextPlayable.playback_state = 'Playing'
        this.setNowPlayingMeta(nextPlayable)
        this.showNotification(nextPlayable)
        this.registerPlay(nextPlayable)
      }
    }

    // Fade out the primary player during an active crossfade
    if (crossfadeService.active && crossfadeService.state) {
      const remaining = media.duration - media.currentTime
      const progress = Math.max(0, 1 - remaining / crossfadeDuration)
      this.setVolume(volumeManager.get() * (1 - progress))
    }
  }

  public rewind(seconds: number): void {
    this.media.currentTime -= seconds
  }

  public fastSeek(position: number): void {
    this.media.fastSeek(position || 0)
  }

  public seekTo(position: number): void {
    this.cancelCrossfade()
    this.media.currentTime = position || 0
    this.lastPlaybackPosition = this.media.currentTime
  }

  private trackListenedTime(currentPosition: number): void {
    if (!this.activeListeningSessionId || this.media.paused) {
      this.lastPlaybackPosition = currentPosition
      return
    }

    const elapsedSeconds = currentPosition - this.lastPlaybackPosition
    this.lastPlaybackPosition = currentPosition

    if (elapsedSeconds <= 0 || elapsedSeconds > 2) {
      return
    }

    this.listenedSeconds += elapsedSeconds

    if (Math.floor(this.listenedSeconds) - this.lastReportedSeconds >= 5) {
      this.flushListeningSession()
    }
  }

  private async flushListeningSession(): Promise<void> {
    const listenedSeconds = Math.floor(this.listenedSeconds)

    if (!this.activeListeningSessionId || listenedSeconds <= this.lastReportedSeconds) {
      return
    }

    this.lastReportedSeconds = listenedSeconds

    try {
      await http.silently.put(`interaction/listening-sessions/${this.activeListeningSessionId}`, {
        listened_seconds: listenedSeconds,
      })
    } catch (error: unknown) {
      this.lastReportedSeconds = 0
      logger.error(error)
    }
  }

  /** Cancel any active crossfade and restore volume */
  private cancelCrossfade() {
    if (crossfadeService.active) {
      crossfadeService.cancel()
      this.setVolume(volumeManager.get())
    }
  }
}

export const playbackService = new QueuePlaybackService()
