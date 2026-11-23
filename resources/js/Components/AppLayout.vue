<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
  LayoutDashboard,
  Sparkles,
  Images,
  Settings,
  LogOut,
  ArrowUpRight,
  Menu,
  X,
  PanelLeft,
} from '@lucide/vue';
defineProps<{ title: string; eyebrow?: string }>();
const page = usePage();
const user = computed(() => (page.props.auth as { user: { name: string; email: string } }).user);
const flash = computed(() => (page.props.flash as { success?: string })?.success);
const menuOpen = ref(false);
const nav = [
  { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
  { label: 'Create image', href: '/generations/create', icon: Sparkles },
  { label: 'My creations', href: '/generations', icon: Images },
];
const selected = (href: string) =>
  href === '/generations'
    ? page.url.startsWith('/generations') && !page.url.startsWith('/generations/create')
    : page.url.split('?')[0] === href;
</script>

<template>
  <Head :title="title" />
  <div class="app-shell">
    <button
      v-if="menuOpen"
      class="nav-backdrop"
      aria-label="Close navigation"
      @click="menuOpen = false"
    ></button>
    <aside class="sidebar" :class="{ open: menuOpen }">
      <Link href="/dashboard" class="brand"
        ><span class="brand-mark">f</span>forma<span class="brand-dot">.</span></Link
      >
      <button
        class="mobile-close icon-button"
        aria-label="Close navigation"
        @click="menuOpen = false"
      >
        <X :size="20" />
      </button>
      <div class="workspace">
        <span class="workspace-avatar">P</span>
        <div><strong>Personal workspace</strong><small>Your space to create</small></div>
      </div>
      <p class="nav-label">STUDIO</p>
      <nav aria-label="Main navigation">
        <Link
          v-for="item in nav"
          :key="item.href"
          :href="item.href"
          :class="['nav-link', { selected: selected(item.href) }]"
          @click="menuOpen = false"
          ><component :is="item.icon" :size="19" />{{ item.label
          }}<span v-if="selected(item.href)" class="nav-dot"></span
        ></Link>
      </nav>
      <div class="sidebar-bottom">
        <div class="sidebar-note">
          <Sparkles :size="21" /><strong>A little idea.<br />A whole new possibility.</strong>
          <p>Make something only you could imagine.</p>
          <Link href="/generations/create">Start creating <ArrowUpRight :size="15" /></Link>
        </div>
        <Link
          href="/settings"
          :class="['nav-link', { selected: selected('/settings') }]"
          @click="menuOpen = false"
          ><Settings :size="18" />Account settings</Link
        >
        <div class="user-row">
          <span class="user-avatar">{{ user.name.slice(0, 1).toUpperCase() }}</span>
          <div>
            <strong>{{ user.name }}</strong
            ><small>Personal account</small>
          </div>
          <button
            class="icon-button"
            aria-label="Sign out"
            title="Sign out"
            @click="router.post('/logout')"
          >
            <LogOut :size="18" />
          </button>
        </div>
      </div>
    </aside>
    <div class="app-body">
      <header class="topbar">
        <div>
          <button
            class="mobile-menu icon-button"
            aria-label="Open navigation"
            @click="menuOpen = true"
          >
            <Menu :size="21" /></button
          ><PanelLeft class="desktop-only muted" :size="18" /><span class="crumb"
            >Workspace <span>/</span> <strong>{{ title }}</strong></span
          >
        </div>
        <span class="environment-pill"><span></span>PixelForge studio</span>
      </header>
      <main id="main-content">
        <div v-if="flash" class="notice success" role="status">{{ flash }}</div>
        <slot />
      </main>
      <footer class="app-footer">
        <span>Made for your imagination.</span
        ><span>Powered by PixelForge · Simulated generations</span>
      </footer>
    </div>
  </div>
</template>
