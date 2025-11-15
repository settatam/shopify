<script setup>
import { computed, ref } from 'vue'


const props = defineProps({
    variant: { type: Object, required: true },
    items: { type: Array, required: true },
    locations: { type: Array, required: true },
})


const qty = ref(1)
const unitCost = ref(null)
const reason = ref('adjust')
const working = ref(false)


function findItem(locationId){
    return props.items.find(i => i.location_id === locationId) || { on_hand:0, reserved:0, available:0, avg_cost:0 }
}

async function post(url, payload){
    working.value = true
    try {
        const { data } = await window.axios.post(url, payload)
// naive update; in a real app, prefer reload or events
        const updated = data.item
        const idx = props.items.findIndex(i => i.id === updated.id)
        if (idx >= 0) props.items[idx] = updated
        else props.items.push(updated)
    } finally { working.value = false }
}


async function doAdjust(locationId){
    await post('/api/inventory/adjust', { variant_id: props.variant.id, location_id: locationId, qty: qty.value, unit_cost: unitCost.value, reason: reason.value })
}

async function doReserve(locationId){
    await post('/api/inventory/reserve', { variant_id: props.variant.id, location_id: locationId, qty: qty.value })
}
async function doRelease(locationId){
    await post('/api/inventory/release', { variant_id: props.variant.id, location_id: locationId, qty: qty.value })
}
</script>

<template>
    <div class="rounded-2xl border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
            <tr class="text-left">
                <th class="p-3">Location</th>
                <th class="p-3">On hand</th>
                <th class="p-3">Reserved</th>
                <th class="p-3">Available</th>
                <th class="p-3">Avg Cost</th>
                <th class="p-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="l in locations" :key="l.id" class="border-t">
                <td class="p-3">{{ l.name }}</td>
                <td class="p-3">{{ findItem(l.id).on_hand }}</td>
                <td class="p-3">{{ findItem(l.id).reserved }}</td>
                <td class="p-3">{{ findItem(l.id).available }}</td>
                <td class="p-3">{{ Number(findItem(l.id).avg_cost).toFixed(2) }}</td>
                <td class="p-3">
                    <div class="flex items-center gap-2">
                        <input v-model.number="qty" type="number" class="w-20 border rounded p-1"/>
                        <input v-model.number="unitCost" type="number" step="0.01" class="w-24 border rounded p-1" placeholder="Unit cost"/>
                        <select v-model="reason" class="border rounded p-1">
                            <option value="adjust">Adjust</option>
                            <option value="receipt">Receipt</option>
                            <option value="correction">Correction</option>
                        </select>
                        <button :disabled="working" @click="doAdjust(l.id)" class="px-3 py-1 rounded text-white" style="background:#016d77">Adjust</button>
                        <button :disabled="working" @click="doReserve(l.id)" class="px-3 py-1 rounded border" style="border-color:#016d77;color:#016d77">Reserve</button>
                        <button :disabled="working" @click="doRelease(l.id)" class="px-3 py-1 rounded border" style="border-color:#fb8d68;color:#fb8d68">Release</button>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
