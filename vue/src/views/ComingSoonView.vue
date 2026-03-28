<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Card from 'primevue/card'

const route = useRoute()
const title = computed(() => (route.meta.title as string) || 'Stránka')
</script>

<template>
  <div class="page">
    <h1>{{ title }}</h1>
    <Card>
      <template #content>
        <p class="muted">Táto časť ešte nie je implementovaná.</p>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.page {
  max-width: 720px;
}
.muted {
  color: var(--p-text-muted-color, #64748b);
  margin: 0;
}
</style>
