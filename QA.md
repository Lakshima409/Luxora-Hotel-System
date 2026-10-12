# 🧪 QA & Test Plan — Luxora Hotel Suite

This document records the **manual test cases**, **automated test coverage**, and **quality assurance** performed on the system. It is intended for evaluation and regression reference.

---

## 1. Test Environment

| Item | Value |
|---|---|
| Web server | Apache 2.4.58 (Win64) via XAMPP |
| PHP | 8.2.12 |
| Database | MariaDB (`hotel_management`), InnoDB, utf8mb4 |
| Base URL | `http://localhost/hotel-system/` |
| Test accounts | `clerk1 / 12345`, `admin1 / admin123` |

---

## 2. Automated Test Suite

A **zero-dependency** test suite (no PHPUnit/Composer required) lives in `tests/`. It runs on stock XAMPP PHP.

### How to run
From the project root:
```
C:\xampp\php\php.exe run_tests.php
# add -v to also list skipped tests
```

### Coverage summary
| Suite | File | What it verifies | Type |
|---|---|---|---|
| **AuthTest** | `tests/test_auth.php` | Login gate, role gate (clerk vs admin), absolute login URL, role shortcuts | Logic (child process) |
| **BillingMathTest** | `tests/test_billing.php` | rate×nights + services + late − discount + tax, subtotal never negative, missing reservation throws | Logic (fake PDO) |
| **HelpersTest** | `tests/test_helpers.php` | HTML escaping, nights calculation (min 1), settings default fallback, log errors swallowed | Logic |
| **ReservationFlowTest** | `tests/test_reservation.php` | create → confirm → check-in → check-out → cancel, room occupy/free, eligibility rules | **Live DB** (auto-skips if DB down) |

All test rows are prefixed `ZZTEST*` and deleted automatically after each run (`tearDown`), so the suite never pollutes real data.

### Current result
```
Luxora Hotel Suite — Automated Test Suite
PHP 8.2.12   DB: connected
Suites discovered: 4 across 4 file(s)

Summary:  29 passed, 0 failed, 0 skipped  (exit code 0)
```

> **Exit code** `0` = all passed; `1` = at least one failure. Suitable for a CI step.

---

> **Exit code** `0` = all passed; `1` = at least one failure. Suitable for a CI step.

---

## 3. Manual Functional Test Cases

**Test data prerequisite:** at least one Available room in `rooms`. Log in per the role noted.

### 3.1 Authentication & Access Control
| ID | Test | Steps | Expected | Result |
|---|---|---|---|---|
| M01 | Anonymous redirect | Open `/reservations.php` while logged out | Redirect to `login.php` (absolute URL, no 404) | ✅ |
| M02 | Wrong-role block | Log in as clerk, open `/admin/admin_dashboard.php` | `403 Access denied` message | ✅ |
| M03 | Correct-role access | Log in as admin, open `/admin/admin_dashboard.php` | Page renders, KPIs + room management shown | ✅ |
| M04 | Logout | Click **Logout** | Session destroyed, back to login | ✅ |

### 3.2 Reservation → Check-In → Check-Out → Billing (happy path)
| ID | Test | Steps | Expected | Result |
|---|---|---|---|---|
| M05 | Create reservation | Public **Book** form → submit valid guest + dates | Reservation `Confirmed`, room `Reserved` | ✅ |
| M06 | Duplicate overlap | Book the same room for overlapping dates | Rejected with "Room not available (overlap)" | ✅ |
| M07 | Date validation | Check-out before check-in | Rejected: "Check-out must be after check-in" | ✅ |
| M08 | Check-in | As clerk, open **Check-in**, assign an available room | Status → `Checked-In`, room `Occupied` | ✅ |
| M09 | Add service | **Billing → Add Service**, pick the checked-in reservation | Service saved, appears on bill preview | ✅ |
| M10 | Check-out | **Check-out** → CHECK-OUT the guest | Status → `Checked-Out`, room freed to `Available`, redirected to bill | ✅ |
| M11 | Generate bill | On bill page, confirm late fee / discount / payment, **Finalize** | Bill `Created`, total = subtotal + tax | ✅ |
| M12 | Receipt | Open the receipt | Printable bill with line items + tax breakdown | ✅ |
| M13 | Cancel | **Cancel** a `Confirmed` reservation | Status → `Cancelled`, confirm dialog shown first | ✅ |

### 3.3 Billing Math (spot-check, mirrors automated suite)
| ID | Scenario | Expected |
|---|---|---|
| M14 | 3 nights @ 100, no services | subtotal 300, tax (10%) 30, total **330** |
| M15 | 2 nights @ 150 + 50 services | subtotal 350, tax 35, total **385** |
| M16 | 1 night @ 100, late +20, discount −10 | subtotal 110, tax 11, total **121** |
| M17 | Discount > subtotal | subtotal floored at **0** (never negative) |
| M18 | Custom tax rate 20% | 100 → tax 20 → total **120** |

### 3.4 Reports & Admin
| ID | Test | Steps | Expected | Result |
|---|---|---|---|---|
| M19 | Reports page | Open **Reports** as clerk/admin | Charts/summary render, no errors | ✅ |
| M20 | Audit logs | Open admin audit section | `action_logs` entries listed (login, cancel, checkin, billing) | ✅ |
| M21 | Room management | **Room Management** table | All rooms listed; Edit/Delete links are absolute (no 404) | ✅ |


---

## 4. Non-Functional Test Cases

| ID | Category | Test | Expected | Result |
|---|---|---|---|---|
| N01 | Security — SQLi | Type `' OR '1'='1` in login/forms | Blocked by prepared statements (PDO) | ✅ |
| N02 | Security — XSS | Enter `<script>` as guest name | Rendered as escaped text (`&lt;script&gt;`) via `e()` | ✅ |
| N03 | Session fixation | Log in, then reuse old session id | Session id regenerated on login; stale cookie invalidated | ✅ |
| N04 | Robustness | Bill a non-existent reservation id | Graceful "Reservation not found" exception, no crash page | ✅ |
| N05 | Data integrity | Cancel → re-check-in a cancelled reservation | Rejected (only `Pending`/`Confirmed` eligible) | ✅ |
| N06 | Concurrency | Two check-ins for the last available room | Row lock (`FOR UPDATE`) prevents double-assign | ✅ |
| N07 | Responsive | View dashboards at 375px / 768px / 1440px | Sidebar collapses, tables scroll, no overlap | ✅ |

---

## 5. Bugs Found & Fixed During QA

| # | Bug | Severity | Fix |
|---|---|---|---|
| 1 | `bill_generator.php` fatal error — `require_once 'config.php'` without `/../` in `billing/` | 🔴 Critical | Corrected all requires to `__DIR__.'/../…'` |
| 2 | Admin dashboard raw PHP leaked on page + section rendered 3× | 🔴 Critical | Closed table properly, removed orphaned block |
| 3 | Room/action links relative → 404 from subfolders | 🟠 Major | Anchored to `$BASE` (absolute URLs) |
| 4 | Checkout page double `<html>` + dead query (wrong table/columns) | 🔴 Critical | Rebuilt on shared layout with real schema |
| 5 | `reservations.php` missing header/footer + stray `?>` | 🟠 Major | Added includes, removed stray tag |
| 6 | Sidebar links used `../` from root pages → 404 | 🟠 Major | All sidebar hrefs anchored to `BASE_URL` |
| 7 | Duplicate `Set-Cookie` on login broke session | 🟠 Major | Tombstone for stale cookie after regeneration |
| 8 | `receipt.php` footer include path wrong | 🟡 Minor | `__DIR__.'/../includes/footer.php'` |

---

## 6. Limitations / Known Gaps

- Receipt is a print **template**; payment state is shown but not sourced from a live payment ledger beyond `bills`.
- Room images are placeholders — no real photos wired in yet.
- Automated suite covers **Unit + Integration (DB)** layers; browser-level (E2E) automation (e.g. Selenium) is **not** included — the UI is covered by the manual cases in §3.

---

## 7. How to Re-Run Everything

```
# 1. Automated (logic + DB integration)
C:\xampp\php\php.exe run_tests.php

# 2. Manual smoke (authenticated page health)
#    Log in at http://localhost/hotel-system/login.php
#    then click through: Reservations → Check-in → Services →
#    Check-out → Billing → Reports, comparing to §3 expected results.
```

**QA status:** Core logic covered by 29 automated tests (all passing); all reported UI/fatal bugs fixed; manual checklist (M01–M21, N01–N07) defined for acceptance.

