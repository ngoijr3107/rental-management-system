<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { properties } from '@/api/resources'
import { errorMessage } from '@/api/client'
import { useToastStore } from '@/stores/toast'
import { formatMoney } from '@/composables/format'
import PageHeader from '@/components/PageHeader.vue'
import OccupancyBoard from '@/components/OccupancyBoard.vue'
import StatusPill from '@/components/StatusPill.vue'
import EmptyState from '@/components/EmptyState.vue'
import SkeletonTable from '@/components/SkeletonTable.vue'

const props = defineProps({ id: { type: [String, Number], required: true } })

const router = useRouter()
const toast = useToastStore()
const { t } = useI18n()
const property = ref(null)
const loading = ref(true)

const units = computed(() => property.value?.units ?? [])
const occupied = computed(() => units.value.filter((unit) => unit.status === 'occupied').length)
const monthlyRoll = computed(() =>
  units.value
    .filter((unit) => unit.status === 'occupied')
    .reduce((sum, unit) => sum + Number(unit.rent_amount ?? 0), 0),
)

async function load() {
  loading.value = true
  try {
    const { data } = await properties.get(props.id)
    property.value = data.data
  } catch (error) {
    toast.error(errorMessage(error, t('properties.loadFailed')))
  } finally {
    loading.value = false
  }
}

function openUnit(unit) {
  router.push({ path: '/units', query: { property_id: unit.property_id } })
}

onMounted(load)
</script>

<template>
  <div class="stack">
    <section v-if="loading" class="card"><SkeletonTable :rows="4" :columns="4" /></section>

    <template v-else-if="property">
      <PageHeader :title="property.name" :eyebrow="t('properties.detailEyebrow')">
        <template #actions>
          <RouterLink to="/properties" class="btn btn-ghost">
            {{ t('properties.backToList') }}
          </RouterLink>
          <RouterLink :to="`/units?property_id=${property.id}`" class="btn">
            {{ t('properties.manageUnits') }}
          </RouterLink>
        </template>
      </PageHeader>

      <section class="card summary">
        <div>
          <p class="eyebrow">{{ t('properties.address') }}</p>
          <p class="value">
            {{ property.address }}<template v-if="property.city">, {{ property.city }}</template>
          </p>
        </div>
        <div>
          <p class="eyebrow">{{ t('common.type') }}</p>
          <p class="value"><StatusPill :status="property.type" tone="neutral" /></p>
        </div>
        <div>
          <p class="eyebrow">{{ t('properties.letCount') }}</p>
          <p class="value num">{{ occupied }} / {{ units.length }}</p>
        </div>
        <div>
          <p class="eyebrow">{{ t('properties.rentRoll') }}</p>
          <p class="value num roll">{{ formatMoney(monthlyRoll) }}</p>
        </div>
      </section>

      <section class="card">
        <div class="card-head">
          <h3>{{ t('units.title') }}</h3>
          <span class="muted num">{{ units.length }}</span>
        </div>
        <div class="board-body">
          <OccupancyBoard v-if="units.length" :units="units" @select="openUnit" />
          <EmptyState v-else :title="t('properties.noUnits')" :hint="t('properties.noUnitsHint')">
            <RouterLink :to="`/units?property_id=${property.id}`" class="btn">
              {{ t('properties.addUnit') }}
            </RouterLink>
          </EmptyState>
        </div>
      </section>

      <p v-if="property.description" class="card notes">{{ property.description }}</p>
    </template>
  </div>
</template>

<style scoped>
.summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--sp-5);
  padding: var(--sp-5);
}
.value { margin: 4px 0 0; font-size: var(--text-md); }
.roll { font-size: 17px; font-weight: 600; color: var(--money); }
.board-body { padding: var(--sp-5); }
.notes { padding: var(--sp-4) var(--sp-5); margin: 0; color: var(--text-muted); font-size: var(--text-base); }
</style>
