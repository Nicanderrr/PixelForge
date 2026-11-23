<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePoll } from '@inertiajs/vue3';
import {
  ArrowLeft,
  ArrowUpRight,
  Image,
  LoaderCircle,
  AlertCircle,
  ShieldCheck,
  Clock,
} from '@lucide/vue';
import AppLayout from '../Components/AppLayout.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { type Generation, money, date, active } from '../types';
const props = defineProps<{ generation: Generation }>();
const broken = ref(false);
const expired = computed(
  () =>
    props.generation.image_url_expires_at &&
    new Date(props.generation.image_url_expires_at).getTime() < Date.now(),
);
usePoll(5000, { only: ['generation'] });
const labels: Record<string, string> = {
  input_text_tokens: 'Input text',
  input_image_tokens: 'Reference images',
  output_tokens: 'Output image',
  reasoning_tokens: 'Reasoning',
};
</script>
<template>
  <AppLayout title="Generation details"
    ><Link href="/generations" class="back-link"><ArrowLeft :size="16" />Back to my creations</Link>
    <div class="page-heading">
      <div>
        <p class="eyebrow">FROM YOUR IMAGINATION</p>
        <h1>
          {{
            generation.status === 'succeeded'
              ? 'An idea, brought to life.'
              : generation.status === 'failed'
                ? 'Every experiment teaches us.'
                : 'Your idea is taking shape.'
          }}
        </h1>
        <p>Created {{ date(generation.created_at) }}</p>
      </div>
      <StatusBadge :status="generation.status" />
    </div>
    <div class="detail-grid">
      <div>
        <div class="detail-preview">
          <img
            v-if="generation.image_url && !expired && !broken"
            :src="generation.image_url"
            :alt="generation.prompt"
            @error="broken = true"
          />
          <div v-else class="detail-placeholder">
            <span class="empty-icon"
              ><LoaderCircle v-if="active(generation.status)" :size="34" class="spin" /><AlertCircle
                v-else-if="generation.status === 'failed' || generation.status === 'attention'"
                :size="34" /><Image v-else :size="34"
            /></span>
            <h2>
              {{
                expired || broken
                  ? 'This image link has expired.'
                  : generation.status === 'failed'
                    ? 'This one didn’t come together.'
                    : generation.status === 'attention'
                      ? 'This request needs a closer look.'
                      : 'A little time for a new perspective.'
              }}
            </h2>
            <p>
              {{
                expired || broken
                  ? 'PixelForge image links last 24 hours. Your prompt and cost records remain available.'
                  : generation.status === 'failed'
                    ? 'You were not charged. Try a different prompt or create a new request.'
                    : generation.status === 'attention'
                      ? 'Automatic retries have stopped. An operator can safely check the original request.'
                      : 'You can leave this page. We’ll keep track of your generation and update it automatically.'
              }}
            </p>
          </div>
        </div>
        <div class="image-footer">
          <span
            ><Clock :size="14" />{{
              generation.image_url_expires_at
                ? `Image link expires ${date(generation.image_url_expires_at)}`
                : 'Status updates automatically'
            }}</span
          ><a
            v-if="generation.image_url && !expired"
            :href="generation.image_url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-link"
            >Open image <ArrowUpRight :size="15"
          /></a>
        </div>
        <section class="panel prompt-detail">
          <h2>The original idea</h2>
          <p>{{ generation.prompt }}</p>
          <div class="detail-tags">
            <span>{{ generation.size.replace('x', ' × ') }} px</span
            ><span>{{ generation.quality }} quality</span
            ><span>{{ generation.reference_images.length }} references</span>
          </div>
          <details v-if="generation.reference_images.length">
            <summary>Reference URLs</summary>
            <p v-for="url in generation.reference_images" :key="url" class="break-word">
              {{ url }}
            </p>
          </details>
        </section>
      </div>
      <aside>
        <section class="panel billing-panel">
          <div class="panel-heading"><h2>Every detail, accounted for.</h2></div>
          <p class="eyebrow">
            {{ generation.actual_customer_price === null ? 'UPFRONT ESTIMATE' : 'FINAL CHARGE' }}
          </p>
          <div class="final-price">
            {{ money(generation.actual_customer_price ?? generation.estimated_customer_price)
            }}<small>USD</small>
          </div>
          <p class="help-text">
            {{
              generation.actual_customer_price === null
                ? 'This is an estimate. The final charge is based on actual usage.'
                : generation.status === 'failed'
                  ? 'No image, no charge.'
                  : 'Calculated from the tokens your generation actually used.'
            }}
          </p>
          <dl class="estimate-breakdown">
            <div>
              <dt>Original estimate</dt>
              <dd>{{ money(generation.estimated_customer_price) }}</dd>
            </div>
            <div>
              <dt>Actual provider cost</dt>
              <dd>{{ money(generation.actual_provider_cost) }}</dd>
            </div>
            <div>
              <dt>Studio markup</dt>
              <dd>25%</dd>
            </div>
            <div class="total-line">
              <dt>Final customer charge</dt>
              <dd>{{ money(generation.actual_customer_price) }}</dd>
            </div>
          </dl>
          <div class="price-disclaimer">
            <ShieldCheck :size="17" /><span
              >One successful generation. One charge. Failed generations are always free.</span
            >
          </div>
          <details v-if="generation.actual_customer_price !== null" class="exact-values">
            <summary>View exact accounting amounts</summary>
            <p>
              Provider USD<br /><code>{{ generation.actual_provider_cost }}</code>
            </p>
            <p>
              Customer USD<br /><code>{{ generation.actual_customer_price }}</code>
            </p>
            <small
              >Display amounts round half up to 6 decimal places. Accounting retains full
              precision.</small
            >
          </details>
        </section>
        <section v-if="generation.usage" class="panel usage-panel">
          <h2>Token usage</h2>
          <dl class="estimate-breakdown">
            <div v-for="(label, key) in labels" :key="key">
              <dt>{{ label }}</dt>
              <dd>{{ generation.usage[key]?.toLocaleString() }}</dd>
            </div>
            <div class="total-line">
              <dt>Total tokens</dt>
              <dd>{{ generation.usage.total_tokens?.toLocaleString() }}</dd>
            </div>
          </dl>
        </section>
        <div v-if="generation.error_message" class="notice" role="status">
          <AlertCircle :size="19" />
          <div>
            {{ generation.error_message
            }}<small v-if="generation.error_code" class="error-code">{{
              generation.error_code
            }}</small>
          </div>
        </div>
        <Link
          v-if="!active(generation.status)"
          href="/generations/create"
          class="button secondary wide"
          >Start a new creation <ArrowUpRight :size="17"
        /></Link>
      </aside></div
  ></AppLayout>
</template>
