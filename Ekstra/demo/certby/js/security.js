/**
 * Güvenlik Utilities - Frontend
 * CSRF token yönetimi ve güvenlik kontrolleri
 */

class SecurityManager {
    constructor() {
        this.csrfToken = null;
        this.sessionTimeout = 15 * 60 * 1000; // 15 dakika
        this.lastActivity = Date.now();
        
        this.init();
    }
    
    init() {
        this.setupActivityTracking();
        this.setupSessionTimeout();
        this.loadCSRFToken();
    }
    
    /**
     * CSRF Token'ı al ve formlara ekle
     */
    loadCSRFToken() {
        // Mevcut token'ı kontrol et
        const existingToken = document.querySelector('meta[name="csrf-token"]');
        if (existingToken) {
            this.csrfToken = existingToken.getAttribute('content');
        }
        
        // Tüm formlara CSRF token ekle
        this.addCSRFTokenToForms();
    }
    
    /**
     * Tüm formlara CSRF token ekle
     */
    addCSRFTokenToForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'csrf_token';
                tokenInput.value = this.csrfToken || '';
                form.appendChild(tokenInput);
            }
        });
    }
    
    /**
     * AJAX isteklerine CSRF token ekle
     */
    addCSRFTokenToAjax() {
        const originalFetch = window.fetch;
        const self = this;
        
        window.fetch = function(url, options = {}) {
            // POST, PUT, DELETE isteklerine CSRF token ekle
            if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
                if (!options.headers) {
                    options.headers = {};
                }
                
                if (self.csrfToken) {
                    options.headers['X-CSRF-Token'] = self.csrfToken;
                }
                
                // FormData ise CSRF token ekle
                if (options.body instanceof FormData) {
                    options.body.append('csrf_token', self.csrfToken);
                }
            }
            
            return originalFetch.call(this, url, options);
        };
    }
    
    /**
     * Kullanıcı aktivitesini takip et
     */
    setupActivityTracking() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
            }, true);
        });
    }
    
    /**
     * Session timeout kontrolü
     */
    setupSessionTimeout() {
        setInterval(() => {
            const now = Date.now();
            const timeSinceLastActivity = now - this.lastActivity;
            
            if (timeSinceLastActivity > this.sessionTimeout) {
                this.handleSessionTimeout();
            }
        }, 60000); // Her dakika kontrol et
    }
    
    /**
     * Session timeout durumunda yapılacaklar
     */
    handleSessionTimeout() {
        // Kullanıcıyı uyar
        if (confirm('Oturumunuzun süresi doldu. Sayfayı yenilemek istiyor musunuz?')) {
            window.location.href = 'index.html';
        } else {
            // Zorla çıkış
            this.logout();
        }
    }
    
    /**
     * Güvenli çıkış
     */
    logout() {
        fetch('auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            },
            body: JSON.stringify({
                action: 'logout'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'index.html';
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            window.location.href = 'index.html';
        });
    }
    
    /**
     * Güvenli form gönderimi
     */
    secureFormSubmit(formData, url, method = 'POST') {
        const data = new FormData();
        
        // CSRF token ekle
        if (this.csrfToken) {
            data.append('csrf_token', this.csrfToken);
        }
        
        // Form verilerini ekle
        for (let [key, value] of formData.entries()) {
            data.append(key, value);
        }
        
        return fetch(url, {
            method: method,
            body: data,
            headers: {
                'X-CSRF-Token': this.csrfToken
            }
        });
    }
    
    /**
     * XSS koruması için input sanitization
     */
    sanitizeInput(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }
    
    /**
     * Güvenli innerHTML kullanımı
     */
    safeInnerHTML(element, content) {
        element.innerHTML = this.sanitizeInput(content);
    }
    
    /**
     * Rate limiting kontrolü
     */
    checkRateLimit(action, maxAttempts = 5, timeWindow = 300000) {
        const key = `rate_limit_${action}`;
        const now = Date.now();
        
        if (!localStorage.getItem(key)) {
            localStorage.setItem(key, JSON.stringify({
                count: 0,
                firstAttempt: now
            }));
        }
        
        const rateData = JSON.parse(localStorage.getItem(key));
        
        // Zaman penceresi dolmuşsa sıfırla
        if (now - rateData.firstAttempt > timeWindow) {
            localStorage.setItem(key, JSON.stringify({
                count: 1,
                firstAttempt: now
            }));
            return true;
        }
        
        // Maksimum deneme sayısını kontrol et
        if (rateData.count >= maxAttempts) {
            return false;
        }
        
        // Deneme sayısını artır
        rateData.count++;
        localStorage.setItem(key, JSON.stringify(rateData));
        
        return true;
    }
}

// Global security manager instance
window.securityManager = new SecurityManager();

// Sayfa yüklendiğinde güvenlik kontrollerini başlat
document.addEventListener('DOMContentLoaded', function() {
    // CSRF token'ı meta tag'den al
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        window.securityManager.csrfToken = csrfMeta.getAttribute('content');
    }
    
    // AJAX isteklerine CSRF token ekle
    window.securityManager.addCSRFTokenToAjax();
    
    // Formlara CSRF token ekle
    window.securityManager.addCSRFTokenToForms();
});

// Otomatik logout'u kaldır: beforeunload'ta logout isteme
