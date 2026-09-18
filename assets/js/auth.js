document.addEventListener("DOMContentLoaded", function() {
    // Pastikan variabel PHMX_IS_LOGGED_IN sudah diset oleh index.php
    if (typeof PHMX_IS_LOGGED_IN !== 'undefined' && !PHMX_IS_LOGGED_IN) {
        
        const storedEmail = localStorage.getItem('phmx_email');
        const storedPwd = localStorage.getItem('phmx_pwd');
        
        if (storedEmail && storedPwd) {
            console.log("[PHMX] Mendeteksi sesi Remember Me, mencoba Silent Login...");
            
            // Siapkan payload
            const formData = new FormData();
            formData.append('email', storedEmail);
            formData.append('password', storedPwd);
            
            // Sertakan CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                formData.append('csrf_token', csrfToken.content);
            }
            
            // Hit API Silent Login
            fetch('?api=auth/silent_login', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("[PHMX] Silent Login berhasil! Me-reload halaman...");
                    // Reload halaman yang sama agar session PHP yang baru terbaca
                    window.location.reload();
                } else {
                    console.warn("[PHMX] Silent Login gagal (kredensial mungkin berubah). Membersihkan LocalStorage.");
                    localStorage.removeItem('phmx_email');
                    localStorage.removeItem('phmx_pwd');
                }
            })
            .catch(error => {
                console.error("[PHMX] Error saat Silent Login:", error);
            });
        }
    }
});
