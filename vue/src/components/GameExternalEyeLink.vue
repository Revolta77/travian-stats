<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  href: string | null
  label?: string
}>()

const { t } = useI18n()

const ariaLabel = computed(() => props.label ?? t('common.openInGame'))
</script>

<template>
  <a
    v-if="href"
    :href="href"
    target="_blank"
    rel="noopener noreferrer"
    class="game-external-eye"
    :aria-label="ariaLabel"
  >
    <i class="pi pi-eye" aria-hidden="true" />
  </a>
  <span v-else class="game-external-eye-muted">{{ t('common.emDash') }}</span>
</template>

<style scoped>
.game-external-eye {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--p-border-radius-md, 6px);
  color: var(--p-primary-color);
  text-decoration: none;
}
.game-external-eye:hover {
  background: var(--p-content-hover-background, rgba(0, 0, 0, 0.04));
  text-decoration: none;
}
.game-external-eye .pi {
  font-size: 1.15rem;
}
.game-external-eye-muted {
  display: inline-block;
  width: 2.25rem;
  text-align: center;
  color: var(--p-text-muted-color, #64748b);
}
</style>
