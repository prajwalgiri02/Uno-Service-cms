# Najik mobile API (v1)

Base URL: `https://<host>/api/v1` (local: `http://127.0.0.1:8000/api/v1`). Send `Accept: application/json` on every request.
Times are ISO 8601 in the server timezone (`Asia/Kathmandu`). Distances are metres, prices are whole rupees.
A ready-made Postman collection lives in `docs/postman/najik-api.postman_collection.json`.

## Conventions

### Response envelope

Success:

```json
{ "status": 200, "message": "Signed in.", "data": { }, "meta": { "current_page": 1, "last_page": 3, "per_page": 20, "total": 47 } }
```

`meta` appears only on paginated lists (`GET /nudges`, `GET /support-tickets`). Pass `?page=2` for the next page.

Error:

```json
{ "status": 422, "message": "That code is incorrect. 4 attempts remaining.", "errors": { "code": ["That code is incorrect. 4 attempts remaining."] }, "retry_after": null }
```

| Status | Meaning |
| --- | --- |
| 401 | Missing, expired or blacklisted access token, or a refresh token used as a bearer |
| 403 | Forbidden (blocked account at sign-in) |
| 404 | Not found, or not yours (other users' errands, tickets, etc. return 404) |
| 422 | Validation or business rule failed; `errors` is keyed by field |
| 423 | Account blocked after sign-in; sign the user out |
| 429 | Rate limited; check the `Retry-After` header |

`retry_after` (seconds) is only set on OTP cooldown errors.

### Tokens

- `access_token`: send it as `Authorization: Bearer <token>`. It lasts `expires_in` seconds (60 min by default).
- `refresh_token`: lasts `refresh_expires_in` seconds (30 days by default). It works **only** on `POST /auth/refresh`. Any other route returns 401.
- Refreshing is single use. Every refresh returns a new pair and blacklists the old refresh token, so always store the new pair. If two refresh calls race, the second gets 401 and the user must sign in again.
- On 401 from a normal route, refresh once and retry. If the refresh also fails, send the user to sign-in.

### Rate limits

| Route | Limit |
| --- | --- |
| `POST /auth/otp/send` | 3/min per phone+IP, 30/hour per IP |
| `POST /auth/otp/verify` | 10/min per phone+IP |
| `POST /auth/refresh` | 20/min per IP |
| `POST /nudges/check` | 6/min per user |
| `POST /check-ins`, `POST /vendor/check-ins` | 5/min per user |
| `POST /category-requests` | 20/hour |
| `POST /shops/{id}/report` | 10/hour |
| `POST /support-tickets` | 10/hour |

### Shared shapes

**User** (`data.user` after sign-in, `GET /me`)

```json
{ "id": 2, "name": "Sita Sharma", "phone": "+9779841234567", "email": null, "area": "putalisadak", "language": "en",
  "nudges_enabled": true, "paused_until": null, "pings_per_day": 2, "points": 120, "roles": ["customer"], "has_shop": false,
  "phone_verified_at": "2026-11-25T14:26:03+05:45", "created_at": "2026-11-25T14:26:03+05:45" }
```

`roles` and `has_shop` only appear when loaded (`GET /me`, `PATCH /me`, sign-in responses carry `roles`).

**Shop card** (lists, nudges, home)

```json
{ "id": 1, "name": "Style Point Salon", "initials": "SP", "emoji": "💈",
  "category": { "id": 2, "name": "Men's haircut", "name_ne": "पुरुष कपाल काट्ने", "parent": "Beauty" },
  "headline": "Men's haircut", "blurb": null, "vibes": ["cash or QR"], "latitude": 27.7053, "longitude": 85.3237,
  "distance_m": 160, "walk_min": 3, "open_today": true, "open_now": true, "open_till": "7:30 pm",
  "price_min": 150, "price_max": 500, "no_wait": true, "no_wait_until": "2026-11-25T18:10:00+05:45",
  "offer": { "percent": 10, "label": "10% off · weekdays 2–6 pm", "running_now": true },
  "rating": 4.5, "rating_count": 12, "women_run": false, "favorite": false }
```

`distance_m` and `walk_min` are `null` when no location was sent. `walk_min` assumes 75 m per minute.

**Shop detail** = shop card plus `phone`, `area`, `landmark`, `opening_hours`, `services[]`, `photos[{id,url}]`, `on_your_list`.

**Service**: `{ "id", "name", "for_who": "men|women|kids|all|null", "minutes", "price_min", "price_max", "walk_ins", "photo_url" }`

**Opening hours**: keys `mon` … `sun`. Each is `{"open":"09:00","close":"19:30"}` or `null` for closed. When the whole object is empty, the shop counts as always within hours.

**Weekdays**: ISO numbers, 1 = Monday … 7 = Sunday. "Weekdays" in Nepal = `[1,2,3,4,5,7]` (Sunday to Friday).

---

## Auth

### `POST /auth/otp/send` (public)

| Param | Rules |
| --- | --- |
| `phone` | required, Nepali mobile: 10 digits starting `97`/`98`; spaces, dashes and a `+977`/`977` prefix are accepted |

```json
{ "status": 200, "message": "Verification code sent.", "data": { "phone": "+9779841234567", "expires_in": 300, "resend_in": 60, "code": "7470" } }
```

- `code` appears only when the server sets `OTP_EXPOSE_CODE=true` outside production (for testing without SMS).
- The code is 4 digits and valid for 5 minutes.
- **Errors:**
  - 422 `phone`: invalid number.
  - 422 `phone` + `retry_after`: "Please wait N seconds…" (60 s cooldown), or "Too many codes requested…" (5 sends per 30 min).

### `POST /auth/otp/verify` (public)

| Param | Rules |
| --- | --- |
| `phone` | required, same as above |
| `code` | required, exactly 4 digits |
| `as` | optional `customer` (default) or `vendor`; `vendor` adds the vendor role |

```json
{ "status": 200, "message": "Account created.", "data": {
  "access_token": "eyJ…", "refresh_token": "eyJ…", "token_type": "bearer", "expires_in": 3600, "refresh_expires_in": 2592000,
  "is_new_user": true, "user": { "id": 2, "phone": "+9779841234567", "roles": ["customer"] } } }
```

- The message is "Signed in." for an existing user.
- If `is_new_user` is true, continue onboarding (name, free-time windows, first errand).
- **Errors (all 422 on `code` unless noted):**
  - "Request a new code first."
  - "This code has expired. Request a new one."
  - "That code is incorrect. N attempts remaining."
  - "Too many incorrect attempts. Request a new code." (after 5 wrong tries)
  - 403 "Your account has been blocked."

### `POST /auth/refresh` (public)

Body: `refresh_token` (required). It returns the same shape as verify, without `is_new_user`.
**Errors:** 401 "Invalid or expired refresh token."; 403 if the user is now blocked.

### `GET /auth/me` (bearer)

Returns `data` = user.

### `POST /auth/logout` (bearer)

Body: `refresh_token` (optional, recommended). It blacklists the access token, and the refresh token too when sent. Also call `DELETE /me/devices/{deviceId}` so push stops.

---

## Config & categories

### `GET /config` (public)

```json
{ "data": {
  "nudges": { "daily_cap": 2, "walking_min_m": 200, "walking_max_m": 500, "trigger_min_m": 150, "free_windows_only": true },
  "points": { "per_visit": 20, "perk_at": 200, "rewards_open": false },
  "no_wait_minutes": 45, "check_in_max_distance_m": 500,
  "errand_radii_m": [500, 1000, 2000, 3000], "errand_durations": ["till_done", "7_days", "30_days"],
  "free_window_presets": [{ "key": "after_work", "title": "after work", "emoji": "🌇", "weekdays": [1,2,3,4,5,7], "from": "17:00", "to": "19:00", "enabled": true }],
  "report_reasons": [{ "value": "closed_when_open", "label": "Closed when open listed", "emoji": "🚪" }],
  "languages": ["en", "ne"] } }
```

These rules are edited in the CMS (Nudge rules, Loyalty points) and change without an app update, so fetch them at app start.

### `GET /categories` (public)

This is a two-level tree of active categories. Main categories carry `children`.

```json
{ "data": [ { "id": 1, "parent_id": null, "name": "Beauty", "name_ne": "सौन्दर्य", "slug": "beauty", "emoji": "💇", "options": [], "keywords": [],
  "typical_price_min": null, "typical_price_max": null,
  "children": [ { "id": 2, "parent_id": 1, "name": "Men's haircut", "name_ne": "पुरुष कपाल काट्ने", "emoji": "💈",
    "options": ["men's cut", "beard trim", "kids' cut"], "keywords": ["haircut", "cut", "barber", "कपाल"], "typical_price_min": 200, "typical_price_max": 500 } ] } ] }
```

- `options` are the chips under the search box.
- `keywords` let the app match search text (English and Nepali).
- Wishlisting a main category also matches all of its subcategories.

### `GET /categories/{id}/price-hint?latitude=&longitude=` (bearer)

This powers "most shops nearby charge Rs a–b". It looks at live shops within 2 km.

```json
{ "data": { "shops": 3, "price_min": 150, "price_max": 500, "source": "nearby" } }
```

`source` is `typical` (the category defaults) when no shop is nearby.

### `POST /category-requests` (bearer)

Body: `query` (required, max 80). It returns 201 with "We don't have that yet. We've noted it." Call it when search finds nothing.

---

## Profile & settings (bearer)

| Method | Path | Body / query | Returns |
| --- | --- | --- | --- |
| GET | `/me` | — | user |
| PATCH | `/me` | `name` ≤80, `area` ≤80, `language` `en`\|`ne`, `nudges_enabled` bool, `pings_per_day` 1–5 (all optional) | user |
| POST | `/me/pause-today` | — | user with `paused_until` = tomorrow 00:00 |
| DELETE | `/me/pause-today` | — | user with `paused_until: null` |
| GET | `/me/alert-health` | — | see below |
| POST | `/me/devices` | `device_id` req ≤191, `token` req (FCM), `platform` `android`\|`ios`\|`web`, `notifications_allowed` bool, `location_permission` `always`\|`while_using`\|`denied` | `data: null` |
| DELETE | `/me/devices/{deviceId}` | — | `data: null` |
| GET | `/me/stats` | — | see below |
| GET | `/me/visits?month=YYYY-MM` | `month` optional, default this month | see below |

- Call `POST /me/devices` on every app start, whenever the FCM token rotates, and whenever permissions change. Alert health reads these values.
- Nudges respect the lower of the user's `pings_per_day` and the platform daily cap.

`GET /me/alert-health` (the "alerts are healthy ✓" card):

```json
{ "data": { "healthy": false, "checks": [
  { "key": "nudges_on", "ok": true, "message": "nudges are on" },
  { "key": "device", "ok": true, "message": "this phone can get alerts" },
  { "key": "notifications", "ok": true, "message": "notifications allowed" },
  { "key": "location", "ok": false, "message": "allow location \"all the time\" so we can tell when you are close" },
  { "key": "windows", "ok": true, "message": "2 free-time windows set" },
  { "key": "errands", "ok": true, "message": "your list has things to watch" } ] } }
```

`GET /me/stats` (Me screen, Wins header):

```json
{ "data": { "points": 120, "perk_at": 200, "points_to_next_perk": 80, "perk_unlocked": false, "rewards_open": false,
  "streak_weeks": 3, "errands_done_this_month": 5, "errands_goal_this_month": 8, "visits_this_month": 6,
  "saved_rs_this_month": 340, "no_wait_minutes_saved_this_month": 48 } }
```

`streak_weeks` counts consecutive Sunday-to-Saturday weeks with at least one check-in.

`GET /me/visits` (Wins calendar):

```json
{ "data": { "month": "2026-11", "points": 120, "saved_rs": 25, "minutes_saved": 12, "visits": [
  { "id": 9, "shop": { "id": 1, "name": "Style Point Salon", "emoji": "💈", "category": "Men's haircut" }, "method": "shop_code",
    "points": 20, "saved_rs": 25, "minutes_saved": 12, "first_visit": true, "review": { "stars": 5, "text": "quick" },
    "can_review": false, "created_at": "2026-11-25T17:40:00+05:45" } ] } }
```

### Free-time windows (bearer)

Nudges only fire inside enabled windows (CMS rule "Free-time windows only"). The four presets are created on the first `GET`.

| Method | Path | Body |
| --- | --- | --- |
| GET | `/me/free-windows` | — |
| POST | `/me/free-windows` | `title` req ≤40, `weekdays` req array of 1–7, `from` req `HH:MM`, `to` req `HH:MM` after `from`, `enabled` bool |
| PATCH | `/me/free-windows/{id}` | same fields, all optional (`{ "enabled": true }` to toggle) |
| DELETE | `/me/free-windows/{id}` | custom windows only; presets return 422 "Preset windows can be switched off but not deleted." |

Item: `{ "id", "preset": "after_work|lunch|saturday|late|null", "emoji", "title", "subtitle": "weekdays 5–7 pm", "weekdays", "from": "17:00", "to": "19:00", "enabled" }`

### Blocked places ("never ping me at") (bearer)

`home` and `work` are created on the first `GET`, without coordinates. A place only blocks nudges once it has coordinates and is enabled.

| Method | Path | Body |
| --- | --- | --- |
| GET | `/me/blocked-places` | — (home, work, then custom) |
| POST | `/me/blocked-places` | `label` req ≤60, `latitude`+`longitude` (send both or neither), `radius_m` 50–1000 (default 150), `enabled` bool |
| PATCH | `/me/blocked-places/{id}` | same, all optional, e.g. set home's coordinates |
| DELETE | `/me/blocked-places/{id}` | custom only; home/work return 422 |

Item: `{ "id", "kind": "home|work|custom", "label", "latitude", "longitude", "radius_m", "enabled", "has_location" }`

---

## Home (bearer)

### `GET /home?latitude=&longitude=` (both required)

```json
{ "data": {
  "name": "Sita Sharma",
  "nudges": { "enabled": true, "paused_until": null, "watching_windows": ["after work", "saturday"] },
  "trip": { "errand_count": 2, "within_m": 380, "errands": [ { "errand_id": 4, "title": "men's haircut", "shop_id": 1, "distance_m": 160 } ] },
  "errands_done_this_month": 5, "streak_weeks": 3, "people_nearby_today": 12,
  "near_you": [ { "...": "shop card" } ],
  "stale_errand": null } }
```

- `trip` lists the closest shop per watched errand that is within 500 m and confirmed open today. That drives the "2 errands, 1 trip" card.
- `near_you` holds up to 3 shops for list categories within 1 km.
- `people_nearby_today` counts distinct people who checked in today at shops within 2 km.
- `stale_errand` is an errand untouched for 14+ days ("still need tailoring?"). Answer it with keep or done.

---

## Errands (wishlist) (bearer)

| Method | Path | Body / query |
| --- | --- | --- |
| GET | `/errands?latitude=&longitude=` | location optional; when sent, each active errand gets `open_nearby` |
| POST | `/errands` | see below |
| PATCH | `/errands/{id}` | any field below except `category_id`; plus `active` bool (pause/resume) |
| DELETE | `/errands/{id}` | — |
| POST | `/errands/{id}/keep` | — (answers the stale prompt) |
| POST | `/errands/{id}/done` | — ("x is off your list ✓") |
| GET | `/errands/{id}/matches?latitude=&longitude=` | location required |
| POST | `/errands/pause-category/{categoryId}` | — ("not feeling it?", which pauses every open errand in that category tree) |

Create body:

| Param | Rules |
| --- | --- |
| `category_id` | required, active category (main or sub) |
| `variant` | optional ≤60, one of the category `options` |
| `title` | optional ≤80; defaults to `variant`, then the category name |
| `max_price` | optional integer |
| `radius_m` | `500`, `1000` (default), `2000` or `3000` |
| `only_no_wait`, `offers_only`, `women_run` | bools |
| `duration` | `till_done` (default), `7_days`, `30_days` |

**Errors:** 422 `category_id` "That is already on your list." (same category and variant, still open).

Errand:

```json
{ "id": 4, "category": { "id": 2, "name": "Men's haircut", "name_ne": "पुरुष कपाल काट्ने", "emoji": "💈" }, "title": "men's cut", "variant": "men's cut",
  "max_price": 400, "radius_m": 500, "only_no_wait": true, "offers_only": false, "women_run": false, "active": true, "paused_note": null,
  "is_stale": false, "open_nearby": 2, "expires_at": null, "done_at": null, "created_at": "2026-11-25T14:30:00+05:45" }
```

- `GET /errands` returns `{ "errands": [...], "stale": errand|null }`, active ones first.
- Errands that are done or expired drop off the list.
- A check-in at a shop in the errand's category marks it done automatically.

`GET /errands/{id}/matches` (Matches screen):

```json
{ "data": { "errand": { "...": "errand" }, "shops": [ { "...": "shop card" } ], "also_on_the_way": [ { "...": "shop card" } ] } }
```

- `shops` honours the errand radius, max price, no-wait, offers and women-run filters, closest first.
- `also_on_the_way` lists up to 3 shops within 500 m for the user's other errands.

---

## Shops (bearer)

### `GET /shops` (Explore)

| Query | Rules |
| --- | --- |
| `latitude`, `longitude` | required |
| `radius_m` | 100–5000, default 2000 |
| `category_id` | optional; a main category includes its subs |
| `on_list` | bool; limits results to the categories on the user's list ("your list · n") |
| `search` | ≤60; matches shop name, category name (EN/NE) and service names |
| `no_wait`, `offers`, `open_now`, `women_run` | bool filters |

Returns `{ "list_count": 3, "shops": [shop card, ...] }`. Only approved (live) shops appear, closest first.

### Shop detail and favourites

- `GET /shops/{id}?latitude=&longitude=`: returns the shop detail; location is optional. Returns 404 if the shop isn't live. Each call is logged as a shop-page view for the vendor's insights.
- `GET /shops/favorites`: returns an array of shop cards.
- `POST /shops/{id}/favorite` and `DELETE /shops/{id}/favorite`: return `{ "favorite": true|false }`.

### `POST /shops/{id}/report`

| Param | Rules |
| --- | --- |
| `reason` | required: `closed_when_open`, `price_higher`, `waited_long`, `rude_or_unsafe`, `other` |
| `note` | ≤1000, required when `reason=other` |

- Returns 201 "Thanks. We'll check {shop}."
- **Errors:** 422 `reason` "You already reported this shop…" (one open report per user per shop).
- When a shop reaches the CMS auto-pause threshold (default 2 open reports), it's paused and disappears from the app.

---

## Nudges (bearer)

### How the app should drive nudges

1. On start, read `/config`: `nudges.trigger_min_m`, `walking_max_m`, `free_windows_only`.
2. Watch location in the background only inside the user's enabled free-time windows (`GET /me/free-windows`). Outside them, the server answers `outside_free_window` anyway.
3. When the phone has moved meaningfully, call `POST /nudges/check` with the current position.
   - Moved meaningfully means a significant-location change or a geofence exit.
   - Never use a geofence radius smaller than `trigger_min_m`, because OS alerts are unreliable below it.
   - Respect the 6/min rate limit; once every few minutes while moving is plenty.
4. If `sent` is true, the server has already pushed the notification to every registered device. Show it in-app too if the app is in the foreground.
5. When the user taps the notification, call `POST /nudges/{id}/opened`, then open Matches or the shop.

### `POST /nudges/check`

Body: `latitude`, `longitude` (required).

```json
{ "data": { "sent": true, "reason": "sent", "nudge": {
  "id": 31, "title": "got 20 min? 2 errands, 1 trip", "body": "men's haircut + shoe repair are both within 380 m. both open now.",
  "combined": true, "distance_m": 160, "result": "sent", "errand_ids": [4, 5], "shops": [ { "...": "shop card" } ],
  "opened_at": null, "created_at": "2026-11-25T17:31:00+05:45" } } }
```

When nothing is sent, `nudge` is `null` and `reason` explains why:

| `reason` | Meaning |
| --- | --- |
| `sent` | A nudge was created and pushed |
| `nudges_off` | The user switched nudges off |
| `paused` | Paused for today |
| `outside_free_window` | Now isn't in an enabled window (when the rule is on) |
| `at_blocked_place` | Within a blocked place's radius |
| `daily_cap` | Already reached the lower of `pings_per_day` and the platform cap today |
| `weekly_summary` | 3+ nudges in the last 14 days and none opened; the user gets a weekly summary push instead |
| `no_errands` | Nothing active on the list |
| `no_match` | No eligible shop (see below) |

A shop is eligible when it's live, matches an errand category (main includes subs), is within the errand radius (clamped to the walking-distance rule, 200–500 m by default), and is within opening hours.

- It must also be confirmed "open today" (when that rule is on), within the errand's max price, and match the no-wait, offer and women-run filters.
- It must not have nudged this user within the same-vendor gap (7 days by default).
- Two errands matching different nearby shops become one combined nudge.

### Other nudge routes

- `GET /nudges`: recent nudges, paginated (20 per page).
- `GET /nudges/{id}`: one nudge.
- `POST /nudges/{id}/opened`: marks it opened and returns the nudge.
- `GET /nudges/today`: returns `{ "cap": 2, "sent": 1 }` for the "max 2–3 pings a day" copy.
- Nudge `result` moves from `sent` to `opened` to `visited` (a check-in at one of its shops within 6 h). Unopened nudges become `ignored` after a day.

---

## Check-ins & reviews (bearer)

There are two ways to confirm a visit:

1. **Customer enters the shop's code** (or scans the QR, whose payload is `najik://check-in/NJK-5080`). Send `POST /check-ins`. The phone must be within 500 m of the shop.
2. **The shop enters the customer's code** when the phone can't scan. The customer shows the code from `GET /check-ins/visit-code`. The vendor app sends it to `POST /vendor/check-ins`.

Either way, the customer earns `points.per_visit`. Matching errands are marked done, and a nudge from the last 6 h is linked as `visited`. There's one check-in per user per shop per day.

### `POST /check-ins`

| Param | Rules |
| --- | --- |
| `code` | required ≤20; `NJK-5080`, `njk5080` or `5080` all work |
| `latitude`, `longitude` | required |
| `shop_id` | optional; when sent, the code must belong to this shop |

```json
{ "status": 201, "message": "You're checked in. +20 points", "data": {
  "visit": { "id": 9, "shop": { "id": 1, "name": "Style Point Salon", "emoji": "💈", "category": "Men's haircut" }, "method": "shop_code",
    "points": 20, "saved_rs": 25, "minutes_saved": 12, "first_visit": true, "review": null, "can_review": true, "created_at": "…" },
  "points_earned": 20, "points": 140, "streak_weeks": 4 } }
```

- `saved_rs` is the running offer's percent of the cheapest service.
- `minutes_saved` is 12 when "no wait now" was on.
- **Errors (422 `code`):**
  - "That code doesn't match. Ask at the counter."
  - "You need to be at the shop to check in."
  - "You already checked in here today."
  - "You can't check in at your own shop."
  - "This shop is not taking check-ins right now."

### Visit code and reviews

- `GET /check-ins/visit-code`: returns `{ "code": "4821", "expires_in": 600 }`. The same code comes back until it expires or is used.
- `POST /check-ins/{id}/review`: takes `stars` (required, 1–5) and `text` (≤1000). It returns 201 with `{ "stars", "text" }`. A visit can be reviewed once, within 7 days.
  - **Errors (422 `stars`):** "You already rated this visit." or "Visits can be rated for 7 days."

---

## Support (bearer)

| Method | Path | Body |
| --- | --- | --- |
| GET | `/support-tickets` | paginated, newest first |
| POST | `/support-tickets` | `subject` req ≤120, `message` req ≤3000, `as_vendor` bool (counts only if the user is a vendor) |
| GET | `/support-tickets/{id}` | — |
| POST | `/support-tickets/{id}/messages` | `body` req ≤3000; a reply on a resolved ticket reopens it |

```json
{ "data": { "id": 318, "number": "T-318", "type": "user", "subject": "Points missing", "status": "open",
  "messages": [ { "id": 1, "from_support": false, "body": "I checked in yesterday.", "created_at": "…" } ],
  "created_at": "…", "resolved_at": null } }
```

Admin replies arrive as a push with `data.type = "support"` and `ticket_id`. The team replies within 24 h.

---

## Vendor (bearer, shop owner)

- Every route except `POST /vendor/shop` needs a shop. Without one, the response is 404 "Set up your shop first."
- Creating a shop gives the user the `vendor` role. They keep the customer role.

**Status values**

| `status` | `status_label` | Meaning |
| --- | --- | --- |
| `pending` | Pending | Submitted, waiting for Najik review |
| `changes_requested` | Changes asked | See `status_note`; any `PATCH /vendor/shop` resubmits it as `pending` |
| `approved` | Approved | Live (`is_live: true`): visible, nudgeable, takes check-ins and offers |
| `paused` | Paused | Hidden; by an admin or auto-paused after reports. See `status_note` |
| `rejected` | Rejected | See `status_note` |

Status changes are pushed with `data.type = "shop_status"`.

### Shop

| Method | Path | Body |
| --- | --- | --- |
| POST | `/vendor/shop` | `name` req ≤80, `category_id` req (active), `latitude`/`longitude` req, `area` ≤80, `landmark` ≤120, `phone` ≤20 (defaults to the account phone), `blurb` ≤120, `vibes` array ≤5 of ≤30 chars, `women_run` bool, `opening_hours` (see conventions) |
| GET | `/vendor/shop` | — |
| PATCH | `/vendor/shop` | same fields, all optional |

Vendor shop:

```json
{ "id": 1, "name": "Style Point Salon", "initials": "SP", "category": { "...": "category" }, "phone": "+9779841004521", "area": "Putalisadak",
  "landmark": "near Shanker Dev Campus gate", "latitude": 27.7053, "longitude": 85.3237, "opening_hours": { "mon": { "open": "09:00", "close": "19:30" } },
  "blurb": null, "vibes": [], "women_run": false, "status": "pending", "status_label": "Pending", "status_note": null, "is_live": false,
  "check_in_code": "NJK-5080", "open_today": false, "open_confirmed_at": null, "no_wait": false, "no_wait_until": null,
  "current_offer": null, "services": [], "photos": [], "submitted_at": "…" }
```

**Errors:** 422 `name` "You already have a shop on Najik."; 422 `opening_hours` for unknown days, or an open time that isn't before the close time.

### Today, open-today and no-wait

- `GET /vendor/today` (Today tab) returns `{ "shop": vendor shop, "visits_today": 2, "nudges_today": 18, "check_ins_this_week": 13, "last_7_days": [ { "date": "2026-11-19", "weekday": 4, "visits": 2 } ] }`.
- `POST /vendor/open-today` takes `{ "open": true|false }`. Only shops that confirm today can trigger nudges. Confirm early; it resets every day. Turning it off also ends "no wait".
  - **Error:** 422 `open` "Your shop goes live after Najik approves it."
- `POST /vendor/no-wait` takes `{ "on": true|false }`. It switches itself off after `no_wait_minutes` (45 by default, set in the CMS).
  - **Error:** 422 `on` "Turn on \"open today\" first."

### Photos and services

- `POST /vendor/shop/photos`: multipart `photo` (image ≤5 MB, at most 6 photos). Returns `{ "id", "url" }`.
- `DELETE /vendor/shop/photos/{id}`: removes a photo.

| Method | Path | Body |
| --- | --- | --- |
| GET | `/vendor/services` | — |
| POST | `/vendor/services` | `name` req ≤80, `minutes` req 1–600, `price_min` req, `price_max` req ≥ `price_min`, `for_who` `men`\|`women`\|`kids`\|`all`, `walk_ins` bool, `photo` image ≤5 MB (multipart) |
| PATCH | `/vendor/services/{id}` | same, all optional; to upload a photo use `POST` with `_method=PATCH` (multipart) |
| DELETE | `/vendor/services/{id}` | — |

**Errors:** 422 `price_max` "The first number should be the lower one."

### Offers (quiet-hour offers)

`GET /vendor/offers`:

```json
{ "data": { "busy": { "days": [7,1,2,3,4,5,6], "hours": [10,11,12,13,14,15,16,17,18],
  "levels": [[0,1,3,2,0,0,0,1,2], "… 7 rows, Sunday first, 0 quiet … 3 busy"],
  "suggestion": { "weekdays": [1,2,3,4,5,7], "from": "14:00", "to": "16:00", "label": "weekdays 2–4 pm" } },
  "current_offer": null } }
```

- `POST /vendor/offers` takes `percent` (5–50), `weekdays` (array of 1–7), `from` (`HH:MM`) and `to` (`HH:MM`, after `from`). It replaces any running offer and runs until Saturday of this week. It returns 201 with `{ "id", "percent", "weekdays", "from", "to", "label": "10% off · weekdays 2–4 pm", "running_now", "ends_on" }`.
  - **Error:** 422 `percent` if the shop isn't approved.
- `DELETE /vendor/offers/current` stops the offer.

### Check-in code

- `GET /vendor/check-in-code` returns `{ "code": "NJK-5080", "qr_payload": "najik://check-in/NJK-5080", "check_ins_today": 2, "check_ins_this_week": 13 }`.
- `POST /vendor/check-in-code/rotate` returns a new code with the same shape. The old code stops working at once.

### Confirm a visit with the customer's code

`POST /vendor/check-ins` takes `code` (required, exactly 4 digits, from the customer's `GET /check-ins/visit-code`).

```json
{ "status": 201, "message": "Visit confirmed ✓", "data": { "check_in_id": 9, "customer": "Sita Sharma", "first_visit": true, "visits_today": 3 } }
```

**Errors:** 422 `code` "That code doesn't match. Ask the customer to refresh it in their app.", plus the check-in rule errors above.

### Reviews

`GET /vendor/reviews?filter=all|low|needs_reply` (`low` = 2 stars or fewer; `needs_reply` = low and unanswered):

```json
{ "data": { "summary": { "rating": 4.3, "rating_count": 12, "reliability_score": 92, "visits_90_days": 84, "needs_reply": 1 },
  "reviews": [ { "id": 5, "name": "Sujan K.", "letter": "S", "stars": 2, "text": "waited 20 minutes", "reply": null, "replied_at": null, "needs_look": true, "created_at": "…" } ],
  "meta": { "current_page": 1, "last_page": 1, "total": 12 } } }
```

`reliability_score` ("open when you said, prices kept") counts recent reports that were upheld against visits in the last 90 days.

`POST /vendor/reviews/{id}/reply` takes `reply` (required, ≤1000) and returns the review.

### Insights ("how Najik helped")

`GET /vendor/insights?period=today|week|month` (default `week`):

```json
{ "data": { "period": "week", "people_reminded": 96, "opened_shop_page": 41, "checked_in": 13, "new_customers": 6, "best_time": "5–6 pm" } }
```

`best_time` is `null` until there are check-ins.

---

## Flutter screen → endpoints

Screens are in `Uno-Service-/lib/features`.

| Screen | Endpoints |
| --- | --- |
| `onboarding/welcome_screen` | `GET /config` (cache it), `GET /categories` |
| `onboarding/phone_screen` | `POST /auth/otp/send`, `POST /auth/otp/verify` (`as=vendor` from "I run a shop"), then `POST /me/devices` |
| `onboarding/windows_screen` | `GET /me/free-windows`, `PATCH /me/free-windows/{id}`, `POST /me/free-windows`, `GET/PATCH/POST/DELETE /me/blocked-places` |
| `customer/add_errand_screen` | `GET /categories`, `GET /categories/{id}/price-hint`, `POST /errands`, `POST /category-requests` (no match) |
| `customer/home_screen` | `GET /home`, `PATCH /me` (`nudges_enabled`), `GET /errands/{id}/matches` |
| `customer/explore_screen` | `GET /shops` (`on_list`, `search`, filters) |
| `customer/list_screen` | `GET /errands`, `PATCH /errands/{id}` (`active`), `POST /errands/{id}/keep`, `POST /errands/{id}/done`, `DELETE /errands/{id}` |
| `customer/matches_screen` | `GET /errands/{id}/matches`, `POST /errands/pause-category/{categoryId}` |
| `customer/shop_detail_screen` | `GET /shops/{id}`, `POST/DELETE /shops/{id}/favorite` |
| `customer/checkin` (trip sheet, success) | `POST /check-ins`, `GET /check-ins/visit-code`, `POST /check-ins/{id}/review` |
| `customer/nudge_preview_screen` | `GET /nudges/today`, `GET /nudges`, `POST /nudges/{id}/opened`, `POST /me/pause-today` |
| `customer/wins_screen` | `GET /me/stats`, `GET /me/visits?month=` |
| `customer/me_screen` | `GET /me`, `PATCH /me` (`pings_per_day`, `language`), `GET /me/stats`, `GET /me/alert-health`, `POST /support-tickets`, `POST /auth/logout` |
| `customer/report_screen` | `GET /config` (`report_reasons`), `POST /shops/{id}/report` |
| background location service | `POST /nudges/check` |
| `shop/shop_onboarding_screen` | `GET /categories`, `POST /vendor/shop` |
| `shop/add_service_screen` | `POST /vendor/services`, `PATCH /vendor/services/{id}`, `POST /vendor/shop/photos` |
| `shop/today_screen` | `GET /vendor/today`, `POST /vendor/open-today`, `POST /vendor/no-wait`, `POST /vendor/check-ins` |
| `shop/offers_screen` | `GET /vendor/offers`, `POST /vendor/offers`, `DELETE /vendor/offers/current` |
| `shop/code_screen` | `GET /vendor/check-in-code`, `POST /vendor/check-in-code/rotate` |
| `shop/reviews_screen` | `GET /vendor/reviews`, `POST /vendor/reviews/{id}/reply` |
| `shop/insights_screen` | `GET /vendor/insights?period=` |

## Push notification payloads

The data payload `type` is one of:

- `nudge` (with `nudge_id`)
- `support` (with `ticket_id`)
- `shop_status` (with `status`)
- `weekly_summary`

Push delivery uses the server's `PUSH_DRIVER`. It currently logs instead of sending until FCM is configured.
