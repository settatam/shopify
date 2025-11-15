<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
  channels: Array<{ id: number|string, name: string }>
}>()
</script>

<template>
  <div class="p-6 space-y-6">
    <header class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold" style="color:#016d77">Channels</h1>
    </header>

    <div class="rounded-2xl border shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr class="text-left">
            <th class="py-2 px-3">Name</th>
            <th class="py-2 px-3 w-[280px]">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in props.channels" :key="c.id" class="border-t">
            <td class="py-2 px-3">{{ c.name }}</td>
            <td class="py-2 px-3">
              <div class="flex items-center gap-2">
                <Link
                  :href="route('channels.policy.edit', c.id)"
                  class="px-3 py-1 rounded-xl text-white"
                  style="background:#016d77"
                >Policy</Link>

                <Link
                  :href="route('channels.rules.edit', c.id)"
                  class="px-3 py-1 rounded-xl border"
                  style="border-color:#016d77;color:#016d77"
                >Price Rules</Link>
              </div>
            </td>
          </tr>

          <tr v-if="!props.channels?.length">
            <td colspan="2" class="py-6 px-3 text-center text-gray-500">
              No channels yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
