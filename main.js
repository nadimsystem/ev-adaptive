// --- Skrip Google Translate tidak berubah ---
function setCookie(key, value, expiryDays) {
    let date = new Date();
    date.setTime(date.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
    document.cookie = key + "=" + value + "; expires=" + date.toUTCString() + "; path=/";
}

function getCookie(key) {
    let name = key + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
    }
    return "";
}

function changeLanguage(lang) {
    setCookie('googtrans', '/en/' + lang, 1);
    var googleSelect = document.querySelector("#google_translate_element select");
    if (googleSelect) {
        googleSelect.value = lang;
        var event = new Event('change');
        googleSelect.dispatchEvent(event);
    } else {
        location.reload();
    }
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-lang') === lang) {
            btn.classList.add('active');
        }
    });
}

function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,de,id',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var lang = this.getAttribute('data-lang');
            changeLanguage(lang);
        });
    });
    let currentLang = getCookie('googtrans');
    let initialLang = 'en';
    if (currentLang && currentLang !== 'null' && currentLang.includes('/')) {
        initialLang = currentLang.split('/')[2];
    }
    let activeBtn = document.querySelector('.lang-btn[data-lang="' + initialLang + '"]');
    if (activeBtn) {
        activeBtn.classList.add('active');
    } else {
        document.querySelector('.lang-btn[data-lang="en"]').classList.add('active');
    }
}

// --- Inisialisasi Aplikasi Vue.js ---
const {
    createApp
} = Vue;
createApp({
    data() {
        return {
            pageLoaded: false,
            page: {
                settings: {},
                programs: [],
                team: [],
                gallery: []
            },
            // TAMBAHKAN DUA BARIS DI BAWAH INI
            selectedImage: null,
            galleryModalInstance: null
        }
    },
    mounted() {
        fetch('administrator/api.php?action=get_homepage_data')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    this.page = data.data;
                }
            })
            .finally(() => {
                this.pageLoaded = true;
            });

        // TAMBAHKAN BARIS INI untuk menginisialisasi modal
        this.galleryModalInstance = new bootstrap.Modal(document.getElementById('galleryModal'));
    },
    // TAMBAHKAN BLOK METHODS BARU DI BAWAH INI
    methods: {
        openGalleryModal(image) {
            this.selectedImage = image; // Simpan data gambar yang diklik
            this.galleryModalInstance.show(); // Tampilkan modal
        }
    }

}).mount('#app');

// --- SKRIP UNTUK MENGAKTIFKAN FORMULIR DONASI, KONTAK, & BULETIN ---
document.addEventListener('DOMContentLoaded', function() {
    // Menangani Formulir Kontak
    const contactForm = document.getElementById('contactForm');
    const formAlert = document.getElementById('formAlert');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'submit_contact_form');
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

            fetch('administrator/api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    formAlert.style.display = 'block';
                    if (data.status === 'success') {
                        formAlert.className = 'alert alert-success';
                        formAlert.textContent = data.message;
                        contactForm.reset();
                    } else {
                        formAlert.className = 'alert alert-danger';
                        formAlert.textContent = data.message;
                    }
                })
                .catch(err => {
                    formAlert.style.display = 'block';
                    formAlert.className = 'alert alert-danger';
                    formAlert.textContent = 'A network error occurred.';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = 'Send Message';
                });
        });
    }

    // Menangani Formulir Buletin
    const newsletterForm = document.getElementById('newsletterForm');
    const newsletterAlert = document.getElementById('newsletterAlert');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'subscribe_newsletter');
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            fetch('administrator/api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    newsletterAlert.style.display = 'block';
                    if (data.status === 'success') {
                        newsletterAlert.className = 'text-success';
                        newsletterAlert.textContent = data.message;
                        newsletterForm.reset();
                    } else {
                        newsletterAlert.className = 'text-danger';
                        newsletterAlert.textContent = data.message;
                    }
                })
                .catch(err => {
                    newsletterAlert.style.display = 'block';
                    newsletterAlert.className = 'text-danger';
                    newsletterAlert.textContent = 'A network error occurred.';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
        });
    }

    // Menangani Formulir Donasi
    const donationForm = document.getElementById('mainDonationForm');
    if (donationForm) {
        donationForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_donation');
            formData.append('selected_program', document.getElementById('modalProgramValue').value);
            formData.append('selected_amount', document.getElementById('modalAmountValue').value);
            formData.append('selected_currency', document.getElementById('modalCurrencyValue').value);
            formData.append('selected_frequency', document.getElementById('modalFrequencyValue').value);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

            fetch('administrator/api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Success! ' + data.message);
                        const modal = bootstrap.Modal.getInstance(document.getElementById('donationDataModal'));
                        modal.hide();
                        donationForm.reset();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Submission error:', error);
                    alert('A network error occurred. Please try again.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
        });
    }
});