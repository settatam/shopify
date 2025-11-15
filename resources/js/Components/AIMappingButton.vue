<template>
    <button
        @click="handleClick"
        :disabled="loading"
        class="px-4 py-2 rounded flex items-center gap-2"
        :class="buttonClass"
    >
        <svg v-if="loading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
        <span>{{ buttonText }}</span>
    </button>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
    productId: {
        type: Number,
        required: true,
    },
    variant: {
        type: String,
        default: 'primary', // primary, secondary, success
    },
})

const emit = defineEmits(['generated', 'error'])

const loading = ref(false)

const buttonClass = computed(() => {
    const variants = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white',
        secondary: 'bg-gray-100 hover:bg-gray-200 text-gray-700 border',
        success: 'bg-green-600 hover:bg-green-700 text-white',
    }
    const baseClass = variants[props.variant] || variants.primary
    return loading.value ? baseClass + ' opacity-50 cursor-not-allowed' : baseClass
})

const buttonText = computed(() => {
    return loading.value ? 'Generating...' : 'AI Auto-Map'
})

const handleClick = async () => {
    if (loading.value) return

    loading.value = true
    try {
        const response = await axios.post(`/api/ai-mapping/products/${props.productId}/generate`)
        emit('generated', response.data)

        // Show success message
        const count = response.data.suggestions.length
        alert(`Successfully generated ${count} AI mapping${count !== 1 ? 's' : ''}!`)
    } catch (error) {
        console.error('Failed to generate AI mappings:', error)
        emit('error', error)
        alert('Failed to generate AI mappings: ' + (error.response?.data?.message || error.message))
    } finally {
        loading.value = false
    }
}
</script>
