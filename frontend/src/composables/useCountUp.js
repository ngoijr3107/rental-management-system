import { ref, watch } from 'vue'

/** Animate a figure up to its value. Skipped entirely for reduced motion. */
export function useCountUp(source, duration = 750) {
  const display = ref(Number(source.value ?? 0))

  const reduced =
    window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches

  watch(
    source,
    (target) => {
      const end = Number(target ?? 0)
      if (reduced || Number.isNaN(end)) {
        display.value = end || 0
        return
      }

      const start = display.value
      const startedAt = performance.now()

      function step(now) {
        const progress = Math.min((now - startedAt) / duration, 1)
        // easeOutExpo — fast, then settles
        const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress)
        display.value = start + (end - start) * eased
        if (progress < 1) requestAnimationFrame(step)
      }

      requestAnimationFrame(step)
    },
    { immediate: true },
  )

  return display
}
