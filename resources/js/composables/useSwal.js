import Swal from 'sweetalert2';

const baseOptions = {
    customClass: {
        popup: 'rbac-swal',
    },
    confirmButtonColor: '#4f46e5',
    cancelButtonColor: '#64748b',
};

export function useSwal() {
    function success(message, title = 'Berhasil') {
        return Swal.fire({
            ...baseOptions,
            icon: 'success',
            title,
            text: message,
            confirmButtonText: 'OK',
            timer: 2200,
            timerProgressBar: true,
        });
    }

    function error(message, title = 'Gagal') {
        return Swal.fire({
            ...baseOptions,
            icon: 'error',
            title,
            text: message,
            confirmButtonText: 'Tutup',
        });
    }

    function confirm(message, title = 'Konfirmasi', confirmText = 'Ya, lanjutkan') {
        return Swal.fire({
            ...baseOptions,
            icon: 'warning',
            title,
            text: message,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            reverseButtons: true,
            focusCancel: true,
        });
    }

    return { Swal, success, error, confirm };
}
