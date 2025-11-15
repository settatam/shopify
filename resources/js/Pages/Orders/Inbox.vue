<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Channel Orders</h2>
        <table class="w-full text-sm">
            <thead><tr><th>When</th><th>Channel</th><th>External ID</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <tr v-for="o in orders" :key="o.id" class="border-t">
                <td>{{ new Date(o.placed_at || o.created_at).toLocaleString() }}</td>
                <td>{{ o.channel?.name }}</td>
                <td>{{ o.external_order_id }}</td>
                <td>{{ o.currency }} {{ o.total }}</td>
                <td>{{ o.marketplace_status }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const orders = ref([])
onMounted(async()=>{ const r = await fetch('/api/orders?limit=100'); if(r.ok) orders.value = await r.json() })
</script>
