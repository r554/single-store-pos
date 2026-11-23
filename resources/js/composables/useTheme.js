import { ref, watchEffect } from 'vue';

const STORAGE_KEY = 'rbac-theme';

const theme = ref(localStorage.getItem(STORAGE_KEY) ?? 'light');

watchEffect(() => {
    const root = document.documentElement;
    const dark = theme.value === 'dark';

    root.classList.toggle('dark', dark);
    root.style.colorScheme = dark ? 'dark' : 'light';
    localStorage.setItem(STORAGE_KEY, theme.value);
});

export function useTheme() {
    function toggle() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return { theme, toggle, isDark: () => theme.value === 'dark' };
}
