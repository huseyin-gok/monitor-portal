// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Otomatik refresh için grafikleri yenile
    const refreshInterval = 30; // saniye
    
    setInterval(function() {
        // Zabbix grafiklerini yenile
        const graphImages = document.querySelectorAll('.graph-image');
        graphImages.forEach(function(img) {
            const src = img.src;
            // Cache'i bypass et
            img.src = src.split('?')[0] + '?t=' + new Date().getTime();
        });
    }, refreshInterval * 1000);
    
    // Hata yönetimi
    window.addEventListener('error', function(e) {
        console.error('Dashboard hatası:', e);
    });
});
