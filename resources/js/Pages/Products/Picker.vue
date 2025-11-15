<template>
    <div>
        <h2 class="text-xl font-semibold mb-3">Select Products</h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="p in products" :key="p.id" class="border rounded p-3">
                <div class="font-medium">{{ p.title }}</div>
                <div class="text-xs text-gray-600">#{{ p.shopify_product_id }}</div>
                <button class="mt-2 bg-black text-white px-3 py-1 rounded" @click="$emit('choose', p)">Map & Publish</button>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const products = ref([])
const selectedChannelId = ref(null) // render a select of user channels above the grid


onMounted(async()=>{
    const res = await fetch('/api/me/products')
    if(res.ok) products.value = await res.json()
})

</script>
