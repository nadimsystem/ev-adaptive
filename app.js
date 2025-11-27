document.addEventListener("DOMContentLoaded", function() {

    // --- 1. ANIMASI FADE-IN SAAT SCROLL (BARU DITAMBAHKAN) ---
    // Ini akan memicu class '.show' pada elemen '.hidden' saat terlihat
    (function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                }
                // Opsional: Hapus baris di bawah jika Anda ingin animasi terjadi hanya sekali
                // else {
                //     entry.target.classList.remove('show');
                // }
            });
        });

        const hiddenElements = document.querySelectorAll('.hidden');
        if (hiddenElements.length > 0) {
            hiddenElements.forEach((el) => observer.observe(el));
        }
    })();

    // --- 2. LOGIKA SCROLLSPY NAVIGASI ---
    var scrollSpyEl = document.querySelector('[data-bs-spy="scroll"]');
    if (scrollSpyEl) {
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '.navbar',
            offset: 90 // sesuaikan jika perlu
        });
    }

    function updateActiveBold() {
        document.querySelectorAll('.navbar .nav-link').forEach(function(link) {
            if (link.classList.contains('active')) {
                link.classList.add('fw-bold');
            } else {
                link.classList.remove('fw-bold');
            }
        });
    }

    if (document.body.dataset.bsSpy === 'scroll') {
        updateActiveBold();
        document.body.addEventListener('activate.bs.scrollspy', updateActiveBold);
    }

    // --- 3. NAVBAR BACKGROUND SAAT SCROLL ---
    (function() {
        const nav = document.querySelector('.navbar');
        if (nav) {
            const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 50);
            document.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    })();

    // --- 4. COUNTERS ANGKA (ANIMASI HITUNG) ---
    (function() {
        const counters = document.querySelectorAll('.impact-num');
        if (counters.length > 0) {
            const options = { root: null, rootMargin: '0px', threshold: 0.4 };
            const animateCount = (el) => {
                const target = +el.getAttribute('data-target') || +el.textContent || 0;
                const duration = 1800;
                const start = 0; // Memastikan hitungan MAJU dari 0
                const startTime = performance.now();
                const step = (now) => {
                    const progress = Math.min((now - startTime) / duration, 1);
                    el.textContent = Math.floor(progress * (target - start) + start).toLocaleString();
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            };
            if ('IntersectionObserver' in window) {
                const io = new IntersectionObserver((entries, obs) => {
                    entries.forEach(e => {
                        if (e.isIntersecting) {
                            animateCount(e.target);
                            obs.unobserve(e.target);
                        }
                    });
                }, options);
                counters.forEach(c => io.observe(c));
            } else {
                counters.forEach(c => animateCount(c));
            }
        }
    })();

    // --- 5. LOGIKA FORM KONTAK ---
    (function() {
        const form = document.getElementById('contactForm');
        const alertBox = document.getElementById('formAlert');
        if (form && alertBox) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const name = form.name.value.trim();
                const email = form.email.value.trim();
                const message = form.message.value.trim();
                if (!name || !email || !message) {
                    alertBox.style.display = 'block';
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = 'Please fill all fields before submitting.';
                    return;
                }
                alertBox.style.display = 'block';
                alertBox.className = 'alert alert-success';
                alertBox.textContent = 'Thanks! Your message has been received. We will reply to your email soon.';
                form.reset();
                setTimeout(() => { alertBox.style.display = 'none'; }, 5000);
            });
        }
    })();

    // --- 6. LOGIKA FORM NEWSLETTER ---
    (function() {
        const nf = document.getElementById('newsletterForm');
        if (nf) {
            nf.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = document.getElementById('newsletterEmail').value.trim();
                if (!email) {
                    alert('Please provide an email address.');
                    return;
                }
                alert('Thanks for subscribing — this demo does not connect to an email service.');
                nf.reset();
            });
        }
    })();

    // --- 7. LOGIKA AKSESIBILITAS (TAB) ---
    (function() {
        function handleFirstTab(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('user-is-tabbing');
                window.removeEventListener('keydown', handleFirstTab);
            }
        }
        window.addEventListener('keydown', handleFirstTab);
    })();

    // --- 8. SEMUA LOGIKA DONASI (Dipindahkan ke dalam wrapper utama) ---

    // 8.1: Logika Widget Donasi
    const rates = { EUR: 1, USD: 1.1, IDR: 17000 };
    const donationOptionsEUR = [
        { amount: 25, label: "School supplies for one child for a month" },
        { amount: 50, label: "Textbooks for a small classroom" },
        { amount: 100, label: "One month of teacher training for one educator" },
        { amount: 250, label: "Learning materials for an entire classroom" }
    ];

    function formatCurrency(amount, currency) {
        if (currency === "IDR") return "Rp" + amount.toLocaleString('id-ID');
        if (currency === "USD") return "$" + amount.toLocaleString('en-US');
        return "€" + amount.toLocaleString('de-DE');
    }

    function updateDonationOptions(currency) {
        const options = document.querySelectorAll('.donation-option');
        options.forEach((option, idx) => {
            if (option.querySelector('input')) return;
            if (idx >= donationOptionsEUR.length) return;
            const data = donationOptionsEUR[idx];
            let converted = data.amount;
            if (currency === "USD") converted = Math.round(data.amount * rates.USD);
            else if (currency === "IDR") converted = Math.round(data.amount * rates.IDR / 5000) * 5000;
            option.setAttribute('data-amount', converted);
            option.querySelector('.fw-bold').textContent = formatCurrency(converted, currency);
        });
    }

    // 8.2: IIFE untuk Seleksi Opsi Donasi
    (function() {
        const options = document.querySelectorAll('.donation-option');
        const quickDonationAmount = document.getElementById('quickDonationAmount');
        options.forEach(option => {
            option.addEventListener('click', function() {
                options.forEach(opt => opt.classList.remove('bg-light', 'border-primary'));
                this.classList.add('bg-light', 'border-primary');
                const amount = this.getAttribute('data-amount');
                if (amount && quickDonationAmount) quickDonationAmount.value = amount;
                const customInput = document.getElementById('customDonation');
                if (customInput && this.getAttribute('data-amount')) customInput.value = '';
            });
        });

        const customInput = document.getElementById('customDonation');
        if (customInput) {
            function updateCustom() {
                if (customInput.value && quickDonationAmount) {
                    options.forEach(opt => opt.classList.remove('bg-light', 'border-primary'));
                    customInput.closest('.donation-option').classList.add('bg-light', 'border-primary');
                    quickDonationAmount.value = customInput.value;
                }
            }
            customInput.addEventListener('change', updateCustom);
            customInput.addEventListener('keyup', updateCustom);
        }

        const frequencyButtons = document.querySelectorAll('[data-frequency]');
        const quickDonationFrequency = document.getElementById('quickDonationFrequency');
        frequencyButtons.forEach(button => {
            button.addEventListener('click', function() {
                frequencyButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                if (quickDonationFrequency) quickDonationFrequency.value = this.getAttribute('data-frequency');
            });
        });
    })();

    // 8.3: IIFE untuk Seleksi Mata Uang
    (function() {
        const currencySelect = document.getElementById('currencySelect');
        const quickDonationAmount = document.getElementById('quickDonationAmount');
        const customInput = document.getElementById('customDonation');
        if (!currencySelect) return;

        currencySelect.addEventListener('change', function() {
            const currency = this.value;
            updateDonationOptions(currency);
            let matched = false;
            document.querySelectorAll('.donation-option').forEach(option => {
                if (option.classList.contains('bg-light') && option.getAttribute('data-amount')) {
                    quickDonationAmount.value = option.getAttribute('data-amount');
                    matched = true;
                }
            });
            if (!matched && customInput && customInput.value) {
                quickDonationAmount.value = customInput.value;
            }
        });
        updateDonationOptions(currencySelect.value); // Panggil saat dimuat
    })();

    // 8.4: IIFE untuk Logika QR Toast
    (function() {
        var thumb = document.getElementById('qrThumbnail');
        var toast = document.getElementById('qrToast'); // Asumsi #qrToast ada di HTML
        if (thumb && toast) {
            function showToast() { toast.style.opacity = '1'; }

            function hideToast() { toast.style.opacity = '0'; }
            thumb.addEventListener('mouseenter', showToast);
            thumb.addEventListener('mouseleave', hideToast);
            thumb.addEventListener('focus', showToast);
            thumb.addEventListener('blur', hideToast);
        }
    })();

    // --- 8.5: Logika Modal Donasi (Perbaikan) ---
    const donationModal = document.getElementById('donationDataModal');
    if (donationModal) {
        donationModal.addEventListener('show.bs.modal', function(event) {
            // Mendapatkan tombol mana yang diklik (desktop atau mobile)
            const button = event.relatedTarget;
            // Mencari section donasi terdekat dari tombol yang diklik
            const donateSection = button.closest('section[id^="donate"]');

            if (!donateSection) {
                console.error("Elemen section donasi tidak ditemukan!");
                event.preventDefault(); // Menghentikan modal jika terjadi error
                return;
            }

            // Mencari elemen form HANYA di dalam section yang aktif tersebut
            const programEl = donateSection.querySelector('select[id="programSelect"]');
            const amountEl = donateSection.querySelector('input[id="quickDonationAmount"]');
            const currencyEl = donateSection.querySelector('select[id="currencySelect"]');
            const frequencyEl = donateSection.querySelector('select[id="quickDonationFrequency"]');

            // Mengambil nilai dari elemen yang ditemukan
            const selectedProgram = (programEl && programEl.value) ? programEl.value : '';
            const selectedAmount = amountEl ? amountEl.value : '0';
            const selectedCurrencyText = currencyEl ? currencyEl.options[currencyEl.selectedIndex].text : '';
            const selectedCurrencyValue = currencyEl ? currencyEl.value : 'EUR';
            const selectedFrequency = frequencyEl ? frequencyEl.value : 'one-time';

            if (!selectedProgram && programEl && programEl.tagName === 'SELECT') {
                alert('Please select a program to support first!');
                event.preventDefault();
                return;
            }

            // Memasukkan data ke dalam modal (bagian ini tidak berubah)
            const summaryPreview = donationModal.querySelector('#donationSummaryPreview');
            const modalProgramInput = donationModal.querySelector('#modalProgramValue');
            const modalAmountInput = donationModal.querySelector('#modalAmountValue');
            const modalCurrencyInput = donationModal.querySelector('#modalCurrencyValue');
            const modalFrequencyInput = donationModal.querySelector('#modalFrequencyValue');

            if (summaryPreview) {
                summaryPreview.innerHTML = `
                <h5 class="fw-bold mb-0">Donation Summary</h5>
                <p class="mb-0 mt-2">
                    <span class="fs-4 fw-bold text-bmw-coral">${selectedCurrencyText} ${selectedAmount}</span>
                    <span class="text-muted">(${selectedFrequency})</span>
                </p>
                <p class="mb-0"><strong>Supporting:</strong> ${selectedProgram}</p>
            `;
            }
            if (modalProgramInput) modalProgramInput.value = selectedProgram;
            if (modalAmountInput) modalAmountInput.value = selectedAmount;
            if (modalCurrencyInput) modalCurrencyInput.value = selectedCurrencyValue;
            if (modalFrequencyInput) modalFrequencyInput.value = selectedFrequency;
        });
    }

});
// === AKHIR DARI WRAPPER DOMCONTENTLOADED TUNGGAL ===