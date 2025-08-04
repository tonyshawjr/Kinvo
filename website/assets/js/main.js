/**
 * Kinvo Website JavaScript
 * Mobile-first, performance-optimized interactions
 */

(function() {
    'use strict';

    // ==========================================
    // Utility Functions
    // ==========================================
    
    /**
     * Debounce function to limit function calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function to limit function calls
     */
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Check if element is in viewport
     */
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // ==========================================
    // Navigation Functionality
    // ==========================================
    
    class Navigation {
        constructor() {
            this.nav = document.getElementById('navbar');
            this.navToggle = document.getElementById('navToggle');
            this.navMenu = document.getElementById('navMenu');
            this.navLinks = document.querySelectorAll('.nav-link');
            
            this.isMenuOpen = false;
            this.lastScrollY = window.scrollY;
            
            this.init();
        }

        init() {
            if (this.navToggle) {
                this.navToggle.addEventListener('click', this.toggleMenu.bind(this));
            }

            // Close menu when clicking nav links
            this.navLinks.forEach(link => {
                link.addEventListener('click', this.closeMenu.bind(this));
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (this.isMenuOpen && !this.nav.contains(e.target)) {
                    this.closeMenu();
                }
            });

            // Handle scroll effects
            window.addEventListener('scroll', throttle(this.handleScroll.bind(this), 16));

            // Handle smooth scrolling for anchor links
            this.initSmoothScrolling();
        }

        toggleMenu() {
            this.isMenuOpen = !this.isMenuOpen;
            
            this.navToggle.setAttribute('aria-expanded', this.isMenuOpen);
            this.navMenu.classList.toggle('nav-menu-open', this.isMenuOpen);
            
            // Prevent body scroll when menu is open
            document.body.style.overflow = this.isMenuOpen ? 'hidden' : '';
            
            // Animate hamburger icon
            this.animateToggleIcon();
        }

        closeMenu() {
            this.isMenuOpen = false;
            this.navToggle.setAttribute('aria-expanded', false);
            this.navMenu.classList.remove('nav-menu-open');
            document.body.style.overflow = '';
            this.animateToggleIcon();
        }

        animateToggleIcon() {
            const spans = this.navToggle.querySelectorAll('span');
            if (this.isMenuOpen) {
                spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                spans[1].style.opacity = '0';
                spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
            } else {
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
            }
        }

        handleScroll() {
            const currentScrollY = window.scrollY;
            
            // Add/remove scrolled class based on scroll position
            if (currentScrollY > 20) {
                this.nav.classList.add('nav-scrolled');
            } else {
                this.nav.classList.remove('nav-scrolled');
            }
            
            // Hide/show nav on scroll (mobile)
            if (window.innerWidth < 768) {
                if (currentScrollY > this.lastScrollY && currentScrollY > 100) {
                    this.nav.style.transform = 'translateY(-100%)';
                } else {
                    this.nav.style.transform = 'translateY(0)';
                }
            }
            
            this.lastScrollY = currentScrollY;
        }

        initSmoothScrolling() {
            // Handle smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const href = anchor.getAttribute('href');
                    if (href === '#') return;
                    
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        const navHeight = this.nav.offsetHeight;
                        const targetPosition = target.offsetTop - navHeight - 20;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                        
                        this.closeMenu();
                    }
                });
            });
        }
    }

    // ==========================================
    // Delightful Interactions Controller
    // ==========================================
    
    class DelightController {
        constructor() {
            this.konamiCode = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65]; // Up Up Down Down Left Right Left Right B A
            this.konamiIndex = 0;
            this.scrollProgress = 0;
            this.init();
        }

        init() {
            this.createScrollProgress();
            this.initKinnyHelper();
            this.initKonamiCode();
            this.addButtonPersonality();
            this.initLoadingStates();
            this.createTooltips();
        }

        createScrollProgress() {
            const progressBar = document.createElement('div');
            progressBar.className = 'scroll-progress';
            document.body.appendChild(progressBar);

            window.addEventListener('scroll', throttle(() => {
                const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
                progressBar.style.width = scrolled + '%';
            }, 16));
        }

        initKinnyHelper() {
            // Show Kinny after user scrolls past hero
            const kinnyButton = document.createElement('div');
            kinnyButton.className = 'kinny-float';
            kinnyButton.innerHTML = `
                <svg viewBox="0 0 792 612">
                    <path class="st3" d="M280.7,187.8c9.3-5.8,18.9-11,28.5-15.5,9.7-4.5,20.5-7.8,29.8-12.4,8.2-4,15.8-9.8,23.7-14.3,29.4-16.9,58.9-33.8,89.8-47.9-9-8.7-17.8-16.2-29.2-21.6-30-14.2-75.3-12.9-107.8-.5-29.2,28.5-42.8,71.8-34.8,112.2Z"/>
                    <circle cx="370" cy="235" r="8" fill="#352b52"/>
                    <circle cx="450" cy="235" r="8" fill="#352b52"/>
                </svg>
            `;
            kinnyButton.title = 'Need help? Kinny is here!';
            document.body.appendChild(kinnyButton);

            // Show Kinny when user scrolls past hero
            window.addEventListener('scroll', throttle(() => {
                const heroHeight = document.querySelector('.hero')?.offsetHeight || 600;
                if (window.scrollY > heroHeight * 0.7) {
                    kinnyButton.classList.add('show');
                } else {
                    kinnyButton.classList.remove('show');
                }
            }, 100));

            // Kinny click interaction
            kinnyButton.addEventListener('click', () => {
                this.showKinnyMessage();
            });
        }

        showKinnyMessage() {
            const messages = [
                "Hey there! Need help getting started? I'm here for you! 👻",
                "Looking good! Your invoices are going to be so professional! 💼",
                "Pro tip: Most of our users see payments 67% faster! ⚡",
                "Questions? I've got answers! Check out our quick demo! 🎬",
                "You're doing great! Ready to transform your business? 🚀"
            ];
            
            const randomMessage = messages[Math.floor(Math.random() * messages.length)];
            this.showToast(randomMessage, 'kinny');
        }

        initKonamiCode() {
            document.addEventListener('keydown', (e) => {
                if (e.keyCode === this.konamiCode[this.konamiIndex]) {
                    this.konamiIndex++;
                    if (this.konamiIndex === this.konamiCode.length) {
                        this.activateKonamiMode();
                        this.konamiIndex = 0;
                    }
                } else {
                    this.konamiIndex = 0;
                }
            });
        }

        activateKonamiMode() {
            document.body.classList.add('konami-activated');
            
            const message = document.createElement('div');
            message.className = 'konami-message';
            message.innerHTML = `
                <h3>🎉 You found the secret!</h3>
                <p>Welcome to Kinvo's rainbow mode! 🌈</p>
                <p><em>"Even serious business owners need a little fun!"</em></p>
                <button class="btn btn-primary" onclick="this.parentElement.remove()">Got it!</button>
            `;
            document.body.appendChild(message);

            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (message.parentElement) {
                    message.remove();
                }
                document.body.classList.remove('konami-activated');
            }, 10000);
        }

        addButtonPersonality() {
            document.querySelectorAll('.btn').forEach(btn => {
                // Add subtle click feedback
                btn.addEventListener('click', (e) => {
                    if (!btn.classList.contains('btn-loading')) {
                        this.createClickRipple(e, btn);
                    }
                });

                // Add loading state for form submissions
                if (btn.type === 'submit') {
                    btn.form?.addEventListener('submit', () => {
                        this.setButtonLoading(btn);
                    });
                }
            });
        }

        createClickRipple(event, button) {
            const ripple = document.createElement('span');
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background-color: rgba(255, 255, 255, 0.4);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;

            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        }

        setButtonLoading(button, isLoading = true) {
            if (isLoading) {
                button.classList.add('btn-loading');
                button.disabled = true;
            } else {
                button.classList.remove('btn-loading');
                button.disabled = false;
            }
        }

        initLoadingStates() {
            // Add encouraging loading messages
            const loadingMessages = [
                'Polishing your invoice...',
                'Making it professional...',
                'Adding the finishing touches...',
                'Almost ready...'
            ];

            document.querySelectorAll('.btn-loading').forEach(btn => {
                let messageIndex = 0;
                const messageInterval = setInterval(() => {
                    if (btn.classList.contains('btn-loading')) {
                        btn.setAttribute('data-message', loadingMessages[messageIndex % loadingMessages.length]);
                        messageIndex++;
                    } else {
                        clearInterval(messageInterval);
                    }
                }, 1500);
            });
        }

        createTooltips() {
            // Add helpful tooltips to key elements
            const tooltips = {
                '.proof-stats .stat': 'Real results from real service professionals like you!',
                '.pricing-featured': 'Most popular choice for growing businesses',
                '.feature-icon': 'Tap to see this feature in action'
            };

            Object.entries(tooltips).forEach(([selector, text]) => {
                document.querySelectorAll(selector).forEach(el => {
                    el.title = text;
                });
            });
        }

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 100px;
                right: 20px;
                background: var(--color-white);
                padding: 1rem 1.5rem;
                border-radius: 0.75rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                border-left: 4px solid var(--color-primary);
                max-width: 300px;
                z-index: 9999;
                animation: slideInRight 0.3s ease-out;
                font-size: 0.875rem;
                line-height: 1.4;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        celebrateSuccess(element) {
            // Create confetti celebration
            const celebration = document.createElement('div');
            celebration.className = 'celebration';
            document.body.appendChild(celebration);

            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                celebration.appendChild(confetti);
            }

            setTimeout(() => celebration.remove(), 5000);
        }
    }

    // ==========================================
    // Form Handling with Personality
    // ==========================================
    
    class FormHandler {
        constructor() {
            this.signupForm = document.getElementById('signupForm');
            this.init();
        }

        init() {
            if (this.signupForm) {
                this.signupForm.addEventListener('submit', this.handleSignup.bind(this));
            }
        }

        async handleSignup(e) {
            e.preventDefault();
            
            const email = e.target.email.value;
            const submitButton = e.target.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            // Basic email validation with friendly messaging
            if (!this.isValidEmail(email)) {
                this.showFriendlyError(e.target.email, 'Let\'s make sure that email looks right! 📧');
                return;
            }
            
            // Show encouraging loading state
            submitButton.classList.add('btn-loading');
            submitButton.disabled = true;
            
            try {
                // Here you would typically send to your backend
                // For demo purposes, we'll simulate an API call
                await this.simulateSignup(email);
                
                // Success celebration!
                this.celebrateSignup();
                
                // Show success message before redirect
                this.showMessage('🎉 Welcome aboard! Setting up your professional workspace...', 'success');
                
                // Redirect after a moment
                setTimeout(() => {
                    window.location.href = '../admin/register.php?email=' + encodeURIComponent(email);
                }, 2000);
                
            } catch (error) {
                this.showFriendlyError(null, 'Oops! Something hiccupped. Mind giving it another try? 🤔');
                submitButton.classList.remove('btn-loading');
                submitButton.disabled = false;
            }
        }

        celebrateSignup() {
            // Create celebration effect
            if (window.delightController) {
                window.delightController.celebrateSuccess();
            }

            // Play a subtle success sound if available
            this.playSuccessSound();
        }

        playSuccessSound() {
            // Create a subtle success sound using Web Audio API
            if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
                try {
                    const AudioCtx = AudioContext || webkitAudioContext;
                    const audioContext = new AudioCtx();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                    oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
                    
                    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.3);
                } catch (e) {
                    // Silent fail if audio context isn't available
                }
            }
        }

        showFriendlyError(inputElement, message) {
            if (inputElement) {
                inputElement.classList.add('input-error');
                setTimeout(() => inputElement.classList.remove('input-error'), 3000);
            }
            this.showMessage(message, 'error');
        }

        async simulateSignup(email) {
            // Simulate API delay
            return new Promise((resolve, reject) => {
                setTimeout(() => {
                    // Simulate random success/failure for demo
                    if (Math.random() > 0.1) {
                        resolve({ success: true });
                    } else {
                        reject(new Error('Simulated error'));
                    }
                }, 1500);
            });
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        showMessage(message, type = 'info') {
            // Remove existing messages
            const existingMessage = document.querySelector('.form-message');
            if (existingMessage) {
                existingMessage.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => existingMessage.remove(), 300);
            }
            
            // Create new message with personality
            const messageEl = document.createElement('div');
            messageEl.className = `${type === 'success' ? 'success-message' : 'error-message'}`;
            messageEl.innerHTML = message;
            
            // Insert after form with animation
            setTimeout(() => {
                this.signupForm.insertAdjacentElement('afterend', messageEl);
            }, existingMessage ? 300 : 0);
            
            // Auto-hide after appropriate time
            const hideDelay = type === 'success' ? 8000 : 6000;
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.style.animation = 'slideOutRight 0.3s ease-in';
                    setTimeout(() => messageEl.remove(), 300);
                }
            }, hideDelay);
        }
    }

    // ==========================================
    // Animation & Intersection Observer
    // ==========================================
    
    class AnimationController {
        constructor() {
            this.observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            this.init();
        }

        init() {
            // Only run animations if user hasn't requested reduced motion
            if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.initScrollAnimations();
                this.initCounterAnimations();
            }

            // Add loading animation classes
            this.initLoadAnimations();
        }

        initScrollAnimations() {
            const animatedElements = document.querySelectorAll([
                '.feature-card',
                '.pricing-card',
                '.proof-item',
                '.problem-item'
            ].join(','));

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                        observer.unobserve(entry.target);
                    }
                });
            }, this.observerOptions);

            animatedElements.forEach(el => {
                el.classList.add('animate-on-scroll');
                observer.observe(el);
            });
        }

        initCounterAnimations() {
            const counters = document.querySelectorAll('.stat-number');
            
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animateCounter(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            }, this.observerOptions);

            counters.forEach(counter => {
                counterObserver.observe(counter);
            });
        }

        animateCounter(element) {
            const target = parseInt(element.dataset.target) || parseInt(element.textContent.replace(/[^\d]/g, ''));
            const duration = 2000;
            const increment = target / (duration / 16);
            let current = 0;
            const originalText = element.textContent;

            element.classList.add('counting');

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = originalText.replace(/\d+/, target);
                    element.classList.remove('counting');
                    clearInterval(timer);
                } else {
                    element.textContent = originalText.replace(/\d+/, Math.floor(current));
                }
            }, 16);
        }

        initLoadAnimations() {
            // Add staggered animation delays to grid items
            document.querySelectorAll('.features-grid .feature-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 100}ms`;
            });

            document.querySelectorAll('.pricing-grid .pricing-card').forEach((card, index) => {
                card.style.animationDelay = `${index * 150}ms`;
            });
        }
    }

    // ==========================================
    // Performance Optimizations
    // ==========================================
    
    class PerformanceOptimizer {
        constructor() {
            this.init();
        }

        init() {
            this.lazyLoadImages();
            this.preloadCriticalResources();
        }

        lazyLoadImages() {
            const images = document.querySelectorAll('img[data-src]');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                images.forEach(img => imageObserver.observe(img));
            } else {
                // Fallback for browsers without IntersectionObserver
                images.forEach(img => {
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                });
            }
        }

        preloadCriticalResources() {
            // Preload hero image if not already loaded
            const heroImage = document.querySelector('.device-image');
            if (heroImage && heroImage.dataset.preload) {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.as = 'image';
                link.href = heroImage.src;
                document.head.appendChild(link);
            }
        }
    }

    // ==========================================
    // Analytics & Tracking
    // ==========================================
    
    class Analytics {
        constructor() {
            this.events = [];
            this.init();
        }

        init() {
            this.trackButtonClicks();
            this.trackFormSubmissions();
            this.trackScrollDepth();
        }

        trackButtonClicks() {
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.trackEvent('button_click', {
                        button_text: btn.textContent.trim(),
                        button_class: btn.className,
                        page_url: window.location.href
                    });
                });
            });
        }

        trackFormSubmissions() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', (e) => {
                    this.trackEvent('form_submit', {
                        form_id: form.id,
                        page_url: window.location.href
                    });
                });
            });
        }

        trackScrollDepth() {
            let maxScroll = 0;
            const trackingPoints = [25, 50, 75, 90];
            
            window.addEventListener('scroll', throttle(() => {
                const scrollPercent = Math.round(
                    (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100
                );
                
                if (scrollPercent > maxScroll) {
                    maxScroll = scrollPercent;
                    
                    trackingPoints.forEach(point => {
                        if (scrollPercent >= point && !this.events.includes(`scroll_${point}`)) {
                            this.trackEvent('scroll_depth', {
                                depth: point,
                                page_url: window.location.href
                            });
                            this.events.push(`scroll_${point}`);
                        }
                    });
                }
            }, 500));
        }

        trackEvent(eventName, properties = {}) {
            // Console log for development
            console.log('Analytics Event:', eventName, properties);
            
            // Here you would send to your analytics service
            // Example: Google Analytics, Mixpanel, etc.
            if (typeof gtag !== 'undefined') {
                gtag('event', eventName, properties);
            }
        }
    }

    // ==========================================
    // DOM Ready & Initialization
    // ==========================================
    
    function initializeApp() {
        // Initialize all components
        new Navigation();
        new FormHandler();
        new AnimationController();
        new PerformanceOptimizer();
        new Analytics();
        
        // Initialize delightful interactions
        window.delightController = new DelightController();

        // Add loaded class for any CSS animations
        document.body.classList.add('loaded');

        console.log('Kinvo website initialized successfully');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeApp);
    } else {
        initializeApp();
    }

    // ==========================================
    // CSS Animation Classes (Added via JS)
    // ==========================================
    
    // Add CSS for scroll animations, performance optimizations, and delightful interactions
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @keyframes particleFloat {
            0% {
                transform: translateX(0) translateY(0) rotate(0deg);
                opacity: 0.6;
            }
            50% {
                opacity: 0.3;
            }
            100% {
                transform: translateX(-200px) translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
        
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        /* Critical CSS for above-the-fold content */
        .hero {
            contain: layout style;
        }
        
        /* Performance optimizations */
        .feature-card,
        .pricing-card,
        .testimonial-card {
            contain: layout style paint;
            will-change: transform;
        }
        
        .feature-card:not(:hover),
        .pricing-card:not(:hover),
        .testimonial-card:not(:hover) {
            will-change: auto;
        }
        
        .animate-in {
            opacity: 1;
            transform: translateY(0);
        }
        
        .form-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .form-message-error {
            background-color: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        
        .form-message-success {
            background-color: #F0FDF4;
            color: #16A34A;
            border: 1px solid #BBF7D0;
        }
        
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Mobile menu styles */
        @media (max-width: 767px) {
            .nav-menu {
                position: fixed;
                top: 72px;
                left: 0;
                right: 0;
                background-color: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(20px);
                padding: 2rem;
                transform: translateY(-100%);
                transition: transform 0.3s ease-in-out;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
            
            .nav-menu-open {
                transform: translateY(0);
            }
            
            .nav-menu a {
                display: block;
                padding: 1rem 0;
                border-bottom: 1px solid #E5E7EB;
                font-size: 1.125rem;
            }
            
            .nav-menu .btn {
                margin-top: 1rem;
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Navigation scroll effects */
        .nav-scrolled {
            background-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        /* Toast notifications */
        .toast {
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .animate-on-scroll {
                transform: translateY(20px);
            }
        }
    `;
    document.head.appendChild(style);

    // ==========================================
    // Service Worker Registration (PWA)
    // ==========================================
    
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/website/assets/sw.js')
                .then(registration => {
                    console.log('ServiceWorker registration successful');
                })
                .catch(error => {
                    console.log('ServiceWorker registration failed');
                });
        });
    }

    // ==========================================
    // Performance Monitoring
    // ==========================================
    
    function measurePerformance() {
        if ('performance' in window) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                    const domContentLoaded = perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart;
                    
                    console.log('Performance Metrics:', {
                        pageLoadTime: loadTime + 'ms',
                        domContentLoaded: domContentLoaded + 'ms',
                        firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime + 'ms'
                    });
                    
                    // Track performance metrics for analytics
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'page_performance', {
                            'page_load_time': Math.round(loadTime),
                            'dom_content_loaded': Math.round(domContentLoaded)
                        });
                    }
                }, 0);
            });
        }
    }
    
    measurePerformance();

    // ==========================================
    // Additional Delightful Features
    // ==========================================
    
    // Add some personality to form interactions
    document.querySelectorAll('input[type="email"]').forEach(input => {
        let encouragementTimeout;
        
        input.addEventListener('focus', () => {
            // Show encouraging placeholder after a moment
            encouragementTimeout = setTimeout(() => {
                if (input.placeholder === 'your.email@company.com' && !input.value) {
                    input.placeholder = 'e.g., john@smithplumbing.com';
                }
            }, 2000);
        });
        
        input.addEventListener('blur', () => {
            clearTimeout(encouragementTimeout);
            if (input.placeholder === 'e.g., john@smithplumbing.com') {
                input.placeholder = 'your.email@company.com';
            }
        });
        
        // Add real-time validation feedback
        input.addEventListener('input', debounce((e) => {
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.target.value);
            if (e.target.value.length > 3) {
                if (isValid) {
                    e.target.style.borderColor = 'var(--color-success)';
                    e.target.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
                } else {
                    e.target.style.borderColor = 'var(--color-warning)';
                    e.target.style.boxShadow = '0 0 0 3px rgba(245, 158, 11, 0.1)';
                }
            } else {
                e.target.style.borderColor = '';
                e.target.style.boxShadow = '';
            }
        }, 300));
    });
    
    // Add encouraging messages for long page visits
    let visitDuration = 0;
    const visitTimer = setInterval(() => {
        visitDuration += 1000;
        
        // After 30 seconds, show a gentle nudge
        if (visitDuration === 30000 && window.delightController) {
            window.delightController.showToast(
                "Taking your time? That's smart! Good businesses are built on careful decisions. 🤔"
            );
        }
        
        // After 2 minutes, offer help
        if (visitDuration === 120000 && window.delightController) {
            window.delightController.showToast(
                "Still here? We love thorough researchers! Need a quick demo? 🎬"
            );
        }
    }, 1000);
    
    // Clean up timer when user leaves
    window.addEventListener('beforeunload', () => {
        clearInterval(visitTimer);
    });
    
    // Add subtle particle effect on scroll for visual interest
    let particles = [];
    
    function createParticle() {
        if (particles.length < 3 && Math.random() > 0.98) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: 4px;
                height: 4px;
                background: var(--color-primary);
                border-radius: 50%;
                pointer-events: none;
                z-index: 1;
                opacity: 0.6;
                right: ${Math.random() * 100}px;
                top: ${Math.random() * window.innerHeight}px;
                animation: particleFloat 8s linear forwards;
            `;
            
            document.body.appendChild(particle);
            particles.push(particle);
            
            setTimeout(() => {
                particle.remove();
                particles = particles.filter(p => p !== particle);
            }, 8000);
        }
    }
    
    // Only add particles on scroll if user hasn't opted for reduced motion
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window.addEventListener('scroll', throttle(() => {
            if (window.scrollY > 100) {
                createParticle();
            }
        }, 200));
    }

})();