<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Warehouses & Locations</h2>
            <button @click="create" class="bg-black text-white rounded px-3 py-2">Add Location</button>
        </div>


        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="l in locations" :key="l.id" class="border rounded p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium">{{ l.name }}</div>
                        <div class="text-xs text-gray-500">{{ l.code || '—' }}</div>
                    </div>
                    <span class="text-xs" :class="l.is_active ? 'text-emerald-600' : 'text-gray-400'">{{ l.is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <div class="mt-2 text-xs text-gray-600">Handling: {{ l.default_handling_days }} days</div>
                <div class="mt-1 text-xs text-gray-600" v-if="l.lat && l.lng">({{ l.lat }}, {{ l.lng }})</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
const locations = ref([])
async function load(){ const r = await fetch('/api/locations'); if(r.ok) locations.value = await r.json() }
async function create(){
    const name = prompt('Location name?'); if(!name) return;
    await fetch('/api/locations',{method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ shop_id: window.__SHOP_ID__, name })})
    load()
}
onMounted(load)
</script>
