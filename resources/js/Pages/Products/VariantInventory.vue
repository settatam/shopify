<script setup>
import AppShell from '@/Layouts/AppShell.vue'
import VariantInventoryMatrix from '@/Components/VariantInventoryMatrix.vue'
const props = defineProps({ variant: Object, items: Array, locations: Array })
</script>
<template>
    <AppShell :product-id="props.variant?.product_id" :variant-id="props.variant?.id">
        <div class="p-6 space-y-6">
            <header class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold" style="color:#016d77">Inventory — {{ props.variant.sku || props.variant.title }}</h1>
            </header>
            <VariantInventoryMatrix :variant="props.variant" :items="props.items" :locations="props.locations" />
        </div>
    </AppShell>
</template>
