// Interaktivitas JS Sederhana untuk Toko Lentera
document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss alert jika ada data-dismiss
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 3000);
    });
});
