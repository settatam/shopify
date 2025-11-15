<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import TopbarLinks from '@/Components/Nav/TopBarLinks.vue'
import ChannelSelect from '@/Components/Nav/ChannelSelect.vue'


const channelId = ref<string | number | null>(null)
const props = defineProps<{ productId?: number|string, variantId?: number|string }>()
const primary = '#016d77'
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <header class="w-full border-b bg-white">
            <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/" class="font-semibold text-lg" :style="{color: primary}">Shopmata</Link>
                    <TopbarLinks :channel-id="channelId" :product-id="props.productId" :variant-id="props.variantId"/>
                </div>
                <div class="flex items-center gap-4">
                    <ChannelSelect v-model="channelId" />
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>
    </div>
</template>
