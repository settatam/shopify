<template>
    <div class="space-y-6 max-w-4xl">
        <header class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Amazon Attribute Picker</h2>
                <p class="text-sm text-gray-600">{{ product.title }} • {{ productType || 'Select product type in mapping first' }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('preview',{ product: product.id, channel: channel.id })" class="px-4 py-2 rounded border">Preview</Link>
                <button @click="save" class="px-4 py-2 rounded bg-black text-white">Save</button>
            </div>
        </header>


        <section class="grid md:grid-cols-2 gap-6">
            <div class="border rounded p-4 space-y-3">
                <h3 class="font-semibold">Variation & Relationship</h3>
                <FormRow label="Variation Theme Attribute">
                    <input v-model="form.variation_theme_attr" class="w-full border rounded p-2"/>
                </FormRow>
                <FormRow label="Parentage Attribute">
                    <input v-model="form.parentage_attr" class="w-full border rounded p-2"/>
                </FormRow>
                <FormRow label="Relationship Type Attribute">
                    <input v-model="form.relationship_type_attr" class="w-full border rounded p-2"/>
                </FormRow>
                <FormRow label="Parent SKU Attribute">
                    <input v-model="form.parent_sku_attr" class="w-full border rounded p-2"/>
                </FormRow>
                <div class="grid grid-cols-2 gap-3">
                    <FormRow label="Size Attribute"><input v-model="form.size_attr" class="w-full border rounded p-2"/></FormRow>
                    <FormRow label="Color Attribute"><input v-model="form.color_attr" class="w-full border rounded p-2"/></FormRow>
                </div>
            </div>


            <div class="border rounded p-4 space-y-3">
                <h3 class="font-semibold">Images</h3>
                <FormRow label="Main Image Field"><input v-model="form.image.main" class="w-full border rounded p-2"/></FormRow>
                <div class="grid grid-cols-2 gap-3">
                    <FormRow label="Other Image Prefix"><input v-model="form.image.other_prefix" class="w-full border rounded p-2"/></FormRow>
                    <FormRow label="Max Other Images"><input type="number" min="0" max="20" v-model.number="form.image.max_others" class="w-full border rounded p-2"/></FormRow>
                </div>
                <p class="text-xs text-gray-600">We will send <code>{{ form.image.other_prefix }}1..N</code> up to the max set here.</p>
            </div>
        </section>

        <section class="border rounded p-4">
            <h3 class="font-semibold mb-2">Required Attributes (from schema)</h3>
            <div v-if="attributes.required.length === 0" class="text-sm text-gray-600">No declared required attributes in the schema root, or product type not selected.</div>
            <div v-else class="grid md:grid-cols-2 gap-3">
                <div v-for="name in attributes.required" :key="name" class="bg-gray-50 rounded p-3">
                    <label class="text-xs text-gray-600">{{ name }}</label>
                    <input v-model="form.attributes[name]" placeholder="{product.*} or static" class="mt-1 w-full border rounded p-2"/>
                </div>
            </div>
        </section>

        <section class="border rounded p-4">
            <h3 class="font-semibold mb-2">Optional Attributes</h3>
            <details class="text-sm">
                <summary class="cursor-pointer select-none">Show list</summary>
                <div class="grid md:grid-cols-2 gap-3 mt-3">
                    <div v-for="name in attributes.optional.slice(0, 60)" :key="name" class="bg-gray-50 rounded p-3">
                        <label class="text-xs text-gray-600">{{ name }}</label>
                        <input v-model="form.attributes[name]" placeholder="(optional) token or text" class="mt-1 w-full border rounded p-2"/>
                    </div>
                </div>
            </details>
        </section>
    </div>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'


const props = defineProps({ product:Object, channel:Object, productType:String, attributes:Object, mapping:Object })


const form = useForm(JSON.parse(JSON.stringify(props.mapping || {})))
if(!form.attributes) form.attributes = {}


function save(){ useForm({ amazon: form }).post(route('amazon.attrs.update', { product: props.product.id, channel: props.channel.id })) }
</script>


<!-- dumb form row helper -->
<script>
export default { components: { FormRow: { props:['label'], template:`<label class='block text-sm'><span class='text-sm text-gray-700'>{{label}}</span><slot/></label>` } } }
</script>
