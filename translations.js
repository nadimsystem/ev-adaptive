// File: translation.js

/**
 * Fungsi helper untuk mengatur Cookie browser
 */
function setCookie(key, value, expiryDays) {
    var date = new Date();
    date.setTime(date.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
    document.cookie = key + "=" + value + "; expires=" + date.toUTCString() + "; path=/";
}

/**
 * Fungsi helper untuk membaca Cookie browser
 */
function getCookie(key) {
    var name = key + "=";
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1);
        if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
    }
    return "";
}

/**
 * Fungsi utama untuk mengganti bahasa
 * @param {string} lang - Kode bahasa (en, de, id)
 */
function changeLanguage(lang) {
    // Set cookie Google Translate: /auto/target_lang atau /source/target
    // Kita set source 'en' karena base web dalam bahasa Inggris
    setCookie('googtrans', '/en/' + lang, 1);
    
    // Coba trigger dropdown Google jika elemennya sudah ada di DOM
    var googleSelect = document.querySelector("#google_translate_element select");
    if (googleSelect) {
        googleSelect.value = lang;
        var event = new Event('change');
        googleSelect.dispatchEvent(event);
    } else {
        // Jika dropdown belum siap, reload halaman agar cookie terbaca saat muat ulang
        location.reload();
    }

    // Update tampilan tombol bendera yang aktif
    updateActiveFlag(lang);
}

/**
 * Update visual tombol bendera (tambah class .active)
 */
function updateActiveFlag(lang) {
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-lang') === lang) {
            btn.classList.add('active');
        }
    });
}

/**
 * Fungsi Callback yang dipanggil otomatis oleh Script Google Translate
 * Nama fungsi ini harus cocok dengan parameter ?cb=... di URL script Google
 */
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en', // Bahasa asli website
        includedLanguages: 'en,de,id', // Bahasa yang diizinkan
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');

    // Pasang event listener ke semua tombol bendera kustom
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var lang = this.getAttribute('data-lang');
            changeLanguage(lang);
        });
    });

    // Cek cookie yang tersimpan untuk menentukan bendera mana yang aktif saat load
    var currentLang = getCookie('googtrans');
    var initialLang = 'en'; // Default bahasa
    
    // Format cookie google biasanya: "/en/id" -> kita ambil 'id'
    if (currentLang && currentLang !== 'null' && currentLang.includes('/')) {
        initialLang = currentLang.split('/')[2];
    }
    
    updateActiveFlag(initialLang);
}