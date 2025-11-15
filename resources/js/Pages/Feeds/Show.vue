<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold">Feed Batch #{{ batch.id }}</h2>
            <Link :href="route('feeds.index')" class="border px-3 py-1 rounded">Back</Link>
        </div>
        <div class="grid md:grid-cols-3 gap-4 text-sm">
            <div class="border rounded p-3">
                <div><b>Status:</b> {{ batch.status }}</div>
                <div><b>Feed ID:</b> {{ batch.feed_id || '—' }}</div>
                <div><b>Doc ID:</b> {{ batch.feed_document_id || '—' }}</div>
                <div><b>Result Doc:</b> {{ batch.result_feed_document_id || '—' }}</div>
            </div>
            <div class="border rounded p-3">
                <div><b>Type:</b> {{ batch.feed_type }}</div>
                <div><b>Marketplaces:</b> {{ (batch.marketplace_ids||[]).join(', ') }}</div>
                <div><b>Submitted:</b> {{ batch.submitted_count }}</div>
                <div><b>Processed:</b> {{ batch.processed_count }}</div>
            </div>
            <div class="border rounded p-3">
                <div class="text-rose-600" v-if="batch.error">{{ batch.error }}</div>
                <div v-else class="text-gray-500">No batch errors.</div>
            </div>
        </div>
        <h3 class="font-semibold">Items</h3>
        <table class="w-full text-sm">
            <thead><tr><th>SKU</th><th>Op</th><th>Result</th><th>Message</th></tr></thead>
            <tbody>
            <tr v-for="it in batch.items" :key="it.id" class="border-t">
                <td class="font-mono text-xs">{{ it.sku }}</td>
                <td>{{ it.operation }}</td>
                <td>{{ it.result_code || '—' }}</td>
                <td class="whitespace-pre-wrap">{{ it.result_message || '—' }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
<script setup>
import { Link } from '@inertiajs/vue3'
const props = defineProps({ batch:Object })
</script>
