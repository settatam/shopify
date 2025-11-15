<template>
    <div class="space-y-6 max-w-2xl">
        <h2 class="text-xl font-semibold">eBay Business Policies</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm">Marketplace</label>
                <select v-model="market" class="mt-1 w-full border rounded p-2">
                    <option value="EBAY_US">EBAY_US</option>
                    <option value="EBAY_GB">EBAY_GB</option>
                    <option value="EBAY_AU">EBAY_AU</option>
                </select>
            </div>
            <div class="flex items-end"><button @click="load" class="bg-black text-white px-4 py-2 rounded">Load Policies</button></div>
        </div>


        <div v-if="lists" class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm">Fulfillment Policy</label>
                <select v-model="sel.fulfillment_policy_id" class="mt-1 w-full border rounded p-2">
                    <option v-for="p in lists.fulfillment" :key="p.fulfillmentPolicyId" :value="p.fulfillmentPolicyId">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm">Payment Policy</label>
                <select v-model="sel.payment_policy_id" class="mt-1 w-full border rounded p-2">
                    <option v-for="p in lists.payment" :key="p.paymentPolicyId" :value="p.paymentPolicyId">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm">Return Policy</label>
                <select v-model="sel.return_policy_id" class="mt-1 w-full border rounded p-2">
                    <option v-for="p in lists.return" :key="p.returnPolicyId" :value="p.returnPolicyId">{{ p.name }}</option>
                </select>
            </div>
        </div>


        <div class="flex justify-end" v-if="lists">
            <button @click="savePolicies" class="bg-emerald-600 text-white px-4 py-2 rounded">Save Policies</button>
        </div>


        <h2 class="text-xl font-semibold mt-8">Inventory Location</h2>
        <div class="grid gap-3">
            <input v-model="loc.merchant_location_key" class="border rounded p-2" placeholder="merchantLocationKey" />
            <input v-model="loc.name" class="border rounded p-2" placeholder="Location Name" />
            <textarea v-model="locAddr" class="border rounded p-2 h-32" placeholder='{"address":{"addressLine1":"123 Main","city":"Austin","stateOrProvince":"TX","postalCode":"78701","country":"US"}}'></textarea>
            <label class="inline-flex items-center gap-2"><input type="checkbox" v-model="loc.is_default"/> <span>Set as default</span></label>
            <button @click="saveLocation" class="bg-black text-white px-4 py-2 rounded">Create/Update Location</button>
        </div>
    </div>
</template>
<script setup>
import { ref, reactive, computed } from 'vue'


const channelId = ref(null) // inject via route/prop
const market = ref('EBAY_US')
const lists = ref(null)
const sel = reactive({ fulfillment_policy_id: '', payment_policy_id: '', return_policy_id: '' })
const loc = reactive({ merchant_location_key: '', name: '', address_json: {}, is_default: true })
const locAddr = computed({ get:()=> JSON.stringify(loc.address_json||{},null,2), set:v=>{ try{ loc.address_json = JSON.parse(v||'{}') }catch{} } })
</script>
