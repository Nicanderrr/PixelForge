<script setup lang="ts">
import { Link, usePage, usePoll } from '@inertiajs/vue3';
import {
  Sparkles,
  ArrowUpRight,
  ArrowRight,
  Image,
  CircleCheck,
  Layers,
  Wallet,
  Plus,
} from '@lucide/vue';
import AppLayout from '../Components/AppLayout.vue';
import GenerationCard from '../Components/GenerationCard.vue';
import { type Generation, money } from '../types';
defineProps<{
  recent: Generation[];
  stats: { total: number; succeeded: number; active: number; spent: string };
}>();
const name = (usePage().props.auth as { user: { name: string } }).user.name.split(' ')[0];
usePoll(10000, { only: ['recent', 'stats'] });
</script>
<template>
  <AppLayout title="Overview">
    <div class="page-heading">
      <div>
        <p class="eyebrow">YOUR PERSONAL STUDIO</p>
        <h1>A little inspiration, {{ name }}.</h1>
        <p>Big ideas start with a blank canvas. What will you create today?</p>
      </div>
      <Link href="/generations/create" class="button primary"><Plus :size="18" />Create image</Link>
    </div>
    <section class="hero-panel">
      <div>
        <span class="hero-tag"><Sparkles :size="14" /> FROM THOUGHT TO FORM</span>
        <h2>Your imagination.<br />A new perspective.</h2>
        <p>
          Describe a world, explore a style, or follow a spark.<br />Let’s see where your next idea
          takes you.
        </p>
        <Link href="/generations/create" class="button dark"
          >Bring an idea to life <ArrowUpRight :size="18"
        /></Link>
      </div>
      <div class="hero-art" aria-hidden="true">
        <div class="hero-disc disc-back"></div>
        <div class="hero-disc disc-front"></div>
        <div class="hero-grid"></div>
        <span class="floating-star">✳</span><span class="art-label">MAKE SOMETHING NEW</span>
      </div>
    </section>
    <div class="stats-grid">
      <div class="stat-card">
        <span><Image :size="18" />Total creations</span
        ><strong>{{ stats.total }}<small>ideas explored</small></strong>
      </div>
      <div class="stat-card">
        <span><CircleCheck :size="18" />Completed</span
        ><strong>{{ stats.succeeded }}<small>images brought to life</small></strong>
      </div>
      <div class="stat-card">
        <span><Layers :size="18" />In progress</span
        ><strong>{{ stats.active }}<small>ideas taking shape</small></strong>
      </div>
      <div class="stat-card">
        <span><Wallet :size="18" />Finalized spend</span
        ><strong class="money-stat"
          >{{ money(stats.spent) }}<small>USD · actual charges only</small></strong
        >
      </div>
    </div>
    <section>
      <div class="section-heading">
        <div>
          <h2>Recent creations</h2>
          <p>Your latest ideas, all in one place.</p>
        </div>
        <Link href="/generations" class="text-link"
          >View all creations <ArrowRight :size="16"
        /></Link>
      </div>
      <div v-if="recent.length" class="cards-grid">
        <GenerationCard
          v-for="generation in recent"
          :key="generation.id"
          :generation="generation"
        />
      </div>
      <div v-else class="empty-state">
        <span class="empty-icon"><Image :size="28" /></span>
        <h3>Your creative story starts here.</h3>
        <p>
          Give your first idea a little space to grow.<br />Your completed images will appear here.
        </p>
        <Link href="/generations/create" class="button secondary"
          ><Plus :size="17" />Create your first image</Link
        >
      </div>
    </section>
  </AppLayout>
</template>
