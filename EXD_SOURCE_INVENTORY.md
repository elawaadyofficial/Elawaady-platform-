# EXD — Source Inventory

What the real EXD product already is, recorded so no agent mistakes the thin
GitHub skeleton for the whole project. **The live theme and the dashboard
prototype are the specification. This repository is behind them, not ahead.**

Captured 2026-08-30 from sources supplied by the project owner.

---

## 1. Live storefront — `elawaady-old-theme.rukn.cloud`

The existing EXD store. Its structure, routes and assets are the target this
repository is being brought up to. This is **EXD's own property** — unlike the
competitor references (`s-trx.com`, `digital-plus3.com`), whose assets, text
and designs must never be copied.

### Routes (this repo currently has 6 of them)

| Route | In this repo? | Notes |
|---|---|---|
| `/` | ✅ `index.php` | far thinner than the live one |
| `/categories` | ✅ `categories.php` | |
| `/category/{id}` | ⚠️ `subcategories.php?category_id=` | different URL scheme |
| `/service/{id}` | ⚠️ `service.php?id=` | different URL scheme |
| `/search` | ✅ `search.php` | |
| `/contact` | ✅ `contact.php` | |
| `/mediation` | ❌ **missing** | escrow / وساطة — a core EXD offering |
| `/login` | ❌ **missing** | |
| `/register` | ❌ **missing** | |
| `/register?type=user` | ❌ **missing** | three distinct account types |
| `/register?type=merchant` | ❌ **missing** | |
| `/register?type=supplier` | ❌ **missing** | |
| `/forgot-password` | ❌ **missing** | |
| `/policies/terms` | ❌ **missing** | |
| `/policies/privacy` | ❌ **missing** | |
| `/policies/refund` | ❌ **missing** | |
| `/policies/security` | ❌ **missing** | |
| `/policies/usage-policy` | ❌ **missing** | |
| `/policies/mediation-terms` | ❌ **missing** | |
| `/policies/merchant-terms` | ❌ **missing** | |
| `/policies/supplier-terms` | ❌ **missing** | |
| `/policies/marketer-terms` | ❌ **missing** | |
| `?lang=en` | ❌ **missing** | the live store is bilingual AR/EN |

**23 routes live, 6 here.** Cart, accounts, mediation, policies and the
language switch are entirely absent from this repository.

### Homepage sections on the live store

`hero hero-exp` · `trust-strip` (6 trust items) · `featured-slider-section`
(Swiper 11) · category-grouped service sections, each with a **"عرض الكل"**
link · `payment-strip-section` · footer.

### Service page components

Breadcrumb · image gallery with thumbnail strip · price block · availability
badge · **quantity stepper** (`qty-block`, `qty-control`, `qty-btn`,
`qty-input`) · optional order-notes textarea · three CTAs — اشتري الآن /
إضافة للسلة / استشارة قبل الطلب · payment strip · sticky mobile quantity bar
(`sticky-qty`) · final CTA panel.

This repo's `service.php` has none of it: no gallery, no quantity, no cart,
no notes — one link out to `contact.php`.

### Footer groups

- **روابط رسمية** — الموقع الرسمي · واتساب رسمي · واتساب إضافي · تيليجرام · ماسنجر · support@elawaady.com · الجروب الرسمي
- **الحسابات** — دخول · حساب مستخدم · حساب مورد · حساب تاجر · استعادة كلمة المرور
- **قانوني وسياسات** — the nine policy pages above

### Third-party dependency

Swiper 11 via jsDelivr. This repo implements its carousel by hand in
`main.js` with no library — **keep it that way** unless a real need appears.

---

## 2. Dashboard prototype — `elbadaway83-ui.github.io/Dashboard`

A static HTML/CSS prototype: the **UI specification** for the admin panel to
be built in PHP. Sixteen pages, none of which exist in this repository.

| Page | Purpose |
|---|---|
| `index.html` | لوحة التحكم — KPIs, latest orders, quick actions |
| `pages/categories.html` | الأقسام — table of all 43, sort order, active/hidden, edit/delete |
| `pages/category-form.html` | add / edit a category |
| `pages/services.html` | الخدمات |
| `pages/service-form.html?id=` | add / edit a service |
| `pages/orders.html` | الطلبات |
| `pages/order-view.html?id=` | single order, ref format `ORD-YYYYMMDD-NNNN` |
| `pages/suppliers.html` | الموردون |
| `pages/supplier-dashboard.html` | لوحة المورد |
| `pages/merchants.html` | التجار |
| `pages/merchant-dashboard.html` | لوحة التجار |
| `pages/chatbot.html` | الشات بوت |
| `pages/homepage-settings.html` | إعدادات الرئيسية |
| `pages/visual-sections.html` | صور الأقسام والخدمات |
| `pages/brand-settings.html` | الهوية البصرية |
| `pages/carousel.html` | الكاروسيل |
| `pages/logout.html` | تسجيل الخروج |

Sidebar groups: **المحتوى** (الأقسام · الخدمات) · **المبيعات** (الطلبات ·
الشات بوت) · **الإعدادات** (إعدادات الرئيسية · صور الأقسام والخدمات ·
الهوية البصرية · الكاروسيل) · **عام** (عرض الموقع).

Confirms three seller roles beyond the customer: **مورد**, **تاجر**, **admin**.

---

## 3. Assets brought into this repository

Sourced from the live EXD theme — EXD's own creative work.

| Path | What | Origin |
|---|---|---|
| `assets/catalog/exd-01..21.webp` | 21 product artworks, 600×600, EXD 3D CGI style | `/uploads/carousel/carousel-NN.jpg` |
| `assets/payments/01..13-*.webp` | 13 payment logos, sliced from the official strip | `/uploads/brand/payment-methods.png` |
| `assets/brand/exd-logo-official.webp` | official header logo, transparent | `/uploads/brand/1783731757_logo_header.png` |
| `assets/brand/favicon-32.png`, `-180.png` | favicon set | `/uploads/brand/favicon.png` |
| `assets/brand/exd-logo.*`, `exd-mark.*` | lockup + mark, keyed from the owner-supplied JPG | supplied in chat |
| `assets/fonts/*.woff2` | The Year of Handicrafts, five weights | project Drive archive |

Payment methods, in strip order: InstaPay · Fawry · PayPal · GPay ·
Vodafone Cash · Orange Cash · etisalat Cash · stc pay · WE Pay · Apple Pay ·
Mastercard · mada · تحويل بنكي.

**Caveat:** the payment source strip is only 512×74, so each sliced logo is
roughly 32×45. Fine at ~36 px display height, soft on high-DPI screens.
Higher-resolution originals are worth requesting.

**The product artwork is not yet mapped to services.** `store_services.image`
is empty for every row, and inventing a service→image mapping would be
fabricating catalogue data. The images are staged and ready; the mapping
belongs to the admin panel, or to an explicit list from the project owner.

---

## 4. Gap summary

| Area | Live / prototype | This repo |
|---|---|---|
| Storefront routes | 23 | 6 |
| Accounts & roles | user / merchant / supplier / admin | none |
| Cart & checkout | quantity, cart, order refs | none |
| Mediation (escrow) | dedicated page + terms | none |
| Policy pages | 9 | 0 |
| Bilingual AR/EN | yes | Arabic only |
| Admin panel | 16 pages | none |
| Product imagery | real 3D artwork | first letter of the name |
| Homepage sections | hero, trust, featured slider, per-category rows, payments | hero, benefits, categories, 2 product grids, reviews, stats, FAQ, payments |

---

## 5. Preserve

Nothing listed here justifies deleting anything already in the repository.
`style.css` still carries 19 classes that are the sole styling for
`categories.php`, `subcategories.php`, `service.php`, `search.php` and
`contact.php`. `backend/` is a separate, incomplete Python service. Both
stay. Anything unclear is **preserve until reviewed**.
