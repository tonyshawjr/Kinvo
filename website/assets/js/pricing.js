/**
 * Kinvo Pricing Page JavaScript
 * Interactive pricing calculator and plan comparison
 */

(function() {
    'use strict';

    // ==========================================
    // Pricing Toggle (Monthly/Annual)
    // ==========================================
    
    class PricingToggle {
        constructor() {
            this.toggle = document.getElementById('pricingToggle');
            this.priceElements = document.querySelectorAll('.price-amount');
            this.periodElements = document.querySelectorAll('.price-period');
            this.annualNotes = document.querySelectorAll('.pricing-annual-note');
            this.isAnnual = false;
            
            this.init();
        }

        init() {
            if (this.toggle) {
                this.toggle.addEventListener('click', this.handleToggle.bind(this));
            }
        }

        handleToggle() {
            this.isAnnual = !this.isAnnual;
            this.toggle.classList.toggle('active', this.isAnnual);
            
            // Update prices
            this.priceElements.forEach(element => {
                const monthly = element.dataset.monthly;
                const annual = element.dataset.annual;
                element.textContent = `$${this.isAnnual ? annual : monthly}`;
            });
            
            // Update periods
            this.periodElements.forEach(element => {
                element.textContent = this.isAnnual ? '/month' : '/month';
            });
            
            // Show/hide annual notes
            this.annualNotes.forEach(note => {
                note.style.display = this.isAnnual ? 'block' : 'none';
            });
        }
    }

    // ==========================================
    // ROI Calculator
    // ==========================================
    
    class ROICalculator {
        constructor() {
            this.monthlyInvoicesSlider = document.getElementById('monthlyInvoices');
            this.averageInvoiceSlider = document.getElementById('averageInvoice');
            this.paymentDelaySlider = document.getElementById('paymentDelay');
            
            this.invoiceValueDisplay = document.getElementById('invoiceValue');
            this.invoiceAmountDisplay = document.getElementById('invoiceAmountValue');
            this.delayValueDisplay = document.getElementById('delayValue');
            
            this.timeSavedDisplay = document.getElementById('timeSaved');
            this.fasterPaymentDisplay = document.getElementById('fasterPayment');
            this.monthlyValueDisplay = document.getElementById('monthlyValue');
            this.annualROIDisplay = document.getElementById('annualROI');
            
            this.init();
        }

        init() {
            if (this.monthlyInvoicesSlider) {
                this.monthlyInvoicesSlider.addEventListener('input', this.updateCalculation.bind(this));
                this.averageInvoiceSlider.addEventListener('input', this.updateCalculation.bind(this));
                this.paymentDelaySlider.addEventListener('input', this.updateCalculation.bind(this));
                
                // Initial calculation
                this.updateCalculation();
            }
        }

        updateCalculation() {
            const monthlyInvoices = parseInt(this.monthlyInvoicesSlider.value);
            const averageInvoice = parseInt(this.averageInvoiceSlider.value);
            const paymentDelay = parseInt(this.paymentDelaySlider.value);
            
            // Update slider displays
            this.invoiceValueDisplay.textContent = monthlyInvoices;
            this.invoiceAmountDisplay.textContent = `$${averageInvoice}`;
            this.delayValueDisplay.textContent = `${paymentDelay} days`;
            
            // Calculate savings
            const calculations = this.calculateSavings(monthlyInvoices, averageInvoice, paymentDelay);
            
            // Update displays with animation
            this.animateNumber(this.timeSavedDisplay, calculations.timeSaved);
            this.animateNumber(this.fasterPaymentDisplay, calculations.fasterPayment);
            this.animateValue(this.monthlyValueDisplay, calculations.monthlyValue, '$', '');
            this.animateValue(this.annualROIDisplay, calculations.annualROI, '', '%');
        }

        calculateSavings(monthlyInvoices, averageInvoice, currentDelay) {
            // Time saved calculations
            const timePerChase = 15; // minutes per chase call/email
            const chasesPerInvoice = Math.max(1, Math.floor(currentDelay / 15)); // More chases for longer delays
            const timeSavedMinutes = monthlyInvoices * chasesPerInvoice * timePerChase;
            const timeSaved = Math.round(timeSavedMinutes / 60); // Convert to hours
            
            // Payment speed improvement
            const kinvoAverageDelay = 7; // days with Kinvo
            const fasterPayment = Math.max(0, currentDelay - kinvoAverageDelay);
            
            // Monthly value calculation
            const hourlyRate = 50; // Assumed hourly rate for service professionals
            const timeSavingsValue = timeSaved * hourlyRate;
            
            // Cash flow improvement (simplified calculation)
            const monthlyRevenue = monthlyInvoices * averageInvoice;
            const cashFlowImprovement = (monthlyRevenue * (fasterPayment / 30)) * 0.01; // 1% per day improvement
            
            const monthlyValue = timeSavingsValue + cashFlowImprovement;
            
            // Annual ROI calculation (vs Professional plan at $39/month)
            const annualPlanCost = 39 * 12; // $468
            const annualValue = monthlyValue * 12;
            const annualROI = Math.round(((annualValue - annualPlanCost) / annualPlanCost) * 100);
            
            return {
                timeSaved: Math.max(1, timeSaved),
                fasterPayment: fasterPayment,
                monthlyValue: Math.round(monthlyValue),
                annualROI: Math.max(100, annualROI)
            };
        }

        animateNumber(element, targetValue) {
            const currentValue = parseInt(element.textContent) || 0;
            const increment = (targetValue - currentValue) / 20;
            let current = currentValue;
            
            const animation = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                    element.textContent = targetValue;
                    clearInterval(animation);
                } else {
                    element.textContent = Math.round(current);
                }
            }, 50);
        }

        animateValue(element, targetValue, prefix = '', suffix = '') {
            const currentText = element.textContent.replace(/[^0-9]/g, '');
            const currentValue = parseInt(currentText) || 0;
            const increment = (targetValue - currentValue) / 20;
            let current = currentValue;
            
            const animation = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= targetValue) || (increment < 0 && current <= targetValue)) {
                    element.textContent = `${prefix}${targetValue.toLocaleString()}${suffix}`;
                    clearInterval(animation);
                } else {
                    element.textContent = `${prefix}${Math.round(current).toLocaleString()}${suffix}`;
                }
            }, 50);
        }
    }

    // ==========================================
    // FAQ Accordion
    // ==========================================
    
    class FAQAccordion {
        constructor() {
            this.faqItems = document.querySelectorAll('[data-faq]');
            this.init();
        }

        init() {
            this.faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                const answer = item.querySelector('.faq-answer');
                
                question.addEventListener('click', () => {
                    const isExpanded = question.getAttribute('aria-expanded') === 'true';
                    
                    // Close all other FAQs
                    this.faqItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            const otherQuestion = otherItem.querySelector('.faq-question');
                            const otherAnswer = otherItem.querySelector('.faq-answer');
                            otherQuestion.setAttribute('aria-expanded', 'false');
                            otherAnswer.style.maxHeight = '0px';
                        }
                    });
                    
                    // Toggle current FAQ
                    if (isExpanded) {
                        question.setAttribute('aria-expanded', 'false');
                        answer.style.maxHeight = '0px';
                    } else {
                        question.setAttribute('aria-expanded', 'true');
                        answer.style.maxHeight = answer.scrollHeight + 'px';
                    }
                });
            });
        }
    }

    // ==========================================
    // Plan Comparison Highlights
    // ==========================================
    
    class PlanComparison {
        constructor() {
            this.planHeaders = document.querySelectorAll('.plan-header');
            this.init();
        }

        init() {
            this.planHeaders.forEach(header => {
                header.addEventListener('mouseenter', this.highlightColumn.bind(this, header));
                header.addEventListener('mouseleave', this.removeHighlight.bind(this));
            });
        }

        highlightColumn(header) {
            const columnIndex = Array.from(header.parentNode.children).indexOf(header);
            const table = document.querySelector('.comparison-table-detailed');
            const rows = table.querySelectorAll('.feature-row, .table-footer');
            
            // Add highlight class to corresponding cells
            rows.forEach(row => {
                const cell = row.children[columnIndex];
                if (cell) {
                    cell.classList.add('column-highlight');
                }
            });
        }

        removeHighlight() {
            const highlightedCells = document.querySelectorAll('.column-highlight');
            highlightedCells.forEach(cell => {
                cell.classList.remove('column-highlight');
            });
        }
    }

    // ==========================================
    // Scroll-triggered Animations
    // ==========================================
    
    class ScrollAnimations {
        constructor() {
            this.observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            this.init();
        }

        init() {
            if ('IntersectionObserver' in window) {
                this.initCounterAnimations();
                this.initTableAnimations();
            }
        }

        initCounterAnimations() {
            const roiSection = document.querySelector('.roi-calculator');
            if (!roiSection) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Trigger ROI calculation animation
                        const calculator = new ROICalculator();
                        observer.unobserve(entry.target);
                    }
                });
            }, this.observerOptions);
            
            observer.observe(roiSection);
        }

        initTableAnimations() {
            const tableRows = document.querySelectorAll('.feature-row');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('animate-in');
                        }, index * 50);
                        observer.unobserve(entry.target);
                    }
                });
            }, this.observerOptions);
            
            tableRows.forEach(row => {
                row.classList.add('animate-on-scroll');
                observer.observe(row);
            });
        }
    }

    // ==========================================
    // Initialize Everything
    // ==========================================
    
    function initPricingPage() {
        new PricingToggle();
        new ROICalculator();
        new FAQAccordion();
        new PlanComparison();
        new ScrollAnimations();
        
        console.log('Pricing page initialized');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPricingPage);
    } else {
        initPricingPage();
    }

    // ==========================================
    // Additional CSS for Pricing Page
    // ==========================================
    
    const pricingStyles = `
        /* Pricing Toggle */
        .pricing-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .toggle-label {
            font-weight: 500;
            color: var(--color-gray-600);
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 32px;
            background-color: var(--color-gray-300);
            border-radius: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle-switch.active {
            background-color: var(--color-primary);
        }
        
        .toggle-slider {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 28px;
            height: 28px;
            background-color: white;
            border-radius: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .toggle-switch.active .toggle-slider {
            transform: translateX(28px);
        }
        
        .toggle-save {
            background-color: var(--color-primary);
            color: var(--color-secondary);
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* ROI Calculator */
        .roi-calculator {
            padding: 5rem 0;
            background: linear-gradient(135deg, var(--color-secondary) 0%, #443366 100%);
            color: white;
        }
        
        .calculator-content {
            display: grid;
            gap: 3rem;
            align-items: start;
        }
        
        @media (min-width: 1024px) {
            .calculator-content {
                grid-template-columns: 1fr 1fr;
                gap: 4rem;
            }
        }
        
        .calculator-inputs {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .input-group label {
            font-weight: 600;
            color: var(--color-primary);
            font-size: 1.125rem;
        }
        
        .range-input {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
            outline: none;
            cursor: pointer;
        }
        
        .range-input::-webkit-slider-thumb {
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 12px;
            background: var(--color-primary);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .range-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary);
            text-align: center;
            padding: 0.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .results-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--color-primary);
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .result-item {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .result-item.highlight {
            background: var(--color-primary);
            color: var(--color-secondary);
            border-color: var(--color-primary);
        }
        
        .result-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .result-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .result-note {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        /* Detailed Comparison Table */
        .comparison-table-detailed {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--color-gray-200);
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            background: linear-gradient(135deg, var(--color-secondary) 0%, var(--color-accent) 100%);
            color: white;
        }
        
        .header-cell {
            padding: 1.5rem 1rem;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.2);
        }
        
        .header-cell:last-child {
            border-right: none;
        }
        
        .plan-header h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .plan-price {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .plan-badge {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--color-primary);
            color: var(--color-secondary);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .featured {
            position: relative;
        }
        
        .table-section {
            border-bottom: 1px solid var(--color-gray-200);
        }
        
        .section-title-row {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            background: var(--color-gray-50);
        }
        
        .section-title-cell {
            padding: 1rem;
            font-weight: 700;
            color: var(--color-secondary);
            border-right: 1px solid var(--color-gray-200);
        }
        
        .feature-row {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            border-bottom: 1px solid var(--color-gray-100);
            transition: all 0.2s ease;
        }
        
        .feature-row:hover {
            background: var(--color-gray-50);
        }
        
        .feature-cell {
            padding: 1rem;
            font-weight: 500;
            color: var(--color-gray-800);
            border-right: 1px solid var(--color-gray-200);
        }
        
        .value-cell {
            padding: 1rem;
            text-align: center;
            border-right: 1px solid var(--color-gray-200);
            font-weight: 500;
        }
        
        .value-cell:last-child {
            border-right: none;
        }
        
        .table-footer {
            display: grid;
            grid-template-columns: 2fr repeat(3, 1fr);
            padding: 1.5rem 0;
            background: var(--color-gray-50);
        }
        
        .footer-cell {
            padding: 0 1rem;
            text-align: center;
        }
        
        .column-highlight {
            background: var(--color-primary) !important;
            color: var(--color-secondary) !important;
        }
        
        /* FAQ Styles */
        .faq-section {
            padding: 5rem 0;
            background: var(--color-white);
        }
        
        .faq-grid {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .faq-item {
            border-bottom: 1px solid var(--color-gray-200);
        }
        
        .faq-question {
            width: 100%;
            padding: 1.5rem 0;
            text-align: left;
            background: none;
            border: none;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-secondary);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-icon {
            width: 20px;
            height: 20px;
            transition: transform 0.3s ease;
        }
        
        .faq-question[aria-expanded="true"] .faq-icon {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .faq-answer p {
            padding: 0 0 1.5rem 0;
            color: var(--color-gray-600);
            line-height: 1.6;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table-header,
            .section-title-row,
            .feature-row,
            .table-footer {
                grid-template-columns: 2fr 1fr;
            }
            
            .header-cell:nth-child(3),
            .header-cell:nth-child(4),
            .value-cell:nth-child(3),
            .value-cell:nth-child(4),
            .footer-cell:nth-child(3),
            .footer-cell:nth-child(4) {
                display: none;
            }
            
            .results-grid {
                grid-template-columns: 1fr;
            }
            
            .calculator-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animation classes */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }
        
        .animate-in {
            opacity: 1;
            transform: translateY(0);
        }
    `;
    
    // Add styles to document
    const styleSheet = document.createElement('style');
    styleSheet.textContent = pricingStyles;
    document.head.appendChild(styleSheet);

})();