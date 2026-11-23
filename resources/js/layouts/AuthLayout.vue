<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 lg:grid lg:grid-cols-2">
        <div class="hidden lg:flex flex-col justify-between p-12 text-white">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 font-bold">
                        RB
                    </div>
                    <div>
                        <p class="text-lg font-semibold">RBAC Studio</p>
                        <p class="text-xs text-slate-400">Laravel + Vue + MySQL</p>
                    </div>
                </div>
            </div>

            <div class="max-w-md">
                <slot name="hero" />
            </div>

            <p class="text-xs text-slate-500">&copy; {{ year }} RBAC Studio. All rights reserved.</p>
        </div>

        <div class="flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:min-h-0">
            <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
                <div class="mb-8 lg:hidden flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white">
                        RB
                    </div>
                    <p class="text-lg font-semibold text-slate-900">RBAC Studio</p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
const year = new Date().getFullYear();
</script>
