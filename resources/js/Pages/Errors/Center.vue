<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Integration Errors</h2>
        <table class="w-full text-sm">
            <thead><tr><th class="text-left">Time</th><th>Channel</th><th>Subject</th><th>Details</th></tr></thead>
            <tbody>
            <tr v-for="e in items" :key="e.id" class="border-t">
                <td>{{ new Date(e.created_at).toLocaleString() }}</td>
                <td>{{ e.channel?.name || '-' }}</td>
                <td>{{ e.subject }}</td>
                <td><pre class="whitespace-pre-wrap">{{ JSON.stringify(e.context_json, null, 2) }}</pre></td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
const items = ref([])
onMounted(async()=>{
    const r = await fetch('/api/errors?limit=100'); if(r.ok) items.value = await r.json()
})
</script>
