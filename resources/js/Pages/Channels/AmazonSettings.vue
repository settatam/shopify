<template>
    <div class="max-w-2xl space-y-6">
        <h2 class="text-xl font-semibold">Amazon Settings</h2>
        <div class="grid gap-3">
            <label class="block text-sm">Seller ID
                <input v-model="form.seller_id" class="mt-1 w-full border rounded p-2"/>
            </label>
            <label class="block text-sm">Marketplace IDs (comma separated)
                <input v-model="marketplaceStr" class="mt-1 w-full border rounded p-2" placeholder="ATVPDKIKX0DER"/>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">Region
                    <input v-model="form.region" class="mt-1 w-full border rounded p-2" placeholder="us-east-1"/>
                </label>
                <label class="block text-sm">API Host
                    <input v-model="form.host" class="mt-1 w-full border rounded p-2" placeholder="sellingpartnerapi-na.amazon.com"/>
                </label>
            </div>
            <label class="block text-sm">Currency
                <input v-model="form.currency" class="mt-1 w-full border rounded p-2" placeholder="USD"/>
            </label>
            <div class="flex justify-end">
                <button @click="save" class="bg-black text-white rounded px-4 py-2">Save</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'


const props = defineProps({ channel:Object, auth:Object })
const form = useForm({
    seller_id: props.auth?.seller_id || '',
    marketplace_ids: props.auth?.marketplace_ids || [],
    region: props.auth?.region || 'us-east-1',
    host: props.auth?.host || 'sellingpartnerapi-na.amazon.com',
    currency: props.auth?.currency || 'USD',
})
const marketplaceStr = computed({
    get: ()=> (form.marketplace_ids||[]).join(','),
    set: v => form.marketplace_ids = (v||'').split(',').map(s=>s.trim()).filter(Boolean)
})
function save(){ form.post(route('amazon.settings.update', { channel: props.channel.id })) }
</script>
