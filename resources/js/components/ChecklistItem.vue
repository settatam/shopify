<template>
    <li class="flex items-start gap-2">
        <span :class="ok ? 'text-emerald-600' : 'text-rose-600'">●</span>
        <div>
            <div :class="ok ? 'text-gray-900' : 'text-gray-900'" class="text-sm">{{ label }}</div>
            <div v-if="!ok && hint" class="text-xs text-gray-600">{{ hint }}</div>
        </div>
    </li>
</template>
<script setup>
defineProps({ ok: Boolean, label: String, hint: String })
</script>
