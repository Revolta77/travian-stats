<script setup lang="ts">
import Card from 'primevue/card'
import { brandLogoSrc } from '../lib/brandLogo'

const logoUrl = brandLogoSrc()
</script>

<template>
  <div class="page">
    <h1 class="home-title">
      <img :src="logoUrl" alt="Travian Stats" class="home-logo" />
    </h1>
    <p class="lead">Úvodná stránka — štatistiky doplníme neskôr.</p>
    <div class="cards">
      <Card>
        <template #title>Prehľad</template>
        <template #content>
          <p class="muted">Miesto pre súhrnné metriky serverov a trendov.</p>
        </template>
      </Card>
      <Card>
        <template #title>Aktivita</template>
        <template #content>
          <p class="muted">Grafy a KPI po prvých importoch dát.</p>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>

.home-title {
  margin: 0 0 0.75rem;
  line-height: 0;
}
.home-title img {
  margin: auto;
}
.home-logo {
  display: block;
  width: auto;
  max-width: min(22rem, 88vw);
  max-height: 6.5rem;
  height: auto;
  object-fit: contain;
}
.lead {
  color: var(--p-text-muted-color, #64748b);
  margin-bottom: 1.5rem;
}
.cards {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}
.muted {
  color: var(--p-text-muted-color, #64748b);
  margin: 0;
}
</style>
