import { reactive } from 'vue';

const toasts = reactive([]);
let counter = 0;

export function useToast() {
    function push(message, type = 'success') {
        const id = ++counter;
        toasts.push({ id, message, type });
        setTimeout(() => remove(id), 3500);
    }

    function remove(id) {
        const index = toasts.findIndex((toast) => toast.id === id);
        if (index !== -1) toasts.splice(index, 1);
    }

    return {
        toasts,
        push,
        remove,
        success: (message) => push(message, 'success'),
        error: (message) => push(message, 'error'),
    };
}
