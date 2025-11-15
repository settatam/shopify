<template>
    <div class="max-w-xl space-y-4">
        <h2 class="text-xl font-semibold">Connect a Channel</h2>
        <div class="grid gap-3">
            <label class="block">
                <span class="text-sm">Channel Type</span>
                <select v-model="form.type" class="mt-1 w-full border rounded p-2">
                    <option disabled value="">Select...</option>
                    <option value="ebay">eBay</option>
                    <option value="amazon">Amazon</option>
                    <option value="etsy">Etsy</option>
                    <option value="walmart">Walmart</option>
                </select>
            </label>
            <label class="block">
                <span class="text-sm">Name</span>
                <input v-model="form.name" class="mt-1 w-full border rounded p-2" placeholder="My Amazon US" />
            </label>
            <label class="block">
                <span class="text-sm">Sandbox?</span>
                <input type="checkbox" v-model="form.sandbox" class="mt-1" />
            </label>
            <label class="block">
                <span class="text-sm">Auth JSON</span>
                <textarea v-model="authStr" class="mt-1 w-full border rounded p-2 h-32" placeholder='{"refresh_token":"..."}'></textarea>
            </label>
            <button @click="submit" class="bg-black text-white rounded px-4 py-2">Connect</button>
        </div>
    </div>
</template>
<script setup>
import { reactive, computed } from 'vue'

const form = reactive({ shop_id: null, type: '', name: '', sandbox: true, auth_json: {} })
const authStr = computed({
    get: () => JSON.stringify(form.auth_json || {}, null, 2),
    set: v => { try { form.auth_json = JSON.parse(v || '{}') } catch { /* ignore */ } }
})


async function submit(){
    if(!form.shop_id){ alert('Set shop_id in code or pull from session.'); return }
    const res = await fetch('/api/channels/connect',{ method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(form) })
    if(!res.ok){ return alert('Error connecting channel') }
    alert('Connected!')
}
</script>
