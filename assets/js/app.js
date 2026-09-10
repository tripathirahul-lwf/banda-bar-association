/**
 * Vanilla JavaScript helpers and validation logic for District Bar Association, Banda
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Contact Form Client-Side Validation
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            let isValid = true;
            
            const name = document.getElementById('contact_name');
            const mobile = document.getElementById('contact_mobile');
            const email = document.getElementById('contact_email');
            const subject = document.getElementById('contact_subject');
            const message = document.getElementById('contact_message');
            
            // Basic reset
            resetErrors(contactForm);
            
            // Name check
            if (!name.value.trim()) {
                showError(name, 'नाम लिखना आवश्यक है। (Name is required.)');
                isValid = false;
            }
            
            // Mobile check (Indian format 10 digits)
            const mobileRegex = /^[6-9]\d{9}$/;
            if (!mobile.value.trim()) {
                showError(mobile, 'मोबाइल नंबर लिखना आवश्यक है। (Mobile is required.)');
                isValid = false;
            } else if (!mobileRegex.test(mobile.value.trim())) {
                showError(mobile, 'कृपया वैध 10-अंकीय मोबाइल नंबर दर्ज करें। (Enter valid 10-digit mobile.)');
                isValid = false;
            }
            
            // Email check (optional but must be valid if entered)
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email.value.trim() && !emailRegex.test(email.value.trim())) {
                showError(email, 'कृपया वैध ईमेल दर्ज करें। (Enter a valid email.)');
                isValid = false;
            }
            
            // Subject check
            if (!subject.value.trim()) {
                showError(subject, 'विषय लिखना आवश्यक है। (Subject is required.)');
                isValid = false;
            }
            
            // Message check
            if (!message.value.trim()) {
                showError(message, 'संदेश लिखना आवश्यक है। (Message is required.)');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }

    // 2. Login Form Validation & Mature UX
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            let isValid = true;
            const identifier = document.getElementById('identifier') || document.getElementById('username');
            const password = document.getElementById('password');
            
            resetErrors(loginForm);
            
            if (!identifier || !identifier.value.trim()) {
                if (identifier) {
                    showError(identifier, 'कृपया यूज़रनेम, सदस्यता संख्या या मोबाइल नंबर दर्ज करें। (Identifier required.)');
                }
                isValid = false;
            }
            
            if (!password || !password.value.trim()) {
                if (password) {
                    showError(password, 'कृपया पासवर्ड दर्ज करें। (Password required.)');
                }
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    }

    // Auxiliary error UI functions
    function showError(element, message) {
        element.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback fw-medium font-size-xs mt-1 font-hindi';
        feedback.textContent = message;
        element.parentNode.appendChild(feedback);
    }
    
    function resetErrors(form) {
        const invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(input => {
            input.classList.remove('is-invalid');
        });
        
        const feedbacks = form.querySelectorAll('.invalid-feedback');
        feedbacks.forEach(fb => {
            fb.remove();
        });
    }

    // 3. Password Visibility Toggle (FontAwesome & Bootstrap compatible)
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
            if (passwordInput && icon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye-slash', 'bi-eye-slash');
                    icon.classList.add('fa-eye', 'bi-eye');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye', 'bi-eye');
                    icon.classList.add('fa-eye-slash', 'bi-eye-slash');
                }
            }
        });
    }

    // 4. Caps Lock Warning Detector
    const passwordInputForCaps = document.getElementById('password');
    const capslockAlert = document.getElementById('capslockAlert');
    if (passwordInputForCaps && capslockAlert) {
        passwordInputForCaps.addEventListener('keyup', function(e) {
            if (e.getModifierState && e.getModifierState('CapsLock')) {
                capslockAlert.classList.remove('d-none');
            } else {
                capslockAlert.classList.add('d-none');
            }
        });
    }

    // 5. Global helper for Demo Credential 1-Click Auto-fill
    window.autoFillCredentials = function(ident, pass) {
        const identInput = document.getElementById('identifier') || document.getElementById('username');
        const passInput = document.getElementById('password');
        if (identInput && passInput) {
            identInput.value = ident;
            passInput.value = pass;
            identInput.classList.remove('is-invalid');
            passInput.classList.remove('is-invalid');
            identInput.classList.add('is-valid');
            passInput.classList.add('is-valid');
            setTimeout(() => {
                identInput.classList.remove('is-valid');
                passInput.classList.remove('is-valid');
            }, 1500);
            identInput.focus();
        }
    };

    // 6. Dashboard Sidebar Collapsing Toggle & Mobile Backdrop
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebar = document.getElementById('dashboardSidebar');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('collapsed');
            if (sidebarBackdrop && window.innerWidth <= 992) {
                if (!sidebar.classList.contains('collapsed')) {
                    sidebarBackdrop.classList.add('show');
                } else {
                    sidebarBackdrop.classList.remove('show');
                }
            }
        });
    }

    if (sidebarBackdrop && sidebar) {
        sidebarBackdrop.addEventListener('click', function() {
            sidebar.classList.add('collapsed');
            sidebarBackdrop.classList.remove('show');
        });
    }
});
