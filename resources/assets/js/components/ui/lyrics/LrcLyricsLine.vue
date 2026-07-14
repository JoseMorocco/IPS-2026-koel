<template>
  <p
    :class="{ active: isActive }"
    class="relative leading-relaxed origin-left cursor-default"
  >
    {{ line.text }}
  </p>
</template>

<script lang="ts" setup>
defineProps<{
  line: LrcLine
  isActive: boolean
}>()
</script>

<style lang="postcss" scoped>
p {
  color: color-mix(in srgb, var(--color-fg), transparent 40%);
  transition:
    color 300ms ease,
    transform 300ms ease,
    font-weight 300ms ease,
    text-shadow 300ms ease;

  &.active {
    @apply scale-110 font-bold;
    color: var(--color-highlight);
    text-shadow: 0 0 24px color-mix(in srgb, var(--color-highlight), transparent 55%);
  }
}
</style>
