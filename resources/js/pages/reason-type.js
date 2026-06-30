import $ from '$';

const nameField = document.querySelector('input[name="name"]');
const slugField = document.getElementById('slug');

if (nameField && slugField) {
    nameField.addEventListener('blur', function () {
        if (!slugField.value && this.value) {
            slugField.value = this.value.toLowerCase()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]/g, '');
        }
    });
}