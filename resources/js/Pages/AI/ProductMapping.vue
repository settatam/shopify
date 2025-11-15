<template>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">AI Product Mapping</h1>
                <p class="text-gray-600 mt-1">{{ product.title }}</p>
            </div>
            <div class="flex gap-2">
                <button
                    @click="generateMappings"
                    :disabled="generating"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ generating ? 'Generating...' : 'Generate AI Mappings' }}
                </button>
                <button
                    v-if="selectedSuggestions.length > 0"
                    @click="batchApprove"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                >
                    Approve Selected ({{ selectedSuggestions.length }})
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div v-if="suggestions.length > 0" class="grid grid-cols-4 gap-4">
            <div class="bg-white border rounded p-4">
                <div class="text-sm text-gray-600">Total Suggestions</div>
                <div class="text-2xl font-bold">{{ suggestions.length }}</div>
            </div>
            <div class="bg-white border rounded p-4">
                <div class="text-sm text-gray-600">High Confidence</div>
                <div class="text-2xl font-bold text-green-600">{{ highConfidenceCount }}</div>
            </div>
            <div class="bg-white border rounded p-4">
                <div class="text-sm text-gray-600">Pending</div>
                <div class="text-2xl font-bold text-yellow-600">{{ pendingCount }}</div>
            </div>
            <div class="bg-white border rounded p-4">
                <div class="text-sm text-gray-600">Approved</div>
                <div class="text-2xl font-bold text-blue-600">{{ approvedCount }}</div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && suggestions.length === 0" class="bg-white border rounded p-12 text-center">
            <div class="text-gray-400 mb-4">
                <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No AI Mappings Yet</h3>
            <p class="text-gray-600 mb-4">Generate AI mappings to automatically create product listings across all your sales channels.</p>
            <button
                @click="generateMappings"
                :disabled="generating"
                class="px-6 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
                {{ generating ? 'Generating...' : 'Generate AI Mappings' }}
            </button>
        </div>

        <!-- Suggestions List -->
        <div v-if="suggestions.length > 0" class="space-y-4">
            <div
                v-for="suggestion in suggestions"
                :key="suggestion.id"
                class="bg-white border rounded-lg overflow-hidden"
                :class="{
                    'border-green-300': suggestion.high_confidence,
                    'border-yellow-300': !suggestion.high_confidence && suggestion.status === 'pending',
                    'border-blue-300': suggestion.status === 'approved',
                    'border-gray-300': suggestion.status === 'rejected'
                }"
            >
                <!-- Suggestion Header -->
                <div class="p-4 bg-gray-50 border-b flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <input
                            v-if="suggestion.status === 'pending'"
                            type="checkbox"
                            :value="suggestion.id"
                            v-model="selectedSuggestions"
                            class="h-5 w-5 rounded"
                        />
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ suggestion.channel.name }}</h3>
                                <span class="text-xs px-2 py-1 rounded"
                                      :class="statusClass(suggestion.status)">
                                    {{ suggestion.status }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">{{ suggestion.channel.type }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Confidence Score -->
                        <div class="text-right">
                            <div class="text-xs text-gray-600">Confidence</div>
                            <div class="font-semibold"
                                 :class="suggestion.high_confidence ? 'text-green-600' : 'text-yellow-600'">
                                {{ Math.round(suggestion.confidence * 100) }}%
                            </div>
                        </div>
                        <!-- Actions -->
                        <div v-if="suggestion.status === 'pending'" class="flex gap-2">
                            <button
                                @click="editSuggestion(suggestion)"
                                class="px-3 py-1 text-sm border rounded hover:bg-gray-100"
                            >
                                Edit
                            </button>
                            <button
                                @click="approveSuggestion(suggestion.id)"
                                class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"
                            >
                                Approve
                            </button>
                            <button
                                @click="rejectSuggestion(suggestion.id)"
                                class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Suggestion Details -->
                <div class="p-4 space-y-4">
                    <!-- Category -->
                    <div v-if="suggestion.category">
                        <div class="text-sm font-medium text-gray-700">Category</div>
                        <div class="text-sm">{{ suggestion.category.name }}</div>
                        <div class="text-xs text-gray-500">{{ suggestion.category.path }}</div>
                    </div>

                    <!-- Mapping Preview -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-1">Title</div>
                            <div class="text-sm bg-gray-50 p-2 rounded">{{ suggestion.mapping.title }}</div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-700 mb-1">Brand</div>
                            <div class="text-sm bg-gray-50 p-2 rounded">{{ suggestion.mapping.brand }}</div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <div class="text-sm font-medium text-gray-700 mb-1">Description</div>
                        <div class="text-sm bg-gray-50 p-2 rounded max-h-24 overflow-y-auto">
                            {{ suggestion.mapping.description }}
                        </div>
                    </div>

                    <!-- Attributes -->
                    <div v-if="suggestion.mapping.attributes && Object.keys(suggestion.mapping.attributes).length > 0">
                        <div class="text-sm font-medium text-gray-700 mb-1">Attributes</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div
                                v-for="(value, key) in suggestion.mapping.attributes"
                                :key="key"
                                class="text-xs bg-gray-50 p-2 rounded"
                            >
                                <div class="font-medium text-gray-700">{{ key }}</div>
                                <div class="text-gray-600">{{ value }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Reasoning -->
                    <div v-if="suggestion.reasoning">
                        <div class="text-sm font-medium text-gray-700 mb-1">AI Reasoning</div>
                        <div class="text-sm text-gray-600 bg-blue-50 p-3 rounded">
                            {{ suggestion.reasoning }}
                        </div>
                    </div>

                    <!-- Warnings -->
                    <div v-if="suggestion.warnings && suggestion.warnings.length > 0"
                         class="bg-yellow-50 border border-yellow-200 rounded p-3">
                        <div class="text-sm font-medium text-yellow-800 mb-1">Warnings</div>
                        <ul class="text-sm text-yellow-700 space-y-1">
                            <li v-for="(warning, idx) in suggestion.warnings" :key="idx">• {{ warning }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal (simplified - you may want to use a proper modal component) -->
        <div v-if="editingsuggestion" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">Edit Mapping</h2>
                    <!-- Add edit form here -->
                    <div class="flex gap-2 justify-end mt-4">
                        <button
                            @click="editingSuggestion = null"
                            class="px-4 py-2 border rounded hover:bg-gray-100"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveEdit"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                        >
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
    product: Object,
})

const suggestions = ref([])
const selectedSuggestions = ref([])
const generating = ref(false)
const loading = ref(false)
const editingSuggestion = ref(null)

const highConfidenceCount = computed(() =>
    suggestions.value.filter(s => s.high_confidence).length
)

const pendingCount = computed(() =>
    suggestions.value.filter(s => s.status === 'pending').length
)

const approvedCount = computed(() =>
    suggestions.value.filter(s => s.status === 'approved').length
)

const statusClass = (status) => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        approved: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
    }
    return classes[status] || 'bg-gray-100 text-gray-800'
}

const loadSuggestions = async () => {
    loading.value = true
    try {
        const response = await axios.get(`/api/ai-mapping/products/${props.product.id}/suggestions`)
        suggestions.value = response.data.suggestions
    } catch (error) {
        console.error('Failed to load suggestions:', error)
        alert('Failed to load AI suggestions')
    } finally {
        loading.value = false
    }
}

const generateMappings = async () => {
    generating.value = true
    try {
        const response = await axios.post(`/api/ai-mapping/products/${props.product.id}/generate`)
        suggestions.value = response.data.suggestions
        alert('AI mappings generated successfully!')
    } catch (error) {
        console.error('Failed to generate mappings:', error)
        alert('Failed to generate AI mappings: ' + (error.response?.data?.message || error.message))
    } finally {
        generating.value = false
    }
}

const approveSuggestion = async (suggestionId) => {
    try {
        await axios.post(`/api/ai-mapping/suggestions/${suggestionId}/approve`)
        await loadSuggestions()
        alert('Suggestion approved!')
    } catch (error) {
        console.error('Failed to approve suggestion:', error)
        alert('Failed to approve suggestion')
    }
}

const rejectSuggestion = async (suggestionId) => {
    if (!confirm('Are you sure you want to reject this suggestion?')) return

    try {
        await axios.post(`/api/ai-mapping/suggestions/${suggestionId}/reject`)
        await loadSuggestions()
    } catch (error) {
        console.error('Failed to reject suggestion:', error)
        alert('Failed to reject suggestion')
    }
}

const batchApprove = async () => {
    if (!confirm(`Approve ${selectedSuggestions.value.length} suggestions?`)) return

    try {
        await axios.post('/api/ai-mapping/suggestions/batch-approve', {
            suggestion_ids: selectedSuggestions.value,
        })
        selectedSuggestions.value = []
        await loadSuggestions()
        alert('Suggestions approved!')
    } catch (error) {
        console.error('Failed to batch approve:', error)
        alert('Failed to batch approve suggestions')
    }
}

const editSuggestion = (suggestion) => {
    editingSuggestion.value = { ...suggestion }
}

const saveEdit = async () => {
    // Implement edit save logic
    editingSuggestion.value = null
}

onMounted(() => {
    loadSuggestions()
})
</script>
