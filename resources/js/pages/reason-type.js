import $ from '$';

document.querySelector('input[name="name"]').addEventListener('blur', function() {
    var slugField = document.getElementById('slug');
    if (!slugField.value && this.value) {
        slugField.value = this.value.toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]/g, '');
    }
});


window.deleteData = function (id) {
    if (confirm('Are you sure you want to delete this return reason type?')) {
        document.getElementById('delete-form-' + id).submit();
    }
}