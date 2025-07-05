// Global application JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any global components
    initAutoComplete();
    initFormValidations();
});

function initAutoComplete() {
    document.querySelectorAll('[data-autocomplete]').forEach(input => {
        const target = input.dataset.autocomplete;
        const dropdown = document.getElementById(target);
        
        if (!dropdown) return;
        
        input.addEventListener('input', debounce(async function() {
            const query = this.value.trim();
            dropdown.innerHTML = '';
            
            if (query.length < 2) return;
            
            try {
                const res = await fetch(`/search.php?type=${this.dataset.type}&q=${encodeURIComponent(query)}`);
                const items = await res.json();
                
                items.forEach(item => {
                    const option = document.createElement('div');
                    option.className = 'autocomplete-item';
                    option.textContent = item.text;
                    option.addEventListener('click', () => {
                        input.value = item.text;
                        if (input.dataset.valueField) {
                            document.getElementById(input.dataset.valueField).value = item.id;
                        }
                        dropdown.innerHTML = '';
                    });
                    dropdown.appendChild(option);
                });
            } catch (error) {
                console.error('Autocomplete error:', error);
            }
        }, 300));
    });
}

function initFormValidations() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            this.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('error');
                    isValid = false;
                } else {
                    input.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
            }
        });
    });
}

function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}