# Luxora Hotel Suite — Interview Prep & Project Walkthrough

> Use this to answer "tell me about this project / how did you build it / how did you test it"
> with confidence. Read the **30-second pitch**, the **architecture**, the **bugs you fixed**
> (interviewers love debugging stories), and skim the **Q&A bank** at the bottom.

---

## 1. The 30-second pitch

> "I built **Luxora Hotel Suite**, a role-based hotel management web app in **PHP + MySQL**
> (the classic XAMPP stack). It runs the full guest lifecycle: online booking → check-in →
> adding services (laundry, room service) → check-out → automatic billing with tax → receipt.
> There are three user roles — **Clerk** (day-to-day operations), **Manager** (reports/revenue),
> and **Admin** (rooms, users, settings) — each with a gated dashboard.
> I hardened it against real bugs (a session-fixation vulnerability, a double-cookie session bug,
> broken relative links, and a fatal include path), and I wrote a **zero-dependency automated
> test suite (29 tests)** plus a manual **QA plan** covering functional and non-functional testing."

---

## 2. Tech stack & why

| Layer | Choice | Why (say this out loud) |
|---|---|---|
| Language | **PHP 8.2** | Server-rendered, ubiquitous in shared hosting, fast to build CRUD |
| Database | **MySQL / MariaDB** | Relational; the domain (rooms, reservations, bills) is inherently relational |
| DB access | **PDO + prepared statements** | Prevents SQL injection; DB-portable; named/positional binding |
| Front-end | HTML + CSS + a little JS, Bootstrap + Font Awesome (CDN) | Server-rendered pages; no build step needed |
| Styling | Custom CSS (`assests/css/style.css`) on top of the shared layout | Consistent "glass sidebar" theme across every page |
| Server | Apache (XAMPP) | Standard local dev environment |
| Testing | Custom mini test framework in plain PHP | No Composer/PHPUnit needed so it runs anywhere |

**Key phrase to use:** "I used **PDO prepared statements** everywhere, so the app is not
vulnerable to SQL injection — user input is always bound, never concatenated into SQL."

---

## 3. System architecture

```
Browser
   |  HTTP request (e.g. /reservations.php)
   v
Apache
   |
   v
PHP page (reservations.php)
   |-- require includes/auth.php       -> session + role gate
   |-- require includes/helpers.php    -> e() escaping, log_action_pdo(), get_setting()
   |-- require includes/Reservation.php -> business logic (book/cancel rules)
   |-- require includes/BillingEngine.php -> bill math + finalize
   |-- require config.php + db.php     -> settings + PDO connection
   |-- include includes/header.php     -> shared topbar + role-aware sidebar (uses $BASE)
   `-- include includes/footer.php     -> footer + JS
   |
   v
MySQL (hotel_management) <- tables: users, customers, rooms, reservations,
                             services, bills, bill_items, payments, reports, cron_log
```

### The request lifecycle (trace one action)
**"How does a booking flow work?"**
1. `public/book.php` collects guest + dates + room and posts.
2. `includes/Reservation.php::create()` runs inside a **transaction**:
   - validates room + dates,
   - **overlap guard**: rejects if the room is already booked for those dates,
   - inserts the customer, then the reservation with status `Confirmed`,
   - sets the room to `Reserved`.
3. `log_action_pdo()` writes an audit trail.
4. The clerk sees it on the dashboard; check-in flips it to `Checked-In` and the room to `Occupied`.

**Business rules I encoded (great to mention):**
- **No double-booking** — a room can't be booked for overlapping date ranges.
- **Card-on-file rule** — certain bookings require a card or a deposit.
- **7 PM rule** — same-day/no-card bookings are auto-cancelled after 7 PM if not confirmed (a cron/trigger).
- **Late check-out** — leaving after the scheduled check-out adds a late fee (billing).


---

## 4. Role-based access control (RBAC) & security

`includes/auth.php` enforces who can see what:
- `require_login()` → redirects to the login page if there's no valid session.
- `has_any_role([...])` / `require_role('admin')` → gates each page by role.
- Admin/Manager pages return **403 "Access denied"** for clerks (I tested this).

**Login security (important interview material):**
- Passwords are verified against stored hashes (I did **not** store plaintext).
- On login I call `session_regenerate_id(true)` to prevent **session fixation** — and I fixed a
  subtle bug where that call emitted a **stale duplicate cookie** that broke sessions. Now exactly
  one valid session cookie is sent.

**Say this if asked about security:** "Beyond SQL injection (prepared statements), I handled
session fixation on login, escaped all output with `htmlspecialchars` (the `e()` helper) to stop
XSS, and gated every page by role server-side — not just hidden in the UI."

---

## 5. Bugs I found & fixed (your best stories)

Interviewers ask "tell me about a hard bug." Pick 2–3 of these. Frame each as
**symptom → root cause → fix → lesson**:

1. **Fatal include path in `billing/bill_generator.php`**
   - *Symptom:* the page crashed with "Failed opening required `billing/config.php`."
   - *Cause:* requires used `__DIR__.'/config.php'` but the file lives one folder up.
   - *Fix:* corrected to `__DIR__.'/../config.php'`.
   - *Lesson:* always anchor includes to `__DIR__` and get the relative depth right.

2. **Double HTML document on the Check-out page**
   - *Cause:* it included the shared `header.php` **and** printed its own `<!DOCTYPE html>`.
   - *Fix:* rebuilt the page on the shared layout only; fixed a query that joined a
     non-existent table so it now shows the real checked-in guests.

3. **Broken relative sidebar links → 404s**
   - *Cause:* the shared sidebar used `../page.php`, which only works inside subfolders.
   - *Fix:* anchored every link to `BASE_URL` so they resolve from any folder and role.

4. **Session-fixation / duplicate-cookie login bug**
   - *Cause:* `session_regenerate_id(true)` emitted an old and a new cookie at once.
   - *Fix:* tombstone the old cookie so the browser keeps exactly one valid session id.

5. **Reservations page: missing layout + stray `?>`**
   - *Cause:* it never included `header.php` and leaked a literal `?>` onto the page.
   - *Fix:* added the layout include and removed the stray tag.

**Framing tip:** lead with the *symptom the user saw*, then the *root cause*, then the *fix*,
then the *lesson*. That structure shows methodical debugging.

---

## 6. Testing & QA (they will likely ask "how did you test it?")

### Automated tests — zero dependency, runs on XAMPP
```
php run_tests.php          # 29 tests, exits 0 on success
```
- `tests/bootstrap.php` — a tiny test framework (assertions, `setUp/tearDown`, skips, colors).
- `tests/test_billing.php` — bill math: room charge = rate × nights, +services, +late fee,
  −discount, +tax; finalize status (Unpaid/Partial/Paid).
- `tests/test_auth.php` — role gating returns 403 for the wrong role; login/session behavior.
- `tests/test_reservation.php` — booking persists, overlap/validation rules, cancellation.
- `tests/test_helpers.php` — `e()` escaping, `nights_between()`, `get_setting()`.
- Every DB test cleans up after itself (rows prefixed `ZZTEST*` are deleted in `tearDown`),
  so the suite is **repeatable and non-destructive**.

### Manual QA — `QA.md`
- **Functional** test cases: login per role, book → check-in → add service → check-out →
  bill → receipt, with expected vs. actual results.
- **Non-functional**: security (auth bypass attempts, XSS), input validation, responsive
  layout, and the fixed-defects log.

**Strong answer:** "I paired **automated unit tests** for the core logic (billing math, auth,
reservation rules) with a **manual QA plan** for the end-to-end flows and non-functional checks.
The test suite is repeatable and self-cleaning, and it gates regressions — for example, the
include-path and session bugs I fixed are covered by tests so they can't silently come back."

---

## 7. Likely questions & crisp answers

**Q: What's the difference between GET and POST here?**
GET for read/navigation; POST for state changes (book, check-in, checkout, cancel). Never mutate on GET.

**Q: How is the database normalized?**
3NF — separate tables for users, customers, rooms, reservations, services, bills, bill_items,
payments; foreign keys link them; no duplicated room/rate data.

**Q: How do you prevent SQL injection?**
PDO prepared statements with bound parameters everywhere; no string-concatenated SQL.

**Q: How do you prevent XSS?**
Every dynamic value goes through `e()` = `htmlspecialchars()` before printing.

**Q: What was the hardest part?**
The billing engine (room × nights + services + late fee − discount + tax, then payment status)
and making the session login bulletproof after the duplicate-cookie bug.

**Q: What would you do next?**
Wire room images, add password hashing to any remaining plaintext paths, add email/notification
on booking, containerize with Docker, and add an integration test that drives a full booking
through the real database.

---

## 8. One-line memory hooks
- **PDO prepared** = SQL-injection safe.
- **`session_regenerate_id` on login** = no session fixation.
- **`e()` on all output** = no XSS.
- **Transactions** around booking/checkout = consistent state.
- **`__DIR__` anchored includes + `BASE_URL` links** = no path bugs.
- **29 automated tests + QA.md** = proof it works and stays working.

