<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ status: string }>();
const label = computed(
  () =>
    ({
      queued: 'Queued',
      submitting: 'Submitting',
      processing: 'Creating',
      succeeded: 'Completed',
      failed: 'Failed',
      attention: 'Needs attention',
    })[props.status] || props.status,
);
</script>
<template>
  <span :class="['status-badge', status]"><span></span>{{ label }}</span>
</template>
