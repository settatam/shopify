<template>
    <div class="p-6">
        <slot />
    </div>
</template>
<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useAppBridgeNav } from '@/lib/useAppBridgeNav'


const page = usePage()
const ab = useAppBridgeNav()

function apply(){
    const meta = (page.props as any).appBridge || {}
    if (meta.title) ab.setTitle(meta.title)
    if (meta.breadcrumbs) ab.setBreadcrumbs(meta.breadcrumbs)
    if (meta.primary) ab.setPrimary(meta.primary)
    if (meta.secondary) ab.setSecondary(meta.secondary)
    if (meta.nav) ab.setNav(meta.nav)
}


onMounted(apply)
watch(() => page.url, apply)
router.on('success', apply)
</script>
