<template>
    <div class="overflow-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="text-left">
                <th class="py-2 pr-4">SKU</th>
                <th v-for="h in headers" :key="h" class="py-2 pr-4">{{ h }}</th>
                <th class="py-2 pr-4">Price</th>
                <th class="py-2 pr-4">Qty</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="v in variants" :key="v.id" class="border-t">
                <td class="py-2 pr-4 font-mono text-xs">{{ v.sku || v.shopify_variant_id }}</td>
                <td v-for="(h,idx) in headers" :key="h" class="py-2 pr-4">{{ [v.option1,v.option2,v.option3][idx] || '—' }}</td>
                <td class="py-2 pr-4">{{ v.price ?? '—' }}</td>
                <td class="py-2 pr-4">{{ v.quantity ?? '—' }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>

import { computed } from 'vue'
const props = defineProps({ variants: { type:Array, default:()=>[] }, theme: { type:String, default:'SizeColor' } })
const headers = computed(()=>{
    const map = { 'SizeColor': ['Size','Color'], 'ColorSize':['Color','Size'], 'Size':['Size'], 'Color':['Color'] }
    return map[props.theme] || ['Size','Color']
})
</script>
