<!-- Eingabefeld mit Beschriftung darüber, wie FormField in job-match -->
<template>
  <div class="d-flex flex-column ga-2">
    <label :for="id" class="text-body-medium font-weight-medium">{{ label }}</label>
    <v-text-field
      :id="id"
      v-model="model"
      :type="type ?? 'text'"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      hide-details="auto"
      required
    />
  </div>
</template>

<script setup lang="ts">
defineProps<{ label: string; type?: string; autocomplete?: string; placeholder?: string }>()

const model = defineModel<string>({ default: '' })
const id = useId()
</script>
