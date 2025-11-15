<script setup lang="ts">
import AppShell from '@/Layouts/AppShell.vue'
import VariantInventoryMatrix from '@/Components/VariantInventoryMatrix.vue'
import ChannelSelect from '@/Components/Nav/ChannelSelect.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  variant: any,
  product: any,
  items: any[],
  locations: any[],
}>()

const title = computed(() => props.variant?.title || 'Variant')
const sku = computed(() => props.variant?.sku || '—')

// Prefer variant values; fallback to product defaults
function vOrDefault(v: any, defKey: string) {
  return v ?? props.product?.[defKey] ?? null
}
const dims = computed(() => {
  const length = vOrDefault(props.variant?.length, 'default_length')
  const width  = vOrDefault(props.variant?.width, 'default_width')
  const height = vOrDefault(props.variant?.height, 'default_height')
  const weight = vOrDefault(props.variant?.weight, 'default_weight')
  return { length, width, height, weight }
})

const pricing = computed(() => ({
  min: vOrDefault(props.variant?.price_min, 'default_price_min'),
  max: vOrDefault(props.variant?.price_max, 'default_price_max'),
  msrp: vOrDefault(props.variant?.msrp, 'default_msrp'),
}))

const tab = $ref<'inventory'|'pricing'|'channels'>('inventory')

// Pricing tab state
const base = ref({ min: props.variant?.price_min ?? null, max: props.variant?.price_max ?? null, msrp: props.variant?.msrp ?? null })
const ch = ref<string | number | null>(null)
const override = ref<{min:number|null,max:number|null,msrp:number|null}>({ min: null, max: null, msrp: null })
const effective = ref<{min:number|null,max:number|null,msrp:number|null}>({ min: null, max: null, msrp: null })
const savingBase = ref(false)
const savingOv = ref(false)

async function saveBase(){
  savingBase.value = true
  try{
    await window.axios.put(`/api/variants/${props.variant.id}/pricing`, {
      price_min: base.value.min, price_max: base.value.max, msrp: base.value.msrp
    })
    if (ch.value) await loadEffective()
  } finally { savingBase.value = false }
}

async function loadEffective(){
  if (!ch.value) return
  const { data } = await window.axios.get(`/api/channels/${ch.value}/variants/${props.variant.id}/pricing`)
  effective.value.min = data.effective?.price_min ?? null
  effective.value.max = data.effective?.price_max ?? null
  effective.value.msrp = data.effective?.msrp ?? null
  override.value.min = data.override?.price_min ?? null
  override.value.max = data.override?.price_max ?? null
  override.value.msrp = data.override?.msrp ?? null
}

async function saveOverride(){
  if (!ch.value) return
  savingOv.value = true
  try{
    await window.axios.put(`/api/channels/${ch.value}/variants/${props.variant.id}/pricing`, {
      price_min: override.value.min, price_max: override.value.max, msrp: override.value.msrp
    })
    await loadEffective()
  } finally { savingOv.value = false }
}

watch(ch, () => { loadEffective() })
</script>

<template>
  <AppShell :product-id="props.variant?.product_id" :variant-id="props.variant?.id">
    <div class="p-6 space-y-6">
      <!-- Header -->
      <header class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold" style="color:#016d77">{{ title }}</h1>
          <p class="text-gray-600 text-sm">SKU: <span class="font-mono">{{ sku }}</span></p>
          <p v-if="dims.weight || dims.length" class="text-gray-500 text-xs mt-1">
            <span v-if="dims.weight">Wt: {{ dims.weight }}</span>
            <span v-if="dims.length"> &middot; {{ dims.length }} × {{ dims.width || '—' }} × {{ dims.height || '—' }}</span>
          </p>
          <p v-if="pricing.min || pricing.msrp" class="text-gray-500 text-xs mt-1">
            <span v-if="pricing.min">Min: {{ Number(pricing.min).toFixed(2) }}</span>
            <span v-if="pricing.max"> &middot; Max: {{ Number(pricing.max).toFixed(2) }}</span>
            <span v-if="pricing.msrp"> &middot; MSRP: {{ Number(pricing.msrp).toFixed(2) }}</span>
          </p>
        </div>
      </header>

      <!-- Tabs -->
      <div class="flex gap-2">
        <button @click="tab='inventory'" :class="['px-3 py-2 rounded-xl', tab==='inventory' ? 'text-white' : 'border']" :style="tab==='inventory'? {background:'#016d77'} : {}">Inventory</button>
        <button @click="tab='pricing'" :class="['px-3 py-2 rounded-xl', tab==='pricing' ? 'text-white' : 'border']" :style="tab==='pricing'? {background:'#016d77'} : {}">Pricing</button>
        <button @click="tab='channels'" :class="['px-3 py-2 rounded-xl', tab==='channels' ? 'text-white' : 'border']" :style="tab==='channels'? {background:'#016d77'} : {}">Channels</button>
      </div>

      <section v-show="tab==='inventory'">
        <VariantInventoryMatrix :variant="props.variant" :items="props.items" :locations="props.locations"/>
      </section>

      <section v-show="tab==='pricing'" class="grid md:grid-cols-2 gap-6">
        <div class="rounded-2xl border p-4 space-y-3">
          <h3 class="font-medium" style="color:#016d77">Base Pricing</h3>
          <div class="grid grid-cols-3 gap-3">
            <label class="text-sm">Min <input v-model.number="base.min" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
            <label class="text-sm">Max <input v-model.number="base.max" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
            <label class="text-sm">MSRP <input v-model.number="base.msrp" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
          </div>
          <button :disabled="savingBase" @click="saveBase" class="px-4 py-2 rounded-xl text-white" style="background:#016d77">Save Base</button>
        </div>

        <div class="rounded-2xl border p-4 space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="font-medium" style="color:#016d77">Channel Override</h3>
            <ChannelSelect v-model="ch" />
          </div>

          <!-- Effective price summary -->
          <div v-if="ch" class="rounded-xl bg-gray-50 p-3 text-sm">
            <div class="font-medium mb-1">Effective Price</div>
            <div class="grid grid-cols-3 gap-3">
              <div>Min<br><span class="font-mono">{{ effective.min == null ? '—' : Number(effective.min).toFixed(2) }}</span></div>
              <div>Max<br><span class="font-mono">{{ effective.max == null ? '—' : Number(effective.max).toFixed(2) }}</span></div>
              <div>MSRP<br><span class="font-mono">{{ effective.msrp == null ? '—' : Number(effective.msrp).toFixed(2) }}</span></div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3 mt-2">
            <label class="text-sm">Min <input v-model.number="override.min" @focus="loadEffective" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
            <label class="text-sm">Max <input v-model.number="override.max" @focus="loadEffective" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
            <label class="text-sm">MSRP <input v-model.number="override.msrp" @focus="loadEffective" type="number" step="0.01" class="mt-1 w-full border rounded-lg p-2"/></label>
          </div>
          <div class="flex items-center gap-3">
            <button :disabled="savingOv || !ch" @click="saveOverride" class="px-4 py-2 rounded-xl text-white" style="background:#016d77">Save Override</button>
            <button v-if="ch" class="px-4 py-2 rounded-xl border" @click="override = {min:null,max:null,msrp:null}">Clear (nulls)</button>
          </div>
          <p class="text-xs text-gray-500">Leave a field empty to fall back to base pricing.</p>
        </div>
      </section>

      <section v-show="tab==='channels'" class="rounded-2xl border p-4 text-sm text-gray-600">
        <p>Channel status stub — show per-channel publish status, policy summary, and last sync.</p>
      </section>
    </div>
  </AppShell>
</template>
