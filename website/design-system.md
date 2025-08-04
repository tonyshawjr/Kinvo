# Kinvo Design System

## Overview
The Kinvo design system is built for service professionals who need to get paid faster and look more professional. Every design decision prioritizes mobile usability, rapid development, and conversion optimization.

## Brand Foundation

### Brand Promise
"Get Paid Faster, Look More Professional"

### Brand Personality
- **Honest**: No BS, transparent, straightforward
- **Approachable**: Friendly, helpful, understanding
- **Professional**: Credible, trustworthy, competent

### Target Audience
Service professionals including:
- Handymen and contractors
- Cleaning services
- Lawn care professionals
- HVAC technicians
- Electricians and plumbers
- Small trade businesses

## Color System

### Primary Palette
```css
--color-primary: #C7DA39      /* Vibrant Green - CTAs, success states */
--color-primary-dark: #B0C532  /* Hover states */
--color-primary-light: #D4E356 /* Light backgrounds */
```

### Secondary Palette
```css
--color-secondary: #352B52     /* Deep Plum - Headers, text */
--color-secondary-dark: #2A2142 /* Dark backgrounds */
--color-secondary-light: #433562 /* Secondary text */
```

### Accent Palette
```css
--color-accent: #8876B6        /* Soft Lavender - Highlights */
--color-accent-dark: #7A6BA5   /* Accent hover states */
--color-accent-light: #9687C7  /* Light accent backgrounds */
```

### Semantic Colors
```css
--color-success: #10B981       /* Success messages, confirmations */
--color-warning: #F59E0B       /* Warnings, important info */
--color-error: #EF4444         /* Errors, destructive actions */
--color-info: #3B82F6          /* Information, helpful tips */
```

### Neutral Scale
```css
--color-white: #FFFFFF
--color-gray-50: #F9FAFB       /* Light backgrounds */
--color-gray-100: #F3F4F6      /* Card backgrounds */
--color-gray-200: #E5E7EB      /* Borders */
--color-gray-300: #D1D5DB      /* Disabled states */
--color-gray-400: #9CA3AF      /* Placeholder text */
--color-gray-500: #6B7280      /* Secondary text */
--color-gray-600: #4B5563      /* Body text */
--color-gray-700: #374151      /* Dark text */
--color-gray-800: #1F2937      /* Headings */
--color-gray-900: #111827      /* Primary text */
```

### Color Usage Guidelines

#### Primary Green (#C7DA39)
- **Use for**: Primary CTAs, success states, highlights
- **Avoid**: Large background areas, body text
- **Accessibility**: Provides excellent contrast on dark backgrounds

#### Deep Plum (#352B52)
- **Use for**: Headlines, primary text, dark backgrounds
- **Avoid**: CTA buttons (low contrast with green)
- **Accessibility**: AAA contrast ratio with white text

#### Soft Lavender (#8876B6)
- **Use for**: Accent elements, secondary highlights, gradients
- **Avoid**: Primary CTAs, critical actions
- **Accessibility**: Good contrast for secondary elements

## Typography

### Font Family
**Primary**: Manrope (Google Fonts)
- Modern, friendly, highly legible
- Excellent mobile readability
- Professional yet approachable
- Good character set for service industry terminology

**Fallback Stack**:
```css
font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
```

### Type Scale (Mobile-First)

#### Display Text
```css
/* Hero Headlines */
font-size: 2.25rem (36px)   /* Mobile */
font-size: 3rem (48px)      /* Desktop */
font-weight: 800 (Extra Bold)
line-height: 1.25 (Tight)
```

#### Headings
```css
/* H1 - Page Titles */
font-size: 1.875rem (30px)  /* Mobile */
font-size: 2.25rem (36px)   /* Desktop */
font-weight: 700 (Bold)
line-height: 1.25

/* H2 - Section Headers */
font-size: 1.5rem (24px)    /* Mobile */
font-size: 2rem (32px)      /* Desktop */
font-weight: 700
line-height: 1.33

/* H3 - Subsection Headers */
font-size: 1.25rem (20px)   /* Mobile */
font-size: 1.5rem (24px)    /* Desktop */
font-weight: 600 (Semi-bold)
line-height: 1.4
```

#### Body Text
```css
/* Large Body */
font-size: 1.125rem (18px)  /* Mobile */
font-size: 1.25rem (20px)   /* Desktop */
font-weight: 400
line-height: 1.75 (Relaxed)

/* Default Body */
font-size: 1rem (16px)
font-weight: 400
line-height: 1.5

/* Small Text */
font-size: 0.875rem (14px)
font-weight: 400
line-height: 1.43

/* Caption */
font-size: 0.75rem (12px)
font-weight: 400
line-height: 1.67
```

### Typography Usage

#### Headlines
- Use Extra Bold (800) for hero statements
- Use Bold (700) for section headers
- Keep headlines short and action-oriented
- Focus on benefits, not features

#### Body Text
- Use Regular (400) for readability
- Use Semi-bold (600) for emphasis within paragraphs
- Maintain 1.5+ line-height for accessibility
- Optimize for mobile reading

#### Labels & UI Text
- Use Medium (500) for form labels
- Use Semi-bold (600) for button text
- Use Regular (400) for input text
- Maintain consistent sizing across components

## Spacing System

### Base Unit: 4px
All spacing uses a 4px base unit for visual consistency and design system scalability.

```css
--space-1: 0.25rem (4px)     /* Tight spacing */
--space-2: 0.5rem (8px)      /* Default small */
--space-3: 0.75rem (12px)    /* Small-medium */
--space-4: 1rem (16px)       /* Default medium */
--space-5: 1.25rem (20px)    /* Medium-large */
--space-6: 1.5rem (24px)     /* Large */
--space-8: 2rem (32px)       /* Extra large */
--space-10: 2.5rem (40px)    /* Section spacing */
--space-12: 3rem (48px)      /* Large sections */
--space-16: 4rem (64px)      /* Major sections */
--space-20: 5rem (80px)      /* Page sections */
--space-24: 6rem (96px)      /* Hero spacing */
```

### Spacing Usage Guidelines

#### Component Spacing
- **Buttons**: 12px-16px padding (space-3 to space-4)
- **Cards**: 24px-32px padding (space-6 to space-8)
- **Forms**: 16px field spacing (space-4)
- **Navigation**: 16px-24px item spacing (space-4 to space-6)

#### Layout Spacing
- **Sections**: 64px-80px vertical spacing (space-16 to space-20)
- **Grids**: 24px-32px gap (space-6 to space-8)
- **Content**: 16px-24px vertical rhythm (space-4 to space-6)

#### Responsive Spacing
- Mobile: Use smaller spacing values (space-4 to space-12)
- Desktop: Use larger spacing values (space-8 to space-24)
- Scale spacing proportionally with screen size

## Layout System

### Container Widths
```css
--container-sm: 640px       /* Small screens */
--container-md: 768px       /* Medium screens */
--container-lg: 1024px      /* Large screens */
--container-xl: 1280px      /* Extra large screens */
--container-2xl: 1536px     /* Maximum width */
```

### Grid System
- **Mobile**: Single column with full-width components
- **Tablet**: 2-column grids for features and content
- **Desktop**: 3-4 column grids for optimal content display
- **Flexible**: CSS Grid with auto-fit for responsive behavior

### Breakpoints
```css
/* Mobile First Approach */
@media (min-width: 640px)   /* sm: Small tablets */
@media (min-width: 768px)   /* md: Large tablets */
@media (min-width: 1024px)  /* lg: Small desktops */
@media (min-width: 1280px)  /* xl: Large desktops */
@media (min-width: 1536px)  /* 2xl: Extra large */
```

## Component Library

### Buttons

#### Primary Button
```css
background: var(--color-primary)
color: var(--color-secondary)
padding: 12px 24px
border-radius: 8px
font-weight: 500
transition: all 150ms ease
```

#### Secondary Button
```css
background: var(--color-white)
color: var(--color-secondary)
border: 1px solid var(--color-gray-300)
padding: 12px 24px
border-radius: 8px
```

#### Button States
- **Hover**: Lift effect (translateY(-1px)) + deeper shadow
- **Active**: Pressed effect (translateY(0)) + inner shadow
- **Disabled**: Reduced opacity (0.6) + no hover effects
- **Focus**: Outline ring for accessibility

#### Button Sizes
- **Small**: 8px 16px padding, 14px font size
- **Medium**: 12px 24px padding, 16px font size (default)
- **Large**: 16px 32px padding, 18px font size

### Cards

#### Default Card
```css
background: var(--color-white)
border: 1px solid var(--color-gray-200)
border-radius: 16px
padding: 32px
box-shadow: 0 1px 3px rgba(0,0,0,0.1)
```

#### Hover Effects
```css
transform: translateY(-4px)
box-shadow: 0 10px 25px rgba(0,0,0,0.1)
border-color: var(--color-primary)
```

#### Card Variants
- **Feature Card**: Icon + heading + description + list
- **Pricing Card**: Header + price + features + CTA
- **Testimonial Card**: Quote + author + avatar
- **Comparison Card**: Side-by-side feature comparison

### Forms

#### Input Fields
```css
padding: 12px 16px
border: 1px solid var(--color-gray-300)
border-radius: 8px
font-size: 16px
line-height: 1.5
```

#### Input States
- **Default**: Gray border, white background
- **Focus**: Primary color border, box-shadow ring
- **Error**: Red border, error message below
- **Success**: Green border, success indicator

#### Form Layout
- **Mobile**: Stacked fields with full width
- **Desktop**: Inline for simple forms, stacked for complex
- **Labels**: Above fields for clarity
- **Help Text**: Below fields in smaller text

### Navigation

#### Desktop Navigation
```css
Fixed position header
72px height
Backdrop blur effect
Logo left, menu center, CTA right
```

#### Mobile Navigation
```css
Hamburger menu (right side)
Full-screen overlay menu
Logo prominence maintained
CTA accessibility preserved
```

#### Navigation States
- **Scrolled**: Background opacity + shadow
- **Active**: Current page highlighting
- **Hover**: Color transitions on links

## Iconography

### Icon Style
- **Style**: Outline icons (Heroicons)
- **Weight**: 2px stroke width
- **Size**: 20px (default), 24px (large), 16px (small)
- **Color**: Inherit from parent text color

### Icon Usage
- **Navigation**: Arrows, menu hamburger
- **Features**: Relevant service industry icons
- **Actions**: Plus, edit, delete, share
- **Status**: Check marks, warnings, errors

### Custom Icons
- Kinvo logo variations
- Service industry specific icons
- Payment method icons
- Social media icons

## Imagery Guidelines

### Photography Style
- **Authentic**: Real service professionals at work
- **Approachable**: Friendly, hardworking people
- **Professional**: Clean, well-composed shots
- **Diverse**: Various trades and demographics

### Image Treatment
- **Color**: Natural, unsaturated tones
- **Lighting**: Bright, professional lighting
- **Composition**: Clean backgrounds, focused subjects
- **Quality**: High resolution for all screen types

### Illustration Style
- **Minimal**: Simple, clean illustrations
- **Consistent**: Matching color palette
- **Purposeful**: Support content, don't distract
- **Scalable**: Vector-based for all screen sizes

## Animation & Interactions

### Micro-Interactions
- **Hover Effects**: Subtle lift on cards and buttons
- **Loading States**: Smooth skeleton screens
- **Form Feedback**: Instant validation responses
- **Navigation**: Smooth transitions between states

### Page Transitions
- **Scroll Animations**: Fade-in on viewport entry
- **Navigation**: Smooth scroll to anchor links
- **Modal/Overlay**: Fade and scale animations
- **Mobile Menu**: Slide-in from side

### Performance Guidelines
- **Duration**: 150-300ms for most interactions
- **Easing**: Ease-out for natural feel
- **Reduced Motion**: Respect user preferences
- **Battery Optimization**: Avoid continuous animations

## Accessibility Standards

### WCAG 2.1 AA Compliance
- **Color Contrast**: 4.5:1 minimum for normal text
- **Focus Management**: Visible focus indicators
- **Keyboard Navigation**: Full keyboard accessibility
- **Screen Readers**: Semantic HTML and ARIA labels

### Inclusive Design
- **Touch Targets**: Minimum 44px tap targets
- **Font Size**: Scalable text for vision impairments
- **Color Independence**: Information not reliant on color alone
- **Motion Sensitivity**: Respects prefers-reduced-motion

## Performance Optimization

### Technical Requirements
- **Mobile First**: Optimized for mobile performance
- **Lightweight**: Minimal external dependencies
- **Fast Loading**: Under 3 seconds on 3G
- **Progressive Enhancement**: Works without JavaScript

### Image Optimization
- **WebP Format**: Modern browsers with fallbacks
- **Lazy Loading**: Images load as needed
- **Responsive Images**: Appropriate sizes for each device
- **Compression**: Optimized file sizes without quality loss

### Code Optimization
- **CSS**: Custom properties for maintainability
- **JavaScript**: Vanilla JS for performance
- **HTML**: Semantic markup for accessibility
- **Caching**: Proper cache headers for static assets

## Usage Guidelines

### Do's
- Use primary green for CTAs and success states
- Maintain consistent spacing with the 4px system
- Prioritize mobile experience in all designs
- Use authentic imagery of service professionals
- Keep copy honest and straightforward
- Optimize for conversion at every step

### Don'ts
- Don't use primary green for large background areas
- Don't ignore mobile touch target sizes
- Don't use corporate jargon or complex terminology
- Don't sacrifice accessibility for aesthetics
- Don't overload pages with too many CTAs
- Don't use stock photos that look generic

### Brand Voice Examples

#### Correct Tone
- "Get paid faster" (direct benefit)
- "No BS pricing" (honest approach)
- "Made for real service businesses" (authentic)
- "Stop chasing payments" (problem-focused)

#### Incorrect Tone
- "Leverage synergistic solutions" (corporate jargon)
- "Enterprise-grade workflow optimization" (complex)
- "Disruptive fintech innovation" (tech buzzwords)
- "Best-in-class paradigm shift" (meaningless)

This design system ensures consistent, professional, and user-friendly experiences across all Kinvo touchpoints while maintaining the brand's authentic voice and conversion-focused approach.