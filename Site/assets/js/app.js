// File: /assets/js/app.js

document.addEventListener('DOMContentLoaded', function () {
    initAutoComplete();
    initFormValidations();
});

function initAutoComplete() {
    document.querySelectorAll('input[data-autocomplete]').forEach(input => {
        const listId = input.dataset.autocomplete;
        const type = input.dataset.type;
        const listElement = document.getElementById(listId);

        input.addEventListener('input', function () {
            const query = this.value.trim();
            if (query.length < 2) {
                listElement.style.display = 'none';
                return;
            }

            fetch(`/search.php?type=${type}&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    listElement.innerHTML = '';
                    if (!data.length) {
                        listElement.style.display = 'none';
                        return;
                    }

                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = item.text;
                        li.addEventListener('click', () => {
                            input.value = item.text;
                            listElement.style.display = 'none';
                        });
                        listElement.appendChild(li);
                    });

                    listElement.style.display = 'block';
                })
                .catch(err => {
                    console.error('Autocomplete error:', err);
                    listElement.style.display = 'none';
                });
        });

        // Hide suggestion list when clicking outside
        document.addEventListener('click', function (e) {
            if (!listElement.contains(e.target) && e.target !== input) {
                listElement.style.display = 'none';
            }
        });
    });
}

function initFormValidations() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('input-error');
                    valid = false;
                } else {
                    field.classList.remove('input-error');
                }
            });

            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
}
