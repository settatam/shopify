<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'


const modelValue = defineModel<number | string | null>({ default: null })
const channels = ref<{id:number|string, name:string}[]>([])
const loading = ref(false)


function loadPersisted(){
    const v = localStorage.getItem('currentChannelId')
    if (v && !modelValue.value) modelValue.value = v
}


async function fetchChannels(){
    loading.value = true
    try {
        const { data } = await window.axios.get('/api/channels')
        channels.value = data.channels || []
        if (!modelValue.value && channels.value.length) {
            modelValue.value = String(channels.value[0].id)
        }
    } finally { loading.value = false }
}

watch(modelValue, (v) => {
    if (v == null) return
    localStorage.setItem('currentChannelId', String(v))
})


onMounted(() => { loadPersisted(); fetchChannels() })

</script>

<template>
    <label class="inline-flex items-center gap-2">
        <span class="text-sm text-gray-600">Channel</span>
        <select v-model="modelValue" class="border rounded-xl px-3 py-2">
            <option v-if="loading" disabled>Loading…</option>
            <option v-for="c in channels" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
    </label>
</template>
