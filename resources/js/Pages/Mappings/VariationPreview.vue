<template>
    <div class="space-y-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Variation Preview</h2>
                <p class="text-sm text-gray-600">Product #{{ product?.shopify_product_id }} • {{ product?.title }}</p>
            </div>
            <div class="flex items-center gap-2" v-if="ready">
                <button :disabled="!canPublish" @click="queuePublish" class="px-4 py-2 rounded text-white" :class="canPublish ? 'bg-emerald-600' : 'bg-gray-400'">
                    {{ canPublish ? 'Queue Publish' : 'Fix issues to publish' }}
                </button>
            </div>
        </header>


        <section class="grid lg:grid-cols-3 gap-6" v-if="ready">
            <div class="lg:col-span-2 space-y-4">
                <div class="border rounded p-4">
                    <h3 class="font-semibold mb-2">Variants</h3>
                    <VariationMatrix :variants="product.variants" :theme="mapping.variation_theme || 'SizeColor'" />
                </div>


                <div class="border rounded p-4">
                    <h3 class="font-semibold mb-2">Images</h3>
                    <div class="flex flex-wrap gap-2" v-if="(product.images || []).length">
                        <img v-for="(u,i) in product.images" :key="i" :src="u" class="h-16 w-16 object-cover rounded border"/>
                    </div>
                    <p v-else class="text-sm text-gray-600">No product images found.</p>
                </div>
            </div>


            <aside class="space-y-4">
                <div class="border rounded p-4">
                    <h3 class="font-semibold mb-2">Checklist</h3>
                    <ul class="space-y-2">
                        <ChecklistItem :ok="!!channelState?.policies?.has_all" label="Business policies selected" :hint="policyHint"/>
                        <ChecklistItem :ok="!!channelState?.location?.has_default" label="Default inventory location set"/>
                        <ChecklistItem :ok="categoryOk" :label="categoryLabel" :hint="categoryHint"/>
                        <ChecklistItem :ok="!!mapping.title" label="Title mapped"/>
                        <ChecklistItem :ok="!!mapping.description" label="Description mapped"/>
                        <ChecklistItem :ok="imagesOk" label="Images present (≤12)"/>
                        <ChecklistItem :ok="variantsOk" label="All variants have price, qty, and aspect values" :hint="variantsHint"/>
                    </ul>
                </div>


                <div class="border rounded p-4">
                    <h3 class="font-semibold mb-2">Mapping Summary</h3>
                    <dl class="text-sm grid grid-cols-3 gap-2">
                        <dt class="text-gray-600">Theme</dt><dd class="col-span-2">{{ mapping.variation_theme || 'SizeColor' }}</dd>
                        <dt class="text-gray-600">Category</dt><dd class="col-span-2">{{ mapping.category || '—' }}</dd>
                        <dt class="text-gray-600">Marketplace</dt><dd class="col-span-2">{{ channelState?.marketplace_id }}</dd>
                    </dl>
                </div>
            </aside>
        </section>


        <div v-else class="text-sm text-gray-600">Loading…</div>
    </div>
</template>

<script setup>

import { ref, reactive, computed, onMounted } from 'vue'
import VariationMatrix from '@/Components/VariationMatrix.vue'
import ChecklistItem from '@/Components/ChecklistItem.vue'

const props = defineProps({ productId: [String, Number], channelId: [String, Number] })


const productId = ref(Number(props.productId))
const channelId = ref(Number(props.channelId))


const product = ref(null)
const mapping = reactive({})
const channelState = ref(null)
const supportsVar = ref(null)
const ready = computed(()=> !!product.value && !!channelState.value && Object.keys(mapping).length>0)


const imagesOk = computed(()=> (product.value?.images || []).length > 0 && (product.value.images || []).length <= 12)

const variantsOk = computed(()=>{
    if(!product.value) return false
    const theme = mapping.variation_theme || 'SizeColor'
    const aspects = themeToAspects(theme)
    let bad = 0
    for(const v of product.value.variants){
        const priceOk = Number(v.price) > 0
        const qtyOk = Number.isInteger(v.quantity) && v.quantity >= 0
        const aspectOk = aspects.every((name, idx) => {
            const val = [v.option1, v.option2, v.option3][idx]
            return val !== null && val !== ''
        })
        if(!(priceOk && qtyOk && aspectOk)) bad++
    }
    return bad === 0
})

const variantsHint = computed(()=> variantsOk.value ? '' : 'Ensure each variant has a price, non‑negative quantity, and required aspect values (e.g., Size/Color).')
const policyHint = computed(()=> channelState.value?.policies?.has_all ? '' : 'Pick fulfillment, payment, and return policies in the eBay settings.')


const categoryLabel = computed(()=> (mapping.variation_theme && product.value?.variants?.length > 1) ? 'Category supports variations' : 'Category selected')
const categoryOk = computed(()=>{
    if(!mapping.category) return false
    if((product.value?.variants?.length || 0) <= 1) return true
    return supportsVar.value === true
})
const categoryHint = computed(()=> (!mapping.category) ? 'Select a category in your mapping.' : (supportsVar.value===false ? 'This category does not support variations.' : ''))

function themeToAspects(theme){
    const map = { 'SizeColor': ['Size','Color'], 'ColorSize': ['Color','Size'], 'Size': ['Size'], 'Color': ['Color'] }
    return map[theme] || ['Size','Color']
}

async function loadAll(){
    const [p, m, s] = await Promise.all([
        fetch(`/api/products/${productId.value}`).then(r=>r.json()),
        fetch(`/api/mappings/effective?channel_id=${channelId.value}&product_id=${productId.value}`).then(r=>r.json()),
        fetch(`/api/channels/${channelId.value}/state`).then(r=>r.json()),
    ])
    product.value = p
    Object.assign(mapping, m)
    channelState.value = s
    if (mapping.category && (p.variants?.length||0) > 1) {
        try {
            const res = await fetch(`/api/channels/${channelId.value}/ebay/supports-variations?marketplace_id=${encodeURIComponent(s.marketplace_id)}&category_id=${encodeURIComponent(mapping.category)}`)
            supportsVar.value = res.ok ? (await res.json()).supports : null
        } catch { supportsVar.value = null }
    }
}

const canPublish = computed(()=> ready.value && imagesOk.value && variantsOk.value && categoryOk.value && channelState.value?.policies?.has_all && channelState.value?.location?.has_default)

async function queuePublish(){
    const r = await fetch('/api/publish', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id: productId.value, channel_id: channelId.value, channel_category_id: null }) })
    alert(r.ok ? 'Queued publish' : 'Failed to queue publish')
}


onMounted(loadAll)

watch(() => [props.productId, props.channelId], ([pid, cid]) => {
    productId.value = Number(pid)
    channelId.value = Number(cid)
    loadAll()
})

</script>
