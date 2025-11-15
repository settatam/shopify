<template>
    <div class="max-w-lg mx-auto py-16 space-y-6 text-center">
        <h1 class="text-2xl font-semibold">Install Shopmata</h1>
        <p class="text-gray-600">Enter your shop domain (e.g. mystore.myshopify.com)</p>
        <div class="flex gap-2 justify-center">
            <input v-model="shop" class="border rounded p-2 w-80" placeholder="your-store.myshopify.com" />
            <a :href="`/shopify/install?shop=${encodeURIComponent(shop)}`" class="px-4 py-2 rounded bg-black text-white">Install</a>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue'
const shop = ref('')
</script>
