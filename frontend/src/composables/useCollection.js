import { reactive, ref, watch } from 'vue'
import i18n from '@/i18n'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'

/**
 * Shared list-screen behaviour: paginated fetch, debounced filters, and a
 * loading flag. `fetcher` is one of the `list` functions in api/resources.
 */
export function useCollection(fetcher, initialFilters = {}) {
  const toast = useToastStore()

  const items = ref([])
  const meta = ref(null)
  const loading = ref(false)
  const filters = reactive({ ...initialFilters, page: 1, per_page: 15 })

  let debounce

  async function load() {
    loading.value = true
    try {
      const { data } = await fetcher({ ...filters })
      items.value = data.data ?? []
      meta.value = data.meta ?? null
    } catch (error) {
      toast.error(errorMessage(error, i18n.global.t('errors.listFailed')))
      items.value = []
    } finally {
      loading.value = false
    }
  }

  function goToPage(page) {
    filters.page = page
    load()
  }

  // Changing any filter resets to the first page.
  watch(
    () => JSON.stringify({ ...filters, page: undefined }),
    () => {
      clearTimeout(debounce)
      debounce = setTimeout(() => {
        filters.page = 1
        load()
      }, 250)
    },
  )

  return { items, meta, loading, filters, load, goToPage }
}
