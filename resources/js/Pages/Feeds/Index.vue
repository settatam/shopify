<template>
    <div class="space-y-4">
        <h2 class="text-xl font-semibold">Amazon Feeds</h2>
        <table class="w-full text-sm">
            <thead><tr><th>ID</th><th>Channel</th><th>Type</th><th>Status</th><th>Submitted</th><th>Processed</th><th>Created</th></tr></thead>
            <tbody>
            <tr v-for="b in batches.data" :key="b.id" class="border-t">
                <td><Link :href="route('feeds.show',{batch:b.id})" class="underline">#{{ b.id }}</Link></td>
                <td>{{ b.channel?.name }}</td>
                <td>{{ b.feed_type }}</td>
                <td>{{ b.status }}</td>
                <td>{{ b.submitted_count }}</td>
                <td>{{ b.processed_count }}</td>
                <td>{{ new Date(b.created_at).toLocaleString() }}</td>
            </tr>
            </tbody>
        </table>
    </div>
</template>
<script setup>
import { Link } from '@inertiajs/vue3'
const props = defineProps({ batches:Object })
</script>
