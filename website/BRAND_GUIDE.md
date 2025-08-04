# Kinvo Brand Guide

## 🧭 Brand Keywords

* Honest
* Approachable
* Professional
* Built for real work
* Light, not loud

---

## 🎨 Color Palette

### Primary Colors

* **#352B52** – Deep Plum
  Use: Primary buttons, headers, borders, dark text
  Tone: Authority, trust, creative edge

* **#8876B6** – Soft Lavender
  Use: Hover states, accents, empty state icons
  Tone: Friendly, modern

* **#A7A3B6** – Neutral Gray-Lavender
  Use: Subtext, backgrounds, shadows
  Tone: Calm, clean

### Accent Color

* **#C7DA39** – Vibrant Green
  Use: Action buttons, success messages, highlights, status icons
  Tone: Fresh, energetic, optimistic

### Support Shades

* **#F5F5F5** – Light background
* **#1F1B2C** – Optional near-black for high contrast
* **#FFFFFF** – White for clarity and whitespace

---

## 🔤 Typography

### Primary Font:

* **[Manrope](https://fonts.google.com/specimen/Manrope)**
* Modern sans-serif
* Friendly but clean
* Great for UI, headings, and body text

### Alternatives:

* Inter (for system-style feel)
* Plus Jakarta Sans (more personality)

### Font Import Code

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet">
```

### Font Weight Variables

```css
--font-weight-light: 200;
--font-weight-regular: 400;
--font-weight-medium: 500;
--font-weight-semibold: 600;
--font-weight-bold: 700;
--font-weight-extrabold: 800;
```

### Type Scale

```css
--type-sm: clamp(0.8rem, 0.17vi + 0.76rem, 0.89rem);
--type-base: clamp(1rem, 0.34vi + 0.91rem, 1.19rem);
--type-md: clamp(1.25rem, 0.61vi + 1.1rem, 1.58rem);
--type-lg: clamp(1.56rem, 1vi + 1.31rem, 2.11rem);
--type-xl: clamp(1.95rem, 1.56vi + 1.56rem, 2.81rem);
--type-xxl: clamp(2.44rem, 2.38vi + 1.85rem, 3.75rem);
--type-xxxl: clamp(3.05rem, 3.54vi + 2.17rem, 5rem);
```

---

## 🧩 Logo

* Wordmark: `KINVO` (all caps)
* Font weight: Medium or Bold
* Letter spacing: Slightly expanded

### Mascot (Kinny the Ghost)

* Usage: onboarding, empty states, 404 pages, feedback modals, merch
* Styles: full-body (onboarding/404), head-only (nav/profile)
* Expressions: waving, chill, focused, blinking

---

## 🔁 UI Style

### Buttons

* Primary: `#352B52` background with white text
* Primary Hover: `#4A3D6C`
* Secondary: `#A7A3B6` border, white fill
* Secondary Hover: `#C7DA39` icon/text only
* Success: `#C7DA39` fill, dark text
* Success Hover: `#B5C632`

### Shadows

* Subtle elevation: `0 2px 4px rgba(53, 43, 82, 0.1)`
* Focus ring: `2px solid #C7DA39`

---

## 🗣️ Voice & Tone

* Friendly but not casual
* Clear, direct, confident
* Always helpful, never pushy

**Tone Examples:**

* "Let’s get you paid."
* "You’re all set. Nothing due today."
* "Almost there. Just one more invoice."
* "Kinny’s keeping an eye on things."

---

## 📐 Iconography & Mascot Usage

* Icons: Outline or two-tone style
* Corners: Rounded to match mascot energy
* Spacing: Generous and clear

---

## 🎯 Brand Personas

### 🧍‍♂️ Frank Harris – Handyman & Repair Specialist

* Age: 45
* Location: Fayetteville, NC
* Tech Skill: Moderate

**What Frank Needs:**

* Quick way to send estimates and turn them into invoices
* Track jobs by property and line-item labor/materials
* Clear records of who’s paid and who hasn’t

**Why He Uses Kinvo:**

* Sends estimates to landlords and turns them into invoices on-site
* Can itemize time, materials, and travel separately
* Dashboard shows unpaid invoices at a glance

### 🧍‍♀️ Latasha Brooks – Residential Cleaner

* Age: 38
* Location: Jacksonville, FL
* Tech Skill: Low to moderate

**What Latasha Needs:**

* Create invoices for one-time or first-time cleans
* Track what was done in each cleaning
* Let clients review and pay without phone calls

**Why She Uses Kinvo:**

* Uses estimates when quoting new deep cleans
* Sends invoices after each job with itemized extras (like oven or windows)
* Clients get a link to pay or view their invoice without logging in

### 🧍‍♂️ Miguel Reyes – Lawn Care Operator

* Age: 28
* Location: Corpus Christi, TX
* Tech Skill: High

**What Miguel Needs:**

* Keep invoices organized by property
* Track mileage and supply use for each job
* See which invoices are still unpaid

**Why He Uses Kinvo:**

* Sends one-off invoices for mowing, seeding, and hauling
* Mobile-friendly UI makes it easy to create invoices on-site
* Payment tracking keeps cash flow from falling behind

### 🎯 Additional Personas

#### 1. Small Business Owner: Emma

* Age: 35
* Role: Owns a local bookkeeping business
* Needs: Clarity, trust, simplicity
* Drawn to: Deep Plum + Lavender combo, clear calls to action, approachable UX

#### 2. Freelance Pro: Jamal

* Age: 28
* Role: Freelancer managing multiple invoices
* Needs: Confidence in the brand, quick workflows
* Drawn to: Koala mascot in feedback, clear status markers, strong contrast

#### 3. Admin at Startup: Taylor

* Age: 31
* Role: Admin assistant at a 12-person SaaS company
* Needs: Easy invoice management, modern UI
* Drawn to: Soft animations, smart defaults, consistent palette, modern typography

---

## ✅ Application Examples

* Login: Koala head icon + "Welcome back"
* Empty state: Full-body Kinny with "No invoices yet"
* Paid success: Green checkmark + Kinny confetti
* Buttons: Purple default, hover to lavender or green
