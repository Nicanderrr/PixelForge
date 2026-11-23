<script setup lang="ts">
import { Link, router, usePoll } from '@inertiajs/vue3';
import { Plus, Images } from '@lucide/vue';
import AppLayout from '../Components/AppLayout.vue';
import GenerationCard from '../Components/GenerationCard.vue';
import type { Generation } from '../types';
defineProps<{
  generations: {
    data: Generation[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
  };
  filter: string;
}>();
const filters = [
  { value: 'all', label: 'All creations' },
  { value: 'succeeded', label: 'Completed' },
  { value: 'processing', label: 'Creating' },
  { value: 'queued', label: 'Queued' },
  { value: 'failed', label: 'Failed' },
  { value: 'attention', label: 'Needs attention' },
];
usePoll(8000, { only: ['generations'] });
</script>
<template>
  <AppLayout title="My creations"
    ><div class="page-heading">
      <div>
        <p class="eyebrow">YOUR IDEAS, COLLECTED</p>
        <h1>A world of your making.</h1>
        <p>Every experiment, every possibility, every little spark.</p>
      </div>
      <Link href="/generations/create" class="button primary"><Plus :size="18" />Create image</Link>
    </div>
    <div class="history-toolbar">
      <div class="filter-tabs" aria-label="Filter creations">
        <button
          v-for="item in filters"
          :key="item.value"
          :class="{ active: filter === item.value }"
          :aria-pressed="filter === item.value"
          @click="router.get('/generations', { status: item.value }, { preserveState: true })"
        >
          {{ item.label }}
        </button>
      </div>
      <span class="muted small"
        >{{ generations.total }} {{ generations.total === 1 ? 'creation' : 'creations' }}</span
      >
    </div>
    <div v-if="generations.data.length" class="cards-grid history-grid">
      <GenerationCard
        v-for="generation in generations.data"
        :key="generation.id"
        :generation="generation"
      />
    </div>
    <div v-else class="empty-state">
      <span class="empty-icon"><Images :size="30" /></span>
      <h3>{{ filter === 'all' ? 'A blank canvas, for now.' : 'Nothing here just yet.' }}</h3>
      <p>
        {{
          filter === 'all'
            ? 'Your first creation is just a few words away.'
            : 'No creations match this status. Try another filter.'
        }}
      </p>
      <Link href="/generations/create" class="button secondary"
        ><Plus :size="17" />Create an image</Link
      >
    </div>
    <div v-if="generations.last_page > 1" class="pagination">
      <Link
        v-if="generations.prev_page_url"
        :href="generations.prev_page_url"
        class="button secondary"
        >Previous</Link
      ><span>Page {{ generations.current_page }} of {{ generations.last_page }}</span
      ><Link
        v-if="generations.next_page_url"
        :href="generations.next_page_url"
        class="button secondary"
        >Next</Link
      >
    </div></AppLayout
  >
</template>
