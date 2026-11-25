# Najik CMS + API

Laravel 13 backend for the Najik app: the admin console (Inertia + React) and the mobile API (JWT).
Structure follows `yesterday-cms`: services hold logic, controllers stay thin, routes live in
`routes/api/{version}/*.php` (→ `/api/{version}/…`) and `routes/cms/*.php` (→ `/cms/…`).

## Setup

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
# create the MySQL database named in DB_DATABASE (najik_cms), then:
php artisan migrate --seed
npm run build        # or npm run dev
php artisan serve
```

CMS: <http://localhost:8000/cms/login>. Seeded admin: `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`
(defaults `admin@najik.app` / `password`; change them before seeding anywhere shared).

## Mobile API

Full reference for the Flutter app: [docs/api.md](docs/api.md). Postman collection:
`docs/postman/najik-api.postman_collection.json` (Send OTP → Verify OTP stores the tokens).

- Base path `/api/v1`. Responses use `{ status, message, data, meta? }`; errors use `{ status, message, errors }`.
- Sign-in is phone OTP → JWT: `POST /auth/otp/send`, then `POST /auth/otp/verify` (`as=vendor` from the shop flow).
  The access token lasts 60 min; the refresh token lasts 30 days, works only on `/auth/refresh`, and is single-use.
- Customer routes cover profile, free-time windows, blocked places, errands (the wishlist), explore and shop detail,
  nudges, check-ins, reviews and support. Vendor routes live under `/vendor/*` (shop, open today, no wait, services,
  offers, check-in code, reviews, insights).
- The app sends its location to `POST /nudges/check` during the user's free-time windows; the server applies the
  CMS nudge rules and pushes the nudge.
- Roles (spatie/laravel-permission, `web` guard): `admin` (CMS only), `customer` (every app user),
  `vendor` (added when signing in with `as=vendor` or creating a shop).
- SMS and push go through `App\Contracts\SmsGateway` and `App\Contracts\PushGateway`. `SMS_DRIVER=log` and
  `PUSH_DRIVER=log` write to the log until a Nepali SMS provider and FCM are wired in.

## Tests

```sh
php artisan test
```
