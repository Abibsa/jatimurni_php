document.addEventListener('DOMContentLoaded', function() {
    // Periksa apakah elemen-elemen ada sebelum menambahkan event listener
    const roleSelect = document.getElementById('role');
    const customSelect = document.querySelector('.select-selected');
    const selectItems = document.querySelector('.select-items');

    if (customSelect && selectItems) {
        // Menampilkan pilihan saat customSelect diklik
        customSelect.addEventListener('click', function() {
            selectItems.classList.toggle('select-hide');
        });

        // Menangani klik pada item dropdown
        selectItems.querySelectorAll('div').forEach(item => {
            item.addEventListener('click', function() {
                customSelect.innerHTML = this.innerHTML;
                if (roleSelect) {
                    roleSelect.value = this.getAttribute('data-value');
                }
                selectItems.classList.add('select-hide');
            });
        });

        // Menutup dropdown jika klik di luar
        document.addEventListener('click', function(e) {
            if (!customSelect.contains(e.target) && !selectItems.contains(e.target)) {
                selectItems.classList.add('select-hide');
            }
        });
    }

    // Tambahkan fungsi untuk toggle password visibility
    const togglePassword = document.querySelectorAll('.toggle-password');
    togglePassword.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            this.classList.toggle('active');
        });
    });
}); 