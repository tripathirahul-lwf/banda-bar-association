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

    // 2. Login Form Validation
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            let isValid = true;
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            
            resetErrors(loginForm);
            
            if (!username.value.trim()) {
                showError(username, 'यूज़रनेम दर्ज करें। (Username is required.)');
                isValid = false;
            }
            
            if (!password.value.trim()) {
                showError(password, 'पासवर्ड दर्ज करें। (Password is required.)');
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
        feedback.className = 'invalid-feedback fw-medium font-size-xs mt-1';
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

    // 3. Password Visibility Toggle (Feature Enhancement)
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
            if (passwordInput && icon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            }
        });
    }

    // 4. Dashboard Sidebar Collapsing Toggle (Requirement 55)
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const sidebar = document.getElementById('dashboardSidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        });
    }
});
