<template>
    <label class="block">
        <span class="text-sm uppercase tracking-wide text-gray-600">{{ attr }}</span>
        <input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" class="mt-1 w-full border rounded p-2" placeholder="{variant.option1}" />
        <p class="text-xs text-gray-500 mt-1">Supports tokens like {product.title}, {variant.option1}, {product.metafields.color}</p>
    </label>
</template>
<script setup>

defineProps({ attr: String, modelValue: [String, Number] })
defineEmits(['update:modelValue'])

</script>
