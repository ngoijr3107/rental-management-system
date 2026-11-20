<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { unitPhotos } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import AppIcon from './AppIcon.vue'

/*
 * Photos of a unit. The first one uploaded becomes the cover, which is what
 * appears wherever the unit is listed; any other can be promoted later.
 */
const props = defineProps({
  unitId: { type: [Number, String], required: true },
})

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToastStore()

const photos = ref([])
const loading = ref(false)
const uploading = ref(false)
const fileInput = ref(null)

const canEdit = () => auth.can('properties.manage')

async function load() {
  if (!props.unitId) return
  loading.value = true
  try {
    const { data } = await unitPhotos.list(props.unitId)
    photos.value = data.data ?? []
  } catch (error) {
    toast.error(errorMessage(error, t('photos.loadFailed')))
  } finally {
    loading.value = false
  }
}

async function pick(event) {
  const files = [...(event.target.files ?? [])]
  if (!files.length) return

  uploading.value = true
  try {
    await unitPhotos.upload(props.unitId, files)
    toast.success(t('photos.added', files.length))
    await load()
  } catch (error) {
    toast.error(errorMessage(error, t('photos.uploadFailed')))
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function makeCover(photo) {
  try {
    await unitPhotos.setCover(props.unitId, photo.id)
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

async function remove(photo) {
  try {
    await unitPhotos.remove(props.unitId, photo.id)
    toast.success(t('photos.removed'))
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  }
}

watch(() => props.unitId, load)
onMounted(load)
</script>

<template>
  <div class="photos">
    <div class="head">
      <p class="eyebrow">{{ t('photos.title') }}</p>
      <template v-if="canEdit()">
        <input
          ref="fileInput"
          class="hidden"
          type="file"
          accept="image/png,image/jpeg,image/webp"
          multiple
          @change="pick"
        />
        <button
          class="btn btn-ghost btn-sm"
          type="button"
          :disabled="uploading"
          @click="fileInput?.click()"
        >
          <AppIcon name="plus" :size="14" />
          {{ uploading ? t('photos.uploading') : t('photos.add') }}
        </button>
      </template>
    </div>

    <p v-if="loading" class="muted small">{{ t('common.loading') }}</p>

    <div v-else-if="photos.length" class="grid">
      <figure v-for="photo in photos" :key="photo.id" class="shot">
        <img :src="photo.url" :alt="photo.caption || t('photos.title')" loading="lazy" />
        <span v-if="photo.is_cover" class="badge">{{ t('photos.cover') }}</span>
        <figcaption v-if="canEdit()" class="tools">
          <button
            v-if="!photo.is_cover"
            type="button"
            :title="t('photos.makeCover')"
            @click="makeCover(photo)"
          >
            <AppIcon name="check" :size="14" />
          </button>
          <button type="button" class="danger" :title="t('common.delete')" @click="remove(photo)">
            <AppIcon name="trash" :size="14" />
          </button>
        </figcaption>
      </figure>
    </div>

    <p v-else class="muted small">{{ t('photos.empty') }}</p>
  </div>
</template>

<style scoped>
.photos { display: flex; flex-direction: column; gap: var(--sp-3); }
.head { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-3); }
.head .eyebrow { margin: 0; }
.hidden { display: none; }
.small { font-size: var(--text-sm); margin: 0; }

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
  gap: var(--sp-2);
}

.shot {
  position: relative;
  margin: 0;
  aspect-ratio: 4 / 3;
  border-radius: var(--radius-sm);
  overflow: hidden;
  border: 1px solid var(--line);
  background: var(--surface-2);
}
.shot img { width: 100%; height: 100%; object-fit: cover; display: block; }

.badge {
  position: absolute;
  left: 6px;
  top: 6px;
  padding: 2px 8px;
  border-radius: var(--radius-pill);
  background: var(--accent);
  color: var(--accent-contrast);
  font-size: 10px;
  font-weight: 600;
}

.tools {
  position: absolute;
  right: 6px;
  bottom: 6px;
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.15s var(--ease);
}
.shot:hover .tools,
.shot:focus-within .tools { opacity: 1; }

.tools button {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border: 0;
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
  box-shadow: var(--shadow-1);
}
.tools button.danger:hover { background: var(--negative); color: var(--on-negative); }
</style>
