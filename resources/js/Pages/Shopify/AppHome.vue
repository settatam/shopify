<template>
    <div class="p-6 space-y-6">
        <header class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Welcome to Shopmata</h2>
            <a :href="`/shopify/install?shop=${shop.domain}`" class="text-sm underline">Reinstall</a>
        </header>
        <div class="grid md:grid-cols-3 gap-4">
            <div class="border rounded p-4">
                <div class="font-medium mb-1">Channels</div>
                <p class="text-sm text-gray-600">Connect Amazon, eBay, Etsy, Walmart.</p>
                <a href="/channels" class="mt-2 inline-block text-sm underline">Open Channels</a>
            </div>
            <div class="border rounded p-4">
                <div class="font-medium mb-1">Mappings</div>
                <p class="text-sm text-gray-600">Set category & attributes per channel.</p>
                <a href="/mappings" class="mt-2 inline-block text-sm underline">Open Mapping Editor</a>
            </div>
            <div class="border rounded p-4">
                <div class="font-medium mb-1">Feeds</div>
                <p class="text-sm text-gray-600">Monitor Amazon JSON/XML feeds.</p>
                <a href="/feeds" class="mt-2 inline-block text-sm underline">Open Feeds Console</a>
            </div>
        </div>
    </div>
</template>
<script setup>
const props = defineProps({ shop:Object, apiKey:String })
</script>
