<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { UserRound, LockKeyhole, Check } from '@lucide/vue';
import AppLayout from '../Components/AppLayout.vue';
const user = (usePage().props.auth as { user: { name: string; email: string } }).user;
const form = useForm({
  name: user.name,
  email: user.email,
  current_password: '',
  password: '',
  password_confirmation: '',
});
</script>
<template>
  <AppLayout title="Account settings"
    ><div class="page-heading">
      <div>
        <p class="eyebrow">MAKE YOURSELF AT HOME</p>
        <h1>Your account.</h1>
        <p>A few details that make this space yours.</p>
      </div>
    </div>
    <form
      class="settings-form stack-form"
      @submit.prevent="
        form.patch('/settings', {
          onSuccess: () => form.reset('current_password', 'password', 'password_confirmation'),
        })
      "
    >
      <section class="panel">
        <div class="panel-heading">
          <h2><UserRound :size="20" />Personal details</h2>
        </div>
        <div class="stack-form">
          <div>
            <label for="name">Full name</label
            ><input id="name" v-model="form.name" required autocomplete="name" />
          </div>
          <div>
            <label for="email">Email address</label
            ><input id="email" v-model="form.email" required type="email" autocomplete="email" />
          </div>
        </div>
      </section>
      <section class="panel">
        <div class="panel-heading">
          <h2><LockKeyhole :size="20" />Change password</h2>
          <span class="optional">Optional</span>
        </div>
        <p class="help-text">Leave these fields blank to keep your current password.</p>
        <div class="stack-form">
          <div>
            <label for="current_password">Current password</label
            ><input
              id="current_password"
              v-model="form.current_password"
              type="password"
              autocomplete="current-password"
            />
          </div>
          <div>
            <label for="new_password">New password</label
            ><input
              id="new_password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              minlength="12"
              placeholder="At least 12 characters"
            />
          </div>
          <div>
            <label for="confirm_password">Confirm new password</label
            ><input
              id="confirm_password"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
            />
          </div>
        </div>
      </section>
      <p v-for="(error, key) in form.errors" :key="key" class="field-error" role="alert">
        {{ error }}
      </p>
      <button class="button primary" :disabled="form.processing">
        <Check :size="18" />{{ form.processing ? 'Saving…' : 'Save changes' }}
      </button>
    </form></AppLayout
  >
</template>
