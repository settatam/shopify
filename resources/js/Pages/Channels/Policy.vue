<template>
    <div class="space-y-6">
        <h2 class="text-xl font-semibold">Channel Warehouse Policy</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="md:col-span-2 border rounded p-4">
                <h3 class="font-semibold mb-2">Included Warehouses</h3>
                <div class="grid sm:grid-cols-2 gap-2">
                    <label v-for="l in locations" :key="l.id" class="flex items-center gap-2">
                        <input type="checkbox" :value="l.id" v-model="included" />
                        <span>{{ l.name }}</span>
                    </label>
                </div>
                <div class="mt-4">
                    <label class="block text-sm">Safety stock (units to hide)</label>
                    <input type="number" min="0" v-model.number="safetyStock" class="mt-1 w-32 border rounded p-2" />
                </div>
                <div class="mt-4">
                    <button @click="save" class="bg-black text-white rounded px-4 py-2">Save Policy</button>
                </div>
            </div>
            <div class="border rounded p-4">
                <h3 class="font-semibold mb-2">Preview</h3>
                <p class="text-sm text-gray-600">The selected warehouses will be used to compute available quantity for this channel. Safety stock will be subtracted.</p>
                <ul class="list-disc list-inside text-sm mt-2">
                    <li v-for="id in included" :key="id">{{ locations.find(x=>x.id===id)?.name }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const props = defineProps({ channelId: { type: Number, required: true }, initialIncluded: Array, initialSafety: Number })
const locations = ref([])
const included = ref(props.initialIncluded || [])
const safetyStock = ref(props.initialSafety ?? 0)
async function load(){ const r = await fetch('/api/locations'); if(r.ok) locations.value = await r.json() }
async function save(){
    await fetch(`/api/channels/${props.channelId}/policy`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ included_location_ids: included.value, safety_stock: safetyStock.value }) })
    alert('Saved')
}
onMounted(load)
</script>
