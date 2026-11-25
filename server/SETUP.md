# PREMium Mind (naya React site) - Hostinger backend setup

Ye `server/` folder me wahi files hain jo naye site ko chahiye. Purani `PREMium Mind/server/` ko maine chhua nahi.
Sab kuch `public_html/premind/` me upload hota hai (jahan `api.php`, `db_connect.php` pehle se hain).

## 1. Upload karo

| File | Kaam | Naya / Replace |
|---|---|---|
| `pm_cors.php` | Kaunsi website API bula sakti hai (ek hi jagah) | **Naya** |
| `pm_orders.php` | Payment ke common functions (course dena, Cashfree se order padhna) | **Naya** |
| `create_order.php` | Cashfree order banata hai (token se identity, price DB se) | **Replace** |
| `verify_payment.php` | Payment ke baad Cashfree se pakka karke course deta hai | **Replace** |
| `webhook.php` | Cashfree khud bata deta hai ki payment hua | **Replace** |
| `progress.php` | Resume / syllabus ticks / mock score cloud me save | **Naya** |
| `my_courses.php` | User ke kharide hue course ids | **Naya** |

Ye pehle se server par hone chahiye (badalna nahi): `pm_load_env.php`, `pm_session_token.php`, `pm_load_secrets.php`,
`db_connect.php`, `.env`, `.htaccess`, `api.php` (secure wala), `get_pdf_session.php`, `get_pdf_token.php`, `proxy_pdf.php`,
`secure_page_image.php`, `pm_pdf_access.php`.

Purani `create_order.php` / `verify_payment.php` / `webhook.php` ko replace karne se **purana HTML site** (index.html) ka
payment bhi chalta rahega ya nahi, ye dhyan rakho: purana site `verify_payment.php?order_id=..&course_id=..&email=..`
bhejta hai bina login token ke. Naya `verify_payment.php` token maangta hai. Agar purana site bhi chalana hai, to naye
files ko alag naam se upload karo (jaise `create_order_v2.php`) - bata dena, main frontend me naam badal dunga.

## 2. `.env` me ye lines jodo (baaki jaisa hai waisa)

```
# Cashfree (pehle se honi chahiye)
CASHFREE_APP_ID=...
CASHFREE_SECRET_KEY=...
CASHFREE_ENV=PROD

# Naye site ka live address (payment ke baad buyer yahin wapas aata hai)
SITE_URL=https://premind.netlify.app

# Naya site kisi aur domain par ho to (comma se alag):
EXTRA_ALLOWED_ORIGINS=https://yournewdomain.com

# SIRF testing ke liye: localhost:5173 se API chalane do. Live hone par ye line HATA do ya 0 kar do.
ALLOW_LOCALHOST_DEV=1
```

## 3. Cashfree dashboard me

1. **Webhook:** Developers -> Webhooks -> *Payment Success* -> URL: `https://premind.diplomawallah.in/webhook.php`
2. **Domain whitelist:** Payment Gateway -> Settings me naye site ka domain add karo. Cashfree production me
   `localhost` par payment chalne nahi deta, isliye payment ka test **live/Netlify address** par hi hoga.

## 4. Admin panel me 1 chhota change (Important Questions category ke liye)

`admin_panel.php` me `<select name="category" id="inp_category">` ke andar (lagbhag line 1586-1592) ye line jodo:

```html
<option value="important">Important Questions</option>
```

Bina iske bhi chalega, par Important Questions wale products admin se nahi bana paoge.

## 5. Products

- Admin panel se ya `products.sql` (phpMyAdmin -> SQL tab) se. `products.sql` wahi 8 products banata hai jo abhi site me hain.
- **Resume template** ka row: `category = resume`, aur **`demo_link = template:modern`** (ya `template:classic`, `template:compact`).
  Isi se site pata lagati hai ki kharidne ke baad kaunsa editor kholna hai.
- **Price** wahi lagti hai jo `courses.price` me hai. Browser se aayi hui koi price nahi chalti.
- **PDF**: `uploads/pdfs/` me file honi chahiye. Kharidne se pehle uska path kisi ko nahi dikhta.

## 6. Naye tables

`orders` aur `user_progress` apne aap ban jate hain (pehli call par). Chaho to `schema.sql` phpMyAdmin me chala do.

## 7. Localhost par login/PDF test ke liye (sirf aapki machine)

Jin purani files me `$allowed_origins` ki list hai (`get_pdf_session.php` pehle ho gaya; baaki `get_pdf_token.php`,
`pm_pdf_access.php`, `proxy_pdf.php`, `secure_page_image.php`), unme ye 2 entries jod do, ya wahan list ki jagah
`require_once __DIR__.'/pm_cors.php';` ke `pm_origin_ok($origin)` ka istemal karo:

```php
'http://localhost:5173', 'http://127.0.0.1:5173',
```

Live hone par ye 2 entries hata dena.

## 8. Test (upload ke baad)

PowerShell me (apni site ka token `localStorage.pm_pdf_session` se mil jata hai):

```powershell
# 1) bina login ke: 401 aana chahiye
curl.exe -s -X POST https://premind.diplomawallah.in/create_order.php -H "Content-Type: application/json" -d "{\"course_id\":1}"

# 2) galat origin: 403 'Origin not allowed' aana chahiye
curl.exe -s -X POST https://premind.diplomawallah.in/create_order.php -H "Origin: https://evil.example" -H "Content-Type: application/json" -d "{\"course_id\":1}"

# 3) apne token ke saath: kharidari shuru ho jati hai (payment_session_id milta hai)
curl.exe -s -X POST https://premind.diplomawallah.in/create_order.php -H "Content-Type: application/json" -H "X-PM-Session: TOKEN_YAHAN" -d "{\"course_id\":1}"
```

## 8b. Naye site (frontend) me kya jodha gaya - isse jaano

- Store ke products ab seedha **`api.php`** se aate hain (wahi `courses` table jo admin panel me hai). Server na mile
  tab hi site ka built-in demo list dikhta hai.
- **Buy** dabane par: login hona zaroori -> `create_order.php` -> Cashfree (phone par full page, desktop par popup)
  -> `verify_payment.php`. Phone par buyer wapas **`/payment-return`** page par aata hai.
- Naye site ko host par **SPA redirect** chahiye, warna `/payment-return` kholne par 404 aayega. Netlify ke liye
  `public/_redirects` bana diya hai (`/*  /index.html  200`). Hostinger par `.htaccess` me
  `RewriteRule ^ index.html [L]` jaisa rule chahiye.
- Kharide hue courses `my_courses.php` se aate hain. Resume, syllabus ticks aur mock scores `progress.php` se
  account me save hote hain (login ho tabhi).
- **Payment localhost par nahi chalega** (Cashfree ki rok). Site ko Netlify/Hostinger par deploy karke hi test karo.

## 9. Kya-kya safe kiya

- Kaun kharid raha hai: sirf signed token se (email browser se nahi maanta).
- Price, phone, naam: database se. Browser se bheji price ignore.
- Pehle se kharida course dobara nahi bikta; trashed / coming-soon / app-only course nahi bikta.
- `verify_payment`: Cashfree se PAID pakka, order usi user ka, amount match, tab hi course milta hai.
- `webhook`: signature check, order DB se, amount match, dobara chalne par duplicate nahi banta.
- Return page server banata hai (open redirect nahi ban sakta).
- Progress API: har user sirf apna data padh/likh sakta hai, max 200 KB.
