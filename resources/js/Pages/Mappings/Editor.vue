<template>
    <div>
        <h3 class="font-semibold mb-2">Required Attributes</h3>
        <AttributeMapRow v-for="a in requiredAttrs" :key="a" :attr="a" v-model="mapping.attributes[a]"/>
    </div>
    <div>
        <h3 class="font-semibold mb-2">Optional Attributes</h3>
        <AttributeMapRow v-for="a in optionalAttrs" :key="a" :attr="a" v-model="mapping.attributes[a]"/>
    </div>
    </div>


    <div class="border rounded p-4">
        <h3 class="font-semibold mb-2">Core Mapping</h3>
        <div class="grid gap-3">
            <AttributeMapRow attr="title" v-model="mapping.title"/>
            <AttributeMapRow attr="description" v-model="mapping.description"/>
            <AttributeMapRow attr="brand" v-model="mapping.brand"/>
            <AttributeMapRow attr="images" v-model="mapping.images"/>
            <AttributeMapRow attr="variation_theme" v-model="mapping.variation_theme"/>
        </div>
    </div>

    <div class="flex gap-2 justify-end mt-4">
        <button @click="queuePublish" class="bg-emerald-600 text-white rounded px-4 py-2">Publish with Mapping</button>
        <button @click="goPreview"
                :disabled="!productId || !channelId"
                class="rounded px-4 py-2"
                :class="!productId || !channelId ? 'bg-gray-300 text-gray-600' : 'bg-black text-white'">
            Preview
        </button>
    </div>

</template>
<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AttributeMapRow from '@/components/AttributeMapRow.vue'


const channels = ref([])
const channelId = ref(null)
const categories = ref([])
const categoryId = ref(null)
const productId = ref(null) // pass in via route/prop

function goPreview(){
    if(!productId.value || !channelId.value) return
    //router.push({ name: 'variation-preview', params: { productId: productId.value, channelId: channelId.value } })
}


const mapping = reactive({
    title: '{product.title}',
    description: '{product.description}',
    brand: '{product.vendor|fallback:\'Generic\'}',
    images: '{product.images|limit:8}',
    variation_theme: 'SizeColor',
    attributes: {}
})


const category = computed(() => categories.value.find(c => c.id === categoryId.value))
const requiredAttrs = computed(() => Object.keys(category.value?.attributes_json?.required || {}))
const optionalAttrs = computed(() => Object.keys(category.value?.attributes_json?.optional || {}))


watch(channelId, async (id) => {
    if(!id) return
    const res = await fetch(`/api/channels/${id}/categories`)
    categories.value = await res.json()
})


async function save(){
    const body = { channel_id: channelId.value, product_id: productId.value, channel_category_id: categoryId.value, mapping_json: mapping }
    const res = await fetch('/api/mappings',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) })
    if(!res.ok){ return alert('Error saving') }
    alert('Mapping saved')
}


async function queuePublish(){
    const body = { product_id: productId.value, channel_id: channelId.value, channel_category_id: categoryId.value }
    const res = await fetch('/api/publish',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) })
    if(!res.ok){ return alert('Error queueing publish') }
    alert('Queued publish')
}


onMounted(async ()=>{
// TODO: load channels for this shop
    const res = await fetch('/api/me/channels') // or inject via page props
    if(res.ok) channels.value = await res.json()
})
</script>
