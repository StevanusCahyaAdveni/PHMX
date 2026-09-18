// assets/js/pwa.js - Client-side PWA & Web Push Manager

(function () {
    const isPwaEnabled = typeof window.PHMX_PWA_ENABLED !== 'undefined' ? window.PHMX_PWA_ENABLED : false;
    const vapidPublicKey = typeof window.PHMX_VAPID_PUBLIC_KEY !== 'undefined' ? window.PHMX_VAPID_PUBLIC_KEY : '';

    // 1. Jika PWA dinonaktifkan di config.php, pastikan Service Worker di-unregister
    if (!isPwaEnabled) {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function (registrations) {
                for (let registration of registrations) {
                    registration.unregister();
                    console.log('[PHMX PWA] Service Worker dinonaktifkan & dihapus.');
                }
            });
        }
        return;
    }

    // 2. Registrasi Service Worker jika PWA aktif
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js', { scope: './' })
                .then(function (registration) {
                    console.log('[PHMX PWA] Service Worker aktif:', registration.scope);
                })
                .catch(function (error) {
                    console.warn('[PHMX PWA] Gagal meregistrasi Service Worker:', error);
                });
        });

        // 3. Listener Pesan dari Service Worker untuk memunculkan In-App Toast
        navigator.serviceWorker.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'PHMX_PUSH_NOTIFICATION') {
                const payload = event.data.payload || {};

                // Tampilkan In-App Toast SweetAlert2
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'bottom-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.onmouseenter = Swal.stopTimer;
                            toast.onmouseleave = Swal.resumeTimer;
                        }
                    });

                    Toast.fire({
                        icon: payload.icon_type || 'info',
                        title: payload.title || 'Pemberitahuan Baru',
                        text: payload.body || ''
                    });
                }
            }
        });
    }

    // Helper Konversi VAPID Public Key ke Uint8Array
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // PWA Object API untuk digunakan oleh halaman / UI
    window.PHMX_PWA = {
        // Cek status izin notifikasi
        async isPushSubscribed() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) return false;
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            return sub !== null;
        },

        // Subscribe ke Push Service
        async subscribePush() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                throw new Error('Push Notifications tidak didukung di browser ini.');
            }
            
            const vapidKey = window.PHMX_VAPID_PUBLIC_KEY || vapidPublicKey;
            if (!vapidKey) {
                throw new Error('VAPID Public Key belum dikonfigurasi. Jalankan: php phmx vapid:generate');
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error('Izin notifikasi ditolak oleh pengguna.');
            }

            const reg = await navigator.serviceWorker.ready;

            // Bersihkan subskripsi lama jika ada agar tidak bentrok
            try {
                const oldSub = await reg.pushManager.getSubscription();
                if (oldSub) {
                    await oldSub.unsubscribe();
                }
            } catch (e) {
                console.warn('[PHMX PWA] Clean old sub warning:', e);
            }

            const convertedVapidKey = urlBase64ToUint8Array(vapidKey);

            let subscription;
            try {
                subscription = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: convertedVapidKey
                });
            } catch (err) {
                if (err.message && (err.message.includes('push service error') || err.name === 'AbortError')) {
                    throw new Error('Push Service Error: Jika menggunakan Brave Browser, aktifkan "Use Google services for push messaging" di brave://settings/privacy lalu restart browser.');
                }
                throw err;
            }

            // Kirim data subscription ke server backend
            const response = await fetch('api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(subscription)
            });

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Gagal menyimpan subskripsi ke server.');
            }

            return result;
        },

        // Unsubscribe dari Push Service
        async unsubscribePush() {
            if (!('serviceWorker' in navigator)) return false;
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            if (sub) {
                const endpoint = sub.endpoint;
                await sub.unsubscribe();

                // Hapus di server backend
                await fetch('api/push/unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ endpoint: endpoint })
                });
            }
            return true;
        }
    };

    // Prompt Instalasi PWA
    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const installBtn = document.getElementById('phmx-pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'inline-block';
            installBtn.onclick = async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const choice = await deferredPrompt.userChoice;
                    if (choice.outcome === 'accepted') {
                        installBtn.style.display = 'none';
                    }
                    deferredPrompt = null;
                }
            };
        }
    });
})();
