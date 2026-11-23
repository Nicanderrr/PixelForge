<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Image, LoaderCircle, AlertCircle, ArrowUpRight, Clock } from '@lucide/vue';
import StatusBadge from './StatusBadge.vue';
import { type Generation, money, date, active } from '../types';
const props = defineProps<{ generation: Generation }>();
const broken = ref(false);
const expired = computed(
  () =>
    props.generation.image_url_expires_at &&
    new Date(props.generation.image_url_expires_at).getTime() < Date.now(),
);
</script>
<template>
  <Link :href="`/generations/${generation.id}`" class="generation-card">
    <div class="generation-preview">
      <img
        v-if="generation.image_url && !expired && !broken"
        :src="generation.image_url"
        :alt="generation.prompt"
        loading="lazy"
        @error="broken = true"
      />
      <div v-else class="preview-placeholder">
        <LoaderCircle v-if="active(generation.status)" :size="30" class="spin" /><AlertCircle
          v-else-if="generation.status === 'failed' || generation.status === 'attention'"
          :size="30"
        /><Image v-else :size="30" /><span>{{
          expired || broken
            ? 'Image link expired'
            : generation.status === 'failed'
              ? 'No image generated'
              : generation.status === 'attention'
                ? 'Waiting for review'
                : 'Your idea is taking shape'
        }}</span>
      </div>
      <StatusBadge :status="generation.status" /><span class="card-open"
        ><ArrowUpRight :size="18"
      /></span>
    </div>
    <div class="card-content">
      <h3>{{ generation.prompt }}</h3>
      <p>
        {{ generation.size.replace('x', ' × ') }} <span>·</span> {{ generation.quality }} quality
      </p>
      <div class="card-meta">
        <span><Clock :size="12" />{{ date(generation.created_at) }}</span
        ><strong
          >{{ money(generation.actual_customer_price ?? generation.estimated_customer_price)
          }}<small v-if="generation.actual_customer_price === null"> est.</small></strong
        >
      </div>
    </div>
  </Link>
</template>
