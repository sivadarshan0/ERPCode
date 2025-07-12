// Utility Functions
const escapeHtml = (unsafe) => unsafe?.toString()?.replace(/[&<>"']/g, 
    m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])) || '';

const debounce = (func, wait) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
};

const showAlert = (message, type) => {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    (document.querySelector('main') || document.body).prepend(alert);
    setTimeout(() => alert.remove(), 5000);
};

// Form Handling
const initFormSubmit = () => {
    const forms = document.querySelectorAll('form.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
            
            const submitBtn = this.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    Processing...
                `;
            }
        });
    });
};

// Main Initialization
document.addEventListener('DOMContentLoaded', () => {
    initFormSubmit();
    
    // Auto-dismiss alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = 0;
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Initialize other page-specific components
    if (document.getElementById('phone')) {
        // Live search is now handled in the PHP file's inline script
    }
});