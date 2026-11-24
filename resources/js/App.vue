<template>
    <RouterView />
    <ToastStack />
    <SyncStatusBadge />
</template>

<script setup>
import { RouterView } from 'vue-router';
import ToastStack from './components/ToastStack.vue';
import SyncStatusBadge from './components/SyncStatusBadge.vue';
</script>
