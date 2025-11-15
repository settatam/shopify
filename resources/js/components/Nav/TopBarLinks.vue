<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import buildNavLinks, { type NavContext, type NavItem } from '@/utils/buildNavLinks'


const props = defineProps<{
    channelId?: number | string
    productId?: number | string
    variantId?: number | string
}>()


const page: any = usePage()
const baseNav = computed<NavItem[]>(() => page.props.nav || [])


const resolved = computed(() => buildNavLinks(baseNav.value, {
    channelId: props.channelId,
    productId: props.productId,
    variantId: props.variantId,
} as NavContext))
</script>

<template>
    <nav class="flex items-center gap-4">
        <template v-for="item in resolved" :key="item.label">
            <div v-if="item.children?.length" class="relative group">
                <button class="px-3 py-2 rounded-xl font-medium" style="color:#016d77">{{ item.label }}</button>
                <div class="hidden group-hover:block absolute top-full left-0 bg-white border rounded-2xl shadow-md mt-1 p-2 min-w-56">
                    <ul>
                        <li v-for="c in item.children" :key="c.label">
                            <Link :href="c.href || '#'" class="block px-3 py-2 rounded-lg hover:bg-gray-50">{{ c.label }}</Link>
                        </li>
                    </ul>
                </div>
            </div>
            <Link v-else :href="item.href || '#'" class="px-3 py-2 rounded-xl font-medium hover:bg-gray-50" style="color:#016d77">{{ item.label }}</Link>
        </template>
    </nav>
</template>
