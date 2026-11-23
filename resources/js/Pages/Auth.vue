<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowRight, Sparkles, Check } from '@lucide/vue';
const props = defineProps<{ mode: 'login' | 'register' }>();
const register = computed(() => props.mode === 'register');
const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  remember: false,
});
const submit = () =>
  form.post(register.value ? '/register' : '/login', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
</script>
<template>
  <Head :title="register ? 'Create an account' : 'Welcome back'" />
  <div class="auth-page">
    <section class="auth-art">
      <Link href="/" class="brand"
        ><span class="brand-mark">f</span>forma<span class="brand-dot">.</span></Link
      >
      <div class="auth-art-content">
        <span class="eyebrow">A SPACE FOR YOUR IMAGINATION</span>
        <h1>Small spark.<br />Infinite possibilities.</h1>
        <p>
          Turn the ideas in your head into something you can see. Your next creation starts here.
        </p>
        <div class="abstract-art" aria-hidden="true">
          <div class="orbit orbit-one"></div>
          <div class="orbit orbit-two"></div>
          <div class="art-sphere"></div>
          <span class="art-star">✳</span><span class="art-caption">IMAGINATION, IN FORM.</span>
        </div>
      </div>
      <small>Creative tools. Clear pricing. Entirely your vision.</small>
    </section>
    <section class="auth-form-section">
      <div class="auth-form">
        <span class="section-icon"><Sparkles :size="24" /></span>
        <p class="eyebrow">YOUR CREATIVE WORKSPACE</p>
        <h2>{{ register ? 'Make room for your ideas.' : 'Welcome back.' }}</h2>
        <p class="muted">
          {{
            register
              ? 'Create your account and bring your first idea to life.'
              : 'Sign in to pick up where your imagination left off.'
          }}
        </p>
        <form @submit.prevent="submit" class="stack-form">
          <div v-if="register">
            <label for="name">Full name</label
            ><input
              id="name"
              v-model="form.name"
              autocomplete="name"
              required
              maxlength="100"
              placeholder="Your name"
            />
            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
          </div>
          <div>
            <label for="email">Email address</label
            ><input
              id="email"
              v-model="form.email"
              type="email"
              autocomplete="email"
              required
              placeholder="you@example.com"
            />
            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
          </div>
          <div>
            <label for="password">Password</label
            ><input
              id="password"
              v-model="form.password"
              type="password"
              :autocomplete="register ? 'new-password' : 'current-password'"
              required
              :minlength="register ? 12 : undefined"
              :placeholder="register ? 'At least 12 characters' : 'Enter your password'"
            />
            <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
          </div>
          <div v-if="register">
            <label for="password_confirmation">Confirm password</label
            ><input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              placeholder="One more time"
            />
          </div>
          <label v-else class="checkbox-label"
            ><input v-model="form.remember" type="checkbox" />Keep me signed in</label
          >
          <button class="button primary wide" :disabled="form.processing">
            {{
              form.processing
                ? 'Please wait…'
                : register
                  ? 'Create your account'
                  : 'Sign in to your studio'
            }}<ArrowRight :size="18" />
          </button>
        </form>
        <p class="auth-switch">
          {{ register ? 'Already have an account?' : 'New to Forma?' }}
          <Link :href="register ? '/login' : '/register'">{{
            register ? 'Sign in' : 'Create an account'
          }}</Link>
        </p>
        <div class="auth-footnote">
          <Check :size="15" /> No real payments. This studio uses the assessment’s simulated
          provider.
        </div>
      </div>
    </section>
  </div>
</template>
