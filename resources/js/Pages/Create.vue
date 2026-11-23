<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
  Sparkles,
  ArrowUpRight,
  SlidersHorizontal,
  Link as LinkIcon,
  Plus,
  X,
  ShieldCheck,
  Info,
  WandSparkles,
  LoaderCircle,
} from '@lucide/vue';
import AppLayout from '../Components/AppLayout.vue';
import { money } from '../types';
const props = defineProps<{ requestKey: string; providerConfigured: boolean }>();
const form = useForm({
  prompt: '',
  size: '1024x1024',
  quality: 'medium',
  reference_images: [] as string[],
  request_key: props.requestKey,
});
const estimate = ref<{
  customer_price: string;
  maximum_customer_price: string;
  provider_cost: string;
} | null>(null);
const estimating = ref(false);
const estimateError = ref('');
let timer: ReturnType<typeof setTimeout>;
let aborter: AbortController | undefined;
let sequence = 0;
async function getEstimate(revision: number) {
  aborter?.abort();
  aborter = new AbortController();
  estimating.value = true;
  estimateError.value = '';
  try {
    // Read the current cookie: Laravel rotates the CSRF token after sign-in.
    const token = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length) || '',
    );
    const response = await fetch('/generations/estimate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify(form.data()),
      signal: aborter.signal,
    });
    const data = await response.json();
    if (revision !== sequence) return;
    if (!response.ok) throw new Error(data.message || 'Could not load pricing. Please try again.');
    estimate.value = data;
  } catch (e) {
    if (revision === sequence && (e as Error).name !== 'AbortError')
      estimateError.value = (e as Error).message;
  } finally {
    if (revision === sequence) estimating.value = false;
  }
}
watch(
  () => [form.prompt, form.size, form.quality, [...form.reference_images]],
  () => {
    clearTimeout(timer);
    aborter?.abort();
    sequence++;
    estimate.value = null;
    estimateError.value = '';
    estimating.value = false;
    if (!form.prompt.trim()) return;
    const revision = sequence;
    estimating.value = true;
    timer = setTimeout(() => getEstimate(revision), 650);
  },
  { deep: true },
);
onBeforeUnmount(() => {
  clearTimeout(timer);
  aborter?.abort();
});
const sizes = [
  { value: '1024x1024', label: 'Square', ratio: '1:1', shape: 'square' },
  { value: '1536x1024', label: 'Landscape', ratio: '3:2', shape: 'landscape' },
  { value: '1024x1536', label: 'Portrait', ratio: '2:3', shape: 'portrait' },
];
const qualities = [
  { value: 'low', label: 'Draft', description: 'Quick explorations' },
  { value: 'medium', label: 'Standard', description: 'Everyday creativity' },
  { value: 'high', label: 'High detail', description: 'The finer things' },
];
const prompts = [
  {
    label: 'A quieter world',
    text: 'A tiny cabin beside a glassy alpine lake at dawn, soft morning mist, muted sage and warm peach tones, cinematic photography',
  },
  {
    label: 'Something unexpected',
    text: 'A translucent glass pear resting on a lavender pedestal, playful editorial still life, soft studio lighting, minimal composition',
  },
  {
    label: 'A different perspective',
    text: 'An impossible spiral staircase in a sunlit terracotta courtyard, architectural photography, long shadows and a clear blue sky',
  },
];
</script>
<template>
  <AppLayout title="Create image">
    <div class="page-heading">
      <div>
        <p class="eyebrow">THE POSSIBILITIES START HERE</p>
        <h1>Give your idea a form.</h1>
        <p>A few words. A fresh perspective. Something entirely yours.</p>
      </div>
      <span class="subtle-pill"><Sparkles :size="14" /> Powered by PixelForge</span>
    </div>
    <div v-if="!providerConfigured" class="notice" role="status">
      <Info :size="18" />
      <div>
        <strong>Your studio is almost ready.</strong> Generation will be available once the studio
        is connected. You can explore settings and live estimates in the meantime.
      </div>
    </div>
    <form @submit.prevent="form.post('/generations')" class="create-grid">
      <div class="create-main">
        <section class="panel prompt-panel">
          <div class="panel-heading">
            <h2><WandSparkles :size="19" />The idea</h2>
            <span>01</span>
          </div>
          <label for="prompt">What do you have in mind?</label>
          <p class="help-text">
            Be descriptive. Think subject, setting, lighting, mood, and style.
          </p>
          <div class="prompt-wrap">
            <textarea
              id="prompt"
              v-model="form.prompt"
              maxlength="4000"
              rows="7"
              required
              placeholder="A sun-drenched reading nook, linen curtains moving in a gentle breeze, warm wood, a quiet Sunday morning. Shot on 35mm film…"
            ></textarea
            ><span class="char-count">{{ [...form.prompt].length.toLocaleString() }} / 4,000</span>
          </div>
          <p v-if="form.errors.prompt" class="field-error" role="alert">{{ form.errors.prompt }}</p>
          <div class="prompt-hint">
            <Sparkles :size="15" />Specific details make a world of difference.
          </div>
        </section>
        <section class="panel">
          <div class="panel-heading">
            <h2><SlidersHorizontal :size="19" />Make it yours</h2>
            <span>02</span>
          </div>
          <fieldset>
            <legend>Aspect ratio</legend>
            <div class="size-options">
              <label
                v-for="size in sizes"
                :key="size.value"
                :class="['option-card', { chosen: form.size === size.value }]"
                ><input v-model="form.size" type="radio" name="size" :value="size.value" /><span
                  :class="['ratio-icon', size.shape]"
                ></span
                ><strong
                  >{{ size.label }} <small>{{ size.ratio }}</small></strong
                ><span>{{ size.value.replace('x', ' × ') }} px</span></label
              >
            </div>
          </fieldset>
          <p v-if="form.errors.size" class="field-error">{{ form.errors.size }}</p>
          <fieldset class="quality-field">
            <legend>Quality</legend>
            <div class="quality-options">
              <label
                v-for="quality in qualities"
                :key="quality.value"
                :class="['quality-option', { chosen: form.quality === quality.value }]"
                ><input
                  v-model="form.quality"
                  type="radio"
                  name="quality"
                  :value="quality.value"
                /><strong
                  >{{ quality.label
                  }}<span v-if="quality.value === 'medium'" class="recommended"
                    >POPULAR</span
                  ></strong
                ><small>{{ quality.description }}</small></label
              >
            </div>
          </fieldset>
          <p v-if="form.errors.quality" class="field-error">{{ form.errors.quality }}</p>
        </section>
        <section class="panel">
          <div class="panel-heading">
            <h2><LinkIcon :size="19" />A little reference</h2>
            <span class="optional">Optional</span>
          </div>
          <p class="help-text">
            Add up to 4 image URLs to guide the style. Each adds input token cost.
          </p>
          <div v-for="(_, i) in form.reference_images" :key="i" class="reference-row">
            <label class="sr-only" :for="`reference-${i}`">Reference image {{ i + 1 }}</label
            ><input
              :id="`reference-${i}`"
              v-model="form.reference_images[i]"
              type="url"
              required
              placeholder="https://example.com/image.jpg"
            /><button
              type="button"
              class="icon-button"
              :aria-label="`Remove reference ${i + 1}`"
              @click="form.reference_images.splice(i, 1)"
            >
              <X :size="18" />
            </button>
          </div>
          <p v-for="(error, key) in form.errors" :key="key" class="field-error">
            {{ String(key).startsWith('reference_images') ? error : '' }}
          </p>
          <button
            v-if="form.reference_images.length < 4"
            type="button"
            class="button text-button"
            @click="form.reference_images.push('')"
          >
            <Plus :size="16" />Add reference URL
          </button>
        </section>
      </div>
      <aside class="create-aside">
        <section class="estimate-panel">
          <span class="estimate-icon"><Sparkles :size="23" /></span>
          <p class="eyebrow">A LITTLE CLARITY</p>
          <h2>Your creation, estimated.</h2>
          <p class="muted">Transparent pricing. No surprises hidden in the small print.</p>
          <div class="price-display" aria-live="polite">
            <LoaderCircle v-if="estimating" class="spin" :size="24" /><strong v-else>{{
              money(estimate?.customer_price)
            }}</strong
            ><span>USD · estimated charge</span>
          </div>
          <p v-if="estimateError" class="field-error" role="alert">
            {{ estimateError }}
            <button type="button" class="text-link" @click="getEstimate(sequence)">Retry</button>
          </p>
          <dl class="estimate-breakdown">
            <div>
              <dt>Provider cost estimate</dt>
              <dd>{{ money(estimate?.provider_cost) }}</dd>
            </div>
            <div>
              <dt>Studio markup</dt>
              <dd>25%</dd>
            </div>
            <div>
              <dt>Estimated upper bound</dt>
              <dd>{{ money(estimate?.maximum_customer_price) }}</dd>
            </div>
          </dl>
          <button
            class="button primary wide generate-button"
            :disabled="!providerConfigured || !estimate || estimating || form.processing"
          >
            <LoaderCircle v-if="form.processing" :size="18" class="spin" /><Sparkles
              v-else
              :size="18"
            />{{ form.processing ? 'Queuing your idea…' : 'Generate image'
            }}<ArrowUpRight :size="17" />
          </button>
          <p class="price-disclaimer">
            <ShieldCheck :size="16" /><span
              >You pay actual token cost + 25%. Final usage varies. Failed generations cost
              nothing.</span
            >
          </p>
        </section>
        <section class="inspiration-panel">
          <p class="eyebrow">NEED A STARTING POINT?</p>
          <h3>Follow a little curiosity.</h3>
          <button
            v-for="prompt in prompts"
            :key="prompt.label"
            type="button"
            @click="form.prompt = prompt.text"
          >
            <span>{{ prompt.label }}</span><ArrowUpRight :size="16" />
          </button>
          <p>Pick a thought, then make it your own.</p>
        </section>
      </aside>
    </form>
  </AppLayout>
</template>
