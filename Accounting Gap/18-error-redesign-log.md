# 18 - Error Redesign Log

**Date:** 2026-08-26  
**Status:** **LOG ONLY - nothing is being built.** Recorded while the product is being
redesigned. Once the design is agreed, this becomes the input to the build.  
**Trigger:** the owner hit a bare `419 | PAGE EXPIRED` and asked for every error surface in the
system to be catalogued under the name "error redesign".

---

## 1. The finding that frames everything

There are **no custom error pages in this application at all.**

- `resources/views/errors/` **does not exist**.
- `bootstrap/app.php` -> `withExceptions()` is **empty** (a bare `//` comment).

Every error a user meets - the 419, a 404, a 403, a 500 - is Laravel's stock framework page: a
status number, a thin vertical rule, an uppercase message, on a blank ground. No logo, no
navigation, no footer, **and no way back other than the browser back button.**

Even `/logout` reached by GET returns a bare `405`.

> **Note on debug mode.** Dev currently runs `APP_DEBUG=true`. Any status without a shipped
> framework view (400, 405, 422 ...) renders a full stack trace instead of the minimal page.
> Statuses that DO have a view (404, 403, 419, 500) show the minimal page even in debug mode,
> because the framework prefers a status-matched view over the debug renderer. Production must
> never ship with debug on - that is a hardening item, not a design one.

---

## 2. How this was compiled

Eight parallel read-only census agents over distinct error surfaces, each finding then re-opened
at its cited `file:line` by a second agent instructed to be sceptical and drop or correct
anything that did not hold up. 16 agents, no failures, no writes.

**167 verified error surfaces.** Frequency mix: 44 dead-common, 73 occasional, 37 rare, 13 theoretical.

**99 of 167** offer the user no way back at all.

---

## 3. Design constraints that must survive the redesign

These are not preferences. Breaking them breaks the product.

### 3.1 The module 404 must stay indistinguishable from a genuine 404

`EnsureModuleEnabled` aborts **404, never 403**, when a company has a module switched off. This
is deliberate: a 403 would confirm the route exists and merely refuses them, which itself leaks
the existence of a feature the package deliberately hides. So this one error page must NOT get
friendly module-specific copy, an illustration, or an "upgrade your plan" call to action - any of
those leaks what is being hidden. It has to look exactly like a mistyped URL.

The gate is **not accounting-only**: `routes/web.php` also carries `module:crm`,
`module:payment_gateway`, `module:task_uploader` and `module:resayil`, so the same 404 conceals
five different modules.

### 3.2 The same entitlement failure currently surfaces two different ways

Routes wrapped in the `module:` middleware abort 404. But policies using the
`RequiresCompanyModule` trait return **403 "This action is unauthorized."** for the identical
condition. One disguise works; the other announces that the feature exists. This inconsistency is
a genuine leak and needs a decision, not just a restyle.

### 3.3 Every 403 says the same generic sentence

78 `Gate::authorize()` call sites, and **zero** use `Response::deny()` with a custom message, so
every authorization failure in the product reads "This action is unauthorized." The user is never
told which permission is missing or who to ask. This is a known recurring support issue.

### 3.4 The de-facto error UI today is the browser `alert()` box

158 `alert(` calls across the views, 81 of them on explicit failure paths. There is no global AJAX
error interceptor and no fetch wrapper - every async call hand-rolls its own failure path. No
JavaScript anywhere checks for `401` or `419`, so session expiry has no dedicated message and
degrades into whatever generic catch each call site happens to have.

---

## 4. Findings by surface

### 4.1 Session, CSRF & the 419  (16 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | 419 Page Expired on a stale login form | 419 | dead-common | None. No link, no button. Returning via  |
| 2 | 419 on any authenticated form submit after 2h idle — work is destroyed | 419 | dead-common | None, and worse than the login case: rel |
| 3 | 419 surfaced as a native alert() reading "CSRF token mismatch." | 419 (JSON body) | dead-common | None. Dismissing the alert returns to a  |
| 4 | Silent 302 to /login with no explanation — the graceful path that still confuses | 302 (401 JSON if Accept: application/json) | dead-common | Yes — this is the one path that fully wo |
| 5 | Wrong password — the same red toast, three times in a row for a real user | 302 + validation error | dead-common | Yes — they never leave the login page, a |
| 6 | APP_DEBUG=true turns every error body into a stack-trace dump on a public host | n/a (modifier on all of the above) | dead-common | n/a |
| 7 | 419 swallowed silently — button does nothing | 419 (JSON parsed and ignored, or HTML thrown into a JSON parser) | occasional | None — there is no visible failure at al |
| 8 | Sidebar currency converter prints "CSRF token mismatch." inline, on every page | 419 (JSON body) | occasional | None from the widget. The rest of the pa |
| 9 | Livewire native confirm(): "This page has expired." | 419 | occasional | Partial — pressing OK reloads, the auth  |
| 10 | wire:navigate link swaps the login page in with no page reload | 302 → 200 (login page) | occasional | Yes in practice — it is a login form, an |
| 11 | Raw {"message":"Unauthenticated."} JSON to in-page widgets | 401 (JSON) | occasional | None from the widget. |
| 12 | Login throttle — a red toast, not a 429 page | 302 + validation error (NOT 429) | occasional | Yes — they never leave the login page. T |
| 13 | Password-reset link expiry — inline error, or a 419 if the session died first | 302 + error bag (a) / 419 (b) | occasional | (a) Weak, and weaker than originally des |
| 14 | Orphaned two-step login at /password renders a form with an invisible empty email | 302 + validation error | rare | There is a "Forgot your password?" link  |
| 15 | Stock 429 Too Many Requests page on throttled public routes | 429 | rare | None. |
| 16 | 2FA middleware stack is registered but wired to nothing (and would 500 if switched on) | n/a (currently unreachable) | theoretical | n/a |

#### 4.1.1 419 Page Expired on a stale login form

- **Status:** 419 | **Frequency:** dead-common
- **Trigger:** User opens the login page, walks away (lunch, overnight, tab left open on a second monitor), comes back, types email + password, presses Sign In. Also fires on a browser-restored tab or a back-button return to a cached login page.
- **Mechanism:** After 120 min the session cookie and DB session row are gone. Illuminate\Foundation\Http\Middleware\ValidateCsrfToken compares the form's `_token` against a freshly-minted session token, throws Illuminate\Session\TokenMismatchException('CSRF token mismatch.'), which Handler::prepareException converts to Symfony HttpException(419) at Handler.php:641. renderHttpException finds no resources/views/errors/419 so it renders the framework's built-in errors::419, which is `@extends('errors::minimal')` with code '419' and message 'Page Expired'.
- **User sees:** Full-screen page, one centered line: `419 \| PAGE EXPIRED` in small gray uppercase letters, with a thin vertical rule between code and message. Browser tab title "Page Expired". No logo, no explanation, no next step. Their typed email and password are gone. Verified live: HTTP 419, 6609 bytes uncompressed / 2003 on the wire. The stock minimal view carries `dark:bg-gray-900` / `dark:text-white` under a prefers-color-scheme media query, so it is near-white on a light-mode machine and near-black on a dark-mode one — either way unbranded.
- **Way back:** None. No link, no button. Returning via the back button re-presents the cached login form still carrying the dead token, so pressing Sign In again 419s again — the live log shows exactly that, two 419s 77 seconds apart from the same browser. Only a manual reload of /login recovers.
- **Evidence:** /home/citycomm/development.citycommerce.group/.env:37-38 (SESSION_DRIVER=database, SESSION_LIFETIME=120); /home/citycomm/development.citycommerce.group/config/session.php:35 ('lifetime' => env('SESSION_LIFETIME', 120)); /home/citycomm/development.citycommerce.group/bootstrap/app.php:45-47 (empty withExceptions); /home/citycomm/development.citycommerce.group/routes/auth.php:30 (POST login); /home/citycomm/development.citycommerce.group/resources/views/auth/login.blade.php:32-33 (form + @csrf); /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:641; /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/419.blade.php; /home/citycomm/access-logs/development.citycommerce.group-ssl_log lines 434 and 496
- **Verify note:** VERIFIED (corrected). Corrections: (1) session lifetime is config/session.php:35, not :39. (2) Access log path is /home/citycomm/access-logs/development.citycommerce.group-ssl_log, NOT /home/citycomm/logs/ (that directory holds only rotated .gz archives; the Aug-2026 archive contains zero 419s). (3) There were TWO 419s from that user, not one: 26/Aug/2026:02:04:31 and 02:05:48, both POST /login, both 419 2003 bytes, referrer /login, Chrome 151 from 175.138.93.81. (4) Added the dark-mode caveat to user_sees. Full verified recovery trace: 02:05:53 GET /login 200 (manual reload) -> 02:05:55, 02:06:09, 02:06:21 POST /login each 302 back to /login (three failed credential attempts) -> 02:08:55 POST /login 302 -> 02:08:56 GET /tasks 302 -> 02:08:57 GET /tasks?invoiced=0&view_type=invoice 200. Roughly four minutes from first 419 to a working page. Confirmed that laravel.log contains zero occurrences of 'TokenMismatch' or 'CSRF token mismatch' — TokenMismatchException sits in $internalDontReport at Handler.php:154, so the team has no visibility into how often this happens.

#### 4.1.2 419 on any authenticated form submit after 2h idle — work is destroyed

- **Status:** 419 | **Frequency:** dead-common
- **Trigger:** Agent has an invoice / task / receipt-voucher / client form open, gets pulled into a phone call or a WhatsApp thread for two hours, comes back, finishes typing and hits Save.
- **Mechanism:** Identical to the login case — ValidateCsrfToken vs a regenerated session token → TokenMismatchException → HttpException(419) at Handler.php:641 → errors::419. Applies to every one of the 211 @csrf tokens across 119 blade files, since bootstrap/app.php declares no validateCsrfTokens(except:) exclusions at all, there is no app/Http/Middleware/VerifyCsrfToken.php override, no app/Exceptions/Handler.php, and no renderable()/reportable() callback registered in any provider.
- **User sees:** Same bare `419 \| PAGE EXPIRED` page. The half-hour of data entry that was in the POST body is gone — it was never persisted and the 419 page has no copy of it.
- **Way back:** None, and worse than the login case: reloading gets them to /login, they re-authenticate, land back on an empty form, and the work is unrecoverable. There is no draft/autosave anywhere.
- **Evidence:** /home/citycomm/development.citycommerce.group/bootstrap/app.php:24-44 (no CSRF exceptions declared) and :45-47 (empty withExceptions); /home/citycomm/development.citycommerce.group/resources/views (211 @csrf occurrences in 119 files; 58 in tasks/ invoice/ clients/ alone — both counts re-run and confirmed); /home/citycomm/development.citycommerce.group/routes/web.php:61 (Route::middleware(['auth'])->group opening the main app block); app/Exceptions/ contains only DotwTimeoutException.php
- **Verify note:** VERIFIED. Every count and every negative claim re-checked: 211 @csrf / 119 files / 58 in tasks+invoice+clients all exact; grep for 'renderable\|reportable\|Exceptions::' across app/Providers/ returns nothing; grep for 'redirectUsing' across app/ returns nothing. This is the highest-damage variant because the payload has real value — a designer should assume the 419 page needs to be able to say what was being saved and offer a re-login that returns to the same form.

#### 4.1.3 419 surfaced as a native alert() reading "CSRF token mismatch."

- **Status:** 419 (JSON body) | **Frequency:** dead-common
- **Trigger:** Bulk-editing tasks, saving a setting, or any of the in-page AJAX actions that surface their own failure message, on a page that has been open past the session lifetime.
- **Mechanism:** fetch() that sets `Accept: application/json` makes Handler::shouldReturnJson true, so the 419 comes back as JSON rather than HTML. With APP_DEBUG=true the body is {"message":"CSRF token mismatch.","exception":"Symfony\\Component\\HttpKernel\\Exception\\HttpException","file":"/home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php","line":641,"trace":[...]} — verified live at exactly 13715 bytes. The JS parses it fine, finds no `data.success`, and falls into the else branch which alerts data.message verbatim.
- **User sees:** An OS-native modal alert box titled with the domain, body text exactly: `CSRF token mismatch.` Nothing else. The page behind it is unchanged and still looks logged in.
- **Way back:** None. Dismissing the alert returns to a page that is silently dead — every subsequent action will fail the same way. Nothing tells them to re-login.
- **Evidence:** /home/citycomm/development.citycommerce.group/resources/views/tasks/index.blade.php:436-457 (the submitBulkEdit fetch; `alert(data.message \|\| 'Failed to update tasks.')` is at :451); /home/citycomm/development.citycommerce.group/resources/views/settings/partial/agent_loss.blade.php:482-483 (`alert(data.message \|\| 'Failed to save setting')`); 42 of the 90 X-CSRF-TOKEN fetch sites across resources/views set Accept: application/json, and 28 of those do no HTTP-status check before reading `data.success`; /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:641; live curl -X POST -H 'Accept: application/json' against /login
- **Verify note:** VERIFIED (corrected). Corrections: (1) the cited alert is at tasks/index.blade.php:451, inside the fetch block spanning 436-457 — the original range 439-457 pointed at the headers object, not the alert. (2) The Accept-header count is 42 of 90 CSRF-bearing fetch sites, not 47 (the original 47 was a whole-file grep count that double-counted files where Accept appears in fetches with no CSRF header). (3) tasks/index.blade.php:1743-1761 was cited here but belongs to the silent variant — moved. The live JSON body reproduced byte-for-byte including line 641 and the absolute server path, so the disclosure claim holds: this response leaks the full framework stack trace to the browser because APP_DEBUG=true.

#### 4.1.4 Silent 302 to /login with no explanation — the graceful path that still confuses

- **Status:** 302 (401 JSON if Accept: application/json) | **Frequency:** dead-common
- **Trigger:** After expiry the user clicks any normal link, or reloads, or opens a bookmark. This is the single most common way people discover they were logged out.
- **Mechanism:** Illuminate\Auth\Middleware\Authenticate::unauthenticated() throws AuthenticationException; Handler::unauthenticated() calls redirect()->guest(route('login')), which also stashes url.intended. AuthenticatedSessionController::store() then honours it via redirect()->intended(). Verified live: GET /tasks → 302 → https://development.citycommerce.group/login.
- **User sees:** The login screen, with nothing said about why. No banner, no "your session expired", no note that they'll be returned to where they were. It reads as being randomly kicked out.
- **Way back:** Yes — this is the one path that fully works. url.intended survives, so logging in returns them to the page they wanted.
- **Evidence:** /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:716-721; /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php:100-120 (redirectTo returns null unless static::$redirectToCallback is set; grep for 'redirectUsing' across app/ finds nothing); /home/citycomm/development.citycommerce.group/app/Http/Controllers/Auth/AuthenticatedSessionController.php:64 (redirect()->intended(route('dashboard', absolute: false))); /home/citycomm/access-logs/development.citycommerce.group-ssl_log lines 419-421
- **Verify note:** VERIFIED, and now corroborated by a real user session rather than only by curl. Access log, same browser as the 419 above: 02:08:55 POST /login → 302 (173 bytes); 02:08:56 GET /tasks → 302 (196); 02:08:57 GET /tasks?invoiced=0&view_type=invoice → 200 (69930). That is redirect()->intended() demonstrably returning the user to the page they were originally denied. The designer's free slot claim also holds: /home/citycomm/development.citycommerce.group/resources/views/auth/login.blade.php:4-21 renders session('status') as a green full-width bar fixed to the top that self-fades after 5s (setTimeout at :12, 5000ms at :19), and nothing currently populates it on expiry.

#### 4.1.5 Wrong password — the same red toast, three times in a row for a real user

- **Status:** 302 + validation error | **Frequency:** dead-common
- **Trigger:** User mistypes their password, or is not sure which account they are on, and submits. Overwhelmingly the most frequent failed interaction on the login page.
- **Mechanism:** LoginRequest::authenticate() calls Auth::attempt(); on failure it hits the rate limiter and throws ValidationException::withMessages(['email' => trans('auth.failed'), 'password' => trans('auth.failed')]) — the same message keyed twice. Laravel converts that to a 302 back to /login with the error bag flashed.
- **User sees:** Back on the branded login page. Because the message is bound to BOTH the email and password keys, guest.blade.php's $errors->all() loop renders "These credentials do not match our records." as TWO stacked fixed red boxes top-right, and login.blade.php:52-58 prints it a THIRD time inline under the email field as "Error: These credentials do not match our records." Password field is cleared; email is repopulated via old('email').
- **Way back:** Yes — they never leave the login page, and the form is ready to retry. Getting it wrong five times escalates to the throttle surface above.
- **Evidence:** /home/citycomm/development.citycommerce.group/app/Http/Requests/Auth/LoginRequest.php:60-67; /home/citycomm/development.citycommerce.group/resources/views/layouts/guest.blade.php:26-36 (loops $errors->all()); /home/citycomm/development.citycommerce.group/resources/views/auth/login.blade.php:52-58; /home/citycomm/access-logs/development.citycommerce.group-ssl_log lines 439-446 (three consecutive POST /login → 302 at 02:05:55, 02:06:09, 02:06:21, each followed by GET /login 200 at 3654-3655 bytes vs the 3378-byte clean page)
- **Verify note:** ADDED IN VERIFY. Tripped over while confirming the 419 trace: the same real user who hit the two 419s then failed authentication three consecutive times before succeeding, which makes this the single most-hit error surface in the login flow and it was absent from the census. The triple-render is a genuine visual defect, not a guess — it follows directly from withMessages() setting the identical string on two keys at LoginRequest.php:63-66 while guest.blade.php iterates $errors->all() rather than ->unique(). Included because it shares the login page's error real estate with the 419 and throttle surfaces, so a designer redesigning that page has to solve all three together.

#### 4.1.6 APP_DEBUG=true turns every error body into a stack-trace dump on a public host

- **Status:** n/a (modifier on all of the above) | **Frequency:** dead-common
- **Trigger:** Any of the above that returns JSON, plus any uncaught non-HTTP exception anywhere in the app.
- **Mechanism:** No config cache exists (bootstrap/cache/ contains only packages.php, services.php and lighthouse-schema.php — no config.php), so .env applies live: APP_ENV=local, APP_DEBUG=true. Handler::convertExceptionToArray therefore includes exception class, absolute file path, line and full trace in JSON bodies; and filp/whoops is installed while spatie/laravel-ignition is not, so non-HTTP 500s render the Whoops source-code page rather than errors::500.
- **User sees:** For JSON: a 13715-byte blob starting {"message":"CSRF token mismatch.","exception":"Symfony\\Component\\HttpKernel\\Exception\\HttpException","file":"/home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php","line":641,...}. For 500s: the Whoops page with syntax-highlighted source from the server. Only the pure HTTP-status errors (419/404/403/429) get the bare minimal page.
- **Way back:** n/a
- **Evidence:** /home/citycomm/development.citycommerce.group/.env:2 (APP_ENV=local), :4 (APP_DEBUG=true), :6 (APP_URL=http://127.0.0.1:8000 — wrong for this host); /home/citycomm/development.citycommerce.group/bootstrap/cache/ directory listing (no config.php); vendor/filp/whoops present, vendor/spatie/laravel-ignition absent (ls errors); /home/citycomm/development.citycommerce.group/routes/auth.php:17-20 (GET/POST /register gated only on app()->environment('local'), calling RegisteredUserController::storeAdmin); live curl of /register returning 200
- **Verify note:** VERIFIED, every element reproduced live — the byte count, the line number 641, the absolute path, the /register 200, and the empty bootstrap/cache. Not an error surface in itself: a modifier that changes what every other surface in this census looks like, and the reason the 419 JSON body is a stack trace rather than one line. Also the reason public self-registration of ADMIN accounts is currently live at https://development.citycommerce.group/register, since APP_ENV=local satisfies the environment gate on a publicly reachable host.

#### 4.1.7 419 swallowed silently — button does nothing

- **Status:** 419 (JSON parsed and ignored, or HTML thrown into a JSON parser) | **Frequency:** occasional
- **Trigger:** Same expiry, but on the in-page AJAX actions whose failure path only reaches console.error. User clicks Save/Apply, or a panel tries to lazy-load its data, and nothing at all happens.
- **Mechanism:** Two distinct silent paths, both ending in a console-only handler. (a) The fetch DOES send Accept: application/json, so the 419 JSON parses cleanly; the code then tests `if (data.success)` with no else, or lands in a catch that only calls console.error — e.g. settings/partial/notifications.blade.php loads its settings, gets the CSRF-mismatch JSON, `data.success` is undefined, and the panel just stays empty. (b) The fetch does NOT send Accept, so Laravel returns the 6.6 KB HTML errors::419 page and `response.json()` throws a SyntaxError on the leading `<`, which lands in a catch that only calls console.error. 23 of the 90 CSRF-bearing fetch sites have no user-visible failure output anywhere in the surrounding block — 13 of them are variant (a), 10 are variant (b).
- **User sees:** Absolutely nothing. No spinner resolution, no message, no state change — or a panel stuck permanently empty because its loader silently failed. Users read this as "the button is broken" and click it repeatedly.
- **Way back:** None — there is no visible failure at all, so there is nothing to click.
- **Evidence:** /home/citycomm/development.citycommerce.group/resources/views/settings/partial/notifications.blade.php:110-127 (Accept: application/json, `if (data.success)` with no else, catch → console.error only) and :140-152; /home/citycomm/development.citycommerce.group/resources/views/tasks/index.blade.php:1743-1761 (column-preference save: Accept at :1746, `if (!data.success) console.error(...)` at :1757-1758, `.catch(error => console.error(...))` at :1761); /home/citycomm/development.citycommerce.group/resources/views/settings/partial/ai_config.blade.php:187-209; /home/citycomm/development.citycommerce.group/resources/views/version/index.blade.php:340,369 (no Accept); /home/citycomm/development.citycommerce.group/resources/views/livewire/chat.blade.php:742,1164,1216,1268 (no Accept); /home/citycomm/development.citycommerce.group/resources/views/companies/companiesList.blade.php:544 (no Accept)
- **Verify note:** CORRECTED. The surface is real but the original mechanism and numbers were both wrong. (1) It is NOT defined by the missing Accept header: the majority of the truly silent sites (13 of 23) DO send Accept: application/json and simply ignore the parsed error object. (2) The example originally cited as proof — tasks/index.blade.php:1755-1761 — sends Accept at :1746, so it is the JSON variant, not the SyntaxError variant. (3) The count '43 fetch sites that do NOT send Accept' was wrong arithmetic (90 minus 47); the real split is 42 with Accept / 48 without, and 'without Accept' does not imply silent. (4) A third, previously unlisted variant exists and is worth a designer's attention: several no-Accept sites DO check `!response.ok` and show a useless generic alert — currency-exchange/index.blade.php:259-262, :292-295, :322-325 all pop `alert('Something went wrong')` on a 419. Classification method: each of the 90 X-CSRF-TOKEN occurrences was windowed -30/+55 lines and scanned for alert(/Swal./toastr/this.error=/innerHTML versus console.error only; 56 of 90 give some visible feedback, 23 give none, 11 were ambiguous. Still arguably worse than the alert() variant because the user has no idea anything failed and may believe the data saved.

#### 4.1.8 Sidebar currency converter prints "CSRF token mismatch." inline, on every page

- **Status:** 419 (JSON body) | **Frequency:** occasional
- **Trigger:** User idly uses the currency-converter widget in the left sidebar (present on every authenticated screen) on a tab that has been open a while.
- **Mechanism:** Alpine component POSTs to route('exchange.convert') with Accept: application/json; the 419 JSON comes back, `!res.ok` fires, `msg` is taken from `payload.message` — the raw framework string — and thrown; the catch assigns it to the component's `error` property, which is rendered inline in the widget.
- **User sees:** The literal string `CSRF token mismatch.` in the widget's error slot, embedded in the app chrome, on any of 153 screens.
- **Way back:** None from the widget. The rest of the page still looks normal.
- **Evidence:** /home/citycomm/development.citycommerce.group/resources/views/layouts/sidebar.blade.php:107 (convertUrl: '{{ route('exchange.convert') }}'), :410-435 (fetch with Accept: application/json at :415, `if (!res.ok \|\| ...)` at :433, `throw new Error(msg)` at :435), :462 (this.error = e?.message \|\| ...); /home/citycomm/development.citycommerce.group/routes/web.php:742-743 (POST exchange/convert, inside the web middleware group, CSRF applies); /home/citycomm/development.citycommerce.group/resources/views/components/layouts/app.blade.php:64 (@include('layouts.sidebar')), used by 153 views via <x-app-layout>
- **Verify note:** VERIFIED. Every line reference landed exactly: :107 is the convertUrl, the fetch opens at :410 with the Accept header at :415, the throw is at :435, and `this.error = e?.message` is at :462. Route confirmed at web.php:742-743 with a source comment explaining it is deliberately outside the exchange.* group. Sidebar include and the 153-view x-app-layout count both re-confirmed. Notable because it is the one place a raw framework string is rendered inside the branded UI rather than on a standalone error page.

#### 4.1.9 Livewire native confirm(): "This page has expired."

- **Status:** 419 | **Frequency:** occasional
- **Trigger:** User clicks the notification bell filters (All / Read / Unread), marks a notification read, or triggers any wire:click, after the session died. The notification component is mounted in the nav on every authenticated page.
- **Mechanism:** POST /livewire/update is registered with ->middleware('web'), so it runs through ValidateCsrfToken and 419s. Livewire's client checks `response.status === 419` and calls handlePageExpiry(), which is a bare browser confirm().
- **User sees:** An unstyled OS dialog: "This page has expired. / Would you like to refresh the page?" with OK / Cancel. No branding, no explanation of what expired.
- **Way back:** Partial — pressing OK reloads, the auth middleware 302s to /login, and after logging in url.intended returns them to the page. Pressing Cancel leaves a dead page with no further indication. It is the least-bad existing path in the whole area, and it is entirely Livewire's, not the app's.
- **Evidence:** /home/citycomm/development.citycommerce.group/vendor/livewire/livewire/src/Mechanisms/HandleRequests/HandleRequests.php:21 (Route::post('/livewire/update', $handle)->middleware('web')); /home/citycomm/development.citycommerce.group/vendor/livewire/livewire/dist/livewire.js:4324-4325 (if response.status === 419 → handlePageExpiry()) and :4345-4346 (confirm("This page has expired.\nWould you like to refresh the page?") && window.location.reload()); /home/citycomm/development.citycommerce.group/resources/views/components/layouts/app.blade.php:42 (@livewireStyles) and :114 (@livewireScripts); /home/citycomm/development.citycommerce.group/resources/views/layouts/mobile-drawer.blade.php:453 (<livewire:notification />) included via /home/citycomm/development.citycommerce.group/resources/views/layouts/navigation.blade.php:1; /home/citycomm/development.citycommerce.group/resources/views/livewire/notification.blade.php:6,15,24,177 (wire:click handlers); /home/citycomm/development.citycommerce.group/app/Livewire/Notification.php
- **Verify note:** VERIFIED (corrected). Corrections: (1) the confirm() call is at livewire.js:4346, not :4347. (2) Added the missing load-bearing evidence the original lacked — that /livewire/update actually carries CSRF: HandleRequests.php:21 registers it with ->middleware('web'). Livewire is v3.6.4 (composer.lock:3721-3722). Livewire is loaded on all 153 <x-app-layout> pages, so this dialog can fire almost anywhere. The three admin DOTW tabs using wire:poll.30000ms are confirmed (resources/views/livewire/admin/dotw-dashboard-tab.blade.php:1, dotw-error-tracker-tab.blade.php:1, dotw-booking-lifecycle-tab.blade.php:1) — on those screens the dialog pops up SPONTANEOUSLY within 30 seconds of expiry with no user action at all.

#### 4.1.10 wire:navigate link swaps the login page in with no page reload

- **Status:** 302 → 200 (login page) | **Frequency:** occasional
- **Trigger:** After expiry, user clicks Users List / Companies List / Settings in the sidebar or mobile drawer — the six links that use Livewire SPA navigation.
- **Mechanism:** wire:navigate calls performFetch, which does a plain fetch GET; the auth middleware 302s to /login; fetch follows it, and Livewire reads `response.url` into `finalDestination`, swaps the returned login-page HTML into the document and pushes that URL to history. The @persist('resayil-drawer') block has no matching persist target inside the guest layout, so the drawer is discarded rather than stranded.
- **User sees:** The whole screen is replaced by the login form with no browser reload and no loading indication — the sidebar and nav simply vanish mid-click. The address bar correctly reads /login. Nothing explains why.
- **Way back:** Yes in practice — it is a login form, and url.intended is stashed by redirect()->guest(), so signing in returns them where they were going. The transition is jarring and unexplained but not a dead end.
- **Evidence:** /home/citycomm/development.citycommerce.group/vendor/livewire/livewire/dist/livewire.js:7429-7448 (performFetch: `finalDestination = createUrlObjectFromString(response.url)`, then callback(html, finalDestination)) and :7335-7336 (updateUrlAndStoreLatestHtmlForFutureBackButtons → pushUrl(destination, html)); /home/citycomm/development.citycommerce.group/resources/views/layouts/menu.blade.php:179,185,345; /home/citycomm/development.citycommerce.group/resources/views/layouts/mobile-drawer.blade.php:184,186,272; /home/citycomm/development.citycommerce.group/resources/views/components/layouts/app.blade.php:105 (source comment: persisted element 'unmounts as normal (no @persist match on the far side)'), :109-111
- **Verify note:** CORRECTED, and upgraded from PLAUSIBLE to source-confirmed without needing a browser. The original's two open questions are both now answered from livewire.js: (1) the address bar shows /login, NOT /settings — performFetch explicitly takes the post-redirect `response.url` as finalDestination and that is what gets pushed to history; (2) there are no 'app chrome remnants' — the only @persist block in the app (app.blade.php:109-111, the Resayil drawer) has no match in the guest layout, and app.blade.php:105 documents that it therefore unmounts normally. Also corrected the count: six wire:navigate links, not three (menu.blade.php has three and mobile-drawer.blade.php has three more). The remaining user_sees claim about 'no browser reload' follows directly from the fetch+swap mechanism.

#### 4.1.11 Raw {"message":"Unauthenticated."} JSON to in-page widgets

- **Status:** 401 (JSON) | **Frequency:** occasional
- **Trigger:** A long-open page's background fetch (dashboards, lazy-loaded panels, any GET returning JSON) fires after the session died.
- **Mechanism:** Same AuthenticationException, but shouldReturnJson() is true so Handler::unauthenticated() returns response()->json(['message' => 'Unauthenticated.'], 401) instead of the redirect. Verified live: GET /tasks with Accept: application/json → 401 {"message":"Unauthenticated."}.
- **User sees:** Depends on the caller — usually nothing (console.error) or a widget stuck in its loading state. No caller in the codebase branches on 401 to trigger a re-login.
- **Way back:** None from the widget.
- **Evidence:** /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:716-721; live curl -H 'Accept: application/json' https://development.citycommerce.group/tasks returning exactly {"message":"Unauthenticated."} with status 401
- **Verify note:** VERIFIED. The 401 body is exactly as claimed — and note it is a bare one-liner, not a debug blob, because AuthenticationException is converted before the debug-trace path. The dead-route caveat also holds: /api/payments/{id}/partials and /api/payments/{id}/transactions sit behind auth:sanctum at routes/api.php:156-159, bootstrap/app.php registers no stateful/session middleware for the api group (grep for EnsureFrontendRequestsAreStateful across bootstrap/ and app/ finds nothing), and no blade anywhere references those paths — so that particular 401 is unreachable/dead.

#### 4.1.12 Login throttle — a red toast, not a 429 page

- **Status:** 302 + validation error (NOT 429) | **Frequency:** occasional
- **Trigger:** User forgets which of their passwords it is and gets it wrong 5 times from the same IP with the same email.
- **Mechanism:** LoginRequest::ensureIsNotRateLimited() fires a Lockout event and throws a ValidationException carrying trans('auth.throttle') — deliberately NOT a ThrottleRequestsException. So it is a 302 back to /login with an error bag, not an HTTP 429.
- **User sees:** They stay on the branded login page. A fixed red box appears top-right with a white × close button reading "Too many login attempts. Please try again in N seconds." The same message also renders inline under the email field via login.blade.php:52-58 prefixed with a bold "Error:", so it appears twice.
- **Way back:** Yes — they never leave the login page. This is the best-behaved surface in the whole area.
- **Evidence:** /home/citycomm/development.citycommerce.group/app/Http/Requests/Auth/LoginRequest.php:78-94 (RateLimiter::tooManyAttempts(..., 5), event(new Lockout), ValidationException with auth.throttle), :99-102 (throttleKey = lowercased email + '\|' + IP), :55-70 (authenticate/RateLimiter::hit); rendered by /home/citycomm/development.citycommerce.group/resources/views/layouts/guest.blade.php:26-36 and inline by /home/citycomm/development.citycommerce.group/resources/views/auth/login.blade.php:52-58; string from vendor/laravel/framework/src/Illuminate/Translation/lang/en/auth.php
- **Verify note:** VERIFIED (minor correction). throttleKey is at LoginRequest.php:99-102, not :55-70 (that range is authenticate()). The lang string is Laravel's stock 'Too many login attempts. Please try again in :seconds seconds.' — there is no lang/en/auth.php override in the project, so the wording is the framework default. The duplicate-render claim is confirmed: guest.blade.php:26-36 loops $errors->all() into a fixed red box while login.blade.php:52-58 separately prints $errors->first('email'). Worth calling out to a designer precisely because it is the counter-example: same failure family, completely different (and acceptable) treatment.

#### 4.1.13 Password-reset link expiry — inline error, or a 419 if the session died first

- **Status:** 302 + error bag (a) / 419 (b) | **Frequency:** occasional
- **Trigger:** User requests a reset, the mail sits in their inbox for an hour or more, then they click the link and submit a new password.
- **Mechanism:** Two distinct outcomes. (a) Token older than 60 min: Password::reset returns PASSWORD_INVALID_TOKEN and NewPasswordController::store returns back()->withInput()->withErrors(['email' => __($status)]). (b) The reset page itself sat open past 120 min, or the reset link is opened in a browser whose guest session already lapsed: the POST 419s at the CSRF middleware and the user never reaches the controller at all.
- **User sees:** (a) The reset form again with "This password reset token is invalid." rendered twice — once as a fixed red box top-right from guest.blade.php:26-36, once inline under the Email field from the x-input-error at :26. (b) The bare `419 \| PAGE EXPIRED` page.
- **Way back:** (a) Weak, and weaker than originally described. The reset-password page contains exactly one link: the app logo at :5 pointing at url('/'), which for a guest 302s to /login, from where they can click "Forgot your password?". There is no "Forgot your password?" link on the reset page itself and no explicit way to request a fresh link. (b) None.
- **Evidence:** /home/citycomm/development.citycommerce.group/config/auth.php:106 ('expire' => 60 under passwords.users); /home/citycomm/development.citycommerce.group/app/Http/Controllers/Auth/NewPasswordController.php:41-59; /home/citycomm/development.citycommerce.group/routes/auth.php:44-48 (both under 'guest' middleware, both CSRF-protected); /home/citycomm/development.citycommerce.group/resources/views/auth/reset-password.blade.php:15-16 (form + @csrf), :26 (x-input-error for email), :5 (the page's only anchor); string from vendor/laravel/framework/src/Illuminate/Translation/lang/en/passwords.php ('token' => 'This password reset token is invalid.')
- **Verify note:** CORRECTED. The way_back field was wrong: grep for 'password.request' or 'Forgot' across resources/views/auth/reset-password.blade.php returns nothing, and the file's only <a> tag is the logo at line 5. The user must guess that the logo leads somewhere useful. Also added that the error double-renders, same as the login surfaces. Everything else verified: config/auth.php:106 expire=>60, controller lines, guest-middleware CSRF exposure. Variant (b) remains the nastier one and is quite likely for exactly this flow, since reset emails routinely sit unopened for longer than the 120-minute session window.

#### 4.1.14 Orphaned two-step login at /password renders a form with an invisible empty email

- **Status:** 302 + validation error | **Frequency:** rare
- **Trigger:** Reaching https://development.citycommerce.group/password directly (it returns 200), refreshing that page, or arriving there with an expired session.
- **Mechanism:** AuthenticatedSessionController::check_email() stashes the email in the session and redirects to route('password'). PasswordController::index() reads session('email') and immediately session()->forget('email'), so the value survives exactly one render. The view then emits <input type="hidden" value="" name="email"> and posts to route('login'), where LoginRequest requires email → ValidationException.
- **User sees:** A password-only form. On submit, a red box saying "The email field is required" — for a field that is not visible anywhere on the screen.
- **Way back:** There is a "Forgot your password?" link (password.blade.php:26-29) but no link back to /login.
- **Evidence:** /home/citycomm/development.citycommerce.group/app/Http/Controllers/Auth/AuthenticatedSessionController.php:24-49 (esp. :41 session()->put and :43 redirect to route('password')); /home/citycomm/development.citycommerce.group/app/Http/Controllers/Auth/PasswordController.php:14-22 (:16 get, :19 forget); /home/citycomm/development.citycommerce.group/resources/views/auth/password.blade.php:2-4; /home/citycomm/development.citycommerce.group/routes/auth.php:32-36; live curl of /password returning 200 with literally `<input type="hidden" value="" name="email">`
- **Verify note:** VERIFIED, including the part the original could only infer: a live fetch of /password returns 200 and the rendered HTML contains exactly `<input type="hidden" value="" name="email">`, so the empty-hidden-field claim is observed, not deduced. REACHABLE BUT ORPHANED confirmed: grep across resources/, app/ and routes/ for 'check-email'/'check_email' finds only the controller method and the route definition — no view anywhere posts to it, so nothing in the current UI leads users into this flow. Treat as dead code that is still publicly addressable, not as a live path.

#### 4.1.15 Stock 429 Too Many Requests page on throttled public routes

- **Status:** 429 | **Frequency:** rare
- **Trigger:** Someone opens a company-registration invite link repeatedly, resubmits the registration form more than 5×/min, or spams the resend-verification-email button.
- **Mechanism:** Illuminate\Routing\Middleware\ThrottleRequests throws ThrottleRequestsException (HttpException 429) → errors::429, which is `@extends('errors::minimal')` with code '429' and message 'Too Many Requests'.
- **User sees:** The same bare minimal page: `429 \| TOO MANY REQUESTS`. No Retry-After shown in human terms, no link.
- **Way back:** None.
- **Evidence:** /home/citycomm/development.citycommerce.group/routes/web.php:1121 and :1123 (throttle:20,1 on company-register.show and company-register.agents-template), :1125 (throttle:5,1 on company-register.store); /home/citycomm/development.citycommerce.group/routes/auth.php:56 (throttle:6,1 on verification.verify), :60 (throttle:6,1 on verification.send); /home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/429.blade.php
- **Verify note:** VERIFIED, and the sceptical framing turned out to be exactly right. Confirmed in /home/citycomm/logs/development.citycommerce.group-ssl_log-Aug-2026.gz: 14 requests returned 429 in the whole August archive, every one of them from GPTBot/1.4 (34.138.117.119, 34.67.216.136, 74.7.243.207 and others), every one with a 0-byte body, hitting /.aws/credentials, /private-key and / — i.e. produced by the edge/CSF layer, not by Laravel's ThrottleRequests, which would have returned a 6.6 KB body. Zero real-user 429s. The email-verification throttles are effectively dead: User implements MustVerifyEmail (app/Models/User.php:13) but grep for the 'verified' middleware across routes/web.php, routes/auth.php and routes/api.php returns nothing, so nobody is ever sent through verification.

#### 4.1.16 2FA middleware stack is registered but wired to nothing (and would 500 if switched on)

- **Status:** n/a (currently unreachable) | **Frequency:** theoretical
- **Trigger:** None today. Would trigger the moment anyone attaches the '2fa' or 'check2fa' middleware to a route and flips APP_OTP_ENABLED.
- **Mechanism:** Three separate 2FA implementations coexist and none is mounted. (1) PragmaRX\Google2FALaravel\Middleware aliased as '2fa' and App\Http\Middleware\CheckFactorAuthentication aliased as 'check2fa' — grep across routes/ and app/Http/Controllers/ finds ZERO usages of either alias as middleware (the only hits are a route NAMED '2fa' and one named 'verify2fa'). (2) APP_OTP_ENABLED=false, so PragmaRX would short-circuit anyway. (3) If enabled, config/google2fa.php defaults throw_exceptions to true, so a wrong OTP raises PragmaRX\Google2FALaravel\Exceptions\InvalidOneTimePassword — an uncaught 500. (4) CheckFactorAuthentication dereferences Auth::user()->two_factor_code with no null guard → fatal on a guest. (5) App\Http\Middleware\Verify2FA (not even aliased) calls action("Auth\OTPController@show"), and no OTPController exists in app/Http/Controllers/Auth/.
- **User sees:** Nothing today — the 2FA screens (resources/views/auth/pin.blade.php, two-fa.blade.php, both present) are reachable by URL but enforce nothing. POST /verify2fa is a stub closure that redirects to the dashboard without checking any code.
- **Way back:** n/a
- **Evidence:** /home/citycomm/development.citycommerce.group/bootstrap/app.php:26-27 (aliases); /home/citycomm/development.citycommerce.group/app/Http/Middleware/CheckFactorAuthentication.php:20; /home/citycomm/development.citycommerce.group/app/Http/Middleware/Verify2FA.php:26,40; /home/citycomm/development.citycommerce.group/config/google2fa.php:8 ('enabled' => env('APP_OTP_ENABLED', true)), :74 ('throw_exceptions' => env('OTP_THROW_EXCEPTION', true)); /home/citycomm/development.citycommerce.group/.env:90 (APP_OTP_ENABLED=false), :93 (EMAIL_OTP_ENABLED=false); /home/citycomm/development.citycommerce.group/routes/web.php:85-87 (POST verify2fa is a closure that just redirects to dashboard), :91-92; app/Http/Controllers/Auth/ directory listing contains no OTPController
- **Verify note:** VERIFIED — all five sub-claims independently confirmed, including that no route in routes/web.php or routes/auth.php uses '2fa' or 'check2fa' as middleware, and that OTPController genuinely does not exist. One correction in the tail note: the working email-OTP flow for password changes is at routes/web.php:71-76 (correct as cited), but the degradation message quoted belongs to ProfileController::showConfirmCodeForm at app/Http/Controllers/ProfileController.php:472 ('Please request a verification code first.', redirecting to profile.edit?tab=Security), not to verifyCode — verifyCode at :434-458 returns back()->withErrors(['code' => 'Invalid or expired code.']) at :449. Both degrade acceptably. Honest read stands: 2FA is NOT a live error surface on this deployment; it is included because turning it on would immediately produce 500 Whoops pages rather than a styled OTP-failure screen.

---

### 4.2 HTTP status codes & explicit aborts  (33 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Genuine 404 — unknown URL, dead link, deleted record | 404 | dead-common | None whatsoever. |
| 2 | 403 from policy authorization — under-privileged user opens a page they can see linked | 403 | dead-common | None. |
| 3 | 403 'Unauthorized action.' hand-rolled role checks scattered across controllers | 403 | dead-common | None. AdminUsersController.php:100 in pa |
| 4 | 419 Page Expired on a stale form or expired session | 419 | dead-common | None — no 'log in again' link, no redire |
| 5 | Module-gate 404 that deliberately hides package-disabled modules (PRODUCT FEATURE, not a bug) | 404 | occasional | None. No link, no redirect, no app chrom |
| 6 | 403 from the SAME module gate that elsewhere returns 404 — inconsistent disguise | 403 | occasional | None. |
| 7 | 403 tenant-isolation guard fired mid-AJAX, surfaced as a raw browser alert() | 403 (delivered as JSON, rendered as alert()) | occasional | Dismissing the alert returns them to the |
| 8 | 404 / 403 / 500 shown to a paying CUSTOMER on a shared payment link | 404 / 500 | occasional | None. The customer has no account, no na |
| 9 | 405 Method Not Allowed — full debug page | 405 | occasional | None. |
| 10 | 500 crash — AI chain constructs ResayilClient with missing credentials | 500 | occasional | None. |
| 11 | 500 for any other uncaught exception (QueryException, TypeError, null property access) | 500 | occasional | None in either mode. |
| 12 | 404 from findOrFail/firstOrFail on a record that was deleted, renamed, or belongs to another co | 404 | occasional | None. Loses their place in whatever list |
| 13 | 404 to a CUSTOMER on a shared receipt-voucher link | 404 | occasional | None. |
| 14 | 404 to a CUSTOMER on a shared invoice / proforma / Arabic invoice link | 404 | occasional | None for a guest. For staff the same con |
| 15 | 403 'Access denied' from AccountantView — locks out admins too | 403 | occasional | None. |
| 16 | 404 on the public file-download route | 404 | occasional | None. |
| 17 | AJAX abort renders as a permanent 'Loading records...' with no error at all | 403/404/500 (invisible to the user) | occasional | n/a — the page is still there; there is  |
| 18 | API errors return JSON with a full stack trace and server filesystem paths | 404/403/500 as JSON | occasional | n/a. |
| 19 | 403 fail-closed when a user resolves to no company at all | 403 | occasional | None. |
| 20 | 404 to a CUSTOMER on a shared refund-voucher link and on shared ticket / hotel-voucher PDF link | 404 | occasional | None. Guest has no account, no nav, no c |
| 21 | 400 Bad Request on payment initiation failure — full stack trace to a customer | 400 | rare | None. The debug page's only interactive  |
| 22 | 422 with no error view — full debug page on an invite resend and on the DOTW token screen | 422 | rare | Controller path: none. Livewire path: Es |
| 23 | 403 'Access denied.' from DotwAuditAccess on the DOTW admin page | 403 | rare | None. |
| 24 | 403 on admin-only inline closure routes for the AIR uploader dashboard | 403 | rare | None — and the POST variant destroys wha |
| 25 | 404 on Excel template downloads when the template file is missing from disk | 404 | rare | None. Back button returns to the import  |
| 26 | 404 on the Postman collection download | 404 | rare | None. |
| 27 | 404 on the invite-gated agents Excel template during company self-registration | 404 | rare | None. The designed sibling page exists b |
| 28 | 403 on the machine-to-machine Cygnet sync endpoint | 403 | rare | n/a — machine endpoint. |
| 29 | 404 when a company row is missing behind the Auto-Billing screens | 404 | rare | None — even though three other branches  |
| 30 | 403 on voiding a refund from the wrong role | 403 | rare | None. They were on a refund detail page  |
| 31 | 404 on the DOTW documentation pages, with the helpful message thrown away | 404 | theoretical | n/a |
| 32 | 404 when the hardcoded 'clients' ledger account row does not exist | 404 | theoretical | n/a |
| 33 | 403 guest branch of EnsureModuleEnabled — fail-closed, not reachable in practice | 404 | theoretical | n/a |

#### 4.2.1 Genuine 404 — unknown URL, dead link, deleted record

- **Status:** 404 | **Frequency:** dead-common
- **Trigger:** Typo in the address bar, a stale bookmark, a link from an old email/WhatsApp message, an external site linking to a renamed route, or a search engine result.
- **Mechanism:** Laravel routing throws Symfony\Component\HttpKernel\Exception\NotFoundHttpException from AbstractRouteCollection.php:45. HTTP 404, renders errors::404. Note: the framework renders the minimal error view even with APP_DEBUG=true, because renderHttpException() prefers a status-matched view over the debug renderer — which is why 404/403/419/500 get the bare page while 400/405/422 (no view shipped) get the stack trace.
- **User sees:** White (or near-black in dark mode) page, `404` in 18px gray-500, thin vertical rule, `NOT FOUND` uppercase. That is the entire page. No logo, no nav, no footer, no search, no link.
- **Way back:** None whatsoever.
- **Evidence:** Re-verified live during this pass: GET https://development.citycommerce.group/definitely-not-a-real-page-xyz → status 404, 6,603 bytes. Body is vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/minimal.blade.php via 404.blade.php, which hardcodes @section('message', __('Not Found')) — so any message passed to abort(404, '...') anywhere in the app is silently discarded. resources/views/errors/ does not exist (confirmed).
- **Verify note:** VERIFIED. Live re-test reproduced the exact byte count and view.

#### 4.2.2 403 from policy authorization — under-privileged user opens a page they can see linked

- **Status:** 403 | **Frequency:** dead-common
- **Trigger:** An agent, branch user, or accountant clicks a menu item or row action the nav did not hide for their role — or a newly created user whose Spatie role carries no permissions yet opens almost anything. Known recurring support issue on this product.
- **Mechanism:** Gate::authorize() throws Illuminate\Auth\Access\AuthorizationException, converted by the framework to HTTP 403 with the default message "This action is unauthorized." No policy uses Response::deny() with a custom message (grep for '::deny(' across app/Policies/ returns exactly 0 hits), so all 78 call sites produce that same generic sentence. errors::403 DOES echo the message ($exception->getMessage() ?: 'Forbidden'), so the generic sentence is what the user reads.
- **User sees:** Bare page reading `403 \| THIS ACTION IS UNAUTHORIZED.` — uppercased by the stock view's CSS. No indication of which permission is missing or who to ask.
- **Way back:** None.
- **Evidence:** 78 Gate::authorize() sites in app/ (confirmed by count). Per-controller density confirmed exactly: SettingController.php (11), SystemSettingController.php (9), SupplierController.php (6), RoleController.php (6), ClientController.php (6), ChargeController.php (5), LockManagementController.php (4), InvoiceController.php (4), AccountingController.php (4), TaskController.php:72/148/4950, ReportController.php:593/3614/3880, CoaController.php:30/1194/1225. Framework view: vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/403.blade.php.
- **Verify note:** VERIFIED. Every cited line number resolved to an actual Gate::authorize call. One addition: CoaController has a third site at :1225 (census listed only :30 and :1194).

#### 4.2.3 403 'Unauthorized action.' hand-rolled role checks scattered across controllers

- **Status:** 403 | **Frequency:** dead-common
- **Trigger:** A COMPANY/BRANCH/AGENT/ACCOUNTANT user opens an admin-only or company-only screen — supplier management, payment methods, currency exchange, chart of accounts, roles, auto-billing, the accounting summary, or a dozen reports.
- **Mechanism:** Plain `abort(403, 'Unauthorized action.')` or `return abort(403, 'Unauthorized action.')` after an inline `if (!in_array($user->role_id, [Role::X, ...]))`. HTTP 403, renders errors::403 which DOES echo the message.
- **User sees:** `403 \| UNAUTHORIZED ACTION.` on a blank page. The AdminUsers variant reads `403 \| CANNOT CHANGE ROLE OF ADMIN USERS.`
- **Way back:** None. AdminUsersController.php:100 in particular destroys the admin's unsaved form state on a mistake that should have been a field-level error.
- **Evidence:** 41 occurrences of the exact string `abort(403, 'Unauthorized action.')` in app/ (NOT ~60). Verified line-by-line: AccountingController.php:37,228,511,591,703,791,895,922; ReportController.php:839,988,1148,1695,1886,2177,4050,4108,4160; SupplierController.php:90,153,238,252,296,356,576; AdminUsersController.php:76,94,158,334; SettingController.php:726,766,851,880; AutoBillingController.php:147,200; RoleController.php:44; PaymentMethodController.php:39; CurrencyExchangeController.php:86; BankPaymentController.php:61; CreditController.php:43; CoaController.php:35; InvoiceController.php:3079. The genuinely useful one is AdminUsersController.php:100 — `abort(403, 'Cannot change role of Admin users.');` — thrown as a full-page error instead of a form validation message. Two sites in the same family handle it gracefully instead: ReportController.php:962 and :1111 use `redirect()->back()->with('error', 'Unauthorized action.')`.
- **Verify note:** CORRECTED. Three fixes: (1) count is 41, not ~60; (2) ReportController.php:1603 does NOT exist as claimed — the actual ReportController sites are 839, 988, 1148, 1695, 1886, 2177, 4050, 4108, 4160, plus two graceful redirect siblings at 962 and 1111; (3) DashboardController.php:32 was miscategorised — it is `abort(403);` with NO message, inside aiHealthStatus(), a JSON-returning AJAX endpoint, so it belongs with the bare-403 family, not this one. Added the previously unlisted InvoiceController.php:3079. AdminUsersController.php:100 confirmed verbatim.

#### 4.2.4 419 Page Expired on a stale form or expired session

- **Status:** 419 | **Frequency:** dead-common
- **Trigger:** A user leaves the login page (or any form) open past the session lifetime and submits; or has multiple tabs open and logs out in one; or restores a browser session the next morning and hits Save on a long-open invoice/task form.
- **Mechanism:** Illuminate\Session\TokenMismatchException from the VerifyCsrfToken middleware → HTTP 419 → renders errors::419.
- **User sees:** `419 \| PAGE EXPIRED` on a blank page. The phrase is meaningless to a non-technical travel agent, and every field they had typed is gone.
- **Way back:** None — no 'log in again' link, no redirect back to the form, no draft preservation.
- **Evidence:** vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/419.blade.php confirmed present: @section('title', __('Page Expired')), @section('code','419'), @section('message', __('Page Expired')) — the message is hardcoded, not derived. bootstrap/app.php:45-47 adds no custom handling; no resources/views/errors/419.blade.php override exists. .env: SESSION_DRIVER=database, SESSION_LIFETIME=120 (two hours), so this is reachable by an ordinary lunch break with a form open.
- **Verify note:** VERIFIED. Added the concrete session config (database driver, 120-minute lifetime) that makes the frequency claim defensible.

#### 4.2.5 Module-gate 404 that deliberately hides package-disabled modules (PRODUCT FEATURE, not a bug)

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A user at a package client whose company has a module switched off opens any URL belonging to that module — a bookmark from before the module was turned off, a link pasted in WhatsApp/email by a colleague at another company, the browser's back button after an admin toggled the module, an autocompleted URL, or one of the stray in-app links never wrapped in the `$hasAccountingModule` blade guard. The nav is already hidden for them (resources/views/layouts/menu.blade.php:11, layouts/sidebar.blade.php:10, layouts/mobile-drawer.blade.php:14, dashboard.blade.php:24 — all four confirmed at the exact cited lines), so they only ever arrive here off-nav.
- **Mechanism:** EnsureModuleEnabled middleware calls abort(404) — never 403 — when Company::hasModule() is false. Docblock is explicit that this is intentional: "A 403 would confirm to the caller that the route exists and simply refuses them — which itself leaks the presence of a module the product deliberately hides from package clients... A 404 makes the route indistinguishable from one that was never built." NotFoundHttpException, HTTP 404, renders errors::404.
- **User sees:** The identical bare grey "404 \| NOT FOUND" page as a genuine typo'd URL (verified live: 6,603 bytes, framework minimal.blade.php, `text-lg` = 18px gray-500, thin vertical rule, uppercase message, bg-gray-100 in light / bg-gray-900 in dark). Nothing distinguishes "this feature is not part of your package" from "this page does not exist" — which is the design intent.
- **Way back:** None. No link, no redirect, no app chrome. Only the browser back button or retyping the site root.
- **Evidence:** app/Http/Middleware/EnsureModuleEnabled.php:73 (module-disabled branch) and :66 (no-user branch) — both confirmed to be literal `abort(404);`. Alias 'module' => EnsureModuleEnabled::class registered bootstrap/app.php:35. 47 `module:` occurrences in routes/web.php (mix of per-route chains and group-level declarations, covering roughly 50 endpoints): 103, 281, 288, 336, 355, 428, 442, 460, 466-505, 510, 722, 744, 814-817, 937, 958, 1219. DB (citycomm_city-tour-test, settings table): company_id=3 ("Akeed") is the ONLY company with any module.* rows, and it has SIX of them — module.accounting=false (id 37), module.agent_profit=true, module.crm=true, module.payment_gateway=true, module.resayil=true, module.task_uploader=true. Companies 1 (City Travelers) and 2 (Ojeen Travel & Tourism Operations) have zero module.* rows and fail open to ON per app/Models/Company.php:123-136.
- **Verify note:** CORRECTED. Two errors in the original: (1) the gate is NOT accounting-only — routes/web.php also carries module:crm (:815), module:payment_gateway (:816-817), module:task_uploader (:744) and module:resayil (:1219), so the same 404 hides five different modules; (2) company 3 has six explicit module rows, not "the only company with an explicit module row" singular — five are true, only accounting is false. Everything else (abort lines, docblock intent, nav guard lines, fail-open default) verified exactly. REDESIGN CONSTRAINT stands: this 404 must remain indistinguishable from the genuine-404 finding; any module-specific copy, illustration, or 'upgrade' CTA leaks the feature. Note the same entitlement condition surfaces as a 403 elsewhere (see the Gate::authorize module finding).

#### 4.2.6 403 from the SAME module gate that elsewhere returns 404 — inconsistent disguise

- **Status:** 403 | **Frequency:** occasional
- **Trigger:** A user at a module-disabled company reaches an accounting/CRM/agent-profit/task-uploader action whose route is NOT wrapped in the `module:` middleware but whose Policy method is gated by RequiresCompanyModule.
- **Mechanism:** The policy's first check is moduleEnabled($user, Modules::X) → returns false → the ability returns false → Gate::authorize() throws AuthorizationException → HTTP 403 "This action is unauthorized." — whereas the identical entitlement failure one route over aborts 404 and pretends the page never existed.
- **User sees:** `403 \| THIS ACTION IS UNAUTHORIZED.` — which confirms the route exists and is merely locked, the precise leak the 404 design was written to prevent.
- **Way back:** None.
- **Evidence:** app/Policies/Concerns/RequiresCompanyModule.php:45-56 is moduleEnabled() (NOT :49-59 as originally cited): resolves getCompanyId($user), returns false if falsy, else Company::find()->hasModule(). The trait is used by 17 policies — AccountPolicy, AgentPolicy, AutoBillingPolicy, COAPolicy, ClientPolicy, CreditPolicy, CurrencyExchangePolicy, InvoicePolicy, PaymentPolicy, RefundClientPolicy, RefundPolicy, TaskPolicy, ChargePolicy, PaymentMethodPolicy, ReportPolicy, SettingPolicy, SupplierPolicy — i.e. the policies sitting behind the 78 Gate::authorize() sites (e.g. AccountPolicy.php:16,25,34,43 gate on Modules::ACCOUNTING; AgentPolicy.php:21,36,60,72,84,96 on Modules::AGENT_PROFIT). Contrast app/Http/Middleware/EnsureModuleEnabled.php:73 whose docblock argues 403 must never be used for exactly this reason.
- **Verify note:** CORRECTED. Trait line range fixed from 49-59 to 45-56; added the concrete list of 17 consuming policies and sample gate lines, which the original asserted without citing. Substance of the inconsistency confirmed.

#### 4.2.7 403 tenant-isolation guard fired mid-AJAX, surfaced as a raw browser alert()

- **Status:** 403 (delivered as JSON, rendered as alert()) | **Frequency:** occasional
- **Trigger:** A user clicks 'Apply Payments' on the invoice edit screen and the submitted companyId does not match their own resolved company — which also happens benignly when an admin's session company_id has drifted from the invoice they are looking at.
- **Mechanism:** abort_unless(getCompanyId(Auth::user()) == $request->input('companyId'), 403, 'Unauthorized: this invoice does not belong to your company.'). The fetch() sets `Accept: application/json`, so Laravel returns JSON `{"message":"Unauthorized: this invoice does not belong to your company.","exception":"Symfony\\...\\HttpException","file":...,"line":...,"trace":[...]}`. The JS reads `result.success` (undefined), falls into the else branch, and calls `alert(result.message)`.
- **User sees:** A native OS `alert()` dialog, single OK button, reading "Unauthorized: this invoice does not belong to your company." The page stays put and the button re-enables. Zero styling, zero context, and the wording accuses the user of something they did not knowingly do.
- **Way back:** Dismissing the alert returns them to the invoice with state preserved — one of the few surfaces that does NOT strand the user. But no next step is offered.
- **Evidence:** app/Http/Controllers/InvoiceController.php:945-949 (abort_unless opens at 945, message string on 948), plus :6517 ('client does not belong'), :6577, :6644, :6696, :6717, :6739, and a JSON-shaped variant at :6772. app/Services/PaymentApplicationService.php:61-64, :89-92, :571-574, :577-580, :599, :617. app/Http/Controllers/CreditController.php:175-178 ('this invoice split does not belong to your company.'). Client side confirmed at resources/views/invoice/edit.blade.php:6679-6702: fetch with 'Accept': 'application/json', `const result = await response.json();`, then `alert(result.message \|\| 'Failed to apply payments. Please try again.');` with the button re-enabled.
- **Verify note:** VERIFIED. Line anchors are accurate as opening-line references; the message literals sit 3 lines below on the multi-line abort_unless calls (948, 64, 92, 574, 580, 178). Client-side fetch/alert path confirmed verbatim.

#### 4.2.8 404 / 403 / 500 shown to a paying CUSTOMER on a shared payment link

- **Status:** 404 / 500 | **Frequency:** occasional
- **Trigger:** A client taps the payment link a travel agent sent over WhatsApp or email, and the voucher was cancelled, the invoice was deleted, the client record was unlinked, the agent record was removed, or the companyId segment doesn't match. routes/web.php:649 hardcodes `companyId => 1` on the legacy short-link redirect, so any non-company-1 voucher reaching that path lands on a mismatched lookup.
- **Mechanism:** Ternaries of the shape `return Auth::user() ? <staff branch> : abort(404);` — logged-in staff get a redirect, the anonymous CUSTOMER gets a raw abort. Routes are explicitly `->withoutMiddleware(['auth'])`.
- **User sees:** A customer about to pay an invoice gets a white page reading `404 \| NOT FOUND`, or `500 \| SERVER ERROR`. No agency branding, no invoice reference, no phone number, no 'contact your agent'. They have no idea whether their money moved.
- **Way back:** None. The customer has no account, no nav, and no way to reach the agency from that page. Only option is to message the agent.
- **Evidence:** app/Http/Controllers/PaymentController.php:3106, 3110, 3114 (paymentShowLink — payment / client / agent not found), :193, :197, :206 (create — invoice / client / company not found), :3311 (paymentLinkInitiate), :3370, :3748, :7264 (abort(500) when the payment has no company_id), :3799, :3814 (abort(500) on MyFatoorah reinitiation failure) — all confirmed verbatim. Routes: routes/web.php:647 `/payment/link/show/{companyId}/{voucherNumber}` ->withoutMiddleware(['auth']); legacy redirect closure at :648-650 with `['companyId' => 1, ...]` on :649; :630 POST /payment/create/{companyId}/{invoiceNumber} ->withoutMiddleware(['auth']); :653 /initiate and :655 /reinitiate likewise public.
- **Verify note:** CORRECTED (detail). The asymmetry is real and confirmed at every cited line, but the staff branch is not uniformly "a friendly flash redirect": PaymentController:3106/3110/3114 redirect staff to payment.link.index with NO flash message at all, so staff also get a silent bounce there. The :193/:197/:206 and :3370/:3748/:7264/:3799/:3814 sites do carry ->with('error', ...). Highest-stakes surface in the census: the product's face to the paying end-customer is a stock framework page.

#### 4.2.9 405 Method Not Allowed — full debug page

- **Status:** 405 | **Frequency:** occasional
- **Trigger:** A user refreshes or back-buttons onto a POST-only URL, an old bookmark points at a form action, a mistyped URL collides with a POST route, or a crawler GETs a form endpoint.
- **Mechanism:** MethodNotAllowedHttpException. No 405 view ships with the framework and none exists in resources/views/errors/ → convertExceptionToResponse → debug renderer.
- **User sees:** A dark developer error page with a red exception banner, the exception class name, and a scrollable syntax-highlighted stack trace. Incomprehensible to an end user and indistinguishable from a crash.
- **Way back:** None.
- **Evidence:** RE-VERIFIED LIVE this pass: GET https://development.citycommerce.group/version/update/current → status 405, 251,967 bytes, page contains the string 'MethodNotAllowedHttpException'. Route is POST-only in routes/web.php.
- **Verify note:** VERIFIED. Live re-test reproduced status 405 and the exact 251,967-byte debug page.

#### 4.2.10 500 crash — AI chain constructs ResayilClient with missing credentials

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** Any user opens the in-app AI chat and sends a message or uploads a file to chat (web POST /chat/* or API POST /api/chat/upload); any user triggers passport-image AI extraction from the task screen or the WhatsApp hotel flow.
- **Mechanism:** AIManager::__construct() eagerly builds the WHOLE fallback chain (not lazily), and config('ai.chain') contains three `resayil` entries. ResayilClient::__construct throws a plain `\Exception('Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).')` because config('ai.primary') defaults to 'resayil' (AI_PRIMARY is unset in .env) making $inUse true, while RESAYIL_API_KEY is unset (.env has RESAYIL_API_TOKEN and RESAYIL_BASE_URL — DIFFERENT key names from the RESAYIL_API_KEY / RESAYIL_BASE the config reads). Not an HttpException → convertExceptionToResponse → debug renderer.
- **User sees:** For a normal browser POST: the ~250KB Laravel debug page with the exception message and stack. For the chat UI's own XHR (Accept: application/json): a JSON blob with message, file, line and full trace — which the chat JS does not render, so the chat simply stops responding.
- **Way back:** None.
- **Evidence:** CONFIRMED LIVE by running `/opt/cpanel/ea-php82/root/usr/bin/php artisan route:list`, which dies with exactly: "Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY). at app/AI/Services/ResayilClient.php:55" with frames `1 app/AI/AIManager.php:31 App\AI\Services\ResayilClient::__construct("qwen3.5:397b")` and `2 app/AI/AIManager.php:46`. Code: app/AI/AIManager.php:18-21 (constructor calls createClient eagerly), :31 ('resayil' => new ResayilClient($model)), :36-47 (chain loop instantiates every entry); ResayilClient.php:53-56 (the $inUse test and the throw); config/ai.php:4 ('primary' => env('AI_PRIMARY','resayil')), :20-27 (three resayil chain entries), :67 ('key' => env('RESAYIL_API_KEY')). .env contains only AI_PROVIDER=openai, OPENAI_API_KEY=sk-placeholder, RESAYIL_BASE_URL and RESAYIL_API_TOKEN. Web entry points confirmed: app/Http/Controllers/ChatController.php:42-45 constructor-injects AIManager, so route resolution alone throws for every /chat/* route (routes/web.php:406-413, routes/api.php:106); TaskController.php:4894 `new AIManager()`; WhatsAppHotelController.php:2204 `new AIManager()`.
- **Verify note:** CORRECTED. Two claimed entry points do NOT hold: (1) DashboardController's AI health card — DashboardController.php:36 calls \App\Services\AiHealthCheck::run(), and AiHealthCheck talks to the gateway with raw Http::withToken() (lines 165, 225); it never constructs AIManager or ResayilClient, so ?fresh=1 does not fire this exception; (2) `<livewire:chat />` on /ai/openai (resources/views/ai/openai/index.blade.php:48) resolves app/Livewire/Chat.php, which boots `App\AIService` and OpenAiController — not AIManager. Also corrected: DashboardController.php:32 is a separate bare `abort(403)` on a JSON endpoint. The throw line is ResayilClient.php:55 (census said 52-56). Everything else — eager chain construction, the RESAYIL_API_KEY/RESAYIL_API_TOKEN name mismatch, ChatController constructor injection, and the fact that this is why artisan route:list and tinker are dead on this server — confirmed live.

#### 4.2.11 500 for any other uncaught exception (QueryException, TypeError, null property access)

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** Any genuine bug — a null relation dereferenced in a blade, a bad date filter producing a SQL error, a missing config key, a third-party API returning an unexpected shape.
- **Mechanism:** Uncaught Throwable → not an HttpException → convertExceptionToResponse() → with APP_DEBUG=true, Laravel 11's built-in exception renderer. With APP_DEBUG=false it would instead render errors::500 → `500 \| SERVER ERROR`.
- **User sees:** Today (debug on): a developer stack-trace page exposing server paths and source code. With debug off: `500 \| SERVER ERROR` on a blank page and nothing else.
- **Way back:** None in either mode.
- **Evidence:** bootstrap/app.php:45-47 is `->withExceptions(function (Exceptions $exceptions) { // })->create();` — literally an empty closure with a bare `//` comment: no reportable(), no renderable(), no custom mapping. resources/views/errors/ does not exist (ls returns 'No such file or directory'). .env: APP_ENV=local, APP_DEBUG=true. Framework 500 view at vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/500.blade.php hardcodes @section('message', __('Server Error')).
- **Verify note:** VERIFIED. The environment flag is the biggest amplifier in the census — it turns the same event from 'leaks the codebase' into 'says nothing at all'. Neither is a designed state. An internal Admin\ErrorDashboardController exists (routes/web.php:1094-1097, names admin.error-dashboard.index / .metrics), so errors ARE tracked internally — the end-user page just was never built.

#### 4.2.12 404 from findOrFail/firstOrFail on a record that was deleted, renamed, or belongs to another company

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A user opens a client, task, invoice, supplier, charge, refund, receipt voucher, term, reminder or branch from a stale list, a bookmark, or a link in an old notification — after the record was deleted, or while the user's session company differs from the record's.
- **Mechanism:** Illuminate\Database\Eloquent\ModelNotFoundException, converted by the framework to NotFoundHttpException → HTTP 404 → errors::404. Nothing catches it anywhere in app/.
- **User sees:** `404 \| NOT FOUND`, identical to a typo'd URL and identical to a hidden module. The user cannot tell whether the record was deleted, whether they lack access, or whether they mistyped.
- **Way back:** None. Loses their place in whatever list they came from.
- **Evidence:** 168 findOrFail/firstOrFail sites app-wide, 148 of them in app/Http/Controllers. Densest (re-counted): ClientController.php (16), InvoiceController.php (13), TaskController.php (12 — e.g. :4665 flightPdf, :4692 hotelPdf), PaymentController.php (12), SupplierController.php (8), SystemSettingController.php (7), ReceiptVoucherController.php (7), CompanyController.php (6), ChargeController.php (6), RefundController.php (5), TermController.php (4), ResayilController.php (4), ReminderController.php (4), BankPaymentController.php (4), WhatsappController.php (3), SupplierProcedureController.php (3), SupplierCompanyController.php (3), SettingController.php (3), PaymentMethodController.php (3), BulkInvoiceController.php (3). Also implicit route-model binding: routes/web.php:157-158 bind CompanyInvite on company-invites/{invite}/cancel and /resend.
- **Verify note:** CORRECTED (count only). 168 app-wide, not 173; controller-only figure is 148. Route-model-binding lines are 157 (cancel) and 158 (resend), not 157-158 as a pair of binding declarations. Surface itself fully confirmed.

#### 4.2.13 404 to a CUSTOMER on a shared receipt-voucher link

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A client opens the receipt/voucher link an agent shared with them, and the voucher number or companyId doesn't resolve — mistyped, cancelled, or from a different company.
- **Mechanism:** `->firstOrFail()` on an unauthenticated, deliberately auth-exempt route. ModelNotFoundException → 404 → errors::404.
- **User sees:** Bare `404 \| NOT FOUND` — a customer expecting a payment receipt gets a blank framework page.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/ReceiptVoucherController.php:649 is literally `->firstOrFail();`, terminating a whereHas() on transaction.company_id + reference_number inside `public function show($companyId, $voucherNumber)`. Route routes/web.php:949: `Route::get('/{companyId}/{voucherNumber}', ...)->name('show')->withoutMiddleware(['auth', 'module:accounting'])`. The comment block at routes/web.php:925-933 documents that the double exemption is deliberate — widening the module exemption is required because an anonymous visitor has no company to check against.
- **Verify note:** VERIFIED. Both the firstOrFail line and the route exemption confirmed verbatim, including the explanatory comment.

#### 4.2.14 404 to a CUSTOMER on a shared invoice / proforma / Arabic invoice link

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A client opens the invoice link sent by their agent after the invoice was deleted, the invoice number changed, the companyId segment is wrong, or the invoice has no partial/split rows yet.
- **Mechanism:** Staff-vs-guest ternary in expanded form: `if (Auth::user()) { return redirect()->route('invoices.index')->with('error', 'Invoice not found!'); } return abort(404);`. Public routes carry ->withoutMiddleware(['auth']).
- **User sees:** `404 \| NOT FOUND`. The :3233/:3368 variant fires when the invoice EXISTS but has no partial rows — a data-state problem shown to the customer as a missing page. The :3167 variant shows `403 \| FORBIDDEN` (no message passed).
- **Way back:** None for a guest. For staff the same conditions redirect to the invoices list with a flash message, which works fine.
- **Evidence:** All cited lines confirmed verbatim in app/Http/Controllers/InvoiceController.php: :3148 (proforma, invoice missing), :3167 (`return abort(403);` when a logged-in user fails $hasAccess — staff branch redirects with 'Unauthorized access.'), :3197 (proformaGeneratePdf), :3221 (show — invoice missing) and :3233 (show — invoicePartials empty, staff message 'No invoice partials found for this invoice!'), :3353 and :3368 (showArabic — same pair), :3459, :4290 (`abort(404, 'Invoice not found.')` in showDetails — message discarded by the 404 view). Public routes: routes/web.php:570 show, :571 pdf, :572 proforma, :573 proforma-pdf, :532 arabic, :545-546 split/split-arabic — every one ->withoutMiddleware(['auth']).
- **Verify note:** VERIFIED. Every one of the nine cited line numbers matched an actual abort at that exact line.

#### 4.2.15 403 'Access denied' from AccountantView — locks out admins too

- **Status:** 403 | **Frequency:** occasional
- **Trigger:** Anyone who is not exactly role ACCOUNTANT opens the accountant invoice-edit screen — including an ADMIN or the COMPANY owner following a link a colleague sent them.
- **Mechanism:** `if (auth()->check() && auth()->user()->role_id !== Role::ACCOUNTANT) { abort(403, 'Access denied'); }` — no admin bypass. HTTP 403, message echoed by errors::403.
- **User sees:** `403 \| ACCESS DENIED` — two words, no explanation of which role is required.
- **Way back:** None.
- **Evidence:** app/Http/Middleware/AccountantView.php:20 confirmed verbatim. Alias 'accountant' => AccountantView::class at bootstrap/app.php:31. Applied at routes/web.php:565 (`'middleware' => 'accountant'` on the `accountant.` group opened at :562) covering THREE routes: :567 GET {companyId}/edit/{invoiceNumber}, :568 PUT /update, :569 POST /create-payment-link-shortage. The guard is `auth()->check() &&`, so a GUEST passes straight through this middleware.
- **Verify note:** CORRECTED (line anchors). Group middleware sits at routes/web.php:565 and the group opens at :562; the protected routes are :567-:569 — three of them, not the two cited (create-payment-link-shortage was missed). Middleware abort line :20 and the missing-admin-bypass / guest-passthrough observations confirmed exactly.

#### 4.2.16 404 on the public file-download route

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** Anyone opens a /download-pdf/... link (shared invoice PDF, ticket, voucher) after the underlying file was moved, purged, or never written.
- **Mechanism:** `if (!Storage::exists($fullPath)) { abort(404, 'File not found'); }` inside a route closure. Message DISCARDED by the 404 view. Route is public — defined after the auth group closes.
- **User sees:** `404 \| NOT FOUND` — the specific 'File not found' wording never reaches them.
- **Way back:** None.
- **Evidence:** routes/web.php:900-906 is the closure (`Route::get('/download-pdf/{path}', function ($path) { $fullPath = 'city_travelers/' . $path; ...`), with the abort at routes/web.php:903. The auth group closes at routes/web.php:898.
- **Verify note:** VERIFIED. Closure and abort line confirmed verbatim.

#### 4.2.17 AJAX abort renders as a permanent 'Loading records...' with no error at all

- **Status:** 403/404/500 (invisible to the user) | **Frequency:** occasional
- **Trigger:** A user on the bank-payment or receipt-voucher creation screens picks a date range to pull journal entries, and the endpoint aborts (403 tenant guard, 404, or any 500).
- **Mechanism:** The fetch() sends NO Accept header, so Laravel returns an HTML error page; `.then(response => response.json())` throws on the HTML; there is no `.catch()` anywhere on the chain, so it becomes an unhandled promise rejection and the placeholder text is never replaced.
- **User sees:** Absolutely nothing. A grey 'Loading records...' that never resolves. No spinner timeout, no message, no retry. The user assumes the system is slow and waits, then reloads.
- **Way back:** n/a — the page is still there; there is simply no signal that anything failed.
- **Evidence:** resources/views/bank-payments/create.blade.php:746 sets `container.innerHTML = '<p class="text-gray-500">Loading records...</p>';` and :748 opens `fetch('/bank-payments/fetch-journals-by-date?from=...')` with no headers; the chain that follows contains no .catch (verified by grepping the following 70 lines — zero hits). resources/views/receipt-voucher/create.blade.php:836/838 is the same code and, notably, also points at the /bank-payments/ endpoint. Server side: app/Http/Controllers/ReportController.php:1660 `abort_unless($companyId, 403);` in journalEntriesByDate (route routes/web.php:482-483), plus BankPaymentController/ReceiptVoucherController fetchPaymentsByDate at routes/web.php:945 and :966.
- **Verify note:** VERIFIED (line anchors shifted by ~2: the placeholder is at bank-payments/create.blade.php:746 and receipt-voucher/create.blade.php:836). Confirmed there is no .catch on either chain. This is the 'silent' half of the census: the status code is real, but the UI is built as if errors do not exist — a redesigned error PAGE does nothing for these; they need an inline error state.

#### 4.2.18 API errors return JSON with a full stack trace and server filesystem paths

- **Status:** 404/403/500 as JSON | **Frequency:** occasional
- **Trigger:** The mobile app, the n8n workflows, the AIR uploader agent, or any external integrator calls an /api/* endpoint that 404s, 403s, or crashes.
- **Mechanism:** Laravel's handler sees expectsJson() and returns JSON. With APP_DEBUG=true it includes `exception`, `file`, `line` and the entire `trace` array.
- **User sees:** An integrator sees a 9KB payload where a 60-byte one was expected. A mobile app user sees whatever the app does with an unparseable/oversized error — typically a generic 'something went wrong' or a hang.
- **Way back:** n/a.
- **Evidence:** RE-VERIFIED LIVE this pass: GET /api/nope-xyz with Accept: application/json → status 404, content-type application/json, 9,619 bytes, body begins `{"message":"The route api/nope-xyz could not be found.","exception":"Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException","file":"/home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Routing/AbstractRouteCollection.php","line":45,"trace":[...`. routes/api.php is 199 lines of largely ungated endpoints — :25 GET /agents, :26 /agents/{userId}, :29 /companies, :30 /companies/{id}, :32-40 the /task/* group, :106 POST /chat/upload (which additionally hits the ResayilClient 500 described above).
- **Verify note:** VERIFIED. Live re-test reproduced the exact 9,619-byte JSON body and absolute server path. Route line references confirmed (the /task/* group opens at :32, not :33).

#### 4.2.19 403 fail-closed when a user resolves to no company at all

- **Status:** 403 | **Frequency:** occasional
- **Trigger:** A newly created user, a user whose agent/branch/company relation was broken by a data fix, or an admin whose session('company_id') was never set, opens a supplier ledger, the settlements report, or the journal-entries-by-date endpoint.
- **Mechanism:** `abort_unless($companyId, 403);` with no message → errors::403 falls back to 'Forbidden'. Added deliberately as tenant-isolation fail-closed guards; the surrounding comments say the endpoints previously leaked every company's data.
- **User sees:** `403 \| FORBIDDEN` with zero indication that the real problem is an unconfigured account. Matches the known support pattern where a bare AGENT user with no permissions gets 403s across the app. Note ReportController:1660 is an AJAX endpoint, so there it surfaces as the silent 'Loading records...' hang instead.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/SupplierController.php:219, preceded by the comment at :213-216 ('Task carries no BelongsToCompany scoping of its own, so without this the endpoint would let any authenticated user enumerate any other company's booking/task data... by simply varying it'). app/Http/Controllers/ReportController.php:1660, preceded by :1657-1659 ('A falsy/orphaned companyId used to fall through with no company filter applied at all, silently returning every company's journal entries for the requested date. Fail closed instead.'). Related: app/Services/PaymentApplicationService.php:61 and :571.
- **Verify note:** VERIFIED. Both abort lines and both explanatory comments confirmed verbatim.

#### 4.2.20 404 to a CUSTOMER on a shared refund-voucher link and on shared ticket / hotel-voucher PDF links

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A client opens the refund voucher, e-ticket PDF, or hotel voucher PDF link an agent sent them over WhatsApp/email, after the refund or task was deleted, the id changed, or the companyId segment is wrong.
- **Mechanism:** `->firstOrFail()` / `->findOrFail()` on deliberately auth-exempt public routes. ModelNotFoundException → NotFoundHttpException → HTTP 404 → errors::404. No staff-vs-guest ternary here at all: unlike the invoice and payment-link flows there is no logged-in branch, so BOTH staff and customers get the bare 404.
- **User sees:** Bare `404 \| NOT FOUND`. A customer expecting a refund confirmation or their flight e-ticket gets a blank framework page with no agency branding and no reference number.
- **Way back:** None. Guest has no account, no nav, no contact route back to the agency.
- **Evidence:** app/Http/Controllers/RefundController.php:1091 `->firstOrFail();` closing the Refund::where('refund_number')->where('company_id') lookup in `public function show($companyId, $refundNumber)` at :1080 — route routes/web.php:615 `Route::get('/{companyId}/{refundNumber}', [RefundController::class,'show'])->name('show')->withoutMiddleware(['auth'])`. app/Http/Controllers/TaskController.php:4665 `Task::with([...])->findOrFail($taskId)` in flightPdf() (:4663) and :4692 in hotelPdf() (:4690) — routes routes/web.php:250 `/pdf/flight/{taskId}` and :251 `/pdf/hotel/{taskId}`, both ->withoutMiddleware(['auth']). Additionally routes/web.php:701 `/{id}/credits` (ClientController@showCredit) is public.
- **Verify note:** ADDED IN VERIFY. Tripped over while confirming the ->withoutMiddleware(['auth']) inventory for the invoice/receipt-voucher findings. The census covered the customer-facing invoice link, receipt-voucher link and payment link but missed the refund voucher and the two task PDF links, which are the same shared-link-to-a-paying-customer shape and are arguably worse because they have no logged-in branch at all — even staff hitting a stale id land on the bare 404.

#### 4.2.21 400 Bad Request on payment initiation failure — full stack trace to a customer

- **Status:** 400 | **Frequency:** rare
- **Trigger:** An anonymous customer submits the payment form on a shared link and the gateway (MyFatoorah/Hesabe/Tap) returns an error — declined config, bad API key, gateway downtime, unsupported method.
- **Mechanism:** `return abort(400, $errorMessage);` where $errorMessage is the gateway's own message. There is NO 400.blade.php in vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/ (confirmed: only 401, 402, 403, 404, 419, 429, 500, 503, layout, minimal), so renderHttpException falls through to convertExceptionToResponse → with APP_DEBUG=true, Laravel 11's exception page: exception class, message, file/line, full stack trace with source excerpts, request/headers tabs.
- **User sees:** ~250KB developer debug page. The customer sees the server's absolute filesystem paths (/home/citycomm/development.citycommerce.group/...), the vendor stack, and the app name 'City Tour' in the tab title.
- **Way back:** None. The debug page's only interactive elements are stack-frame toggles.
- **Evidence:** app/Http/Controllers/PaymentController.php:239-246 — the authenticated branch at :241-243 returns `redirect()->back()->with('error', $errorMessage)`, the guest branch at :246 is `return abort(400, $errorMessage);`. Reached from the public POST route routes/web.php:630. Rendering shape confirmed against the live 405 response (same code path).
- **Verify note:** VERIFIED. The staff/guest asymmetry at :241-246 is verbatim as described, and the absence of a 400 view was confirmed by listing the framework view directory.

#### 4.2.22 422 with no error view — full debug page on an invite resend and on the DOTW token screen

- **Status:** 422 | **Frequency:** rare
- **Trigger:** An admin clicks 'Resend' on a company invite that has already expired, been used, or been cancelled. Or a Super Admin clicks 'Generate token' for a DOTW company whose primary user record is missing.
- **Mechanism:** `abort_unless($invite->isUsable(), 422)` / `abort_if(is_null($user), 422, 'Company has no primary user.')`. No 422 view ships with the framework and none exists in resources/views/errors/ → debug renderer → ~250KB stack-trace page. For the Livewire component the non-2xx response is intercepted by Livewire 3's showHtmlModal and painted as a full-screen modal iframe over the page.
- **User sees:** Controller path: a developer stack-trace page for what should have been an inline 'this invite has expired' message. Livewire path: the same page crammed into a modal overlay on top of the admin screen, dismissible with Esc. The two message-less 403s at DotwApiTokenIndex:40/:65 render as `403 \| FORBIDDEN`, not 'Super Admin only.'
- **Way back:** Controller path: none. Livewire path: Esc returns to the page (state preserved).
- **Evidence:** app/Http/Controllers/CompanyInviteController.php:67 `abort_unless($invite->isUsable(), 422);` inside resend() (route routes/web.php:158, POST /company-invites/{invite}/resend). app/Http/Livewire/Admin/DotwApiTokenIndex.php:48 `abort_if(is_null($user), 422, 'Company has no primary user.');` plus :25 `abort_unless($this->isSuperAdmin(), 403, 'Super Admin only.');`, :40 `abort_unless($this->isSuperAdmin(), 403);` and :65 `abort_unless($this->isSuperAdmin(), 403);` — note :40 and :65 pass NO message. Livewire ^3.5 per composer.json:22; 'showHtmlModal' appears 3 times in vendor/livewire/livewire/dist/livewire.js.
- **Verify note:** CORRECTED (detail). The census implied all four DotwApiTokenIndex aborts carry 'Super Admin only.' — only :25 does; :40 and :65 pass no message and therefore render the generic 'Forbidden'. Everything else confirmed verbatim, including the Livewire version and showHtmlModal presence.

#### 4.2.23 403 'Access denied.' from DotwAuditAccess on the DOTW admin page

- **Status:** 403 | **Frequency:** rare
- **Trigger:** A branch/agent/accountant user opens /admin/dotw (or the legacy /admin/dotw/audit-logs, /admin/dotw/api-tokens redirects) from a shared link or bookmark.
- **Mechanism:** `if (! auth()->check() \|\| ! in_array(auth()->user()->role_id, $allowed)) { abort(403, 'Access denied.'); }` where $allowed = [Role::ADMIN, Role::COMPANY]. HTTP 403 → errors::403 echoes the message.
- **User sees:** `403 \| ACCESS DENIED.`
- **Way back:** None.
- **Evidence:** app/Http/Middleware/DotwAuditAccess.php:16 confirmed verbatim. Alias 'dotw_audit_access' registered bootstrap/app.php:32. Applied routes/web.php:1101-1108 (`Route::middleware(['auth','dotw_audit_access'])->prefix('admin/dotw')->name('admin.dotw.')` wrapping the index view plus two 301 redirects).
- **Verify note:** VERIFIED. Middleware line, alias registration and route group all confirmed at the cited lines.

#### 4.2.24 403 on admin-only inline closure routes for the AIR uploader dashboard

- **Status:** 403 | **Frequency:** rare
- **Trigger:** A non-admin follows a link to the AIR uploader logs page or triggers the remove-host action.
- **Mechanism:** `abort_unless(auth()->user()?->hasRole('admin'), 403);` inside route closures. HTTP 403, no message → errors::403 renders the fallback 'Forbidden'.
- **User sees:** `403 \| FORBIDDEN`.
- **Way back:** None — and the POST variant destroys whatever page the admin was on.
- **Evidence:** routes/web.php:1134 (inside the POST /air/uploader/remove-host closure at :1132) and routes/web.php:1144 (inside the GET /air/uploader/logs closure at :1143) — both confirmed as the only two abort_unless calls in routes/web.php. Group opens at routes/web.php:1129: `Route::middleware('auth')->prefix('air/uploader')->name('air.uploader.')`.
- **Verify note:** VERIFIED. Both abort lines exact; group opening line is 1129 (census said 1130).

#### 4.2.25 404 on Excel template downloads when the template file is missing from disk

- **Status:** 404 | **Frequency:** rare
- **Trigger:** A user clicks 'Download template' for company / agents / tasks / clients bulk import and the .xlsx is absent from public/templates/ — e.g. after a deploy that didn't ship the public assets.
- **Mechanism:** `return abort(404);` in a private helper, no message. HTTP 404 → errors::404.
- **User sees:** They click a download button and the whole tab navigates away from their import screen to a blank `404 \| NOT FOUND` page. Any partially-filled import form is lost.
- **Way back:** None. Back button returns to the import page but form state is gone.
- **Evidence:** app/Http/Controllers/ExportController.php:17 is `return abort(404); // Returns a 404 error if the file doesn't exist`, inside `private function downloadTemplate($templateName)` opened at :10 which resolves public_path("templates/{$templateName}.xlsx"). Reached from downloadCompany/downloadAgent/downloadTask/downloadClient (the four public methods immediately below, lines 21-39). Routes routes/web.php:971-974 (/download-company, /download-agent, /download-task, /download-client) — confirmed to sit AFTER the auth group, which closes at routes/web.php:898 (`}); // auth middleware end`), so these four are fully public.
- **Verify note:** VERIFIED. abort line :17 exact; auth group closes at :898 (census said 899); export routes confirmed unauthenticated.

#### 4.2.26 404 on the Postman collection download

- **Status:** 404 | **Frequency:** rare
- **Trigger:** A developer/integrator on the public /docs pages clicks 'Download Postman collection' and resources/postman/Task_Webhook_API.postman_collection.json isn't present.
- **Mechanism:** `abort(404, 'Postman collection file not found');` in a route closure. Message discarded by the 404 view.
- **User sees:** `404 \| NOT FOUND` — an external integrator's first impression of the product's API docs.
- **Way back:** None.
- **Evidence:** routes/web.php:1006, inside the `docs.` group opened at routes/web.php:999 and closed at :1008. Confirmed that /docs/api (:1001) and /docs/developer (:1002) carry no auth middleware at all — only /docs/user (:1000) has ->middleware('auth').
- **Verify note:** VERIFIED. Route line, group bounds and the auth asymmetry between /docs/user and /docs/api\|/docs/developer all confirmed.

#### 4.2.27 404 on the invite-gated agents Excel template during company self-registration

- **Status:** 404 | **Frequency:** rare
- **Trigger:** A brand-new company owner working through the public registration wizard reaches step 4 and clicks 'Download agents template' after their invite expired mid-session or after an admin cancelled the invite while they were filling it in.
- **Mechanism:** `if (!$invite \|\| !$invite->isUsable()) { abort(404); }` — deliberately a 404 so an expired/unknown token cannot be used to probe for the template. HTTP 404 → errors::404.
- **User sees:** Bare `404 \| NOT FOUND`, in stark contrast to the hand-designed sibling page the same controller uses one method up.
- **Way back:** None. The designed sibling page exists but this path doesn't use it.
- **Evidence:** app/Http/Controllers/CompanyRegistrationController.php:52 is `abort(404);` inside agentsTemplate(), whose docblock at :45-48 states the intent ('an expired/used/unknown token must not leak the template'). Route routes/web.php:1122-1123 `/register/company/{token}/agents-template` with ->middleware('throttle:20,1'). Contrast the SAME controller's show() at :31-33, which for the identical `!$invite \|\| !$invite->isUsable()` condition returns `response()->view('register.company-invalid')` — and resources/views/register/company-invalid.blade.php exists (alongside company.blade.php and company-success.blade.php).
- **Verify note:** VERIFIED. abort line :52 exact; the graceful sibling at :31-33 and the existence of register/company-invalid.blade.php both confirmed.

#### 4.2.28 403 on the machine-to-machine Cygnet sync endpoint

- **Status:** 403 | **Frequency:** rare
- **Trigger:** The external scheduler (every 15 min) calls /api/cygnet-sync with a wrong or rotated key — or a human pastes the URL into a browser without the key.
- **Mechanism:** `if (!hash_equals((string) config('cygnet.sync_key'), (string) $request->query('key'))) { abort(403); }` in a route closure. No message → errors::403 falls back to 'Forbidden'.
- **User sees:** Machine caller: an HTML error page where it expected JSON (the route's success path returns response()->json([...])), so the scheduler logs a parse error rather than a clear auth failure. Human in a browser: `403 \| FORBIDDEN`.
- **Way back:** n/a — machine endpoint.
- **Evidence:** routes/api.php:183, inside the closure opened at routes/api.php:181 and closed at :191. RE-VERIFIED LIVE this pass: GET https://development.citycommerce.group/api/cygnet-sync?key=wrong → status 403, 6,603 bytes, `<title>Forbidden</title>`, body renders `403 \| FORBIDDEN`.
- **Verify note:** VERIFIED. Live re-test reproduced status 403, the 6,603-byte minimal page and the 'Forbidden' title. Not a human design surface, but it is the one abort() that can be exercised live and it pins down the 403 rendering path exactly.

#### 4.2.29 404 when a company row is missing behind the Auto-Billing screens

- **Status:** 404 | **Frequency:** rare
- **Trigger:** An admin whose session company_id points at a deleted company, or a user whose company row was removed, opens Auto Billing (index, create, or edit a rule).
- **Mechanism:** `return abort(404, 'Company not found.');` — message discarded by the 404 view. Sibling branches in the same file handle the same class of problem gracefully, so the codepath is inconsistent within one controller.
- **User sees:** `404 \| NOT FOUND` with the actual reason ('Company not found.') stripped out by the hardcoded view.
- **Way back:** None — even though three other branches of the same controller already demonstrate a graceful in-page treatment.
- **Evidence:** app/Http/Controllers/AutoBillingController.php:36, :92 and :153 are all `return abort(404, 'Company not found.');`. Graceful siblings confirmed at :30 (returns the view with `'noCompanySelected' => true`), :86 (`return back()->withErrors(['company' => 'No company selected.'])`) and :112 (another withErrors branch). The role guards at :147 and :200 are `return abort(403, 'Unauthorized action.');`.
- **Verify note:** VERIFIED. All five cited line numbers exact; found a third graceful sibling at :112 that strengthens the inconsistency point.

#### 4.2.30 403 on voiding a refund from the wrong role

- **Status:** 403 | **Frequency:** rare
- **Trigger:** A company owner or branch user clicks 'Void' on a completed refund. Only admin and accountant may do it.
- **Mechanism:** `abort(403, 'Only admin or accountant can void refunds.');` The method returns RedirectResponse and its very next branch handles a comparable condition gracefully — so this is a full-page abort sitting three lines above a flash-message pattern.
- **User sees:** `403 \| ONLY ADMIN OR ACCOUNTANT CAN VOID REFUNDS.` — the message is genuinely useful, which makes the blank-page delivery more jarring, not less.
- **Way back:** None. They were on a refund detail page and are now on a blank framework page.
- **Evidence:** app/Http/Controllers/RefundController.php: `public function void(Refund $refund): RedirectResponse` at :1286, guard `if (!$user \|\| !$user->hasAnyRole(['admin','accountant']))` at :1289, `abort(403, 'Only admin or accountant can void refunds.');` at :1290. Graceful contrast at :1292-1294: `if ($refund->status === 'voided') { return back()->with('error', 'Refund is already voided.'); }`.
- **Verify note:** VERIFIED (line anchors shifted by ~2: abort is at :1290, graceful sibling at :1292-1294). Note the guard uses Spatie hasAnyRole(['admin','accountant']), not role_id, so a user with the right role_id but no matching Spatie role also gets this page.

#### 4.2.31 404 on the DOTW documentation pages, with the helpful message thrown away

- **Status:** 404 | **Frequency:** theoretical
- **Trigger:** Would fire if someone opened a /docs/dotw/<slug> URL with an unknown slug or a slug whose markdown file is missing — but no such URL exists on this deploy.
- **Mechanism:** `abort(404, 'Documentation page not found.')` for an unknown key; `abort(404, 'Documentation file not found.')` when the .md is absent. Both messages would be silently dropped because 404.blade.php hardcodes 'Not Found'.
- **User sees:** Nothing today — no URL reaches this controller.
- **Way back:** n/a
- **Evidence:** app/Http/Controllers/Docs/DotwDocumentationController.php:38 and :45 confirmed as the two abort calls; valid slugs are the array keys at :15-19 (overview, api, services, integration, architecture); index() at :31-34 renders a docs.dotw-hub view. HOWEVER: `grep -rn 'DotwDocumentationController' --include=*.php .` (excluding vendor) returns exactly ONE hit — the class declaration itself at :10. No route file (api.php, auth.php, console.php, resailai-admin.php, web.php) references it. The controller is unrouted dead code on branch port/phase1-launch-2026-08-24.
- **Verify note:** CORRECTED — downgraded from 'rare' to 'theoretical'. The abort lines are real, but the census asserted a reachable /docs/dotw/<slug> surface that does not exist on this deploy: the controller is never routed. A designer should not budget for this page; if the routes are added back it collapses into the generic 404.

#### 4.2.32 404 when the hardcoded 'clients' ledger account row does not exist

- **Status:** 404 | **Frequency:** theoretical
- **Trigger:** Would fire on the journal-entries-by-account view for a company whose chart of accounts has no account literally named 'clients' — but the method is not wired to any route.
- **Mechanism:** `$account = Account::where('name', 'clients')->first(); if ($account) { ...return view... } return abort(404); // Or redirect with a message` — the trailing comment shows the author knew this was a placeholder.
- **User sees:** Nothing today — no URL reaches this method.
- **Way back:** n/a
- **Evidence:** app/Http/Controllers/ReportController.php:1578-1594 is `public function show($account_name)`, with the hardcoded lookup at :1583 and `return abort(404); // Or redirect with a message` at :1593 (NOT :1594). HOWEVER: `grep -n "ReportController::class, 'show'" routes/*.php` returns nothing, and the only journal-entries.show route (routes/web.php:446) maps to JournalEntryController@show (app/Http/Controllers/JournalEntryController.php:79), a different method with a different signature. ReportController::show is unreachable dead code.
- **Verify note:** CORRECTED — downgraded from 'rare' to 'theoretical', and the abort line corrected from :1594 to :1593. The census read the code correctly but assumed it was routed; the journal-entries/{accountId}/account route actually resolves to JournalEntryController@show. Real dead code, not a live design surface.

#### 4.2.33 403 guest branch of EnsureModuleEnabled — fail-closed, not reachable in practice

- **Status:** 404 | **Frequency:** theoretical
- **Trigger:** None in the shipped route table. Would require the `module:` middleware to be applied to a route with no `auth` middleware in front of it.
- **Mechanism:** `if (! $user) { abort(404); }` — the middleware's own docblock states this branch 'should be unreachable in practice (an auth middleware placed before this one already redirects guests to login), but if this middleware is ever applied without auth in front, fail closed instead of leaking anything.'
- **User sees:** Nothing today — the branch is dead code as currently routed.
- **Way back:** n/a
- **Evidence:** app/Http/Middleware/EnsureModuleEnabled.php:59-67 (comment at :60-64, `abort(404)` at :66). Every `module:` usage in routes/web.php sits inside or alongside `auth` — the big `Route::middleware(['auth'])` group encloses lines 103-898, and the standalone groups at :937, :958 and :1219 all declare 'auth' explicitly in the same array. The one deliberate exemption, routes/web.php:949, strips BOTH 'auth' and 'module:accounting' together precisely so this branch cannot fire (explained in the comment at routes/web.php:925-933).
- **Verify note:** VERIFIED. abort line is :66 as cited; note the header says '403 guest branch' but the code is abort(404) — the name is a typo in the original census, the mechanism description is correct. Listed so the designer knows it produces the SAME 404 as the product-feature gate if a future route is ever added without auth.

---

### 4.3 Permissions & tenancy denials  (17 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Agent clicks "Currency Exchange" in the sidebar → 403 Unauthorized action | 403 | dead-common | None. The stock page contains zero `href |
| 2 | "Gate passes, then role_id rejects" — the whole nav-item-that-403s family | 403 | dead-common | None. |
| 3 | "This action is unauthorized." — the default policy denial (highest-volume wording in the app) | 403 | dead-common | None. |
| 4 | Companies List / company view / company edit 403 — Spatie role vs role_id split brain | 403 | occasional | None. |
| 5 | Module not in your package → silent 404 Not Found | 404 | occasional | None. |
| 6 | Cross-tenant action → 403 "…does not belong to your company" (full-page variant) | 403 | occasional | None. |
| 7 | Cross-tenant action → native browser alert() (AJAX variant, invoice edit) | 403 (JSON) | occasional | Yes, de facto — dismissing the alert ret |
| 8 | The soft path that already works — redirect + flash banner | 302 (inline banner, no error status) | occasional | Yes — they are already on a usable page, |
| 9 | Shared proforma invoice link opened while logged out → HTTP 500 stack-trace page | 500 | occasional | None — and notably no login link, so a s |
| 10 | "Void Refund" button → 403 Only admin or accountant can void refunds | 403 | rare | None, and worse than usual: the request  |
| 11 | 403 FORBIDDEN with no message at all | 403 | rare | None. |
| 12 | Accountant-only invoice edit → 403 Access denied (blocks admins too) | 403 | rare | None. |
| 13 | DOTW Admin → 403 Access denied | 403 | rare | None. |
| 14 | Role-change guard → 403 Cannot change role of Admin users | 403 | rare | None. |
| 15 | System Settings / email tester → 403 This action is unauthorized | 403 (page) / 403 JSON (AI config + email tester AJAX) | rare | None. |
| 16 | Livewire component denial → stock 403 page inside a full-screen modal | 403 | theoretical | Yes, accidentally — Esc dismisses the Li |
| 17 | Registered-but-unused denial middleware (permission / role_or_permission) | 403 (would be) | theoretical | n/a |

#### 4.3.1 Agent clicks "Currency Exchange" in the sidebar → 403 Unauthorized action

- **Status:** 403 | **Frequency:** dead-common
- **Trigger:** An agent-role user opens the sidebar (or mobile drawer), sees the "Currency Exchange" item, clicks it. The menu shows it because the agent genuinely holds the `view currency exchange` permission; the controller then rejects them purely for being an agent (`role_id == Role::AGENT`).
- **Mechanism:** `return abort(403, 'Unauthorized action.');` — Symfony HttpException, HTTP 403, rendered by the vendor `errors::403` view via `errors::minimal` (no app view exists). Note the abort fires AFTER the controller has already fetched live FX rates; if that fetch fails first, line 81 redirects back with a flash instead.
- **User sees:** Full page replacement on a gray ground (`bg-gray-100` / `dark:bg-gray-900`): a single centered line `403 \| UNAUTHORIZED ACTION.` — the message div carries Tailwind `uppercase` in the vendor minimal layout. No logo, no nav, no sidebar, no explanation.
- **Way back:** None. The stock page contains zero `href`s (confirmed by counting anchors in a live vendor error page). Browser Back is the only exit.
- **Evidence:** app/Http/Controllers/CurrencyExchangeController.php:86, resources/views/layouts/menu.blade.php:409-412, resources/views/layouts/mobile-drawer.blade.php:290-292, app/Policies/CurrencyExchangePolicy.php:18-24
- **Verify note:** VERIFIED (numbers corrected). Line 86, menu 409-412, mobile 290-292 and the policy body are all exact. DB re-checked: the DEFAULT agent role (`roles.id=4`, company 1) does carry `view currency exchange`. Corrected counts: 21 of 32 dev users have `users.role_id=4` (not 22); Spatie role 4 is held by 21 users, 20 of whom are `role_id=4` agents who hit this abort (user 3 also holds Spatie role 4 but has `role_id=1`, so he passes). Gating by `@if($hasAccountingModule)` confirmed present in both menus.

#### 4.3.2 "Gate passes, then role_id rejects" — the whole nav-item-that-403s family

- **Status:** 403 | **Frequency:** dead-common
- **Trigger:** Any agent (and sometimes branch/accountant) clicks a sidebar item their permission set genuinely grants: Chart of Account, Payment Voucher (bank payments), Manage Roles, Users List, Reports → Paid/Unpaid Acc Pay-Receive or Trial Balance, Add Supplier. Two authorization systems disagree: the Blade `@can()` and the controller's `Gate::authorize()` both consult Spatie permissions, then a hand-written `if (!in_array($user->role_id, [...])) abort(403)` immediately below overrides them.
- **Mechanism:** `Gate::authorize(...)` succeeds, then `abort(403, 'Unauthorized action.')` on an integer `role_id` check. HTTP 403, vendor `errors::403`.
- **User sees:** `403 \| UNAUTHORIZED ACTION.` — the same bare vendor page. Identical wording whether the user lacked a permission, held the wrong role_id, or belongs to the wrong company.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/CoaController.php:30+35, app/Http/Controllers/RoleController.php:29+44, app/Http/Controllers/AdminUsersController.php:29+76, app/Http/Controllers/BankPaymentController.php:27+61, app/Http/Controllers/ReportController.php:839,988,1148,1695,1886,2177,4050,4108,4160, app/Http/Controllers/SupplierController.php:51+238, resources/views/layouts/menu.blade.php:55,57,61,63,177,179,249,358,364,366
- **Verify note:** CORRECTED. Verified: every cited line exists and reads as claimed; the concrete dev case holds exactly — user 28 (Sarah Abutaleb, `users.role_id=4` AGENT) carries company-2 agent role `roles.id=8`, which grants `view coa`, `view role`, `view user`, `view report`, `view supplier`, making Chart of Account, Payment Voucher, Users List, Manage Roles, Reports and Suppliers all visible and (except the supplier list itself) 403. THREE corrections: (1) AccountingController:31+37 is NOT nav-reachable — the Accounting menu item at menu.blade.php:99-101 is gated on `@can('viewCompanySummary', Account)`, which needs the `view company summary` permission, held ONLY by Spatie role 1 'admin' per the DB. Dropped from the visible-nav list. (2) PaymentMethodController:39 is the final `else` for an UNRECOGNIZED role_id (AGENT is handled two branches earlier at :34), so it belongs to the no/unknown-role family, not this one. (3) CreditController:43 has no sidebar entry at all — `credits.index` appears in no layout file — so it is direct-URL only. Also missed: ReportController:1603 is `abort(403, 'Unauthorized')` (no trailing period), a FOURTH distinct spelling of the same denial. Grep count: 30+ `Unauthorized action.` call sites confirmed.

#### 4.3.3 "This action is unauthorized." — the default policy denial (highest-volume wording in the app)

- **Status:** 403 | **Frequency:** dead-common
- **Trigger:** Almost anything a Policy says no to: viewing tasks, invoices, clients, suppliers, branches, agents, reports, roles, charges, COA, system settings.
- **Mechanism:** Policy method returns false → Illuminate\Auth\Access\AuthorizationException → converted to HTTP 403 with Laravel's default message "This action is unauthorized."
- **User sees:** `403 \| THIS ACTION IS UNAUTHORIZED.` on the bare vendor page.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/TaskController.php:72, app/Http/Controllers/InvoiceController.php:58, app/Http/Controllers/CompanyController.php:37, app/Http/Controllers/SupplierController.php:51, app/Http/Controllers/SystemSettingController.php:23, app/Policies/ (24 policy classes), app/Policies/Concerns/RequiresCompanyModule.php:45-56, app/Helper/helper.php:6-22
- **Verify note:** VERIFIED (counts corrected). All five cited controller lines contain exactly the claimed `Gate::authorize()` call. Corrected volumes: 78 `Gate::authorize` call sites across app/ (76 of them in app/Http/Controllers/), not 93; and 24 policy classes in app/Policies/, not 25. The critical wording point is fully confirmed: module-gated policies run `RequiresCompanyModule::moduleEnabled()` FIRST (RequiresCompanyModule.php:45-56) and return false if (a) the company's package excludes the module or (b) `getCompanyId($user)` resolves to null. So "your plan doesn't include this", "your account isn't attached to a company" and "you lack this permission" are indistinguishable to user and support alike. Confirmed live in COAPolicy:18-27, ReportPolicy:22-32, SupplierPolicy:14-25, InvoicePolicy:95-104, AccountPolicy:41-48.

#### 4.3.4 Companies List / company view / company edit 403 — Spatie role vs role_id split brain

- **Status:** 403 | **Frequency:** occasional
- **Trigger:** Someone opens /companies, /companies/{id}, or /companies/{id}/edit without carrying the Spatie 'admin' role. Two populations hit it: (a) company owners who were sent or bookmarked the URL; (b) genuine super-admins whose `model_has_roles` row is not 'admin' — treated as ADMIN by every other check in the app but locked out here.
- **Mechanism:** `'middleware' => ['auth', 'role:admin']` → Spatie\Permission\Middleware\RoleMiddleware → `UnauthorizedException::forRoles()` → HTTP 403, message "User does not have the right roles." (`display_role_in_exception => false`, so the required role is not named).
- **User sees:** `403 \| USER DOES NOT HAVE THE RIGHT ROLES.` — grammatically third-person, tells the user nothing about which role or how to get it.
- **Way back:** None.
- **Evidence:** routes/web.php:220-229 (esp. :223), vendor/spatie/laravel-permission/src/Exceptions/UnauthorizedException.php:14-26, config/permission.php:146, routes/web.php:112-138 (the in-repo comment documenting this exact hazard for the neighbouring /users group)
- **Verify note:** VERIFIED, exact. Every line number is correct to the character. Additionally confirmed with `route:list -v` (run with APP_ENV=testing to dodge this server's AIManager boot crash): all four companies routes — GET companies, GET/PUT companies/{id}, GET companies/{id}/edit — resolve to the guarded group and carry `Spatie\Permission\Middleware\RoleMiddleware:admin`, despite duplicate unguarded registrations of edit/update at routes/web.php:214-215 (Laravel's RouteCollection keys by method+URI, so the later group registration wins). DB re-verified: `view company` is granted ONLY to Spatie role id 1 'admin', held by users 1, 8, 34; user 3 has `users.role_id=1` but Spatie role 'agent' → menu item hidden AND route 403. The routes/web.php comment block (112-138) explicitly says the /users group's `role:admin` was deliberately left commented out for this reason while /companies still has it live.

#### 4.3.5 Module not in your package → silent 404 Not Found

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** A user whose company has a module switched off opens any URL in it — most often accounting (chart of accounts, vouchers, trial balance, P&L, journal entries). They get there via a bookmark, a link in an email/PDF, a receipt-voucher link, or an in-page link that wasn't hidden. The nav items themselves ARE hidden.
- **Mechanism:** `abort(404)` in EnsureModuleEnabled — deliberately 404 rather than 403 so a disabled module is indistinguishable from a feature that was never built (documented at length in the middleware docblock).
- **User sees:** `404 \| NOT FOUND` — re-confirmed live against https://development.citycommerce.group: HTTP 404, 6603 bytes, `<title>Not Found</title>`, ZERO `href` attributes in the whole document. Indistinguishable from a typo'd URL or a deleted record.
- **Way back:** None.
- **Evidence:** app/Http/Middleware/EnsureModuleEnabled.php:56 (handle) with aborts at :66 and :73, routes/web.php:103,336,428,442,460,722,814,937,958,1219, app/Support/Modules.php:24-64
- **Verify note:** VERIFIED (two corrections). Middleware, docblock rationale and abort lines all confirmed; every cited routes/web.php line does carry `module:accounting` (or `module:resayil` at 1219). Correction 1: the route count is higher than reported — `route:list -v` shows 93 module-gated routes, 88 of them `module:accounting` (census said ~60), plus 2 payment_gateway, 1 crm, 1 resayil, 1 task_uploader. Correction 2: company 3 (Akeed) has SIX `module.*` rows in `settings` (ids 32-37), not one — task_uploader/payment_gateway/crm/agent_profit/resayil are all `true` and only `module.accounting` is `false`. Companies 1 and 2 have no module rows at all and fall through Company::hasModule()'s fail-OPEN default (Company.php:121-134). The 404-not-403 choice is intentional and documented; preserve it.

#### 4.3.6 Cross-tenant action → 403 "…does not belong to your company" (full-page variant)

- **Status:** 403 | **Frequency:** occasional
- **Trigger:** Acting on an invoice / client / credit / payment that resolves to a different company than the caller. In practice the commonest real cause is not an attacker but an ADMIN whose session company has drifted: `getCompanyId()` silently defaults an ADMIN to company 1 when `session('company_id')` is unset, so an admin who hasn't used the company switcher is treated as company 1 while looking at a company-2 record.
- **Mechanism:** `abort_unless($companyId && $record->…->company_id === $companyId, 403, 'Unauthorized: this invoice does not belong to your company.')`
- **User sees:** `403 \| UNAUTHORIZED: THIS INVOICE DOES NOT BELONG TO YOUR COMPANY.` (also …CLIENT DOES NOT BELONG…, …CREDIT SOURCE…, …PAYMENT SOURCE…, …INVOICE SPLIT…). Shouty all-caps on a bare page.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/InvoiceController.php:945,6517,6577,6644,6696,6717,6739, app/Http/Controllers/CreditController.php:175-178, app/Services/PaymentApplicationService.php:61,89,571,577,599,617 (plus a thrown Exception variant at :219), app/Helper/helper.php:9-10
- **Verify note:** VERIFIED, with one site ADDED. Every cited line confirmed verbatim. Added: InvoiceController.php:945 in `savePartial()` (a `JsonResponse` method) carries the same `Unauthorized: this invoice does not belong to your company.` guard — it was missed and is the AJAX-shaped sibling of the page ones. `getCompanyId()` at helper.php:9-10 confirmed: `case Role::ADMIN: return (int) session('company_id', 1);` — the silent default-to-company-1 is real. The caveat also holds: the same string fires when `getCompanyId()` returns NULL (COMPANY/BRANCH/AGENT/ACCOUNTANT users with no matching relation row, or any unrecognized role_id), where "does not belong to your company" is actively misleading. This is the one place the app distinguishes wrong-tenant from wrong-permission; worth preserving as a separate voice.

#### 4.3.7 Cross-tenant action → native browser alert() (AJAX variant, invoice edit)

- **Status:** 403 (JSON) | **Frequency:** occasional
- **Trigger:** On the invoice edit screen, choosing credits/payments and clicking Apply Payments, where the invoice belongs to another company (or the admin's session company has drifted).
- **Mechanism:** Same `abort_unless(..., 403, 'Unauthorized: this invoice does not belong to your company.')`, but the caller sends `Accept: application/json`, so Laravel returns a JSON body. The JS does `result.success` → undefined → falsy → `alert(result.message \|\| 'Failed to apply payments…')`.
- **User sees:** A native OS/browser modal dialog titled with the site host, body text "Unauthorized: this invoice does not belong to your company.", single OK button. Page stays intact behind it; the Apply button re-enables and its label is restored.
- **Way back:** Yes, de facto — dismissing the alert returns the user to a working page with their work intact. This is the least destructive denial surface in the whole app and is the behavioural model the rest should copy.
- **Evidence:** resources/views/invoice/edit.blade.php:6678-6702 (fetch with `'Accept': 'application/json'`, `const result = await response.json()`, `if (result.success) … else alert(result.message \|\| 'Failed to apply payments. Please try again.')`, `catch → alert('An error occurred. Please try again.')`), app/Http/Controllers/InvoiceController.php:6577, app/Services/PaymentApplicationService.php:61,89
- **Verify note:** VERIFIED, exact. The fetch block, the header, the `result.success` branch, the `alert(result.message \|\| …)` fallback and the generic `catch` alert are all present as described. `.env:4` on this server is `APP_DEBUG=true` (and `APP_ENV=local`), so the JSON body also carries `exception`, `file`, `line` and a full stack trace alongside `message` — the alert shows only `message`, but the trace is in the network tab. The observation about non-JSON AJAX callers holds: without `Accept: application/json` Laravel returns the 6.6KB HTML error page into `.json()`, which throws, so the generic catch fires and the user sees "An error occurred. Please try again." with no hint that it was a permission problem.

#### 4.3.8 The soft path that already works — redirect + flash banner

- **Status:** 302 (inline banner, no error status) | **Frequency:** occasional
- **Trigger:** Same class of denial as everything above, but a handful of controllers chose to redirect instead of abort: an admin with no company selected opening Manage Roles or Chart of Accounts, an agent opening Payment Links, a role that matches no branch opening Credits or Payment Voucher, an accountant/client opening a proforma invoice.
- **Mechanism:** `return redirect()->route('dashboard')->with('error', 'Please select a company first.')` / `redirect()->back()->with('error', 'You are not authorized to view payment links.')` / `'Page not found.'` / `'Unauthorized access.'` — HTTP 302, no exception.
- **User sees:** A styled in-app red alert banner (`bg-red-500 p-3.5 text-white`, with a dismiss X) rendered by layouts/alert.blade.php on a fully working page — dashboard or the page they came from. Reads e.g. "Please select a company first."
- **Way back:** Yes — they are already on a usable page, with nav, and the message tells them the next action.
- **Evidence:** app/Http/Controllers/RoleController.php:38-40, app/Http/Controllers/CoaController.php:38-41, app/Http/Controllers/PaymentController.php:2585-2587, app/Http/Controllers/CreditController.php:44-46, app/Http/Controllers/BankPaymentController.php:62-64, app/Http/Controllers/InvoiceController.php:3164-3166, resources/views/layouts/alert.blade.php:42-46
- **Verify note:** VERIFIED (line numbers tightened). All redirects confirmed: RoleController:39, CoaController:40, PaymentController:2586, CreditController:45, BankPaymentController:63; alert.blade.php:42-46 is the `@if(session('error'))` block exactly. The inconsistency claim is confirmed and is the finding: CoaController.php:35 aborts for an agent while CoaController.php:40 — five lines later, same method — redirects for an admin with no company. `'Page not found.'` is used as a denial message in BankPaymentController:63 and CreditController:45 (a third voice), and InvoiceController:3165 adds a FOURTH, `'Unauthorized access.'`, for an accountant or client opening a proforma. Included as the CONTRAST CASE, not a defect.

#### 4.3.9 Shared proforma invoice link opened while logged out → HTTP 500 stack-trace page

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** A client (or an agent on their phone, or anyone in a WhatsApp group) taps a proforma-invoice link — `/invoice/{companyId}/{invoiceNumber}/proforma` — while not signed in. These links are deliberately public (the route is `withoutMiddleware(['auth'])`, same as the invoice and PDF links that ARE shared with clients).
- **Mechanism:** `$user = Auth::user()` returns null at InvoiceController:3138, then line 3153 dereferences `$user->role_id`. Laravel's error handler escalates the resulting PHP warning ("Attempt to read property \"role_id\" on null") to an ErrorException → HTTP 500. The `return abort(403)` at :3167 that was presumably meant to catch guests is never reached.
- **User sees:** Confirmed live and anonymously against https://development.citycommerce.group: HTTP 500, a 323KB Laravel debug page containing the exception message, the offending file and line, and a full stack trace of application source. With APP_DEBUG=false it would instead be the bare vendor `500 \| SERVER ERROR` line. Either way it is not the invoice they were sent. For contrast, the sibling public link `/invoice/{companyId}/{invoiceNumber}` (invoice.show) returns 200 and renders correctly for the same guest.
- **Way back:** None — and notably no login link, so a signed-out client has no route to the page even though signing in would fix it.
- **Evidence:** routes/web.php:572 (`->withoutMiddleware(['auth'])`), app/Http/Controllers/InvoiceController.php:3136 (proforma), :3138, :3153, :3163-3167
- **Verify note:** ADDED IN VERIFY. Tripped over while checking the census's InvoiceController:3167 citation (finding "403 FORBIDDEN with no message at all"), which is dead code precisely because of this. Reproduced twice: a guest GET of a proforma URL for a real invoice number returns 500 with `Attempt to read property "role_id" on null`; a guest GET for a non-existent invoice number returns the clean 6603-byte vendor 404 (`abort(404)` at :3148). Same shape almost certainly applies to `invoice.proforma.pdf` (routes/web.php:573, also public) — not separately tested.

#### 4.3.10 "Void Refund" button → 403 Only admin or accountant can void refunds

- **Status:** 403 | **Frequency:** rare
- **Trigger:** Reachable only out-of-band: a direct POST to `/refunds/{id}/void`, a re-submitted form, or a page rendered while the user still held admin/accountant whose role changed before they clicked. Not reachable by an ordinary agent clicking through the UI.
- **Mechanism:** `abort(403, 'Only admin or accountant can void refunds.')` after `$user->hasAnyRole(['admin','accountant'])` — a SPATIE role check, not `role_id`. POST request, so the 403 replaces the page.
- **User sees:** `403 \| ONLY ADMIN OR ACCOUNTANT CAN VOID REFUNDS.` on a bare page. The refund list, the filters, the modal state — all gone.
- **Way back:** None, and worse than usual: the request was a POST, so browser Back triggers a form-resubmission warning rather than cleanly returning to the list.
- **Evidence:** app/Http/Controllers/RefundController.php:1286-1291 (abort at :1290), resources/views/refunds/index.blade.php:181 (the guard) and :216-221 (the button), routes/web.php:614
- **Verify note:** CORRECTED — the central claim was wrong. The census said the button "carries no role guard at all". It does: resources/views/refunds/index.blade.php:181 wraps the entire void control in `@if(auth()->user()?->hasAnyRole(['admin','accountant']) && $refund->status !== 'voided')` — the SAME Spatie check the controller runs. Blade and controller agree, so no split-brain and no dead link. Frequency downgraded from occasional to rare, and the trigger rewritten to out-of-band only. What survives: the surface exists and its delivery is destructive (bare page, POST-back). The super-admin observation also survives but changes shape — dev user 3 (Soud Shoja, `users.role_id=1`, Spatie role 'agent') simply never sees the button, so he is silently unable to void refunds rather than being 403'd. The message wording is genuinely good; only the delivery is the problem.

#### 4.3.11 403 FORBIDDEN with no message at all

- **Status:** 403 | **Frequency:** rare
- **Trigger:** A user whose role resolves to no company at all (an unrecognized role_id, a COMPANY user with no companies row, an AGENT with no agents row, an ACCOUNTANT with no accountant row) triggers a company-scoped endpoint; or a non-admin opens Company Invites; or a non-admin opens the new-company / set-company / admin-user endpoints.
- **Mechanism:** `abort_unless($companyId, 403)` / `abort_unless((int) $user->role_id === Role::ADMIN, 403)` — no message argument, so the vendor view's `__($exception->getMessage() ?: 'Forbidden')` falls back to the literal word "Forbidden".
- **User sees:** `403 \| FORBIDDEN` — the absolute minimum. No indication of what was forbidden or why.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/ReportController.php:1660, app/Http/Controllers/SupplierController.php:219, app/Http/Controllers/CompanyInviteController.php:17, app/Http/Controllers/AdminUsersController.php:184,212,310, vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/403.blade.php:5
- **Verify note:** CORRECTED — one citation dropped. All six surviving sites confirmed verbatim, and the vendor 403 view's `?: 'Forbidden'` fallback confirmed at vendor/…/views/403.blade.php:5. DROPPED: app/Http/Controllers/InvoiceController.php:3167 is DEAD CODE, not a reachable surface — in `proforma()` the guest branch never gets there because line 3153 dereferences `$user->role_id` on a null `$user` first, and Laravel escalates that PHP warning to an ErrorException. Proven live: an anonymous GET of a real proforma URL returns HTTP 500, not 403 (see the separate finding added in verify). The two AJAX cases hold: ReportController:1660 (`journalEntriesByDate`) and SupplierController:219 (`ledgerByDateRange`) return a JSON stack trace under APP_DEBUG=true, and `suppliers.ledger-by-date` (routes/web.php:288) has no caller anywhere in resources/views — confirmed unreachable from the UI today.

#### 4.3.12 Accountant-only invoice edit → 403 Access denied (blocks admins too)

- **Status:** 403 | **Frequency:** rare
- **Trigger:** Anyone who is not role_id=ACCOUNTANT opens an accountant-edit invoice link — a link pasted into WhatsApp/email by an accountant, a bookmark, or a page a user reached while an accountant and returns to after their role changed. Includes full super-admins and company owners.
- **Mechanism:** `abort(403, 'Access denied')` in the `accountant` alias middleware. Strict `!==` against Role::ACCOUNTANT — there is no admin bypass.
- **User sees:** `403 \| ACCESS DENIED` on the bare page (no trailing period).
- **Way back:** None.
- **Evidence:** app/Http/Middleware/AccountantView.php:19-21 (abort at :20), routes/web.php:560-568 (group; routes at :565-567), resources/views/invoice/index.blade.php:489-499, app/Policies/InvoicePolicy.php:95-104
- **Verify note:** VERIFIED, exact. `route:list -v` confirms exactly 3 routes carry `App\Http\Middleware\AccountantView`: invoice.accountant.edit (GET), .update (PUT), .create.payment.link.shortage (POST). The entry link IS correctly hidden behind `@can('accountantEdit', $invoice)` at invoice/index.blade.php:489, and InvoicePolicy::accountantEdit (95-104) requires both the payment_gateway module and `role_id == Role::ACCOUNTANT`, so this is only reachable out-of-band. The two non-GET routes mean a denial can land mid-form-submit with unsaved data.

#### 4.3.13 DOTW Admin → 403 Access denied

- **Status:** 403 | **Frequency:** rare
- **Trigger:** An accountant, branch or agent opens a bookmark or pasted link to /admin/dotw (or its two 301 aliases). There is no in-app link to it for those roles.
- **Mechanism:** `abort(403, 'Access denied.')` — allows only role_id in [ADMIN, COMPANY].
- **User sees:** `403 \| ACCESS DENIED.` (with a trailing full stop, unlike AccountantView's identical-but-period-less "Access denied").
- **Way back:** None.
- **Evidence:** app/Http/Middleware/DotwAuditAccess.php:11-18 (abort at :16), routes/web.php:1101-1108
- **Verify note:** CORRECTED — the stated entry path does not exist. The middleware, the routes and the two 301 redirect aliases are all confirmed, and `route:list -v` shows exactly 3 routes carrying DotwAuditAccess. But the claimed trigger ("follows the DOTW Admin → API Tokens link embedded in the DOTW documentation hub") is not reachable: `resources/views/docs/dotw-hub.blade.php` is rendered only by App\Http\Controllers\Docs\DotwDocumentationController:32, and that controller is registered in NO route file — `route:list --path=docs` returns just 5 routes (user, api, developer, magic-webhook, postman download) and none of them is the DOTW hub. So the docs page cannot be opened by anyone and cannot advertise the link. The plain unwrapped `url('/admin/dotw')` at dotw-hub.blade.php:245 is still there, and will start advertising a page most readers cannot open the moment that controller is routed. The 403 itself remains real via direct URL.

#### 4.3.14 Role-change guard → 403 Cannot change role of Admin users

- **Status:** 403 | **Frequency:** rare
- **Trigger:** A company owner opens Users List, clicks Edit on a teammate who happens to be an app-level admin, and the edit-role screen refuses to open.
- **Mechanism:** `abort(403, 'Cannot change role of Admin users.')`, fired when `$user->role_id == Role::ADMIN && Auth::user()->role_id != Role::ADMIN` — i.e. only for a COMPANY owner editing an ADMIN. It sits directly below an earlier `abort(403, 'Unauthorized action.')` guard on the same method.
- **User sees:** `403 \| CANNOT CHANGE ROLE OF ADMIN USERS.` on the bare page — they lose the users list they were working through.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/AdminUsersController.php:92 (editRole), :93-95 (the [ADMIN, COMPANY] gate, abort at :94), :99-101 (the admin-target rule, abort at :100), :157-159 (the update path)
- **Verify note:** VERIFIED (one note corrected). Both aborts confirmed at :94 and :100, and the condition confirmed as company-owner-editing-an-admin. Correction to the census note: AdminUsersController:158 is NOT "the matching guard on the update path" for this rule — it mirrors only the `[Role::ADMIN, Role::COMPANY]` gate with the message `'Unauthorized action.'`. The "cannot change role of Admin users" rule itself is NOT enforced on the POST update path (see the in-repo comment at :150-157), so the business rule is display-time only. The observation stands: a per-row rule is enforced by destroying the whole page instead of disabling one row's Edit button.

#### 4.3.15 System Settings / email tester → 403 This action is unauthorized

- **Status:** 403 (page) / 403 JSON (AI config + email tester AJAX) | **Frequency:** rare
- **Trigger:** A non-super-admin opens /system-settings or one of its AJAX actions (test email, preview email, save tab, hotels/countries CRUD, AI config).
- **Mechanism:** `Gate::authorize('manage-system-settings')` / `('manage-email-tester')`, both defined onto SystemSettingPolicy which is a bare `role_id === Role::ADMIN` check → AuthorizationException → 403. Several sibling methods in SettingController instead use raw `abort(403, 'Unauthorized action.')`, so the same settings area produces two different messages depending on which button you press.
- **User sees:** Page routes: `403 \| THIS ACTION IS UNAUTHORIZED.` AJAX routes: with APP_DEBUG=true, a JSON body containing message + exception class + file + line + full stack trace; the calling JS has no error branch for it, so the panel simply never fills in.
- **Way back:** None.
- **Evidence:** app/Providers/AppServiceProvider.php:62-63, app/Policies/SystemSettingPolicy.php:10-18, app/Http/Controllers/SystemSettingController.php:23,45,78,100,137,171,348,385,424, app/Http/Controllers/SettingController.php:726,766,851,880, resources/views/layouts/menu.blade.php:347, resources/views/layouts/mobile-drawer.blade.php:273
- **Verify note:** VERIFIED, exact. `Gate::define('manage-system-settings', [SystemSettingPolicy::class, 'viewAny'])` and `manage-email-tester` are at AppServiceProvider.php:62-63 exactly; SystemSettingPolicy.php:10-18 is a bare `role_id === Role::ADMIN` pair; all cited controller lines exist (plus three more `manage-email-tester` sites at :78, :100, :137 that the census missed). The nav entry IS gated at menu.blade.php:347 and mobile-drawer.blade.php:273 — both `@can('manage-system-settings')` — so this needs a direct URL.

#### 4.3.16 Livewire component denial → stock 403 page inside a full-screen modal

- **Status:** 403 | **Frequency:** theoretical
- **Trigger:** A Livewire action or mount that aborts. Today the only such component is the DOTW API token manager.
- **Mechanism:** `abort_unless($this->isSuperAdmin(), 403, 'Super Admin only.')` in mount(); bare `abort_unless($this->isSuperAdmin(), 403)` in each action. Livewire's JS receives a non-2xx XHR and calls `showHtmlModal`, injecting the returned HTML into a full-viewport overlay iframe.
- **User sees:** On mount, the bare `403 \| SUPER ADMIN ONLY.` page rendered inside a white full-screen overlay on top of the still-live app, dismissible with Esc. From the two actions it would read `403 \| FORBIDDEN` instead — they pass no message.
- **Way back:** Yes, accidentally — Esc dismisses the Livewire modal and the underlying page survives.
- **Evidence:** app/Http/Livewire/Admin/DotwApiTokenIndex.php:25 (mount, with message), :40 and :65 (actions, NO message), :48 (`abort_if(is_null($user), 422, 'Company has no primary user.')`), app/Providers/AppServiceProvider.php:47 (component alias registration), vendor/livewire/livewire/dist/livewire.js (showHtmlModal)
- **Verify note:** VERIFIED, and the honest call stands. Line numbers are exact. Not reachable today: the only blade rendering the component is resources/views/admin/dotw/api-tokens.blade.php:2, whose route is now a 301 redirect (routes/web.php:1107), and the embed in livewire/admin/dotw-admin-index.blade.php:305 is commented out. One nuance the census missed: the aborts inside generateToken (:40) and revokeToken (:65) pass no message, so those would render `FORBIDDEN`, not `SUPER ADMIN ONLY.` — the same component would produce two different modal texts. Kept because it is the ONLY example of what a Livewire-side denial looks like, and the component is still alias-registered at AppServiceProvider:47, so any `@livewire('admin.dotw-api-token-index')` resurrects it.

#### 4.3.17 Registered-but-unused denial middleware (permission / role_or_permission)

- **Status:** 403 (would be) | **Frequency:** theoretical
- **Trigger:** Nothing today. Reported so the designer isn't asked to style a surface that has no traffic.
- **Mechanism:** `'permission' => PermissionMiddleware::class` and `'role_or_permission' => RoleOrPermissionMiddleware::class` are aliased in bootstrap/app.php but NOT applied to a single route. Their messages would be "User does not have the right permissions." and "User does not have any of the necessary access rights." (required names suppressed by config).
- **User sees:** n/a — never rendered.
- **Way back:** n/a
- **Evidence:** bootstrap/app.php:28-30 (aliases) and :44-46 (empty `->withExceptions`), config/permission.php:138,146, vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/minimal.blade.php (body markup)
- **Verify note:** VERIFIED (three numbers corrected). Re-ran `route:list -v` with APP_ENV=testing (plain `artisan route:list` crashes on this server with the same AIManager/ResayilClient boot error as tinker — the census's claim to have run it plainly does not hold, though its conclusion does). Results across the whole table: `RoleMiddleware` appears 4 times (the /companies group), `PermissionMiddleware` 0 times, `RoleOrPermissionMiddleware` 0 times. Corrections: total route count is 676, not 679; the alias lines are bootstrap/app.php:28-30, not 32-34. Everything else stands and is now confirmed at source: `resources/views/errors/` does not exist; `->withExceptions(function (Exceptions $exceptions) { // })` is empty; the vendor `errors::minimal` body is a single flex row of `@yield('code')` and `@yield('message')` inside `<div class="ml-4 text-lg text-gray-500 uppercase tracking-wider">` — hence the shouty all-caps — with no links and no branding anywhere. APP_DEBUG=true does NOT change this for HttpExceptions; it only changes AJAX/JSON bodies, which then leak file/line/stack trace.

---

### 4.4 Validation & forms  (19 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Validation error toasts all render on top of each other at one absolute coordinate | n/a (inline, after 302 redirect) | dead-common | A small white '×' that sets display:none |
| 2 | 119 form views have no error output and no old() — validation reads as 'nothing happened' | n/a (302 redirect back) | dead-common | None specific. The user has to retype th |
| 3 | 'Add New Client' form inside the invoice builder destroys the whole invoice draft | n/a (302 redirect back) | dead-common | None. The invoice draft is not recoverab |
| 4 | 45 of 70 AJAX POSTs get a 302 HTML redirect instead of 422 JSON, then crash on .json() | 302 -> 200 HTML (should have been 422 JSON) | dead-common | None. There is no signal at all that a s |
| 5 | Native browser alert() is the validation/error UI for AJAX paths (156 call sites) | 400 / 422 / 500, or 200 with success:false | dead-common | An OK button that dismisses the dialog a |
| 6 | Login shows the same failure sentence up to four times | n/a (302 redirect back) | dead-common | The form is still on screen with the ema |
| 7 | Task detail page renders every error and success message twice | n/a (inline) | dead-common | Two separate '×' buttons for the same me |
| 8 | Breeze scaffolding island — the only forms with real field-level errors | n/a (302 redirect back) | dead-common | The form stays on screen with values pre |
| 9 | invoice/edit toast helper piles up permanently-stacked boxes and injects server text as HTML | 422 / 500 | occasional | '×' per box, which hides one and uncover |
| 10 | Company registration wizard bounces to step 1 and prints raw array attribute paths | n/a (302 redirect back) | occasional | The user can click forward through the s |
| 11 | Raw PHP/SQL exception text shown to end users in the error toast | n/a (302 redirect back) / 400-500 in the JSON variants | occasional | The '×' on the toast. The user is redire |
| 12 | App-level file size limits are tighter than the server limits and surface three different ways | 422 for XHR; n/a (302) for form posts | occasional | On the bulk-invoice form the user stays  |
| 13 | max_file_uploads=20 silently truncates multi-PDF batches with no error at all | n/a (silent truncation at the PHP layer) | occasional | None — the user has no idea anything was |
| 14 | AJAX GETs that fail render an empty list with no message | varies (500 / 403 / 302-to-HTML) | occasional | None. Reloading the page is the only rec |
| 15 | CoaController::addCategory uses the same non-existent-rule pattern, and this one IS reachable f | 500 | occasional | None. Browser back button, and the form  |
| 16 | Public payment link page shows nothing when the pay request is rejected | n/a (inline) for the validation path; 404 for the abort path | rare | None on the 404 (there is no site chrome |
| 17 | 413 Payload Too Large renders the stock Laravel page with no way back | 413 | rare | Browser back button only. |
| 18 | SupplierCredentialRequest uses a rule that does not exist — 500 instead of a validation message | 500 | theoretical | None. |
| 19 | The app's only well-formed JSON validation response is dead code, and one FormRequest hard-deni | 422 (unreachable) / 403 (unreachable) | theoretical | n/a |

#### 4.4.1 Validation error toasts all render on top of each other at one absolute coordinate

- **Status:** n/a (inline, after 302 redirect) | **Frequency:** dead-common
- **Trigger:** Submitting any form on any authenticated page with more than one field wrong — e.g. creating a client with no phone and no agent selected, or saving a charge with three blank fields.
- **Mechanism:** ValidationException -> 302 redirect back -> resources/views/layouts/alert.blade.php:1-11 loops $errors->all() and emits one <div class="alert alert-danger fixed mt-5 top-1 right-4 ..."> per message. The project's own rule .alert{position:absolute;top:2rem;right:6.4rem;z-index:50} is compiled AFTER Tailwind's .fixed in the active bundle (byte 427765 vs 293690 in public/build/assets/app-tpo1jr80.css, which public/build/manifest.json maps resources/css/app.css to), same specificity, so it wins. Verified that app.css is @vite'd at app.blade.php:37, i.e. AFTER the CDN Tailwind <link> pulled in by layouts/links.blade.php:14, so nothing later overrides it. Every message box therefore gets identical absolute coordinates and they overlap exactly. The alert include sits directly under <body> so the containing block is the document, not the viewport.
- **User sees:** One red box floating over the top-right of the navbar showing only the LAST error message. The other messages are stacked invisibly underneath it. Nothing is highlighted next to the offending field, the page does not scroll to it, and because the box resolves to position:absolute (not fixed) it is anchored to the top of the document — on a long form the user can scroll it out of view entirely. No auto-dismiss.
- **Way back:** A small white '×' that sets display:none on that one box, revealing the next hidden message underneath. There is no link, no 'go back', and no anchor to the failing field.
- **Evidence:** resources/views/layouts/alert.blade.php:1-11, resources/views/components/layouts/app.blade.php:50 (@include('layouts.alert')), resources/views/components/layouts/app.blade.php:32 (links) and :37 (@vite app.css), resources/css/app.css:10145-10150 (.alert), resources/css/app.css:2757-2759 (.fixed), public/build/assets/app-tpo1jr80.css offset 427765
- **Verify note:** VERIFIED. Every claim held including the byte offsets and the cascade order; additionally confirmed app.css is loaded after the CDN Tailwind stylesheet so the .alert override genuinely wins in the browser. This is the fallback presentation for essentially all 231 inline validate() calls in app/.

#### 4.4.2 119 form views have no error output and no old() — validation reads as 'nothing happened'

- **Status:** n/a (302 redirect back) | **Frequency:** dead-common
- **Trigger:** Filling in almost any form in the app (add client, add task, COA account, supplier, agent, branch, receipt voucher, bank payment, currency exchange) and getting one field wrong.
- **Mechanism:** Inline $request->validate() in the controller throws ValidationException -> redirect()->back() (often ->withInput()). The blade never prints $errors or @error and never echoes old(), so the redirect lands on a freshly-rendered empty form. Measured on the server: 149 blades contain a <form>; only 30 of them contain any of $errors / @error / old(. So 119 are in this state, and 50 of those 119 also render outside <x-app-layout>, meaning they do not even get the global toast.
- **User sees:** The page reloads, every field they typed is blank again, and the only signal anything went wrong is the single overlapping red box described in finding 1 — which on a long form may be above the current scroll position. Users read this as 'the Save button is broken'.
- **Way back:** None specific. The user has to retype the whole form from scratch and guess which field was rejected.
- **Evidence:** app/Http/Controllers/ClientController.php:285-297 (validate) and :326 (redirect back) vs resources/views/clients/create.blade.php (grep count of $errors\|@error\|old( == 0); app/Http/Controllers/CoaController.php:169 (validate) and :189 (redirect back with 'Code already exists') vs resources/views/coa/partials/child-account.blade.php:160 (the live add-category form, no error output); app/Http/Controllers/UserController.php:16-20; resources/views/tasks/tasksUpload.blade.php:5-20; resources/views/agents/agentsUpload.blade.php:5; resources/views/companies/companiesUpload.blade.php:5; resources/views/branches/upload.blade.php:5
- **Verify note:** CORRECTED. Count was 102, actual is 119 of 149 (recounted on the server). The 'roughly 40 outside x-app-layout' sub-count is actually 50. Also corrected the COA evidence: resources/views/components/coa-modal.blade.php:6-8 is a dead <form> with no action attribute and no JS handler anywhere (coa/index.blade.php contains no fetch() at all); the reachable COA account-creation form is resources/views/coa/partials/child-account.blade.php:160. All other cited files/lines confirmed verbatim.

#### 4.4.3 'Add New Client' form inside the invoice builder destroys the whole invoice draft

- **Status:** n/a (302 redirect back) | **Frequency:** dead-common
- **Trigger:** An agent is halfway through building an invoice, opens the client picker, switches to the 'Add' tab, and the new client is rejected — most often because the client is a duplicate (same name+phone, or same civil no), or because the email is malformed, the phone exceeds 15 chars, or no agent was picked.
- **Mechanism:** resources/views/invoice/create.blade.php:451 is a plain <form method="POST" action="{{ route('clients.store') }}"> living inside the modal markup (it is NOT nested inside another form — no <form> opens before line 451). Any rejection is a normal browser POST -> 302 -> full page navigation. ClientController::store validates at :285-297; on duplicate it calls showAssignmentRequestForm which at :466-474 does redirect()->back()->withInput()->with(['duplicate_warning'=>true,...]) — also a full navigation. Either way the page re-renders and the invoice draft, which lives only in the JS array declared at resources/views/invoice/create.blade.php:667 (`let items = [];`), is reinitialised. invoice/create.blade.php contains zero old() calls.
- **User sees:** The modal vanishes, the entire invoice they were assembling is gone. On the duplicate path they get the global duplicate-client modal (which does explain itself). On the plain-validation path they get one red box in the top-right corner reading e.g. 'The agent id field is required.' with no indication it came from the client sub-form.
- **Way back:** None. The invoice draft is not recoverable — they start over.
- **Evidence:** resources/views/invoice/create.blade.php:451 (form), :667 (let items = []), app/Http/Controllers/ClientController.php:285-297, :326, :460-475 (showAssignmentRequestForm), resources/views/components/duplicate-client-warning.blade.php:1 (session('duplicate_warning')), resources/views/components/layouts/app.blade.php (global <x-duplicate-client-warning />)
- **Verify note:** CORRECTED. (a) 'nested inside' is wrong — it is a standalone form inside a modal div; effect is the same. (b) The stated trigger 'misses the dial code' is not real: the dial_code select defaults to +965 via `{{ $country->dialing_code == '+965' ? 'selected' : '' }}`, and first_name/phone/civil_no carry HTML `required`. Replaced with the triggers that actually fire. (c) The duplicate path does NOT render a different view — it redirects back with a session flag consumed by a global component; the draft is destroyed all the same. (d) 'modal markup + <form>' blade count is 37, not 49.

#### 4.4.4 45 of 70 AJAX POSTs get a 302 HTML redirect instead of 422 JSON, then crash on .json()

- **Status:** 302 -> 200 HTML (should have been 422 JSON) | **Frequency:** dead-common
- **Trigger:** Any inline-saving control that posts via fetch without an Accept header — saving a settings tab preference, adding a client to a group, saving an invoice line, updating a supplier — when the server-side rules reject the payload.
- **Mechanism:** Laravel decides JSON-vs-redirect via Request::expectsJson(), which needs either X-Requested-With: XMLHttpRequest or an Accept header preferring JSON. A bare fetch() sends Accept: */* and no X-Requested-With, so ValidationException returns a 302 to the referrer. fetch follows it, gets the HTML page back with status 200, and response.json() throws SyntaxError. The .catch() that fires (where there is one) has no relationship to the actual validation problem. Measured on the server: 70 fetch call sites with method:'POST' in resources/views; 45 of them have no Accept header anywhere in their header block. Note that sending Content-Type: application/json does NOT help — expectsJson() never looks at Content-Type.
- **User sees:** Depends on the call site: usually absolutely nothing (a console.error the user never opens), sometimes a native alert() about the wrong thing ('An error occurred while processing the file.'). The control appears to have worked — a tab switches, a row appears — but the change was never persisted.
- **Way back:** None. There is no signal at all that a save failed, so the user does not know to retry.
- **Evidence:** resources/views/settings/index.blade.php:264-274 (fetch POST, Content-Type json but NO Accept, and no .then and no .catch at all); resources/views/clients/list.blade.php:585-596 (addGroup POST, no Accept, console.error only); resources/views/users/create.blade.php:935-941 (passport upload POST, only X-CSRF-TOKEN); contrast resources/views/tasks/index.blade.php:2952-2960 which does send 'Accept': 'application/json' correctly; app-wide only 3 expectsJson()/wantsJson() checks exist in app/ and routes/
- **Verify note:** CORRECTED — evidence only. The 70/45 counts and settings/index.blade.php:264-274 are exact. But two cited lines were wrong and are replaced: clients/list.blade.php:479-481 is the catch of a GET fetch that DOES send Accept (belongs to the AJAX-GET finding, not here), and coa/index.blade.php:152-154 is a try/catch around JSON.parse of data-* attributes — coa/index.blade.php contains no fetch() at all. Substituted clients/list.blade.php:585-596 and users/create.blade.php:935-941, both verified.

#### 4.4.5 Native browser alert() is the validation/error UI for AJAX paths (156 call sites)

- **Status:** 400 / 422 / 500, or 200 with success:false | **Frequency:** dead-common
- **Trigger:** Bulk-updating tasks, toggling task status, merging uploaded PDFs, uploading a passport image for OCR, switching company from the sidebar — anything that round-trips through fetch and gets a JSON error back.
- **Mechanism:** fetch(...).then(r => r.json()).then(d => { if (d.success) {...} else alert('Error processing file: ' + d.message) }).catch(e => alert('An error occurred while processing the file.')). The d.message is whatever string the controller put in the JSON — in 112 places that is a raw concatenated $e->getMessage(). Measured: 156 alert() call sites across 47 blade files.
- **User sees:** An OS-chrome modal dialog with the site's hostname in the title bar, unstyled system text such as 'Error processing file: Failed to extract passport data using AI: ...', and a single OK button. It blocks the whole page until dismissed, highlights no field, and disappears without trace.
- **Way back:** An OK button that dismisses the dialog and returns to the unchanged form. No retry affordance, no field focus.
- **Evidence:** resources/views/users/create.blade.php:968 ('Error processing file: ' + data.message) and :974 ('An error occurred while processing the file.'); resources/views/tasks/index.blade.php:451, :455, :2967, :2979, :3153, :3159; resources/views/components/sidebar-company.blade.php:110 (alert(data.message \|\| 'Failed to switch company')) and :115
- **Verify note:** CORRECTED. All eight cited alert() lines confirmed verbatim; count is 156 sites across 47 blades (finding said '30+'). The example message was wrong: the passport upload at users/create.blade.php posts to TaskController::clientPassport (app/Http/Controllers/TaskController.php:4879), which has NO validation rules at all — so its alert never shows a validation message, it shows the AI-extraction failure string from the controller's own 400 JSON. Added sidebar-company.blade.php:110/:115, which the original census had mis-filed under the silent-AJAX-GET finding.

#### 4.4.6 Login shows the same failure sentence up to four times

- **Status:** n/a (302 redirect back) | **Frequency:** dead-common
- **Trigger:** Mistyping a password. The single most-executed error path in the product.
- **Mechanism:** app/Http/Requests/Auth/LoginRequest.php:63-66 attaches the identical trans('auth.failed') string to BOTH the 'email' and 'password' keys. resources/views/auth/login.blade.php:52-56 prints $errors->first('email') and :94-98 prints $errors->first('password'), each prefixed with a hardcoded literal '<strong>Error:</strong>'. On top of that, resources/views/layouts/guest.blade.php:26-36 renders every message in the bag as a toast.
- **User sees:** 'Error: These credentials do not match our records.' under the email box, the same sentence again under the password box, plus two red toasts overlapping in the top-right corner. Four copies of one message.
- **Way back:** The form is still on screen with the email retained, so the user can retry — this is one of the few surfaces with a real recovery path.
- **Evidence:** app/Http/Requests/Auth/LoginRequest.php:63-66 (double-attach), :88-91 (throttle, attached to 'email' ONLY), resources/views/auth/login.blade.php:52-56, :94-98, resources/views/layouts/guest.blade.php:26-36; guest pages load only resources/css/guest.css, which contains no .alert rule, so those toasts really are position:fixed
- **Verify note:** CORRECTED. The 4x multiplication on bad credentials is real and confirmed. But the claim that the throttle message 'multiplies identically' is WRONG: LoginRequest:88-91 attaches trans('auth.throttle') to the 'email' key only, so after 5 attempts the user sees 2 copies (one inline, one toast), not 4. Password error block starts at :94, not :95. Confirmed guest.css defines no .alert rule, so the census's parenthetical about guest toasts being genuinely fixed holds.

#### 4.4.7 Task detail page renders every error and success message twice

- **Status:** n/a (inline) | **Frequency:** dead-common
- **Trigger:** Any action on the task detail screen — the app's most-used page — that flashes an error or fails validation.
- **Mechanism:** resources/views/tasks/detail.blade.php:1 opens <x-app-layout>, whose component template already does @include('layouts.alert') at resources/views/components/layouts/app.blade.php:50. Line 2 of tasks/detail.blade.php includes it a second time.
- **User sees:** Two identical red boxes (or two identical green success boxes) — one absolutely positioned by the layout copy, one by the in-page copy, at different points in the document flow. Dismissing one leaves the other.
- **Way back:** Two separate '×' buttons for the same message.
- **Evidence:** resources/views/tasks/detail.blade.php:1-2, resources/views/components/layouts/app.blade.php:50
- **Verify note:** VERIFIED. tasks/detail.blade.php line 1 is <x-app-layout> and line 2 is @include('layouts.alert'); app.blade.php:50 is the layout's own include. Exact.

#### 4.4.8 Breeze scaffolding island — the only forms with real field-level errors

- **Status:** n/a (302 redirect back) | **Frequency:** dead-common
- **Trigger:** Changing your profile email to one already in use, entering a wrong current password, requesting a password reset, deleting your account with a bad password.
- **Mechanism:** 14 views use <x-input-error :messages="$errors->get('field')" /> which renders resources/views/components/input-error.blade.php — a small <ul class="text-sm text-red-600 dark:text-red-400 space-y-1"> directly beneath the input. resources/views/profile/partials/delete-user-form.blade.php:17 additionally re-opens the modal on failure via :show="$errors->userDeletion->isNotEmpty()", the only place in the app that preserves modal state across a validation round trip.
- **User sees:** A small red line directly under the offending field, e.g. 'The email has already been taken.' — correct, scannable, dark-mode aware. But the global layout toast ALSO fires for the same message, so on profile pages the user gets the inline line plus the overlapping corner box.
- **Way back:** The form stays on screen with values preserved. This is the only pattern in the app that works.
- **Evidence:** resources/views/components/input-error.blade.php:1-9; resources/views/profile/partials/delete-user-form.blade.php:17 (:show="$errors->userDeletion->isNotEmpty()") and :41 (x-input-error); resources/views/bulk-invoice/upload.blade.php:86 (@error('agent_id')), :112 (@error('file')), :117-128 (summary <ul> of $errors->all() under 'Please fix the following:'); 14 blades total contain x-input-error
- **Verify note:** VERIFIED. input-error.blade.php is exactly 9 lines as described; delete-user-form.blade.php:17 and :41 exact; bulk-invoice/upload.blade.php:86, :112 and the :117-128 summary block exact; the count of blades using x-input-error is 14. bulk-invoice/upload.blade.php genuinely is the best hand-written form in the repo (per-field @error plus a summary list, dark-mode aware).

#### 4.4.9 invoice/edit toast helper piles up permanently-stacked boxes and injects server text as HTML

- **Status:** 422 / 500 | **Frequency:** occasional
- **Trigger:** Adding or removing tasks on an existing invoice and hitting a rejection more than once in a session.
- **Mechanism:** displayErrorMessage(message) creates a wrapper div, sets its innerHTML to a template literal containing <div class="alert alert-danger fixed mt-5 top-1 right-4 ...">${message}</div>, and document.body.appendChild()s it. Nothing removes it — the '×' only sets display:none on the inner div, leaving the wrapper in the DOM forever. Because .alert resolves to position:absolute at a single fixed coordinate (finding 1), every subsequent error lands in the identical spot. `message` is interpolated straight into innerHTML and is the server-supplied JSON 'message'.
- **User sees:** First error: a red box top-right. Second error: an identical box exactly on top of the first, so the user cannot tell whether it updated. After dismissing, an older stale message from minutes ago is revealed underneath.
- **Way back:** '×' per box, which hides one and uncovers the previous one.
- **Evidence:** resources/views/invoice/edit.blade.php:5765-5776 (displayErrorMessage), resources/views/invoice/edit.blade.php:5211-5221 (call site: throw new Error(result.message) -> displayErrorMessage(error.message)), also defined at resources/views/invoice/create.blade.php:1600 and resources/views/livewire/chat.blade.php:1411
- **Verify note:** VERIFIED (one number corrected). Line ranges, the three definition sites, the innerHTML interpolation and the display:none-only dismissal all confirmed. The raw-$e->getMessage() JSON count is 112, not 86.

#### 4.4.10 Company registration wizard bounces to step 1 and prints raw array attribute paths

- **Status:** n/a (302 redirect back) | **Frequency:** occasional
- **Trigger:** A new agency owner completing the multi-step self-serve registration and getting a duplicate email on agent row 4, or an already-taken company code.
- **Mechanism:** CompanyRegistrationRequest validates the whole 7-step payload at once (app/Http/Requests/CompanyRegistrationRequest.php:79-117). Failure redirects back; the Alpine wizard state x-data="{ step: 1, ... }" reinitialises to step 1. Errors are printed twice: once inline at the top of the page and once as toasts by the guest layout.
- **User sees:** Back at 'Step 1: Company details' with a red block listing messages like 'The agents.3.email has already been taken.' and 'The company code has already been taken.' — dotted array paths with no row highlighted in the agents repeater — and the same list duplicated as overlapping toasts in the corner. Fields are repopulated via old() (this view does use old()).
- **Way back:** The user can click forward through the steps again; data is preserved by old(), so this is recoverable but confusing.
- **Evidence:** resources/views/register/company.blade.php:11 (x-data step:1), :14-18 (inline $errors->all() list), resources/views/layouts/guest.blade.php:26-36 (same messages again as toasts), app/Http/Requests/CompanyRegistrationRequest.php:96-100 (agents.* rules, agents.*.email at :98), :109 (agents_file max:1024), :113 (recaptchav3:company_register,0.7), :48 (ValidationException::withMessages(['agents_file' => 'Could not read the file']))
- **Verify note:** CORRECTED. All line citations confirmed verbatim. But the reCAPTCHA note is wrong: 'The system detected suspicious activity. Please try again.' is defined only in app/Http/Requests/Auth/LoginRequest.php:46 and app/Http/Controllers/Auth/RegisteredUserController.php:40. CompanyRegistrationRequest has no messages() method, and vendor/josiasmontag/laravel-recaptchav3 registers the rule via $validator->extend() with no message and ships no lang file — so a low-score registration renders the unresolved translation key itself, i.e. the user is shown the literal string 'validation.recaptchav3'. The census's separate claim that iata_client_secret is 'deliberately not re-flashed' was not checked and is dropped from this entry.

#### 4.4.11 Raw PHP/SQL exception text shown to end users in the error toast

- **Status:** n/a (302 redirect back) / 400-500 in the JSON variants | **Frequency:** occasional
- **Trigger:** Any save that fails deeper than validation — a task update violating a foreign key, a receipt voucher hitting a constraint, a payment status check timing out.
- **Mechanism:** 38 controller catch blocks do redirect()->with('error', '<prefix>: ' . $e->getMessage()), which flows into resources/views/layouts/alert.blade.php:42-68. A further 112 responses put $e->getMessage() into a 'message' key that the front end pipes into alert() or displayErrorMessage().
- **User sees:** A red toast containing text like 'Task update failed: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`citycomm_city-tour-test`.`tasks`, CONSTRAINT ...)'. Database name, table names and column names are exposed. On MyFatoorahController.php:138 and RoleController.php:175 there is no prefix at all, so the toast is just the bare exception string.
- **Way back:** The '×' on the toast. The user is redirected back or (for MyFatoorah) to the dashboard; whatever they were doing is lost.
- **Evidence:** app/Http/Controllers/ReceiptVoucherController.php:499 and :635 ('Error: ' . $e->getMessage()); app/Http/Controllers/TaskController.php:2895 ('Task update failed: '), :3052, :5746, :6170; app/Http/Controllers/PaymentController.php:7819, :7831, :6943, :2556; app/Http/Controllers/MyFatoorahController.php:138 (bare $ex->getMessage(), no prefix, redirects to dashboard); app/Http/Controllers/RoleController.php:175 (bare $e->getMessage(), no prefix); JSON variants at app/Http/Controllers/TaskController.php:810, :1600, :4324, :5075, :5423, :5465
- **Verify note:** CORRECTED. Every one of the 18 cited file:line pairs was read and matches verbatim. Two corrections: the JSON $e->getMessage() count is 112, not 86; and RoleController.php:175 is also prefix-less, same as MyFatoorahController.php:138. The 38 redirect-with-getMessage count and the 437 total with('error') count are exact.

#### 4.4.12 App-level file size limits are tighter than the server limits and surface three different ways

- **Status:** 422 for XHR; n/a (302) for form posts | **Frequency:** occasional
- **Trigger:** Uploading a bulk-invoice spreadsheet over 10MB, a company logo over 2MB during registration, or an agents spreadsheet over 1MB during registration.
- **Mechanism:** Validation rules cap uploads well below the PHP limits: max:10240 (10MB) on bulk invoice upload, max:2048 on the company logo, max:1024 (1MB) on the agents spreadsheet, max:10240 on the WhatsApp-flow passport. Presentation splits three ways depending on the call site: a proper inline @error block plus a summary list (bulk invoice), the overlapping guest-layout toast (company registration), or a 422 JSON string consumed by an API caller (WhatsApp flow).
- **User sees:** Best case (bulk invoice) an inline red line under the file input reading 'The file must not exceed 10MB.' plus a summary list. Worst case (company registration) the single overlapping corner toast with Laravel's default kilobyte-denominated wording. None of the file inputs advertise their size limit in the label or hint text beforehand.
- **Way back:** On the bulk-invoice form the user stays on the page with the field flagged. Elsewhere the file selection is cleared and they must re-pick.
- **Evidence:** app/Http/Requests/BulkInvoiceUploadRequest.php:25 (max:10240) and :36-40 (custom messages incl. 'The file must not exceed 10MB.'); app/Http/Requests/CompanyRegistrationRequest.php:88 (logo max:2048), :109 (agents_file max:1024); app/Http/Controllers/WhatsAppHotelController.php:2130 (passport max:10240) with the 422 JSON at :2133-2137; contrast app/Http/Controllers/TaskController.php:4879 (clientPassport — the browser passport upload — which has NO size or mime validation at all)
- **Verify note:** CORRECTED. Two of the four cited limits were mis-attributed. (a) app/Http/Controllers/TaskController.php:5255 (max:2048) is inside handleTaskFromEmail, which is an API webhook route (routes/api.php:39 POST /task-from-email), not a browser upload — removed as a user-facing surface. (b) The 'scanned passport photo over 2MB' trigger does not exist: the browser passport upload is TaskController::clientPassport at :4879 and it validates nothing at all, so the only ceiling is PHP's. WhatsAppHotelController.php:2130 confirmed exactly but is a WhatsApp/API JSON surface, not a form. BulkInvoiceUploadRequest and CompanyRegistrationRequest citations confirmed verbatim.

#### 4.4.13 max_file_uploads=20 silently truncates multi-PDF batches with no error at all

- **Status:** n/a (silent truncation at the PHP layer) | **Frequency:** occasional
- **Trigger:** An agent drag-drops 25 or 30 PDFs into the batch uploader on the tasks screen.
- **Mechanism:** The JS accumulates files without any cap — batches[batchIndex].push(...newFiles) on drop and push(...picked) on change — and the on-screen counter reflects the full count. The form then submits as a normal multipart POST. PHP's max_file_uploads=20 discards everything past the 20th during request parsing, raises no error and populates no $_FILES entry, so no validation rule can fire on the missing files.
- **User sees:** The UI shows '30' files queued. The upload reports success. Ten documents are simply not there. There is no error, no warning, and no discrepancy shown between what was queued and what was received.
- **Way back:** None — the user has no idea anything was lost until they notice missing tasks later.
- **Evidence:** resources/views/tasks/index.blade.php:2368 and :2652 (<input type="file" class="file hidden" accept="application/pdf" multiple />), :2427-2437 (unbounded push in both the drop and change handlers), :2806-2814 (only minimum-count guards: alert('Please add at least one batch (min 1 PDF).') and alert('Each batch must have at least 1 PDF...')); /opt/cpanel/ea-php82/root/etc/php.ini max_file_uploads=20 confirmed via php -i
- **Verify note:** VERIFIED. All four line ranges confirmed (the push handlers land at 2427-2437 and the guards at 2806-2814, within the cited windows). max_file_uploads=20 confirmed live. The only client-side guards really are minimums — there is no maximum anywhere in the handler.

#### 4.4.14 AJAX GETs that fail render an empty list with no message

- **Status:** varies (500 / 403 / 302-to-HTML) | **Frequency:** occasional
- **Trigger:** Opening a client's sub-client or parent-client panel, loading terms templates in Settings — any lookup that 500s, 403s or returns non-JSON.
- **Mechanism:** try { const r = await fetch(url, {headers:{Accept:'application/json'}}); if (!r.ok) throw new Error(...); const data = await r.json(); render(data); } catch (error) { console.error('Error fetching sub-clients:', error); } — the catch writes to the console only.
- **User sees:** An empty panel, an empty dropdown, or a spinner that just stops. In clients/list the list stays completely blank (the 'No sub-clients found.' copy at :490 only renders on a successful empty response, so a failure and a genuine zero-result look different but both look uninformative).
- **Way back:** None. Reloading the page is the only recourse and the user is not told to.
- **Evidence:** resources/views/clients/list.blade.php:461-481 (fetchSubClients; catch at :479-481, console.error only) and :503-522 (fetchParClients; catch at :520-522); resources/views/settings/index.blade.php:335-351 (loadTemplates; catch at :349-351, console.error only)
- **Verify note:** CORRECTED — evidence. clients/list.blade.php:479-481 and settings/index.blade.php:349-351 confirmed exactly. Two cited sites were wrong and are dropped: coa/index.blade.php:152-154 is a try/catch around JSON.parse of data-* attributes (coa/index.blade.php has no fetch() at all), and components/sidebar-company.blade.php:113 is a catch that DOES surface an alert('Failed to switch company') at :115 — it belongs to the alert() finding. Parent-client catch is at :520-522, not :519-521.

#### 4.4.15 CoaController::addCategory uses the same non-existent-rule pattern, and this one IS reachable from the UI

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** An accountant adds a sub-account in the Chart of Accounts and picks Client, Agent or Branch in the 'Entity' dropdown before pressing 'Create New'.
- **Mechanism:** app/Http/Controllers/CoaController.php:176 declares 'entity' => 'nullable\| enum: client, agent, branch'. Laravel splits on '\|', explodes on ':' to rule ' enum', then Str::studly(trim(' enum')) = 'Enum'. There is no validateEnum method anywhere in Illuminate\Validation (Enum is an invokable rule object, not a string rule), so Validator::__call throws BadMethodCallException and the request 500s. Because of 'nullable' this is skipped when the entity select is left on its blank default, but the select at resources/views/coa/partials/child-account.blade.php:193-196 offers real values client/agent/branch, so any entity-linked account creation blows up before the controller body ever runs.
- **User sees:** Stock unbranded Laravel 500 'Server Error' page — no site chrome, no navigation, no logo. Everything typed into the add-account form is gone.
- **Way back:** None. Browser back button, and the form is empty again.
- **Evidence:** app/Http/Controllers/CoaController.php:169-184 (validate block; :176 is the enum rule), resources/views/coa/partials/child-account.blade.php:160 (<form action="{{ route('coa.addCategory') }}" method="POST">), :191-197 (select name="entity" with <option value="client">/<option value="agent">/<option value="branch">), routes/web.php:343 (Route::post('/addCategory', [CoaController::class, 'addCategory'])), vendor/laravel/framework/src/Illuminate/Validation/Validator.php:1660-1662 (BadMethodCallException), grep for 'function validateEnum' across vendor/laravel/framework/src/Illuminate/Validation/ returns nothing
- **Verify note:** ADDED IN VERIFY. Found while confirming finding 16's date_time claim — same class of bug but, unlike date_time, this one has a live form wired to it. Note this also means CoaController's related rules ('client' => 'required_if:entity,client\|integer' etc.) can never be exercised.

#### 4.4.16 Public payment link page shows nothing when the pay request is rejected

- **Status:** n/a (inline) for the validation path; 404 for the abort path | **Frequency:** rare
- **Trigger:** A customer (not logged in) opens a shared payment link and clicks Pay, and the hidden payment_id no longer resolves — stale link, deleted payment, tampered form.
- **Mechanism:** PaymentController::paymentLinkInitiate (app/Http/Controllers/PaymentController.php:3297-3299) runs $request->validate(['payment_id' => 'required\|exists:payments,id']). The blade posts as a normal browser form so Laravel redirects back with an $errors bag, but resources/views/payment/link/show.blade.php renders only session('status') at :47-49 and session('error') at :51-53 — it has no $errors block anywhere. Separately, if the id validates but the record has since gone, line 3311 does `return abort(404)` for anonymous visitors.
- **User sees:** Validation path: clicks Pay, the page reloads looking identical, nothing changes. Reads as a dead button. abort(404) path: the stock unbranded Laravel 404 page — white background, '404 \| NOT FOUND', no logo, no navigation, no way back to the invoice or the agency.
- **Way back:** None on the 404 (there is no site chrome at all on that page). On the silent validation path, none either — the customer just re-clicks.
- **Evidence:** app/Http/Controllers/PaymentController.php:3297-3299 (validate), :3305-3311 (abort(404) for guests, redirect back with 'Payment not found.' for logged-in users), resources/views/payment/link/show.blade.php:51-52 (session('error') only, no $errors), :251 and :297 (the two Pay forms), same session-only pattern at resources/views/payment/link/multi-payment.blade.php:51-52 and resources/views/payment/link/show-arabic.blade.php:46-47 (the Arabic view's Pay forms are at :201 and :248)
- **Verify note:** VERIFIED (line numbers tightened). validate is at 3297-3299 not 3296-3300; abort(404) at 3311 exact; show.blade.php has zero $errors references, confirmed by grep. One nit: multi-payment.blade.php contains no route('payment.link.initiate') form, so it is cited here only for the identical session-only error panel. The census's point that the gateway-error path DOES work (with('error') rendered in-page at :51-52) was confirmed.

#### 4.4.17 413 Payload Too Large renders the stock Laravel page with no way back

- **Status:** 413 | **Frequency:** rare
- **Trigger:** Uploading a very large file — realistically only a multi-hundred-megabyte PDF batch or a mis-selected video.
- **Mechanism:** Laravel's default ValidatePostSize global middleware throws Illuminate\Http\Exceptions\PostTooLargeException when CONTENT_LENGTH exceeds post_max_size. bootstrap/app.php:45-47 registers no handler (the ->withExceptions() closure is a bare // comment) and resources/views/errors/ does not exist, so Symfony's stock error page is used.
- **User sees:** Unbranded white page reading '413 \| PAYLOAD TOO LARGE'. No navigation, no logo, no link back to the form. In the AJAX upload paths it is worse — the HTML body is fed to response.json(), which throws SyntaxError, and the generic catch fires a native alert('An error occurred while processing the file.').
- **Way back:** Browser back button only.
- **Evidence:** bootstrap/app.php:45-47 (empty ->withExceptions()), /opt/cpanel/ea-php82/root/etc/php.ini via php -i: post_max_size=256M, upload_max_filesize=320M, memory_limit=728M, max_file_uploads=20; no resources/views/errors/ directory; no .user.ini in the docroot or app root
- **Verify note:** VERIFIED. bootstrap/app.php's empty withExceptions is at 45-47 (census said 44-46). php.ini values confirmed by running php -i on the server; the misconfiguration is real — upload_max_filesize (320M) is larger than post_max_size (256M), so any single file between 256M and 320M produces a hard 413 rather than a per-file validation message.

#### 4.4.18 SupplierCredentialRequest uses a rule that does not exist — 500 instead of a validation message

- **Status:** 500 | **Frequency:** theoretical
- **Trigger:** Posting supplier credentials with a non-empty expires_at value (API caller, imported payload, or a future UI that adds the field).
- **Mechanism:** app/Http/Requests/SupplierCredentialRequest.php:34 declares 'expires_at' => 'nullable\|date_time'. ValidationRuleParser::parseStringRule returns Str::studly(trim($rule)) = 'DateTime', so Validator calls $this->validateDateTime(...). No such method exists (vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php has validateDate and validateDateFormat but no validateDateTime), so Validator::__call throws BadMethodCallException — not a ValidationException — and it escapes as a 500. With no resources/views/errors/500.blade.php it renders the stock page. The 'nullable' rule means this only fires when expires_at is actually present and non-null.
- **User sees:** Stock unbranded Laravel 500 page ('Server Error'), no navigation, no way back. With APP_DEBUG on it would instead be the Ignition stack trace.
- **Way back:** None.
- **Evidence:** app/Http/Requests/SupplierCredentialRequest.php:34; app/Http/Controllers/SupplierCredentialController.php:13 (store(SupplierCredentialRequest $request)); routes/web.php:748 (Route::post('credentials', ...)->name('credentials.store')); vendor/laravel/framework/src/Illuminate/Validation/ValidationRuleParser.php:274 (Str::studly(trim($rule))); vendor/laravel/framework/src/Illuminate/Validation/Validator.php:678-683 ($method = "validate{$rule}"; called with no method_exists guard) and :1660-1662 (throw new BadMethodCallException)
- **Verify note:** CORRECTED — mechanism confirmed and traced end-to-end through vendor, but the census's note about reachability was wrong. resources/views/suppliers/credential-modal.blade.php posts to route('suppliers.tbo.credentials'), NOT credentials.store, so it can never hit this FormRequest at all. And it is not 'injected on EVERY authenticated page' — app.blade.php:76-78 includes it only when session('tbo.url') === null AND request()->routeIs('suppliers.tbo.index'). The route at web.php:748 has no UI caller anywhere, so this stays genuinely theoretical.

#### 4.4.19 The app's only well-formed JSON validation response is dead code, and one FormRequest hard-denies everything

- **Status:** 422 (unreachable) / 403 (unreachable) | **Frequency:** theoretical
- **Trigger:** n/a — neither class is reachable from any route today.
- **Mechanism:** app/Http/Requests/DocumentProcessingRequest.php:104-113 overrides failedValidation() to throw an HttpResponseException carrying {status:'error', message:'Validation failed', errors:{...}} at 422 — the correct, machine-readable shape. Nothing references the class. Separately app/Http/Requests/OpenAiRequest.php:14 has authorize() returning false, so any route type-hinting it would AuthorizationException into a bare 403.
- **User sees:** Nothing today. If OpenAiRequest were ever wired up, the stock unbranded 403 Forbidden page.
- **Way back:** n/a
- **Evidence:** app/Http/Requests/DocumentProcessingRequest.php:104-113 (the only reference to the class name anywhere in app/ or routes/ is its own class declaration at :9); app/Http/Requests/OpenAiRequest.php:14 (return false); app/Http/Controllers/OpenAiController.php:9 imports OpenAiRequest but :55 declares store(Request $request)
- **Verify note:** VERIFIED. failedValidation is at exactly 104-113; a repo-wide grep for DocumentProcessingRequest returns only its own class declaration; OpenAiRequest::authorize() returns false at :14; OpenAiController imports it at :9 and never uses it, declaring store(Request $request) at :55. It really is the one place in the codebase that models a JSON error contract properly.

---

### 4.5 Livewire, AJAX & in-page errors  (22 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Global wallet dropdown: "Failed to load wallet" with a raw PHP exception string underneath | 200 (failure smuggled in the body as `error`); 422 if company_id validation fails | dead-common | Yes — one of the few surfaces with a gen |
| 2 | Dashboard yellow warning banner carrying a raw exception message | n/a (inline, 200 page) | dead-common | None — no dismiss, no link to the compan |
| 3 | Native browser alert() is the app's de-facto error UI — ~82 error-path call sites | varies; most sites never read response.status at all | dead-common | OK dismisses it and returns the user to  |
| 4 | The layout's AJAX error toast slot is dead code — nothing has ever written to it | n/a (inline) | dead-common | n/a |
| 5 | APP_DEBUG=true means every JSON error body and every surfaced error.message can be a full stack | 500 (JSON `{message, exception, file, line, trace}`) | dead-common | n/a |
| 6 | Dashboard stat cards render the literal word "Error" in five tiles at once | varies — 404 from the module gate, 500 from the endpoint, or n/a (network); the JS never reads the status | occasional | None. Each tile is wrapped in an <a> to  |
| 7 | Livewire failure modal — full-screen black overlay with a raw stack trace, reachable from every | any non-2xx (500 most often, 403 from a policy, 404 from the module gate) | occasional | Partially. Livewire attaches a click han |
| 8 | Livewire 419 — native OS confirm() dialog "This page has expired." fires with no user action | 419 | occasional | OK reloads the page (which then redirect |
| 9 | wire:navigate silent dead-click — nothing happens, forever | n/a (network) or unread 404/500 | occasional | On the silent-failure path there is noth |
| 10 | Refund processing failure tells the user to open the browser console | any non-2xx; body read as text and discarded to console | occasional | OK returns to the refund list with the r |
| 11 | Invoice gateway save shows server ERRORS inside the GREEN success box | 422 / 500 / 419 — none of which the code inspects | occasional | None. The box hides itself after 3 secon |
| 12 | Invoice payment toasts stack invisibly on top of each other and never auto-dismiss | varies (message comes from `errorData.message` at :5740 or `error.message` at :5757) | occasional | A small `×` per toast, whose onclick set |
| 13 | Report filters replace the account dropdown with an un-selectable "Error loading accounts" opti | unread — both catches ignore status entirely (neither checks response.ok before .json()) | occasional | None in-page. Only a manual browser refr |
| 14 | Client profile tabs collapse to flat red one-liners with no retry | unread | occasional | None. Switching tabs and back re-runs th |
| 15 | Invoice detail modal shows a bare centred red sentence | any non-2xx (thrown as a generic 'Network response was not ok'), or network failure | occasional | Backdrop click / closeInvoiceModal() onl |
| 16 | Livewire chat surfaces "Error: Something went wrong!" and "Fetch error: <raw JS message>" | varies; the `error.error` key is read but `response.status` never is | occasional | OK only. The chat panel stays open in wh |
| 17 | Currency exchange fires two alerts for one failure, then silently reloads or doesn't | non-2xx, unread beyond `response.ok` | occasional | OK dismisses; the form keeps whatever ra |
| 18 | Silent failures — actions that fail with no error surface whatsoever | 200 with success:false (a); n/a (b) | occasional | n/a — the user does not know anything we |
| 19 | Supplier procedure modal has two different error states for the same modal | 404 with `{success:false, message}` — NOT 200 | rare | Only the modal's own close control (clos |
| 20 | MyFatoorah card form alerts a raw error object on a page with no navigation at all | n/a (client-side SDK promise rejection) | rare | Absolutely none. The checkout page has n |
| 21 | MyFatoorah error page prints an untranslated translation key on a blank page | 200 (the failure is rendered as a normal successful response) | rare | None. The page contains no links, no but |
| 22 | Error-monitoring dashboard's own failure is an alert() | unread | rare | OK dismisses. The 'Please try again' ins |

#### 4.5.1 Global wallet dropdown: "Failed to load wallet" with a raw PHP exception string underneath

- **Status:** 200 (failure smuggled in the body as `error`); 422 if company_id validation fails | **Frequency:** dead-common
- **Trigger:** User clicks the wallet icon in the header or mobile drawer (present on every authenticated page). Fires automatically on open via an Alpine $watch, and again on the refresh button.
- **Mechanism:** POST to route('iata.company-wallet') → DashboardController::iataCompanyWallet, which returns HTTP 200 even on failure with an `error` key in the body; the JS turns that into a thrown Error (`if (data.error) throw new Error(data.error)`). The catch writes an HTML block into `.iata-info`. The server side is `catch (\Throwable $e) { $error = $e->getMessage(); }`, so ANY exception message reaches the browser verbatim. There IS a `!response.ok` branch, but it does `response.json().then(err => Promise.reject(err))` — so an HTML error body (500 with APP_DEBUG=true, or 419) makes .json() throw and the panel shows the raw JS SyntaxError instead.
- **User sees:** Inside the wallet dropdown: a red circular exclamation icon, bold red "Failed to load wallet", and below it in small grey text the raw `error.message`. For the most common case that string is 'Missing IATA credentials. Please update your company profile with the IATA Code, Client ID, and Client Secret.' — a configuration prompt dressed up as a system crash. For a real fault it could be an IATA HTTP body, a cURL timeout string, or a SQLSTATE. For an HTML error body it is `Unexpected token '<', "<!DOCTYPE "... is not valid JSON`.
- **Way back:** Yes — one of the few surfaces with a genuine retry. The `.finally()` block re-enables the `.profile-wallet-reload-btn` (removes `opacity-50 cursor-not-allowed`), so the user can hit refresh. Nothing links to the company profile page the message tells them to go fix.
- **Evidence:** resources/views/layouts/profile.blade.php:190-273 (fetch at 216-226, ok-branch at 227-232, error-throw at 235-237, catch at 250-265, headline literal at 260, raw detail at 261, finally at 266-272); triggers at profile.blade.php:77 ($watch) and :92 (reload button), mobile-drawer.blade.php:475 and :497; server app/Http/Controllers/DashboardController.php:443-464 (iataCompanyWallet) and :158-200 (getCompanyWallets, throw at :168, `$error = $e->getMessage()` at :183-184); route routes/web.php:1031
- **Verify note:** CORRECTED — surface and copy confirmed exactly; line references were slightly off and one omission mattered. Corrected: error-throw is at 235-237 (not 236-238), catch at 250-265, controller method is 443-464 (not 448-464), getCompanyWallets is 158-200 with the catch at 183-184 (not 183-185). Added the `!response.ok` branch at 227-232 that the original mechanism did not mention, and its HTML-body failure mode. Frequency confirmed against the DB: 2 of the 3 companies in citycomm_city-tour-test have null/empty iata_code, iata_client_id and iata_client_secret, so those companies hit this on every single wallet click.

#### 4.5.2 Dashboard yellow warning banner carrying a raw exception message

- **Status:** n/a (inline, 200 page) | **Frequency:** dead-common
- **Trigger:** Loading the dashboard as any company that does not have IATA EasyPay fully configured, or when the IATA API is down.
- **Mechanism:** Server-side render: `@if (!empty($iataErrorMessage)) <div class="p-3 m-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-md">⚠️ {{ $iataErrorMessage }}</div>`. `$iataErrorMessage` is set to `$e->getMessage()` from a bare `catch (\Throwable $e)` in DashboardController::getCompanyWallets, and the same shape is duplicated in the AppLayout view component so the value flows to layout-rendered pages too.
- **User sees:** A pale yellow bar with a native ⚠️ emoji and the raw exception text, sitting between the KPI row and the IATA Wallets panel. Typically 'Missing IATA credentials. Please update your company profile with the IATA Code, Client ID, and Client Secret.' Not dismissible, no heading, reappears on every dashboard load.
- **Way back:** None — no dismiss, no link to the company profile page it tells the user to update.
- **Evidence:** resources/views/dashboard.blade.php:181-185 (banner; literal at :183); app/Http/Controllers/DashboardController.php:163 ($error = null), :168 (throw of the 'Missing IATA credentials…' message), :183-184 (catch assigning $e->getMessage()), :191 and :198 (the value passed out in both the log payload and the return array); duplicate implementation app/View/Components/AppLayout.php:106-107 (catch) and :114 ('iataErrorMessage' => $error)
- **Verify note:** CORRECTED — banner and copy confirmed verbatim; the block starts at :181 not :180, and the DashboardController catch is :183-184 not :183-185. AppLayout.php:114 confirmed as the duplicated pass-through, with its own catch at :106-107. The census's core observation holds and is worth keeping: this is the same underlying condition as the wallet-dropdown finding, rendered a second time in a completely different visual language (yellow inline banner vs red modal state), with zero consistency between them.

#### 4.5.3 Native browser alert() is the app's de-facto error UI — ~82 error-path call sites

- **Status:** varies; most sites never read response.status at all | **Frequency:** dead-common
- **Trigger:** Almost any failed AJAX action outside the dashboard: switching company, processing a refund, saving a settings row, exporting accounting data, declining a bank reconciliation, uploading a passenger file, updating exchange rates, chat pricing, revoking an API key.
- **Mechanism:** Hand-rolled `.catch(() => alert('...'))` or `if (!data.success) alert(data.message \|\| '...')` at each call site. No shared helper, no toast, no inline state. Messages range from `alert(error)` (raw object, in the MyFatoorah form) to `alert('Something went wrong')`.
- **User sees:** An unstyled OS-level modal dialog: 'development.citycommerce.group says: Failed to switch company' with a single OK button. Blocks the entire browser tab. Zero product branding, no icon, no next step, not usefully copy-pasteable, and gone from any screenshot the user takes after dismissing it.
- **Way back:** OK dismisses it and returns the user to exactly the broken state they were in — usually with a spinner already stopped and a form still filled. Nothing retries. Nothing is logged where support can see it.
- **Evidence:** 158 `alert(` occurrences across 47 files in resources/views/; ~82 of those carry error-flavoured text. Every one of these was opened and confirmed: resources/views/components/sidebar-company.blade.php:110,115 (company switcher); refunds/index.blade.php:395,400; accounting/index.blade.php:169; bank-payments/edit.blade.php:501,506; receipt-voucher/edit.blade.php:498; clients/list.blade.php:646,741; clients/profile.blade.php:688; tasks/detail.blade.php:486,2604; tasks/index.blade.php:3159; users/create.blade.php:974; payment/show.blade.php:939; payment/link/create.blade.php:1084; settings/partial/agent_charges.blade.php:510; settings/partial/agent_loss.blade.php:492; reports/settlements.blade.php:231; admin/system-settings/partials/hotel.blade.php:666; resailai/admin-api-keys.blade.php:232; resailai/suppliers.blade.php:151
- **Verify note:** VERIFIED — every spot-checked file:line matched the claimed text exactly. Count refined: 158 total `alert(` occurrences confirmed (across 47 files); the error-path subset measures ~82 by message text rather than the claimed 81. The pointer to the dead in-page toast slot below is correct.

#### 4.5.4 The layout's AJAX error toast slot is dead code — nothing has ever written to it

- **Status:** n/a (inline) | **Frequency:** dead-common
- **Trigger:** n/a — this is the absence of a surface. Every AJAX error that should have used it falls back to alert() or ad-hoc inline HTML instead.
- **Mechanism:** `layouts/alert.blade.php` ships three pre-built hidden toast containers under the comment `<!-- for ajax alert -->`: `#custom-success-alert`, `#custom-error-alert`, `#custom-success-ajax-alert`. A grep across resources/, app/, public/ and resources/js finds writers for `#custom-success-ajax-alert` only. `#custom-error-alert` AND `#custom-success-alert` both have zero writers anywhere in the codebase.
- **User sees:** Nothing — an empty `<div id="custom-error-alert" class="alert flex items-center justify-between rounded bg-red-500 p-3.5 text-white hidden" role="alert">` with an empty `<p></p>` inside it is present in the DOM of every authenticated page and is never shown.
- **Way back:** n/a
- **Evidence:** resources/views/layouts/alert.blade.php:70-90 (definitions: success at :71-76, error at :78-83, ajax-success at :85-90); the only consumers anywhere are resources/views/settings/partial/invoice.blade.php:50,85, resources/views/settings/partial/notifications.blade.php:183,318,366 and resources/views/settings/partial/payment.blade.php:78 — all six targeting `custom-success-ajax-alert`
- **Verify note:** CORRECTED — claim strengthened, not weakened. The census said writers exist for "the two SUCCESS ones"; in fact `#custom-success-alert` (:71-76) also has zero writers — only `#custom-success-ajax-alert` is ever used, by 6 call sites all in settings partials. So two of the three prebuilt slots are entirely dead. The dismissal-inconsistency point is confirmed and is actually worse than stated: within this one 90-line file there are three different dismiss behaviours — `.remove()` (:38, :66, :75), `classList.add('hidden')` (:82, :89), and `style.display='none'` (:6, on the `$errors->any()` validation toast at :1-11 that renders server-side validation failures as fixed red bars). The product already decided what an in-page error toast should look like and never wired it up.

#### 4.5.5 APP_DEBUG=true means every JSON error body and every surfaced error.message can be a full stack trace

- **Status:** 500 (JSON `{message, exception, file, line, trace}`) | **Frequency:** dead-common
- **Trigger:** Any 500 on any XHR endpoint, on this server as currently configured.
- **Mechanism:** `.env` has `APP_ENV=local` (line 2) and `APP_DEBUG=true` (line 4), and `bootstrap/app.php` `->withExceptions()` is an empty closure, so Laravel's default JSON error renderer emits `{message, exception, file, line, trace:[...]}`. Dozens of controllers additionally hand-build `response()->json(['message' => '...' . $e->getMessage()], 500)`, which the UI then prints verbatim.
- **User sees:** Wherever the UI prints `data.message` or `error.message` — alert() dialogs, the wallet sub-line, the invoice red box, the supplier procedure modal — the text can be e.g. 'Failed to create transaction or journal entry: SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row…'. In the Livewire failure modal it is the complete Ignition page with source excerpts and the environment tab.
- **Way back:** n/a
- **Evidence:** .env:2 (APP_ENV=local), .env:4 (APP_DEBUG=true); bootstrap/app.php:45-47 (empty withExceptions); no resources/views/errors/ directory exists; leak sites confirmed by opening each: app/Http/Controllers/TaskController.php:810 ('Currency conversion failed: ' . $e->getMessage()), :1600 ('Task creation failed: ' . …), :2660, :4324, :5075, :5245, :5423, :5465, :5586, :6717; app/Http/Controllers/PaymentController.php:2110, :2116, :2947, :3184, :3207, :4665, :4739, :5614, :5948, :6065, :6383, :6618, :6667, :6701, :6939; app/Http/Controllers/FileController.php:26, :41 (uses an `error` key, not `message`); app/Http/Controllers/SystemSettingController.php:298 ('Error adding country: ' . …), :378, :417; app/Http/Controllers/SupplierCompanyController.php:50
- **Verify note:** VERIFIED — .env flags, the empty withExceptions closure, the absent errors/ view directory, and every spot-checked leak site all confirmed exactly. Both of the census's caveats stand and should be carried forward: (1) this is the DEV box; production is tour.citycommerce.group and its APP_DEBUG was not read in this census, so do not extrapolate; (2) even with debug off, the hand-built `'… ' . $e->getMessage()` strings in the controllers above still reach the browser, because those are application code, not the debug renderer.

#### 4.5.6 Dashboard stat cards render the literal word "Error" in five tiles at once

- **Status:** varies — 404 from the module gate, 500 from the endpoint, or n/a (network); the JS never reads the status | **Frequency:** occasional
- **Trigger:** User opens the dashboard (the landing page after login) and the one-shot stat fetch fails — a 500/timeout from the stats endpoint, a network blip, or a 404 from the accounting module gate when the resolved company changed since the page rendered.
- **Mechanism:** fetch(route('reports.ajax.dashboard-stats')) → .then(r => r.json()) → .catch(() => document.querySelectorAll('.stat-loading').forEach(el => el.textContent = 'Error')). No response.ok check, no status inspection. The console.error is commented out (line 704), so the failure is invisible even to a developer. CORRECTION to the original census: the fetch fires once from a DOMContentLoaded handler (dashboard.blade.php:674-688), i.e. on a page that has just authenticated — so "left the tab open past the 120-minute session" is NOT the dominant path (the dashboard page itself would have bounced to /login first). Real failure modes: (a) 500 from ReportController::getDashboardStats — 5 balance queries, each doing a recursive descendant-account walk; (b) 404 from EnsureModuleEnabled (abort(404), app/Http/Middleware/EnsureModuleEnabled.php:72-74) when the blade's $hasAccountingModule guard and the middleware's evaluation disagree, e.g. admin switched company in another tab; (c) network drop / offline; (d) a history-reload of a stale tab hitting an expired session (302 → login HTML → SyntaxError in .json()).
- **User sees:** Five money tiles — Payable Supplier, Total Receivable, Profit Agent Wise, Total Bank, Gateway Receivable — each showing the bare word "Error" where a KWD figure should be. The word is written INTO the skeleton span, which keeps its `animate-pulse bg-gray-200 dark:bg-gray-700 rounded h-6 w-20 inline-block` classes, so "Error" sits inside a still-pulsing grey chip, coloured by the parent tile's accent (red-600 / green-600 / koromiko-600). No icon, no explanation, no distinction between causes.
- **Way back:** None. Each tile is wrapped in an <a> to the underlying report (reports.payable-supplier, reports.total-receivable, reports.profit-agent, reports.total-bank, reports.gateway-receivable), so clicking "Error" navigates to a report page rather than retrying. There is no retry control, no reload button, no message.
- **Evidence:** resources/views/dashboard.blade.php:690-709 (catch at 703-708, assignment at 706); DOMContentLoaded trigger at dashboard.blade.php:674-688; loading spans at dashboard.blade.php:381, 390, 403, 426, 435 (exactly 5, all in this file); container id at dashboard.blade.php:360; endpoint routes/web.php:512 inside the ajax group carrying 'module:accounting' at routes/web.php:507-513; handler app/Http/Controllers/ReportController.php:3536-3558; recursion app/Http/Controllers/ReportController.php:3583-3593; module gate app/Http/Middleware/EnsureModuleEnabled.php:56-77
- **Verify note:** CORRECTED — surface is real and the code is exactly as described, but the trigger and frequency were overstated. The fetch is a one-shot DOMContentLoaded call on a freshly-authenticated page, so session-expiry is a marginal path, not the dominant one; downgraded dead-common → occasional and rewrote the mechanism. Evidence line ranges tightened (catch is 703-708, recursion is 3583-3593). Confirmed intact: the author's comment at dashboard.blade.php:405-415 explicitly names this ("Skipping the fetch (see script below) rather than showing 'Error'"), and the accounting-off case is guarded at dashboard.blade.php:675-677. Latent hazard found while verifying: accounts row id=1142 'Discount Accounts' (company_id=2) has parent_id=1142, which would make getDescendantAccountIds recurse forever — but it is only its own child, so no root-account walk can reach it today. Not currently reachable; flagged, not counted.

#### 4.5.7 Livewire failure modal — full-screen black overlay with a raw stack trace, reachable from every page

- **Status:** any non-2xx (500 most often, 403 from a policy, 404 from the module gate) | **Frequency:** occasional
- **Trigger:** Any Livewire action returns non-2xx. Reachable app-wide because the notification bell component renders in the global header and mobile drawer: clicking a notification filter tab (All/Read/Unread) or "mark as read". Also reachable from the chat component and from the /admin/dotw tabs' 30-second polls.
- **Mechanism:** Livewire 3.6.4 `showFailureModal(content)` → `showHtmlModal(html)`: creates `<div id="livewire-error">` with `position:fixed; width:100vw; height:100vh; padding:50px; background:rgba(0,0,0,.6); z-index:200000`, prepends it to body, sets `document.body.style.overflow='hidden'`, and document.writes the entire error response HTML into a nested iframe styled `background-color:#17161A; border-radius:5px`.
- **User sees:** The whole app dims behind a 60%-black scrim; a dark rounded panel fills the viewport minus a 50px ring. With APP_DEBUG=true (current .env) the panel contains the full Ignition stack trace — file paths, line numbers, code excerpts, sometimes environment values. With debug off it would be Laravel's stock unbranded "Server Error" page inside the same black panel (there is no resources/views/errors/ directory). Page scroll is locked while it is up.
- **Way back:** Partially. Livewire attaches a click handler on the modal div and an Escape-key handler (livewire.js:4019-4024) that call hideHtmlModal. There is no visible close button, no "×", no text saying Escape works — and because the iframe fills the panel and swallows clicks, the click-to-dismiss only actually fires on the 50px padding ring around the edge. If the user does dismiss it, the underlying component is left half-updated.
- **Evidence:** vendor/livewire/livewire/dist/livewire.js:3991-4026 (showHtmlModal), :4027-4030 (hideHtmlModal), :4348-4351 (showFailureModal), :4313-4327 (the `if (!response.ok)` branch); version pinned v3.6.4 in composer.lock:3721; mount points resources/views/layouts/profile.blade.php:51 and resources/views/layouts/mobile-drawer.blade.php:453 (profile is @included from resources/views/layouts/navigation.blade.php:33 and resources/views/layouts/sidebar.blade.php:315, hence app-wide); components app/Livewire/Notification.php, app/Livewire/NotificationIndex.php; no Livewire.hook or request-interceptor override exists anywhere in resources/
- **Verify note:** CORRECTED — modal behaviour and all vendor code confirmed verbatim, but line numbers and one of the two cited bugs were wrong. Vendor offsets corrected (showFailureModal is 4348-4351 not 4350; the !response.ok branch is 4313-4327 not 4320-4326). The claimed null-deref in app/Livewire/Notification.php:65-71 close() IS present and unguarded, but `close(` is wired in NO blade in the repo (grep for wire:click="close returns nothing) — it is dead code, not a reachable trigger; removed from the reachable set. The second bug is real and reachable: app/Livewire/NotificationIndex.php:24-30 `Notification::find($id)->update(...)` with no null guard and no user scope, wired at resources/views/livewire/notification-index.blade.php:49 on the full-page Livewire route /notifications (routes/web.php:793). A stale id → 'Attempt to assign property on null' → 500 → this modal. Confirmed that Notification.php's own markAsRead (:87-92) does correctly use baseScope(), unlike NotificationIndex's.

#### 4.5.8 Livewire 419 — native OS confirm() dialog "This page has expired." fires with no user action

- **Status:** 419 | **Frequency:** occasional
- **Trigger:** A user leaves any page idle past SESSION_LIFETIME (120 min) and then touches the notification bell (filter tab or mark-as-read) — or logs out in another tab and then interacts with a Livewire component. The next Livewire POST returns 419 and a browser dialog appears.
- **Mechanism:** livewire.js `handlePageExpiry()`: `confirm("This page has expired.\nWould you like to refresh the page?") && window.location.reload()`, called from the `if (response.status === 419)` branch. The branch then falls through to `return showFailureModal(content)` on the very next line, so the user can get the confirm AND the black stack-trace modal in sequence.
- **User sees:** An unstyled native browser modal — Chrome renders it as a grey bar dropping from the address bar reading "development.citycommerce.group says: This page has expired. Would you like to refresh the page?" with OK / Cancel. Zero product branding. Immediately after dismissing it, the black Livewire failure modal appears on top of the dead page.
- **Way back:** OK reloads the page (which then redirects to /login, losing any unsaved state). Cancel leaves the user on a dead page. Neither path explains that they were logged out.
- **Evidence:** vendor/livewire/livewire/dist/livewire.js:4324-4327 (419 branch falling through to showFailureModal), :4345-4346 (handlePageExpiry); .env SESSION_DRIVER=database (line 37), SESSION_LIFETIME=120 (line 38); config/session.php:117 lottery [2,100]; bell mount points resources/views/layouts/profile.blade.php:51, resources/views/layouts/mobile-drawer.blade.php:453; pollers resources/views/livewire/admin/dotw-dashboard-tab.blade.php:1 (`wire:poll.30000ms="refreshMetrics"`), dotw-booking-lifecycle-tab.blade.php:1, dotw-error-tracker-tab.blade.php:1 (all three reached from resources/views/livewire/admin/dotw-admin-index.blade.php:128 via route /admin/dotw, routes/web.php:1101-1105)
- **Verify note:** CORRECTED — the code path is exactly as described, but the stated trigger was wrong. Each `wire:poll.30000ms` request is a normal authenticated request that refreshes the session's last_activity, so a tab left open on the DOTW dashboard KEEPS its session alive rather than expiring — the "admin walks away from the polling dashboard" scenario cannot produce the 419, and the "re-prompts forever every 30 seconds" claim does not hold. Rewrote the trigger around the app-wide notification bell on an idle non-polling page (plus cross-tab logout and session-table GC, lottery [2,100]). Also corrected the location: the DOTW admin dashboard is at /admin/dotw behind the `dotw_audit_access` middleware, not under System Settings. Vendor line numbers corrected (419 branch is 4324-4327 not 4329-4332; handlePageExpiry is 4345-4346). Frequency downgraded dead-common → occasional. Still true and worth keeping: this is the only place in the entire codebase that reacts to a 419 at all, and it is vendor code; no hand-written fetch handler anywhere checks for 419 or 401.

#### 4.5.9 wire:navigate silent dead-click — nothing happens, forever

- **Status:** n/a (network) or unread 404/500 | **Frequency:** occasional
- **Trigger:** User clicks Users List, Companies List, or Settings in the sidebar or mobile drawer while the network is flaky or the server is returning errors.
- **Mechanism:** Livewire's `performFetch(uri, callback)` does `fetch(uri, options).then(...).then(html => callback(html, finalDestination))` with NO `.catch()` and NO `response.ok` check. A rejected fetch becomes an unhandled promise rejection swallowed in the console; the callback that swaps the page never runs. Separately, because there is no response.ok check, a 404/500 HTML body is happily swapped into the page as if it were the destination.
- **User sees:** On network failure: the thin Livewire progress bar starts at the top of the viewport and then just stops — the page never changes, no error, no toast, nothing. The click appears to have been ignored. On a 404/500 destination: the stock unbranded Laravel error page is SPA-swapped into the body with the URL updated, so it looks like the app navigated to an error page rather than crashed.
- **Way back:** On the silent-failure path there is nothing to go back FROM — the user is still on the old page and just thinks the app is broken. On the swapped-in-error-page path the app chrome (sidebar, header) is gone entirely and only browser Back gets out.
- **Evidence:** vendor/livewire/livewire/dist/livewire.js:7428-7449 (performFetch — confirmed, no catch, no ok check), :7422-7427 (fetchHtml), :7453-7462 (prefetchHtml uses the same); links at resources/views/layouts/menu.blade.php:179, 185, 345 and resources/views/layouts/mobile-drawer.blade.php:184, 186, 272 (grep for wire:navigate returns 14 hits repo-wide, of which exactly these 6 are real link attributes; the other 8 are blade comments)
- **Verify note:** VERIFIED — every cited line matched exactly, including the absence of both .catch() and response.ok in performFetch. Confirmed the scope claim: only 6 links in the whole app use wire:navigate, so this is a small surface and the app is otherwise full page loads.

#### 4.5.10 Refund processing failure tells the user to open the browser console

- **Status:** any non-2xx; body read as text and discarded to console | **Frequency:** occasional
- **Trigger:** An agent clicks "complete process" on a refund and the server returns non-2xx.
- **Mechanism:** `fetch('/refunds/${refundId}/complete-process', {POST, credentials:'same-origin'})` → `else { const text = await response.text(); console.error('Server response:', text); alert('Something went wrong. Check console for details.'); }`, plus a transport-level `.catch` that alerts 'Error processing refund. Please try again.'
- **User sees:** Native dialog: 'Something went wrong. Check console for details.' The actual server response — which may be the only record of why a customer's refund failed — is written only to the DevTools console, where a travel agent will never look.
- **Way back:** OK returns to the refund list with the refund in an unknown state. No retry, no indication of whether the refund partially applied.
- **Evidence:** resources/views/refunds/index.blade.php:378-401 — ok-branch at :387-390, failure alert at :395, network catch alert at :400
- **Verify note:** CORRECTED — code confirmed exactly at the cited lines, but the closing claim was wrong. The success path is not silent: it alerts 'Refund process completed successfully!' at :389 before `window.location.href = '/refunds'` at :390, so success and failure ARE distinguishable by the dialog wording. Removed the 'visually indistinguishable' claim. Everything else stands, including that this is a money-moving action whose only diagnostic goes to the console.

#### 4.5.11 Invoice gateway save shows server ERRORS inside the GREEN success box

- **Status:** 422 / 500 / 419 — none of which the code inspects | **Frequency:** occasional
- **Trigger:** On the invoice edit screen, changing the payment gateway on an invoice when the server rejects it (validation, permission, or 500).
- **Mechanism:** `const result = await response.json();` with NO `response.ok` check, followed unconditionally by `responseBox.classList.add('bg-green-100','text-green-700'); responseBox.textContent = result.message \|\| 'Success';`. A 422/500 JSON body carrying a `message` key is therefore painted as a success. If the response is HTML instead of JSON (500 with APP_DEBUG=true, or 419 Page Expired), `.json()` throws and the catch paints `error.message` — the raw JS SyntaxError — into a red box.
- **User sees:** Either a green confirmation bar reading e.g. 'The selected gateway is invalid.' — success styling on a failure — or a red bar reading `Unexpected token '<', "<!DOCTYPE "... is not valid JSON`. Both vanish after exactly 3000ms via setTimeout, whether or not the user was looking.
- **Way back:** None. The box hides itself after 3 seconds and clears its own text, so there is no way to re-read what happened. The invoice is left in an unknown gateway state.
- **Evidence:** resources/views/invoice/edit.blade.php:5450-5475 — json parse at :5460, unconditional green at :5462-5464, catch at :5466-5470, red text at :5469, auto-hide setTimeout at :5472-5475
- **Verify note:** VERIFIED — line-for-line accurate, including the missing response.ok check, the unconditional green styling, and the 3000ms auto-hide applying to the error branch as well.

#### 4.5.12 Invoice payment toasts stack invisibly on top of each other and never auto-dismiss

- **Status:** varies (message comes from `errorData.message` at :5740 or `error.message` at :5757) | **Frequency:** occasional
- **Trigger:** Applying payments to multiple invoice line items in one go; each failing item fires its own toast.
- **Mechanism:** `displayErrorMessage(message)` builds `document.createElement('div')` with innerHTML `<div class="alert alert-danger fixed mt-5 top-1 right-4 bg-red-500 text-white p-4 rounded shadow-lg">${message}<button ... onclick="this.parentElement.style.display='none';">×</button></div>` and appends it to body. Every toast uses the identical `fixed top-1 right-4` coordinates. No stacking offset, no timeout, no dedupe. `${message}` is interpolated unescaped.
- **User sees:** One red bar pinned to the top-right corner. If five line items failed, there are five identical bars perfectly overlapping — the user sees one, closes it, and another appears underneath, apparently at random. They never auto-dismiss, so a failed batch leaves red bars sitting over the invoice indefinitely.
- **Way back:** A small `×` per toast, whose onclick sets `display:none` on the inner alert div and leaves the empty outer wrapper div in the DOM forever.
- **Evidence:** resources/views/invoice/edit.blade.php:5765-5776 (displayErrorMessage), :5778-5789 (displaySuccessMessage, identical coords), :5791-5805 (showNotification, a third variant with the same coords); call sites at :5738-5746 (errorData.message) and :5755-5762 (error.message)
- **Verify note:** VERIFIED — all three toast implementations confirmed at the cited lines with identical `fixed mt-5 top-1 right-4` positioning, no timeout, and unescaped `${message}` in innerHTML. The dismissal-leaves-the-wrapper detail is correct: `this.parentElement` from the button is the inner alert div, not the appended wrapper.

#### 4.5.13 Report filters replace the account dropdown with an un-selectable "Error loading accounts" option

- **Status:** unread — both catches ignore status entirely (neither checks response.ok before .json()) | **Frequency:** occasional
- **Trigger:** On the Unpaid Report and the New Report screens, changing the filter that reloads the account list when the accounts endpoint fails.
- **Mechanism:** `.catch(error => { accountSelect.innerHTML = '<option value="...">Error loading accounts</option>'; })`. The entire `<select>` is wiped, so the real options are gone until a full page reload.
- **User sees:** The Account dropdown now contains exactly one entry reading "Error loading accounts", which is selected. The Filter button is deliberately re-enabled in the `.finally()` block, so it looks clickable — but submitting sends `account=""` (unpaid-report) or `account="all"` (new-report), producing either a validation bounce or a silently wrong report.
- **Way back:** None in-page. Only a manual browser refresh restores the account list. The two screens disagree on the fallback value, so the wrong-report failure mode differs between them.
- **Evidence:** resources/views/reports/unpaid-report.blade.php:331-334 (literal at :332, `value=""`), finally at :335-339; resources/views/reports/new-report.blade.php:319-326 (literal at :322, `value="all"`), finally at :327-331
- **Verify note:** VERIFIED — both catch blocks, both differing fallback values, and both `.finally()` blocks that re-enable the Filter button and reset its label on the error path confirmed exactly as described.

#### 4.5.14 Client profile tabs collapse to flat red one-liners with no retry

- **Status:** unread | **Frequency:** occasional
- **Trigger:** Opening a client's profile and switching to the Tasks, Invoices, or Payment Links tab when the ajax endpoint fails.
- **Mechanism:** Three near-identical `.catch(() => { container.innerHTML = '<div class="py-6 text-center text-red-400 text-sm">Failed to load X</div>' })` blocks. The catch discards the error object entirely.
- **User sees:** The tab body is replaced by a single line of small, low-contrast red-400 text centred in an otherwise empty panel: 'Failed to load tasks'. No icon, no border, no explanation, no button.
- **Way back:** None. Switching tabs and back re-runs the fetch, but nothing tells the user that. There is no retry control.
- **Evidence:** resources/views/clients/new-profile.blade.php:743 ('Failed to load tasks'), :821 ('Failed to load invoices'), :946 ('Failed to load payment links') — all three identical apart from the noun
- **Verify note:** VERIFIED — the three innerHTML assignments are at :743, :821 and :946 (the census's 742-744 / 820-822 / 945-947 ranges bracket them correctly). Contrast concern stands: `text-red-400` at `text-sm` on a white card.

#### 4.5.15 Invoice detail modal shows a bare centred red sentence

- **Status:** any non-2xx (thrown as a generic 'Network response was not ok'), or network failure | **Frequency:** occasional
- **Trigger:** A staff user on the Invoice Links screen clicks to view invoice details in a modal and the fetch fails.
- **Mechanism:** `fetch(url).then(r => { if (!r.ok) throw new Error('Network response was not ok'); return r.text(); })` → `.catch(error => { console.error(...); contentDiv.innerHTML = '<p class="text-center text-red-500">Failed to load invoice details.</p>' })`. The endpoint returns HTML, not JSON, and is injected via innerHTML.
- **User sees:** An open modal whose entire body is one centred red sentence: 'Failed to load invoice details.' The modal chrome, backdrop and close behaviour remain, so it reads as a deliberately empty dialog rather than a failure.
- **Way back:** Backdrop click / closeInvoiceModal() only. No retry. The user is left not knowing whether the invoice exists.
- **Evidence:** resources/views/invoice/link.blade.php:312-336 (throw at :315, catch at :331-336, literal at :334); the view is served by InvoiceController::link (app/Http/Controllers/InvoiceController.php:3119) via route `invoice.link` at routes/web.php:523, which sits inside the top-level `Route::middleware(['auth'])` group (routes/web.php:61-898)
- **Verify note:** CORRECTED — the invoice/link.blade.php half is real and confirmed line-for-line, but two claims were wrong. (1) This is NOT client-facing: invoice.link is a staff-only route behind auth, so the 'potentially an external client on a payment link' framing is removed. (2) The claimed duplicate at resources/views/payment/companyAgentsInvoices.blade.php:368-371 does contain byte-identical code, but that view is referenced NOWHERE — grep for 'companyAgentsInvoices' across app/, routes/ and resources/ returns nothing. It is an orphan file, unreachable, so it is dropped from the evidence rather than counted as a second surface. Still true: the thrown 'Network response was not ok' is discarded to console, so a 403 and a dead network produce identical UI.

#### 4.5.16 Livewire chat surfaces "Error: Something went wrong!" and "Fetch error: <raw JS message>"

- **Status:** varies; the `error.error` key is read but `response.status` never is | **Frequency:** occasional
- **Trigger:** Using the chat panel: selecting tasks for pricing, saving pricing, or the three task-action calls near the bottom of the file (create invoice, create agent, create branch).
- **Mechanism:** fetch handlers that reject with the parsed JSON body and then `alert("Error: " + (error.error \|\| "Something went wrong!"))`, or catch a transport error and `alert("Fetch error: " + error.message)`.
- **User sees:** Native dialogs reading 'Error: Something went wrong!' or, worse, 'Fetch error: Failed to fetch' / 'Fetch error: Unexpected token \'<\', "<!DOCTYPE "... is not valid JSON' — raw browser engine strings prefixed with the words 'Fetch error'.
- **Way back:** OK only. The chat panel stays open in whatever state it was in; `taskSelection.hide()` at :868 runs synchronously right after the fetch is kicked off, so the selection UI closes before the request has even resolved, let alone failed.
- **Evidence:** resources/views/livewire/chat.blade.php:853-866 (ok-check at :854-858 rethrowing the parsed body, alert at :865), :966-968, :1604, :1608, :1689, :1693, :1729, :1733
- **Verify note:** VERIFIED — all eight alert sites confirmed at the cited lines. One refinement: taskSelection.hide() is at :868 (not :869), and it is not merely 'runs regardless of outcome' — it runs synchronously outside the promise chain, so the UI closes before the response arrives. Confirmed this lives inside a Livewire component's view but bypasses Livewire entirely with hand-rolled fetch/jQuery.

#### 4.5.17 Currency exchange fires two alerts for one failure, then silently reloads or doesn't

- **Status:** non-2xx, unread beyond `response.ok` | **Frequency:** occasional
- **Trigger:** Saving manual exchange rates, or triggering the automatic rate update, on the Currency Exchange screen.
- **Mechanism:** `.then(response => { if (!response.ok) { alert('Something went wrong'); throw new Error('Network response was not ok'); } return response.json(); }).then(data => { alert(data.message); window.location.reload(); }).catch(error => { console.error('Error:', error); })`. The alert fires inside the `.then`, then the throw is swallowed by a catch that only console.errors. On the success path a second alert shows `data.message` before a hard reload.
- **User sees:** A native 'Something went wrong' dialog with no detail. On success, a different native dialog with the server message, immediately followed by a full page reload — so success and failure both present as an OS dialog and the only difference is the wording.
- **Way back:** OK dismisses; the form keeps whatever rates the user typed, but nothing indicates which of them (if any) saved.
- **Evidence:** resources/views/currency-exchange/index.blade.php:259-272 (ok-check alert at :261, throw at :262, success alert at :267, reload at :268, swallowing catch at :270-272), :294 and :300 (same shape in the second handler), :322-326 (third handler, alert at :324), plus :201 ('something went wrong', lowercase s)
- **Verify note:** VERIFIED — all four alert sites confirmed, including the capitalisation inconsistency (:201 lowercase 'something went wrong'; :261, :294, :324 capitalised). The swallowing catch at :270-272 is exactly as described. One extra detail found: the third handler's success branch (:329-335) builds its own bespoke green fixed-position toast rather than alerting, so this one file mixes native dialogs and a hand-rolled toast for the same flow.

#### 4.5.18 Silent failures — actions that fail with no error surface whatsoever

- **Status:** 200 with success:false (a); n/a (b) | **Frequency:** occasional
- **Trigger:** Task picker on the task detail screen receives a well-formed `{success:false}` response; the notification bell's 'mark all as read' completes.
- **Mechanism:** (a) `resources/views/tasks/detail.blade.php` `loadTasks()` wraps the whole update in `if (data.success) { ... }` with no `else`, so a 200 `{success:false}` updates nothing and the `finally` just clears the spinner (the `catch` only fires on transport/parse errors). (b) `app/Livewire/Notification.php:84` calls `session()->flash('message', 'All notifications marked as read.')`, but `layouts/alert.blade.php` only renders `session('success')` and `session('error')` — the `message` key is rendered in exactly one unrelated view.
- **User sees:** (a) The task picker's spinner stops and the list stays empty — indistinguishable from 'this client genuinely has no tasks'. (b) 'Mark all as read' works, the badge count changes, but the confirmation the code intends to show is dropped on the floor.
- **Way back:** n/a — the user does not know anything went wrong, so there is nothing to recover from.
- **Evidence:** resources/views/tasks/detail.blade.php:470-490 (the else-less `if (data.success)` at :476-483, catch at :484-486, finally at :487-489); app/Livewire/Notification.php:79-85 (flash at :84); resources/views/layouts/alert.blade.php:14 and :42 (only success/error rendered); the sole `session('message')` consumer repo-wide is resources/views/bulk-invoice/preview.blade.php:33,38
- **Verify note:** VERIFIED — both branches confirmed at the cited lines, and the `session('message')` orphan confirmed by repo-wide grep (one consumer, in bulk-invoice/preview.blade.php). Counts refined: `session()->flash()` is used with exactly three keys app-wide ('message', 'credentials_saved', 'resailai_settings_saved'), against 600 (not 590) `with('success'\|'error')` occurrences in app/Http/Controllers and app/Livewire. Worth keeping the framing: adding a designed error state here means adding the branch, not restyling one.

#### 4.5.19 Supplier procedure modal has two different error states for the same modal

- **Status:** 404 with `{success:false, message}` — NOT 200 | **Frequency:** rare
- **Trigger:** Opening a supplier's procedure from the suppliers list when the procedure id is stale/deleted or the query throws.
- **Mechanism:** `fetch(url).then(r => r.json())` with no response.ok check, so a 404 JSON body parses normally and lands in the `else` branch. Two separate branches write different HTML into `#procedure-content-${procedureId}`: the `else` branch renders 'Failed to load procedure' plus `${data.message}`, and the `.catch` branch (transport/parse failure) renders 'Error loading procedure' plus a fixed 'Please try again later.' `${data.message}` is unescaped and comes from SupplierProcedureController::show, which concatenates raw exception text.
- **User sees:** Inside the modal: a large red FontAwesome warning triangle, bold red headline, and a grey sub-line. Depending on which branch fired, the headline is either 'Failed to load procedure' (with a raw server message — for a deleted procedure that reads 'Failed to fetch procedure: No query results for model [App\Models\SupplierProcedure] 42') or 'Error loading procedure' (with 'Please try again later.'). The two states are visually identical but say different things for reasons the user cannot perceive.
- **Way back:** Only the modal's own close control (closeProcedureModal at :283, plus an Escape-key listener registered at :204). No retry inside the error state.
- **Evidence:** resources/views/suppliers/partials/list_procedure.blade.php:207-208 (fetch, no ok check), :262-270 (else branch, literal at :266, raw message at :267), :272-280 (catch branch, literal at :276); server app/Http/Controllers/SupplierProcedureController.php:68-93 — findOrFail at :72, catch at :88-92 returning `'Failed to fetch procedure: ' . $e->getMessage()` with HTTP 404
- **Verify note:** CORRECTED — surface real and both branches confirmed at the cited lines, but the status was wrong. SupplierProcedureController::show returns HTTP **404** (app/Http/Controllers/SupplierProcedureController.php:92), not 200; the else branch is still reached because the JS never checks response.ok and a 404 JSON body parses fine. Also made the raw-message example concrete: the catch wraps findOrFail, so the leaked text is typically Eloquent's ModelNotFoundException string including the model class name.

#### 4.5.20 MyFatoorah card form alerts a raw error object on a page with no navigation at all

- **Status:** n/a (client-side SDK promise rejection) | **Frequency:** rare
- **Trigger:** A staff user operating the embedded MyFatoorah checkout submits card details and the SDK rejects (declined card, tokenisation failure, network).
- **Mechanism:** `myFatoorah.submit().then(mfCallback).catch(function (error) { alert(error); })`. `error` is the MyFatoorah SDK's rejection value — an object, so `alert()` stringifies it.
- **User sees:** A native browser dialog most likely reading '[object Object]' (or a bare SDK string). The host page is a standalone `<!DOCTYPE html>` document loading only `vendor/myfatoorah/css/style.css` — no app layout, no header, no nav, no footer, no logo.
- **Way back:** Absolutely none. The checkout page has no links whatsoever. After dismissing the dialog the user is stranded on a bare payment form with no way back to the invoice or to the app.
- **Evidence:** resources/views/myfatoorah/includes/sectionForm.blade.php:62-72 (alert at :70); host page resources/views/myfatoorah/checkout.blade.php:1-8 and the @include at :90-92; route routes/web.php:418 (`Route::get('checkout', [MyFatoorahController::class, 'checkout'])`), which sits inside the top-level `Route::middleware(['auth'])` group at routes/web.php:61-898
- **Verify note:** CORRECTED — the `alert(error)` at :70 and the bare host page are confirmed exactly, but the headline claim was wrong. `/checkout` is inside the top-level auth group (routes/web.php:61), so this is NOT a surface a paying END CUSTOMER can reach — it is a staff-operated embedded card form. That removes the 'disproportionately expensive because customers see it' framing and the 'only customer-facing surface' claim. Also corrected the closing note: the 'no payment gateways' state is NOT served from checkout.blade.php:9-13 as claimed — MyFatoorahController::checkout throws before rendering checkout in that case (app/Http/Controllers/MyFatoorahController.php:180-182) and renders myfatoorah/error.blade.php instead; see the separate added finding.

#### 4.5.21 MyFatoorah error page prints an untranslated translation key on a blank page

- **Status:** 200 (the failure is rendered as a normal successful response) | **Frequency:** rare
- **Trigger:** Opening the MyFatoorah embedded checkout when the account has no enabled gateways, or when any exception is thrown while building the checkout session (bad config, API failure, cURL timeout).
- **Mechanism:** `catch (Exception $ex) { $exMessage = __('myfatoorah.' . $ex->getMessage()); return view('myfatoorah.error', compact('exMessage')); }`. The exception message is used as a translation KEY. Only two keys exist in lang/en/myfatoorah.php ('noPaymentGateways' and one literal English sentence), so every other exception — an API error string, a cURL message — falls through Laravel's translator untranslated and is echoed with the `myfatoorah.` prefix still attached.
- **User sees:** A standalone white page with no header, no nav, no logo, no app chrome — just one line of red text. In the good case it reads 'There are no payment methods available on your account, please contact your account manager.' In every other case it reads the literal string `myfatoorah.` followed by the raw exception message, e.g. `myfatoorah.cURL error 28: Operation timed out`.
- **Way back:** None. The page contains no links, no buttons and no back affordance of any kind.
- **Evidence:** app/Http/Controllers/MyFatoorahController.php:180-182 (`throw new Exception('noPaymentGateways')`), :195-198 (catch, key lookup, error view); resources/views/myfatoorah/error.blade.php:1-15 (whole file: a bare <!DOCTYPE html> page whose entire body is `<div class="mf-payment-methods-container"><div class="mf-danger-text">{{$exMessage}}</div></div>`); lang/en/myfatoorah.php:3-27 (only 'noPaymentGateways' at :6 and the literal-sentence key at :26 can ever resolve)
- **Verify note:** ADDED IN VERIFY — found while checking the MyFatoorah checkout finding above, which incorrectly attributed the no-gateway state to checkout.blade.php:9-13. This is the actual error surface for that path and for every other checkout exception, and it was missing from the census.

#### 4.5.22 Error-monitoring dashboard's own failure is an alert()

- **Status:** unread | **Frequency:** rare
- **Trigger:** Admin opens the AI document-processing Error Dashboard, or changes its date range, and the metrics endpoint fails.
- **Mechanism:** `fetch(route('admin.error-dashboard.metrics') + '?range=' + range).then(r => r.json())...catch(error => { console.error(...); showLoading(false); alert('Failed to load metrics. Please try again.'); })`. No response.ok check before .json().
- **User sees:** A native dialog 'Failed to load metrics. Please try again.' over a dashboard whose charts and tables still show the PREVIOUS range's data — because none of updateSummaryCards / updateErrorTrendChart / updateErrorTypeChart / updateSupplierTable / updateDocTypeTable / updateRecentErrors ran, and nothing clears them. The range selector, however, has already moved to the new value.
- **Way back:** OK dismisses. The 'Please try again' instruction has no button attached to it; the user must re-pick the range from the selector, which already displays the range they failed to load.
- **Evidence:** resources/views/admin/error-dashboard/index.blade.php:210-229 (fetch at :213, no ok check at :214, catch at :224-228, alert at :227); showLoading at :231-233; the six update* calls that never run are at :216-221
- **Verify note:** VERIFIED — exact match at the cited lines. The stale-data hazard is real: after clicking OK the screen looks like a successfully loaded dashboard for the wrong period.

---

### 4.6 Payments & gateways (customer-facing)  (19 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Hesabe/KNET payer is dumped on the staff login screen after paying | 302 to /login | dead-common | None that helps. The login form is the o |
| 2 | payment/failed — one generic page for 30+ different failures, and its only button leads to logi | 200 (page renders fine, content is the problem) | dead-common | A single blue 'Return to Homepage' butto |
| 3 | 419 Page Expired when a WhatsApp payment link is opened later than the session allows | 419 | dead-common | None. errors::419 extends errors::minima |
| 4 | Expired payment link: 'Pay Now' returns a bare 500 | 500 | occasional | None. |
| 5 | Anonymous invoice payer gets a raw HTTP 400 carrying an internal error string | 400 | occasional | None. |
| 6 | Stock 404 for an unknown, mistyped, deleted or client-detached payment link | 404 | occasional | None — errors::minimal contains no ancho |
| 7 | Broken/expired partial-payment (split) link sends the customer to the login page | 302 -> 302 -> 200 /login | occasional | None relevant — a login form the custome |
| 8 | Gateway failure shown as a raw internal message in a red banner on the payment page | 302 back, then 200 | occasional | Yes — this is the one surface that keeps |
| 9 | Validation failure on Pay Now is completely invisible | 302 back, then 200 | occasional | The page itself is still there, but ther |
| 10 | Deactivated payment link is a silent dead end | 200 | occasional | None; the page has no navigation. |
| 11 | Already-paid link re-pays on Tap and Hesabe (no guard), while MyFatoorah short-circuits | 302 to gateway (no error surfaced) | occasional | n/a — there is no error page here; the f |
| 12 | 500 TypeError on the MyFatoorah error/decline URL when payment_id is absent | 500 | rare | None. Neither the debug page nor the sto |
| 13 | WhatsApp hotel booking payer lands on raw JSON after paying | 200 (JSON body) | rare | None. It is not an HTML document. |
| 14 | Gateway unreachable or slow: no cURL timeout, then a null-dereference 500 or a hung request | 500, or 502/504 from the web server | rare | None. |
| 15 | Money captured but the payer is told the payment failed (Tap config lookup fails on return) | 302 -> 200 payment/failed | rare | 'Return to Homepage' -> url('/') -> 302  |
| 16 | multiPaymentLinkInitiate aborts 500 for a guest on a data-integrity problem | 500 | rare | None. |
| 17 | Hesabe checkout token missing: customer is redirected to a malformed gateway URL | 302 to gateway (gateway-side error) | rare | Whatever Hesabe's error page offers; not |
| 18 | UPayment cancel/decline returns the generic failure page with no reason at all | 302 -> 200 payment/failed | rare | 'Return to Homepage' -> url('/') -> 302  |
| 19 | MyFatoorah vendor demo controller returns raw JSON / an unstyled error card | 200 (JSON) / 200 (error view) / 302 to dashboard | theoretical | None. |

#### 4.6.1 Hesabe/KNET payer is dumped on the staff login screen after paying

- **Status:** 302 to /login | **Frequency:** dead-common
- **Trigger:** Customer pays a payment link or invoice with Hesabe (which is how KNET is served for company 1). Hesabe redirects the browser back to responseUrl (or failureUrl on decline). The customer's money has already moved.
- **Mechanism:** Both return routes sit INSIDE the top-level `Route::middleware(['auth'])->group()` (routes/web.php:61 … 898) and, unlike every other gateway callback in the same block, neither carries `->withoutMiddleware(['auth'])`. Laravel's Authenticate middleware fires before the controller and 302s the guest to route('login'). handleHesabeResponse()/handleHesabeFailure() never execute for the browser at all — the payment is only ever completed by the separate public webhook at routes/api.php:53. Both routes are also GET-only, so a Hesabe POST return would additionally produce a 405 with no view.
- **User sees:** The application's LOGIN PAGE. No confirmation, no receipt, no mention of the payment. Re-measured live: GET /payment/hesabe-callback -> 302 -> https://development.citycommerce.group/login (same for /payment/hesabe-error).
- **Way back:** None that helps. The login form is the only thing on screen; the customer has no account. There is no link back to their voucher or invoice.
- **Evidence:** routes/web.php:669, routes/web.php:670, routes/web.php:61, routes/web.php:898, app/Http/Controllers/PaymentController.php:5679, app/Http/Controllers/PaymentController.php:5960, app/Http/Controllers/PaymentController.php:3534-3535 (payment-link flow responseUrl/failureUrl), app/Http/Controllers/PaymentController.php:1395-1396 (invoice flow responseUrl/failureUrl), routes/api.php:53
- **Verify note:** VERIFIED. Corrected only the responseUrl/failureUrl evidence lines: they are PaymentController.php:3534-3535 and :1395-1396 (census said :3531 and :1393). Everything else confirmed exactly, including that routes/web.php:663 (tap), :665/:666 (uPayment), :672/:673 (knet) all DO carry `withoutMiddleware(['auth'])` while :669/:670 do not. DB re-checked: payments table has Hesabe completed=47, initiate=7, pending=2 — the 47 completed all took this browser path. CUSTOMER-FACING and the most commercially damaging item in this surface.

#### 4.6.2 payment/failed — one generic page for 30+ different failures, and its only button leads to login

- **Status:** 200 (page renders fine, content is the problem) | **Frequency:** dead-common
- **Trigger:** Any terminal failure in any gateway: card declined, callback missing tap_id/trackId/trandata, payment record not found, status verification failed, KNET decryption failed, Hesabe config missing, hotel-booking confirmation crashed, or an unhandled exception in a callback.
- **Mechanism:** `redirect()->route('payment.failed')->with('error', <specific reason>)` from 33 call sites in PaymentController alone; PaymentController::failed() (line 6629) returns view('payment.failed'). The blade NEVER reads session('error') — grep for 'session(' in resources/views/payment/failed.blade.php returns zero matches — so every distinct reason is discarded and replaced with one fixed sentence.
- **User sees:** Red gradient page, white card, ✗ mark, headline 'Payment Failed!', body 'Unfortunately, your payment could not be processed. Please check your payment details and try again.' — regardless of whether the card was declined, the callback was malformed, or the server threw. Unbranded: no company logo, no voucher number, no amount, no reference, no support contact. A script auto-closes the window after 15s if it was opened as a popup.
- **Way back:** A single blue 'Return to Homepage' button pointing at `url('/')`. Re-measured live: GET / -> 302 -> /login. So the only escape route hands an anonymous paying customer the staff login form.
- **Evidence:** resources/views/payment/failed.blade.php:134-147, app/Http/Controllers/PaymentController.php:6629 (failed()), :6624 (success()), and the 33 call sites at :3876, :3892, :3938, :3943, :3994, :4086, :4094, :4101, :4107, :4344, :4347, :4356, :4374, :4384, :4392, :4409, :4415, :4668, :4735, :4742, :5355, :5362, :5389, :5617, :5669, :5687, :5697, :5719, :5841, :5992, :6011, :6061, :6068
- **Verify note:** CORRECTED: the census said '~15 call sites'; the actual count is 33 `route('payment.failed')` occurrences in PaymentController.php. Blade content lines are 134-147 (census said 132-147). Everything else VERIFIED. The mirror page resources/views/payment/success.blade.php was re-checked: 129 lines, zero `<a>` tags, zero href, no amount, no voucher number, no reference, no receipt link — a paid customer has literally nowhere to go from it. CUSTOMER-FACING.

#### 4.6.3 419 Page Expired when a WhatsApp payment link is opened later than the session allows

- **Status:** 419 | **Frequency:** dead-common
- **Trigger:** Customer receives a payment link on WhatsApp, opens it, leaves the tab or their phone for more than 2 hours (or comes back to it the next day via the browser back button), then taps Pay Now.
- **Mechanism:** Every customer-facing payment POST is CSRF-protected: the forms emit @csrf and there is NO VerifyCsrfToken except-list anywhere (app/Http/Middleware/ contains no VerifyCsrfToken.php; bootstrap/app.php's ->withMiddleware() only aliases and appends SetLocale, and never calls validateCsrfTokens(except:)). SESSION_LIFETIME=120 minutes with SESSION_DRIVER=database, so the token silently dies while the page is still on screen. TokenMismatchException -> HTTP 419 -> vendor errors::419 -> errors::minimal.
- **User sees:** White page, tiny grey text: '419 \| PAGE EXPIRED'. Re-confirmed live: POST /payment/link/initiate without a token returns 419. To the customer this reads as 'this payment link is dead' — they will not know that reloading fixes it.
- **Way back:** None. errors::419 extends errors::minimal, which has no anchor tag.
- **Evidence:** resources/views/payment/link/show.blade.php:252, :298 (@csrf), resources/views/payment/link/multi-payment.blade.php:325, :373, resources/views/invoice/show.blade.php:454, :524, :544, resources/views/invoice/split.blade.php:193-194, .env:37 SESSION_DRIVER=database, .env:38 SESSION_LIFETIME=120, bootstrap/app.php:24-43 (withMiddleware, no CSRF except-list) and :45-47 (withExceptions empty), routes/web.php:653, :661, :655, :630
- **Verify note:** VERIFIED. Corrected blade line numbers to the actual @csrf lines: show.blade.php:252/:298, multi-payment.blade.php:325/:373, invoice/show.blade.php:454/:524/:544 (census cited :541-544). bootstrap/app.php cited as :24-45 — the withMiddleware closure is :24-43 and withExceptions (empty) is :45-47. Absence of any VerifyCsrfToken middleware confirmed by directory listing. Applies to payment.link.initiate, payment.link.multi-initiate, payment.link.reinitiate and payment.create. CUSTOMER-FACING.

#### 4.6.4 Expired payment link: 'Pay Now' returns a bare 500

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** Customer opens a MyFatoorah payment link from WhatsApp days later (MyFatoorah URLs expire ~2 days) and taps Pay Now. The app tries to silently re-issue the link.
- **Mechanism:** paymentLinkInitiate() sees `payment_url` past `expiry_date` (check at :3382) and calls paymentLinkReinitiate() (:3392 -> :3693) -> reinitiateMyFatoorah() (:3731). If the ExecutePayment retry is not `successful()` or the response lacks PaymentURL/InvoiceId, the guest branch is `abort(500)` — a deliberate abort, not a crash. A third guest dead-end sits at :3697: if the payment row is no longer status='initiate', paymentLinkReinitiate does `redirect()->back()`, which for a referer-less guest degrades to / -> /login.
- **User sees:** Stock '500 \| SERVER ERROR' minimal page when APP_DEBUG=false, or Laravel 11's built-in debug stack-trace page on this server. No branding, no explanation that the link expired, no mention that they can ask the agent for a new one.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/PaymentController.php:3381-3392 (status='initiate' + expiry check + reinitiate call), :3693 (paymentLinkReinitiate), :3697 (redirect()->back() dead end), :3731 (reinitiateMyFatoorah), :3792-3795 (Http::post ExecutePayment, no ->timeout()), :3799 (abort(500)), :3814 (abort(500))
- **Verify note:** CORRECTED (evidence lines): the expiry check + reinitiate is at :3381-3392, not :3387-3390; :3693, :3799, :3814 confirmed exactly. Added the :3697 guest dead-end found while verifying. DB re-checked: MyFatoorah payments with status='initiate' = 155 — that population is precisely the pool of links that will hit this path. CUSTOMER-FACING.

#### 4.6.5 Anonymous invoice payer gets a raw HTTP 400 carrying an internal error string

- **Status:** 400 | **Frequency:** occasional
- **Trigger:** Customer opens the public invoice page (/invoice/{companyId}/{invoiceNumber}), clicks Pay Now, and the gateway initiation fails — bad/missing API key for that company, gateway config inactive, MyFatoorah/Tap/KNET rejecting the charge, or a null gateway response.
- **Mechanism:** PaymentController::create() (line 171) calls initiatePayment(), which returns JSON errors (400/422/500) for every failure mode. create() then does `return abort(400, $errorMessage)` when there is no authenticated user. vendor ships error views only for 401/402/403/404/419/429/500/503 — there is no errors::400 and no errors::4xx — so renderHttpException falls through to convertExceptionToResponse.
- **User sees:** On THIS server (APP_DEBUG=true, no Ignition installed) the abort surfaces as Laravel 11's built-in debug exception page — <title>City Tour</title>, the HttpException, and the abort message verbatim, so a paying customer can be shown internal text such as 'API key of Hesabe gateway for company <Company Name> does not exist. Contact support team for more detail'. With APP_DEBUG=false the same abort renders Symfony's unstyled 'Oops! An Error Occurred — The server returned a "400 Bad Request".' and the message is DROPPED entirely, leaving no reason at all.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/PaymentController.php:171 (create()), :206 (abort(404) sibling), :237 (json_decode of initiatePayment), :239-246 (abort(400, $errorMessage)), :1361-1363 ('API key of <Gateway> gateway for company <Name> does not exist. Contact support team for more detail'), :1434 (Hesabe cURL error), :1445 (Hesabe decryption failed), :1456 (Hesabe checkout failed, no response data), :1483 ('KNET payment initiation failed'), :1490 ('Unsupported payment method'), :1515 ('Failed to initiate payment.'), routes/web.php:630, vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/ (no 400 or 4xx file)
- **Verify note:** CORRECTED. (a) user_sees was inverted/muddled: the Symfony 'Oops!' page is the APP_DEBUG=false case and it does NOT carry the abort message; the message leak is the APP_DEBUG=true case via Laravel 11's own debug renderer. (b) Evidence line drift fixed: json_decode is :237 (not :236), abort(400) is :246 (exact), Hesabe api-key message :1361-1363 (not :1360, and the text ends 'more detail' singular here), cURL/decrypt/no-data messages :1434/:1445/:1456 (not :1428/:1441/:1454), KNET :1483 (not :1478), unsupported method :1490 (not :1494), 'Failed to initiate payment.' :1515 (not :1519). routes/web.php:630 exact. The asymmetry the census flagged is real and verbatim: `Auth::user() ? redirect()->back()->with('error', …) : abort(…)`. CUSTOMER-FACING.

#### 4.6.6 Stock 404 for an unknown, mistyped, deleted or client-detached payment link

- **Status:** 404 | **Frequency:** occasional
- **Trigger:** Customer opens a payment-link URL that no longer resolves — the payment row was deleted (initiatePayment deletes stale rows), the client or agent relation was unlinked, the voucher number was mistyped, or the WhatsApp message truncated the URL.
- **Mechanism:** paymentShowLink() does three separate guard checks, each `return Auth::user() ? redirect()->route('payment.link.index') : abort(404);`. Renders vendor errors::404 -> errors::minimal.
- **User sees:** White page, centred, tiny grey text: '404 \| NOT FOUND'. Re-confirmed live: GET /payment/link/show/1/VOU-DOES-NOT-EXIST -> 404, <title>Not Found</title>.
- **Way back:** None — errors::minimal contains no anchor tag at all (grep -c '<a ' returns 0).
- **Evidence:** app/Http/Controllers/PaymentController.php:3098 (method), :3106, :3110, :3114, routes/web.php:647, vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/404.blade.php, vendor/.../views/minimal.blade.php
- **Verify note:** VERIFIED exactly — all four PaymentController line numbers and the route line are correct as written. Same stock 404 also covers the public invoice surfaces, re-confirmed by grep: InvoiceController.php:3148, :3197 (proforma), :3221, :3233 (invoice.show), :3353, :3368 (Arabic invoice), :3459 (invoice PDF), and ReceiptVoucherController.php:649 which uses `firstOrFail()` on the shareable receipt-voucher link (routes/web.php:947). CUSTOMER-FACING.

#### 4.6.7 Broken/expired partial-payment (split) link sends the customer to the login page

- **Status:** 302 -> 302 -> 200 /login | **Frequency:** occasional
- **Trigger:** Customer opens a split/partial invoice link — /invoice/partial/{invoiceNumber}/{clientId}/{partialId} — where the invoice or the partial no longer resolves (partial deleted, invoice renumbered, link copied wrong).
- **Mechanism:** InvoiceController::split() guards only the invoice with `return redirect()->back()->with('error', 'Invoice not found!')`. For a guest arriving straight from WhatsApp there is no Referer, so back() falls through to `/`, which is inside the auth group and 302s to /login. Separately, `$invoicePartial` is never null-checked — line 3485 assigns `$invoicePartial->expiry_date` on it, so a valid invoice with a bad partialId is a PHP Error ('Attempt to assign property on null'), i.e. a 500.
- **User sees:** The login page. Re-confirmed live: GET /invoice/partial/INV-NOPE/1/999999 -> 302 -> / -> 302 -> /login (final URL /login, 200). The flashed 'Invoice not found!' is never displayed because the login view is not the split view.
- **Way back:** None relevant — a login form the customer cannot use.
- **Evidence:** app/Http/Controllers/InvoiceController.php:3473 (split()), :3476-3479 ($invoicePartial lookup, ->first()), :3481-3483 (redirect back), :3485 (unguarded assignment on possibly-null $invoicePartial), routes/web.php:545
- **Verify note:** VERIFIED exactly — :3473, :3482, :3485 and routes/web.php:545 all correct as written, and the live redirect chain reproduces. The same pattern recurs at InvoiceController.php:3522 (splitarabic()), :3530 (redirect()->back()) and :3533 (the same unguarded $invoicePartial->expiry_date assignment). One correction: the 'paid by refund' -> redirect()->route('invoices.index') -> login case is at InvoiceController.php:3357, not :3358. CUSTOMER-FACING.

#### 4.6.8 Gateway failure shown as a raw internal message in a red banner on the payment page

- **Status:** 302 back, then 200 | **Frequency:** occasional
- **Trigger:** Customer taps Pay Now on a payment link and the gateway leg fails in a way the controller does catch — missing company API key, inactive gateway config, ExecutePayment non-2xx, Hesabe cURL/decrypt failure, UPayments malformed response, Tap validation error.
- **Mechanism:** `redirect()->back()->with('error', <message>)` and the payment-link blades render `@if (session('error')) <div class="bg-red-100 text-red-700 p-4 rounded mb-4">{{ session('error') }}</div>`. The message strings are engineering text, and some are verbatim gateway API text.
- **User sees:** The voucher page reloads with a flat red rectangle at the top reading e.g. 'Hesabe decryption failed' or 'ExecutePayment failed.' or 'UPayments: unexpected response'. No icon, no heading, no retry affordance, no support number.
- **Way back:** Yes — this is the one surface that keeps the customer on the payment page with the Pay button still available. It is the least-bad existing behaviour and the natural model for the rest.
- **Evidence:** resources/views/payment/link/show.blade.php:51-53, resources/views/payment/link/multi-payment.blade.php:51-53; single-gateway path: app/Http/Controllers/PaymentController.php:3347 ($response['errors'][0]['description'] straight from Tap), :3357 (MyFatoorah config missing), :3462 ('ExecutePayment failed.'), :3486 ('MyFatoorah response missing PaymentURL or InvoiceId.'), :3495 (Hesabe config missing), :3505 ('API key of Hesabe gateway for company X does not exist. Contact support team for more details'), :3569 ('Hesabe checkout failed due to cURL error'), :3577 ('Hesabe decryption failed'), :3585, :3608 ('Hesabe response missing token for PaymentURL'); multi-gateway path: :7130, :7138, :7148, :7158, :7165, :7242, :7313, :7423, :7430, :7445, :7505 ('UPayments: unexpected response'), :7509, :7539, :7570
- **Verify note:** VERIFIED. The blade banner lines :51-53 are exact in both files, and every cited line in the 7100-7600 multi-gateway range is exact. Corrected the single-gateway line drift: Tap description is :3347 (not :3345), MyFatoorah config missing :3357 (not :3356), Hesabe config missing :3495 (not :3491), the API-key message :3505 (not :3502, and its text ends 'more details' plural here vs 'more detail' singular at :1361), and 'Hesabe response missing token for PaymentURL' :3608 (not :3607). Both caveats hold: (a) `redirect()->back()` degrades to / -> /login when the browser sends no Referer, and (b) the copy is developer-facing and names the company and the gateway's internal state to an outside payer. CUSTOMER-FACING.

#### 4.6.9 Validation failure on Pay Now is completely invisible

- **Status:** 302 back, then 200 | **Frequency:** occasional
- **Trigger:** Customer taps Pay Now (or picks a payment method on the multi-gateway page) and the request fails Laravel validation — payment_id no longer `exists:payments,id` because the row was deleted/replaced, or payment_method_id was removed while the page was open.
- **Mechanism:** `$request->validate([...])` on the public POST routes throws ValidationException -> 302 back with an $errors bag. Neither payment-link blade ever references $errors (grep -c 'errors' returns 0 for both, and for invoice/split.blade.php), so the bag is rendered nowhere.
- **User sees:** The page appears to reload unchanged. No message, no banner, no highlight. The customer taps Pay Now again, gets the same nothing, and concludes the link is broken.
- **Way back:** The page itself is still there, but there is no signal that anything went wrong or what to do.
- **Evidence:** app/Http/Controllers/PaymentController.php:3298-3300 (paymentLinkInitiate validate: payment_id required\|exists:payments,id), :7108-7111 (multiPaymentLinkInitiate validate: payment_id + payment_method_id), resources/views/payment/link/show.blade.php (0 occurrences of 'errors'), resources/views/payment/link/multi-payment.blade.php (0 occurrences), resources/views/invoice/split.blade.php (0 occurrences), resources/views/invoice/show.blade.php:72-75 (@if($errors->any()) … @foreach)
- **Verify note:** VERIFIED exactly — :3298-3300 and :7108-7111 are correct as written, the three blades return 0 for `grep -c errors`, and invoice/show.blade.php:72-75 does render the bag, so the invoice page and the payment-link page genuinely behave differently for the identical failure. CUSTOMER-FACING.

#### 4.6.10 Deactivated payment link is a silent dead end

- **Status:** 200 | **Frequency:** occasional
- **Trigger:** Staff disable a payment link (payment.link.payment.activation toggles is_disabled) after it has already been sent; the customer then opens it.
- **Mechanism:** The blades wrap the entire Pay Now block in `@unless ($payment->status === 'completed' \|\| $payment->is_disabled)`. When disabled, the block is simply omitted. No @else, no message, no status chip. Note also that `is_disabled` is never re-checked server-side anywhere in PaymentController except the toggle itself — only the button is hidden.
- **User sees:** A complete, normal-looking voucher page with the amount and company branding — and no way to pay and no explanation. Indistinguishable from a rendering bug.
- **Way back:** None; the page has no navigation.
- **Evidence:** resources/views/payment/link/show.blade.php:250, :295, resources/views/payment/link/multi-payment.blade.php:323, :370, app/Http/Controllers/PaymentController.php:6920-6945 (paymentLinkActivation), routes/web.php:660; grep for 'is_disabled' across PaymentController.php returns only :6930 and :6933 (both inside the toggle) — nothing in paymentLinkInitiate (:3296+) or multiPaymentLinkInitiate (:7102+)
- **Verify note:** VERIFIED exactly — all four blade line numbers and :6920 are correct, and the absence of any server-side is_disabled guard is confirmed by a whole-file grep (only :6930/:6933 hit, both in the toggle). Not an error page today, but functionally an error state with zero communication. CUSTOMER-FACING.

#### 4.6.11 Already-paid link re-pays on Tap and Hesabe (no guard), while MyFatoorah short-circuits

- **Status:** 302 to gateway (no error surfaced) | **Frequency:** occasional
- **Trigger:** Customer opens a payment link they already paid — from an older WhatsApp message, a cached tab, or the browser back button — and the Pay button is still rendered from the stale HTML.
- **Mechanism:** paymentLinkInitiate() has an already-paid short-circuit ONLY in the MyFatoorah branch (completeIfAlreadyPaid at :3375 -> receipt redirect at :3378; status in ['completed','paid'] at :3393 -> receipt redirect at :3397). The Tap branch (line 3321) and the Hesabe branch (line 3487) contain no status check at all — they build a fresh charge and redirect the customer to the gateway to pay a second time. The UPayment branch (line 3610) checks only status==='initiate' for URL reuse and has no completed-check either.
- **User sees:** On MyFatoorah: the receipt page with a green 'Payment already completed.' flash — correct. On Tap/Hesabe: the gateway's card form again, and a second successful charge with no warning.
- **Way back:** n/a — there is no error page here; the failure is that no error is raised.
- **Evidence:** app/Http/Controllers/PaymentController.php:3321 (tap branch, no guard through :3351), :3487 (hesabe branch, no guard through :3609), :3375-3379 and :3393-3398 (myfatoorah guards), :3610 (upayment branch), :5293 (private function completeIfAlreadyPaid)
- **Verify note:** VERIFIED. :3321, :3487, :3610 and :5293 are exact. Corrected the MyFatoorah-guard span to :3375-3379 and :3393-3398 (census said :3374-3398). Corrected the callback-idempotency evidence: the already-completed returns are at :3961, :3977, :4141, :4289, :4433 and :4622 — NOT :4126/:4418/:5369/:5726 as the census claimed. The substance stands: the second payment is captured by the gateway but recorded as 'already completed' on our side — a silent overcharge, not a visible error. CUSTOMER-FACING and money-affecting.

#### 4.6.12 500 TypeError on the MyFatoorah error/decline URL when payment_id is absent

- **Status:** 500 | **Frequency:** rare
- **Trigger:** MyFatoorah bounces the payer to the ErrorUrl after a decline/cancel, but without echoing the payment_id query parameter (or anyone/any crawler opens /payments/error directly).
- **Mechanism:** handleMyFatoorahError() only assigns `$payment` inside `if ($request->has('payment_id'))` (branch opens at line 4030, assignment at 4036). Line 4053 then does `$payment->invoice` on an undefined variable (PHP 8 warning -> null) and line 4055 passes null into `publicReceiptNotice(Payment $payment, ...)`, whose first parameter is a non-nullable typed param — TypeError, uncaught (the method has no try/catch at all). Note the invoice_id branch (4006) does not help: it never assigns $payment either.
- **User sees:** HTTP 500. Re-confirmed live: `curl https://development.citycommerce.group/payments/error` returns 500 whose body contains 'TypeError' and 'publicReceiptNotice'. spatie/laravel-ignition is NOT installed on this box, so with APP_DEBUG=true (.env line 4) the page is Laravel 11's own built-in exception renderer — <title>City Tour</title> (the app name), 'Server Error', the TypeError, the offending source line and a stack trace with server paths. With APP_DEBUG=false it degrades to the stock white '500 \| SERVER ERROR' (vendor errors::500 -> errors::minimal).
- **Way back:** None. Neither the debug page nor the stock 500 page contains a link (minimal.blade.php has zero `<a>` tags).
- **Evidence:** app/Http/Controllers/PaymentController.php:3998 (method start), :4006 (invoice_id branch), :4030 (payment_id branch), :4036 ($payment assigned only here), :4053, :4055, :6790-6795 (typed signature `private function publicReceiptNotice(Payment $payment, ?string $process = null, string $status = 'success', ?int $partialId = null): array`), routes/web.php:995
- **Verify note:** CORRECTED (evidence lines only): invoice_id branch is at :4006 not :4020, and the payment_id branch opens at :4030 with the assignment at :4036, not :4038. :4053, :4055, :3998 and :6790 confirmed exactly. Also corrected the debug-page attribution — it is Laravel 11's built-in renderer, not Ignition (not installed). Both real initiation paths do append payment_id (PaymentController.php:3425-area and app/Support/PaymentGateway/MyFatoorah.php:115, verified verbatim: `"ErrorUrl" => route('payments.error', ['payment_id' => $payment->id])`), so this fires when MyFatoorah drops the param, when the payer edits/truncates the URL, or on any direct hit — the route is public (routes/web.php:995). CUSTOMER-FACING.

#### 4.6.13 WhatsApp hotel booking payer lands on raw JSON after paying

- **Status:** 200 (JSON body) | **Frequency:** rare
- **Trigger:** A DOTW/WhatsApp B2C customer completes (or cancels) a MyFatoorah payment for a hotel booking. MyFatoorah redirects the browser to the module's callback, which is registered as BOTH CallBackUrl and ErrorUrl.
- **Mechanism:** PaymentCallbackController::handleCallback() is documented as 'ALWAYS returns HTTP 200' for gateway-retry reasons and returns `response()->json([...], 200)` on every branch — success, failure, and internal error alike. It never distinguishes a gateway server-to-server call from the payer's own browser, and never renders a view or redirects.
- **User sees:** A blank white browser page containing literal JSON. Re-confirmed live: GET /api/dotwai/payment_callback returns `{"status":"ignored","reason":"no_payment_id"}` and ?error=1 returns `{"status":"payment_error_received"}`, both HTTP 200. A successful payer sees `{"status":"processing"}` — no receipt, no booking reference, no 'return to WhatsApp'.
- **Way back:** None. It is not an HTML document.
- **Evidence:** app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php:50 (handleCallback), :157 ('processing'), :185 ('payment_failed'), :194 ('error'/'internal_error'), :206-238 (handlePaymentError -> 'payment_error_received' at :238), app/Modules/DotwAI/Services/PaymentBridgeService.php:131 (CallBackUrl) and :132 (ErrorUrl, same URL + '?error=1'), app/Modules/DotwAI/Routes/api.php:39
- **Verify note:** VERIFIED and reproduced live. Corrected controller line numbers: handleCallback is at :50 (not :52); the JSON returns are at :157 ('processing'), :185 ('payment_failed'), :194 ('internal_error'); handlePaymentError spans :206-238 with its return at :238 (census said :151/:182/:195/:218-236). PaymentBridgeService.php:131-132 and Routes/api.php:39 confirmed exactly. Currently low-volume — the DotwAI WhatsApp stack has had essentially no real customer traffic — but this is the designed terminal screen for every B2C hotel payment the moment that channel goes live. CUSTOMER-FACING.

#### 4.6.14 Gateway unreachable or slow: no cURL timeout, then a null-dereference 500 or a hung request

- **Status:** 500, or 502/504 from the web server | **Frequency:** rare
- **Trigger:** Tap (or any gateway called through HttpRequestTrait) is down, TLS-failing, or slow while the customer is clicking Pay Now.
- **Mechanism:** HttpRequestTrait sets CURLOPT_TIMEOUT => 0 (no timeout) on every verb, logs curl_errno and returns `json_decode(false)` = null. Callers then index into null: PaymentController.php:3350 `$paymentUrl = $response['transaction']['url'];` yields null (PHP 8 warning, not fatal), and `return redirect(null)` returns an Illuminate\Routing\Redirector object which Router::toResponse wraps in `new Response($obj)` -> UnexpectedValueException ('The Response content must be a string or object implementing __toString()') -> 500. If the socket merely hangs, the request instead runs until PHP-FPM/nginx kill it.
- **User sees:** Either a stock 500 / Laravel 11 debug stack-trace page, or a very long spinner ending in the web server's own 504/502 page (not a Laravel page at all — plain nginx/Apache text). Nothing identifies it as a temporary gateway problem.
- **Way back:** None.
- **Evidence:** app/Http/Traits/HttpRequestTrait.php:22, :50, :80, :109 (CURLOPT_TIMEOUT => 0 on GET/POST/PUT/DELETE), :38 and :68 (`return json_decode($response, true)` after a possible false), app/Http/Controllers/PaymentController.php:3346-3348 (only isset($response['errors']) is checked), :3350-3351 (unguarded index + redirect), app/Support/PaymentGateway/Tap.php:114-122 (createCharge -> postRequest), :143 (getCharge -> getRequest)
- **Verify note:** VERIFIED. Corrected: the four CURLOPT_TIMEOUT => 0 lines are exactly :22/:50/:80/:109 as claimed; the json_decode returns are :38 and :68 (census said :36/:67); the caller lines are :3346-3348 and :3350-3351 (census said :3348-3351). Tap.php createCharge's postRequest call is :114-122 (census said :110-122). The inconsistent-coverage note is confirmed: PaymentController.php:3456-3459 and :3792-3795 both call `Http::withHeaders(...)->post("$baseUrl/ExecutePayment", …)` with no ->timeout() and no try/catch, while app/Support/PaymentGateway/Hesabe.php:163 sets ->timeout(60) and app/Support/PaymentGateway/MyFatoorah.php:221 sets ->timeout(20). CUSTOMER-FACING.

#### 4.6.15 Money captured but the payer is told the payment failed (Tap config lookup fails on return)

- **Status:** 302 -> 200 payment/failed | **Frequency:** rare
- **Trigger:** Tap captures the customer's card, redirects back to /payment/tap-callback, and our getCharge() lookup cannot resolve Tap credentials (charge row deactivated, config/services.tap missing).
- **Mechanism:** Tap::getCharge() returns `['status' => 'error', 'message' => …]` on config failure, but handleTapCallback only tests `isset($response['errors'])`. The error array falls through, `$response['metadata']['payment_id'] ?? null` is null, and the handler returns 'Payment reference missing.' to payment.failed.
- **User sees:** The generic 'Payment Failed!' page, after their card was actually charged.
- **Way back:** 'Return to Homepage' -> url('/') -> 302 -> /login (see the payment/failed finding).
- **Evidence:** app/Support/PaymentGateway/Tap.php:129-139 (getCharge early-returns ['status'=>'error','message'=>…]), app/Http/Controllers/PaymentController.php:4078 (handleTapCallback), :4092-4095 (checks isset($response['errors']) only), :4097-4102 (metadata payment_id missing -> payment.failed with 'Payment reference missing.')
- **Verify note:** VERIFIED. Corrected line numbers: Tap::getCharge's config-error early return is :129-139 (census said :126-148); the errors-only check is :4092-4095 (census said :4089-4092) and the missing-payment_id branch is :4097-4102 (census said :4097-4101). Mechanism and consequence confirmed verbatim. Worst-possible framing: a captured payment presented as a decline, with no reference number the customer could quote to support. CUSTOMER-FACING.

#### 4.6.16 multiPaymentLinkInitiate aborts 500 for a guest on a data-integrity problem

- **Status:** 500 | **Frequency:** rare
- **Trigger:** Customer on the multi-gateway payment page picks a method, and the payment's agent/branch has no resolvable company_id (orphaned agent or branch row).
- **Mechanism:** `return Auth::user() ? redirect()->back()->with('error', 'Company ID not found for the payment.') : abort(500);` — an explicit abort for the anonymous payer.
- **User sees:** Stock '500 \| SERVER ERROR' when APP_DEBUG=false, or Laravel 11's built-in debug stack-trace page on this server.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/PaymentController.php:7264 (multi path), :3370 (single-gateway path), :3748 (reinitiate path), routes/web.php:661
- **Verify note:** VERIFIED exactly — all three abort(500) lines (:7264, :3370, :3748) and routes/web.php:661 confirmed by grep and by reading the surrounding code. Distinct from a crash — the code deliberately chooses a bare 500 for guests where staff get a readable flash. CUSTOMER-FACING.

#### 4.6.17 Hesabe checkout token missing: customer is redirected to a malformed gateway URL

- **Status:** 302 to gateway (gateway-side error) | **Frequency:** rare
- **Trigger:** Hesabe's /checkout replies with a shape that decrypts but lacks response.data (API change, partial outage, wrong merchant/access code).
- **Mechanism:** `$responseToken = $responseData['response']['data'];` is an unguarded index (PHP 8 'Undefined array key' warning -> null). `$paymentUrl = $baseUrl . '/payment' . '?data=' . $responseToken;` is then still a non-empty string, so the `if ($paymentUrl)` guard passes, the row is saved as status='initiate', and the customer is redirected to `https://…/payment?data=`.
- **User sees:** Hesabe's own error page on hesabe.com — a third-party screen the agency does not control and cannot brand or explain. Meanwhile our DB says the link was successfully initiated.
- **Way back:** Whatever Hesabe's error page offers; nothing brings them back to the voucher.
- **Evidence:** app/Http/Controllers/PaymentController.php:3588-3589 (unguarded token index + URL concat), :3591-3602 (truthy `if ($paymentUrl)` guard + save as 'initiate' + redirect), :3603-3608 (the unreachable else branch), and the identical pattern at :1460-1462 in the invoice flow
- **Verify note:** VERIFIED. Corrected line numbers: the token index and URL concat are :3588-3589 (census said :3588-3590); the truthy guard + save + redirect is :3591-3602 (census said :3592-3607); the dead else branch is :3603-3608 with its message at :3608 (census said :3609). The invoice-flow twin at :1460-1462 is exact. The dead-code claim holds: $paymentUrl is a non-empty string by construction, so the else can never execute. CUSTOMER-FACING.

#### 4.6.18 UPayment cancel/decline returns the generic failure page with no reason at all

- **Status:** 302 -> 200 payment/failed | **Frequency:** rare
- **Trigger:** Customer cancels or is declined on UPayments and is redirected to /payment/uPayment-error with a track_id our system cannot resolve to a Payment row.
- **Mechanism:** handleUPaymentError() writes a 'cancelled' UpaymentPayment row, and when `$payment` is null ends with a bare `return redirect()->route('payment.failed');` — no `->with('error', …)` at all, so even the discarded reason string is absent.
- **User sees:** The generic 'Payment Failed!' page.
- **Way back:** 'Return to Homepage' -> url('/') -> 302 -> /login.
- **Evidence:** app/Http/Controllers/PaymentController.php:5621 (method), :5633 ($payment lookup by payment_reference against track_id), :5635-5646 (UpaymentPayment::create with status 'cancelled'), :5669 (bare redirect, no flash), routes/web.php:666
- **Verify note:** VERIFIED. Only correction: the bare redirect is at :5669, not :5670. :5621 and :5633 exact; routes/web.php:666 exact. Both DB claims re-confirmed: exactly 1 UPayment row exists (status pending), and charges.id=5 is the UPayment row for company_id=2 (is_active=1) — the only company it is configured for. CUSTOMER-FACING but low volume.

#### 4.6.19 MyFatoorah vendor demo controller returns raw JSON / an unstyled error card

- **Status:** 200 (JSON) / 200 (error view) / 302 to dashboard | **Frequency:** theoretical
- **Trigger:** Reaching /myfatoorah/pay-now, /checkout or /callback — the unmodified MyFatoorah Laravel sample code left in the app.
- **Mechanism:** index() catches Exception and returns `response()->json(['IsSuccess' => 'false', 'Message' => $exMessage])` where $exMessage is `__('myfatoorah.' . $ex->getMessage())` — an untranslated lang key when the message is not a known code. checkout() returns view('myfatoorah.error') with that same string. callback() redirects failures to route('dashboard') with a flash.
- **User sees:** Either raw JSON `{"IsSuccess":"false","Message":"myfatoorah.Invoice not found."}` — note the leaked translation prefix — or a bare page with a red line of text and nothing else.
- **Way back:** None.
- **Evidence:** app/Http/Controllers/MyFatoorahController.php:44 (index(), body :47-62, JSON catch at :59-62), :122-142 (callback(), dashboard redirects at :139 and :141), :164-200 (checkout(), error-view catch at :197-199), :269 (getTestOrderData) and :273 (`throw new \Exception("Invoice not found.")`), resources/views/myfatoorah/error.blade.php (14 lines: a <title>, a stylesheet link, one div of red text, nothing else), routes/web.php:416-418
- **Verify note:** VERIFIED as NOT customer-facing and probably dead code. Re-confirmed live: GET /myfatoorah/pay-now, GET /checkout and GET /callback all 302 -> /login (all three sit inside the auth group with no exemption). Corrections: callback() is at :122-142 (census said :117-141); getTestOrderData is at :269 and throws 'Invoice not found.' at :273 (census said :279-288); and the hardcoded default order id of 147 lives in index() at :52 and checkout() at :167 — NOT in getPayLoadData() (:75) as the census stated. Candidate for deletion rather than redesign.

---

### 4.7 Document & AI processing  (21 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Upload succeeds, processing fails, nobody is ever told | 302 redirect + flash (success); failure is n/a (log + folder only) | dead-common | none — there is no uploads/history scree |
| 2 | AI provider misconfigured → AI entry points die before any handler runs | 500 (injected consumers) / 400 JSON (passport endpoints) | dead-common | none |
| 3 | Every uncaught pipeline exception renders a full debug page — on production | - | dead-common | none on any of them — neither page conta |
| 4 | Email-ingested booking that matches no supplier parser vanishes | n/a (silent — log + folder only) | dead-common | none |
| 5 | Amadeus AIR ticket held by the unregistered-agent gate | - | dead-common | The logs page has one "← Back" button wi |
| 6 | WhatsApp document ingest — the one channel that closes the loop | n/a (WhatsApp reply) | dead-common | The conversation itself is the way back  |
| 7 | Dev server processes nothing at all | n/a (silent) | dead-common | none |
| 8 | Wrong file type on the dropzone → raw Laravel validation string | - | occasional | The banner has an × to dismiss. The page |
| 9 | Duplicate-file rejection names another employee | - | occasional | × to dismiss only. No link to the existi |
| 10 | Merge-supplier batch PDF merge failure | - | occasional | × only; the batch UI is gone after the r |
| 11 | Passport OCR failure → native browser alert() with the raw provider error | 400 (JSON) → alert() | occasional | OK dismisses the alert; the modal and th |
| 12 | Passport OCR hangs the button for minutes, then dies | - | occasional | OK on the alert; the button re-enables i |
| 13 | AIR ingest API rejections to the desktop uploader | 401 / 400 / 404 / 500 (JSON) | occasional | none in the web app |
| 14 | WhatsApp passport-photo flow when the AI is down | 400 / 422 / 500 (JSON to webhook), message to user | occasional | Resend in the same chat; admins are page |
| 15 | Dashboard AI status card fails with a message that says the wrong thing | - | occasional | A "Check now" button re-runs probes live |
| 16 | Dropping more than 20 files silently discards the extras | 302 redirect + flash (success) | occasional | none — there is no error and nothing to  |
| 17 | AI chat and chat file upload on the unlinked /open-ai page | 500 (chat prompt on dev), or 400/200-with-error (upload) | rare | none beyond retyping in the chat box; th |
| 18 | GET /tasks/voucher — dead route, 500 with a debug page | 500 | rare | none |
| 19 | Oversized upload batch → 419 Page Expired, everything lost | 419 | rare | none — no link on the page. Browser Back |
| 20 | Deleted uploader account → 500 mid-upload | 500 | theoretical | none — browser Back only, which re-posts |
| 21 | Duplicate POST /tasks/upload registration hijacks the task-upload form | 400 (JSON rendered as a page) | theoretical | none — no chrome, no link; browser Back  |

#### 4.7.1 Upload succeeds, processing fails, nobody is ever told

- **Status:** 302 redirect + flash (success); failure is n/a (log + folder only) | **Frequency:** dead-common
- **Trigger:** An agent opens Tasks → "Add Task For Specific Supplier", picks a supplier, drops a booking PDF, submits. They get a green "uploaded successfully" banner. The file is actually parsed minutes later by a cron; if it fails it is moved to a files_error folder and only written to a log.
- **Mechanism:** supplierTaskForAgent → TaskController@upload moves the file to storage/app/{company}/{supplier}/files_unprocessed and creates a file_uploads row with status='pending', then flashes success. app:process-files later calls handleFileError()/moveFileWithLogging() to shunt the file into files_error and logs it. No error status is ever written back. Two distinct dead ends: (a) files that fail EARLY (no parser matched, AI tool error, exception during processing — ProcessAirFiles.php:710/:750/:759/:823) never reach processTaskData, so the row stays 'pending' forever; (b) files that reach processTaskData get FileUpload::where('file_name',…)->update(['status'=>'completed']) from a `finally` block, so the row reads completed even when the task threw. Either way no view renders file_uploads.
- **User sees:** A green banner: "Files uploaded successfully: BOOKING123.pdf". Then nothing, forever. No status column, no notification, no email, no badge. The task simply never appears in the Tasks list and the agent has no way to find out why.
- **Way back:** none — there is no uploads/history screen to return to and no record the user can see
- **Evidence:** app/Http/Controllers/TaskController.php:3210 (upload), :3489-3502 (create, status='pending'), :3530-3535 (success flash); app/Console/Commands/ProcessAirFiles.php:473, :710, :750, :759, :823, :830 (processTaskData), :1083-1088 (finally → 'completed'), :1161-1171 (handleFileError); grep for FileUpload/file_uploads across resources/views returns zero hits
- **Verify note:** VERIFIED. Prod counts re-measured and exact: 1,502 files in */files_error vs 6,097 in */files_processed (~20% failure), plus 104 stuck in files_unprocessed. Prod DB corroborates that no error state exists: file_uploads has ONLY status 'pending' (21) and 'completed' (1,186) across 1,207 rows. Corrected line numbers (create is :3489, not :3484) and split the 'finally' claim — it only fires for late-stage failures; early failures leave the row 'pending'.

#### 4.7.2 AI provider misconfigured → AI entry points die before any handler runs

- **Status:** 500 (injected consumers) / 400 JSON (passport endpoints) | **Frequency:** dead-common
- **Trigger:** An AI credential is missing or rotated out. This is the live state of the dev server today: .env has no RESAYIL_BASE / RESAYIL_API_KEY (only the unrelated WhatsApp RESAYIL_BASE_URL/RESAYIL_API_TOKEN), while config/ai.php defaults ai.primary to 'resayil'.
- **Mechanism:** ResayilClient::__construct throws \Exception('Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).') whenever resayil is primary/fallback/default. Two different outcomes: (1) consumers that take AIManager by CONSTRUCTOR INJECTION — ChatController and ProcessAirFiles — throw during container resolution, before any controller body or try/catch, so the request 500s and every artisan command aborts; (2) consumers that do `new AIManager()` INSIDE a try — TaskController@clientPassport and ChatController@handleFileUpload — catch it and return 400 JSON, so the user gets the config string in a browser alert instead.
- **User sees:** Laravel 11's debug exception page with "Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).", the ResayilClient.php:55 code frame, the AIManager.php:31 → :46 frames, absolute server paths and Request/Application panels. On the CLI every artisan command aborts with the same message, so the whole document pipeline is dead with no signal in the UI.
- **Way back:** none
- **Evidence:** app/AI/Services/ResayilClient.php:53-56; app/AI/AIManager.php:19-22, :24-34; app/Http/Controllers/ChatController.php:42-46 (ctor injection), :930 (new AIManager() inside try); app/Console/Commands/ProcessAirFiles.php:46-54; app/Http/Controllers/TaskController.php:4894; config/ai.php:3-7; bootstrap/app.php:45-47 (withExceptions empty); dev .env has zero RESAYIL_BASE/RESAYIL_API_KEY, prod .env:251-252 has both
- **Verify note:** CORRECTED and live-verified twice on dev: `artisan route:list` aborts with exactly this exception, and `curl -X POST https://development.citycommerce.group/api/chat/upload` returns HTTP 500 rendering the debug page containing the message. The original claim that EVERY AI entry point 500s is wrong — the two passport endpoints instantiate AIManager inside a try and degrade to a 400 JSON alert. Prod .env does carry the key, so on prod this is one key rotation away rather than current.

#### 4.7.3 Every uncaught pipeline exception renders a full debug page — on production

- **Status:** - | **Frequency:** dead-common
- **Trigger:** Any unhandled error anywhere in document/AI processing: a parser throwing, a DB constraint, a null deref, a missing controller method.
- **Mechanism:** bootstrap/app.php ->withExceptions() is empty and resources/views/errors/ does not exist. APP_DEBUG=true in BOTH .env files, so FoundationServiceProvider::registerExceptionRenderer binds Illuminate\Foundation\Exceptions\Renderer\Renderer, and Handler::renderExceptionContent picks it ahead of the Symfony fallback. HttpExceptions (403/404/419/500) instead hit Laravel's stock vendor error views.
- **User sees:** For real exceptions: Laravel 11's own debug page — Figtree/Tailwind, light+dark aware, Laravel logo favicon, browser tab titled "City Tour", the exception class and message as a large heading, absolute /home/citycomm/… file paths, a syntax-highlighted source excerpt with the throwing line shaded red, a scrolling frame list, and Request / Application panels showing the URL, method and app state. For abort()s: Laravel's stock minimal page — a thin centred row reading "404 \| NOT FOUND" (or 403 \| FORBIDDEN, 419 \| PAGE EXPIRED), grey on white, no logo, no nav, no link.
- **Way back:** none on any of them — neither page contains a single link
- **Evidence:** bootstrap/app.php:45-47; resources/views/errors/ does not exist; APP_DEBUG=true at line 4 of both /home/citycomm/development.citycommerce.group/.env and /home/citycomm/tour.citycommerce.group/.env; vendor/laravel/framework/src/Illuminate/Foundation/Providers/FoundationServiceProvider.php:252-277; vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:841-855; vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/ (401,402,403,404,419,429,500,503)
- **Verify note:** CORRECTED — the mechanism was misidentified. It is NOT the Symfony HtmlErrorRenderer page. filp/whoops IS vendored (vendor/filp/whoops) but is never bound; Laravel 11's built-in Renderer wins whenever app.debug is true. Confirmed live on dev: POST /api/chat/upload → HTTP 500 with hljs code frames, per-frame red line highlighting and a "City Tour" title; GET /tasks/pdf/flight/99999999 → HTTP 404 with the stock "404 / Not Found" minimal page. Everything else in the finding holds.

#### 4.7.4 Email-ingested booking that matches no supplier parser vanishes

- **Status:** n/a (silent — log + folder only) | **Frequency:** dead-common
- **Trigger:** An agent forwards a supplier confirmation to their per-agent ingest mailbox (the documented way to load a booking without touching the app). The sender/PDF doesn't map to a known supplier, or it is a body-only email from an unrecognised sender.
- **Mechanism:** InboundMailIngestService::handle() routes an unmatched PDF to the 'unrouted' supplier slug (EmailIngest.status='unrouted') and drops it into an unrouted/files_unprocessed folder. A body-only email with no recognised supplier is skipped with a bare Log::info and nothing is persisted at all. Every other outcome writes an EmailIngest row — a table with no controller, no route and no view anywhere in the codebase.
- **User sees:** Absolutely nothing. No bounce, no auto-reply, no page, no row anywhere the agent can look. They assume the booking loaded.
- **Way back:** none
- **Evidence:** app/Services/InboundMailIngestService.php:152-186 (unrouted drop + EmailIngest::create with status 'unrouted'), :232-235 (body-only, unknown supplier → Log::info only, nothing persisted); config/mail_ingest.php:151 ('unrouted_slug' => 'unrouted'); grep for EmailIngest across app/Http, routes/ and resources/views returns nothing
- **Verify note:** VERIFIED exactly, including the count: 98 files currently sitting in /home/citycomm/tour.citycommerce.group/storage/app/city_travelers/unrouted/files_unprocessed on production.

#### 4.7.5 Amadeus AIR ticket held by the unregistered-agent gate

- **Status:** - | **Frequency:** dead-common
- **Trigger:** An agent issues or reissues a ticket in Amadeus; the desktop uploader ships the .AIR file automatically. If the acting agent is not registered in the system, or the reissue/refund/void has no original ticket loaded, the file is withheld.
- **Mechanism:** ProcessAirFiles::processTaskData returns success=false with reason 'unregistered_agent' or 'orphan_modification' and prints an UNREGISTERED_AGENT_REJECT marker; AirIngestController greps that marker, writes <file>.error.json, moves the file to /home/citycomm/AIR/NOT LOADED/unregistered_agent, and answers the uploader .exe with JSON {success:false, reject_reason:'unregistered_agent'}.
- **User sees:** Nothing in the web app. The only surface is an admin-only page at /air/uploader/logs — a hand-rolled HTML string with Bootstrap pulled from a CDN, no application chrome, no sidebar: PCC summary chips plus a table of filename / office / held-or-error badge / reason / timestamp, capped at 300 rows. Non-admins get a stock 403.
- **Way back:** The logs page has one "← Back" button wired to history.back(); it offers no action on any listed file (no retry, no reassign, no download).
- **Evidence:** app/Console/Commands/ProcessAirFiles.php:1002-1035; app/Http/Controllers/AirIngestController.php:118-135 (marker grep + .error.json), :146-160 (JSON response); routes/web.php:1146-1210 (the only viewer)
- **Verify note:** VERIFIED, counts re-measured and exact: 1,842 "AIR agent gate" holds in file_processing-2026-08-25.log (454 more by midday 26th); 1,160 held .AIR files in NOT LOADED/unregistered_agent and 345 errored .AIR at the NOT LOADED root. Route line range corrected to :1146-1210. Note the code comment at AirIngestController.php:121 claims the marker is "dev1 only; on prod $isUnregistered is false" — the 1,160 held files prove that comment is stale.

#### 4.7.6 WhatsApp document ingest — the one channel that closes the loop

- **Status:** n/a (WhatsApp reply) | **Frequency:** dead-common
- **Trigger:** An agent forwards a supplier PDF to the WhatsApp bot.
- **Mechanism:** WhatsappPdfIngestService::handleDocument replies through ResayilController for every outcome, and DispatchWhatsappResults sends a follow-up once the cron has (or hasn't) produced a task within a grace window.
- **User sees:** Plain-language WhatsApp messages: "📥 Received, reading it now…", "Sorry — I could not download that file. Please send it again.", "📸 That looks like a passport. Please send it as a *photo* (not a PDF) so I can create the client.", "Couldn't read this one — I've saved it for the team to check.", "Couldn't load that PDF — I've saved it for the team to check.", "ℹ️ That booking is already loaded as task #123.", "Got <supplier> PNR <ref> for <pax> — I couldn't read the price. Reply with the amount in KWD.", "✅ Loaded as task #123 (Jazeera), assigned to you."
- **Way back:** The conversation itself is the way back — the agent can resend, or answer a follow-up question (e.g. a missing price).
- **Evidence:** app/Services/WhatsappPdfIngestService.php:49-54 (download failure), :90-93 (received), :113-116 (passport), :136-140 (review); app/Console/Commands/DispatchWhatsappResults.php:57 (missing price), :73 (loaded), :101 (duplicate), :106-109 (no task after grace)
- **Verify note:** VERIFIED. Both gaps confirmed: (a) EVERY reply in both files is wrapped in `if ($agent)` / `if ($row->agent)`, so a PDF from an unregistered number — e.g. dropped in a supplier group — fails in total silence; 20 such PDFs are parked in city_travelers/wa_review on prod. (b) whatsapp_ingests has no controller, route or view (grep across app/Http, routes/, resources/views returns nothing), so "saved for the team to check" points at a queue nobody can open. Added two more real messages (duplicate-booking and missing-price) that the original finding omitted.

#### 4.7.7 Dev server processes nothing at all

- **Status:** n/a (silent) | **Frequency:** dead-common
- **Trigger:** Anyone testing or demoing document upload on development.citycommerce.group.
- **Mechanism:** `app:process-files` has crontab lines only for tour.citycommerce.group (flock-guarded, every minute) and staging.citycommerce.group (every 2 minutes). The citycomm crontab has exactly two entries for development.citycommerce.group, both dotwai catalog syncs. Dev storage/app has never had a {company}/{supplier} tree created. Separately, the missing RESAYIL_* keys mean the command could not boot even if it were scheduled.
- **User sees:** The same green "Files uploaded successfully" banner as production, followed by a task that never appears — indistinguishable from a real parse failure.
- **Way back:** none
- **Evidence:** `crontab -u citycomm -l` — the two dev lines are `dotwai:sync-static` (Sun 08:00) and `akeed-dotwai:sync-hotels --all` (Sun 08:30); the process-files lines reference tour.* and staging.* only; `ls /home/citycomm/development.citycommerce.group/storage/app/` returns bulk-uploads, final_check.php, public; dev .env has no RESAYIL_BASE/RESAYIL_API_KEY; app/AI/Services/ResayilClient.php:53-56
- **Verify note:** VERIFIED exactly (dev storage/app also contains a stray final_check.php). Matters for design review: any UX validation done on dev will show the success path and never the failure path, because the failure path never executes there.

#### 4.7.8 Wrong file type on the dropzone → raw Laravel validation string

- **Status:** - | **Frequency:** occasional
- **Trigger:** Agent drags a screenshot (.jpg), a .docx voucher or an .eml into the supplier-upload dropzone. The dropzone accepts anything — the input is built in JS with no accept attribute and no client-side filter.
- **Mechanism:** Server-side rules 'task_file.*' => 'mimes:pdf,txt' (supplierTaskForAgent at :4360 and upload at :3235) raise ValidationException → 302 back with the error bag → layouts/alert.blade.php:1-11 renders $errors->all() verbatim. No lang/en/validation.php override exists (lang/en/ contains only myfatoorah.php).
- **User sees:** A red fixed banner pinned top-right reading "The task file.0 field must be a file of type: pdf, txt." — the array index is exposed, the offending filename is not. If several files were dropped, one banner per file, all rendered at the same fixed top-1 right-4 position so they overlap and only the last is legible.
- **Way back:** The banner has an × to dismiss. The page has fully reloaded, so the Add Task modal is closed, the supplier selection is lost and every attached file is gone — the agent restarts from scratch.
- **Evidence:** resources/views/tasks/index.blade.php:2828 (input created, no accept), :2926-2934 (hidden task_file[] input appended to the form), :69-71 (the form: POST tasks.agent.upload, @csrf, multipart); app/Http/Controllers/TaskController.php:4360, :3235; resources/views/layouts/alert.blade.php:1-11
- **Verify note:** VERIFIED (line numbers corrected: input is :2828; the owning form is :69-71). Frequency lowered from dead-common to occasional: validation rejections are not logged anywhere so this is unmeasurable, but the surface is structurally wide open (no accept attribute at all).

#### 4.7.9 Duplicate-file rejection names another employee

- **Status:** - | **Frequency:** occasional
- **Trigger:** Two agents (or the same agent twice) upload the same supplier PDF — common with shared supplier mailboxes and generic filenames like voucher.aspx…pdf / factura.aspx.pdf.
- **Mechanism:** Two separate code paths build the same messages. Non-merge suppliers: TaskController@upload looks up an existing FileUpload on (file_name, supplier_id, company_id) and pushes a per-file message into $errorFilesWithMessage → flash 'error' + 'data'. Merge suppliers: the batch path matches on file_name OR source_files JSON and builds the identical strings. Both render through the alert partial.
- **User sees:** Red banner "Some files failed to upload." with sub-lines: "File has already been uploaded by you", or "File has been uploaded by another user : <real employee name>. Please contact them to resolve this issue.", or "File has been uploaded by your admin. Please contact them to resolve this issue."
- **Way back:** × to dismiss only. No link to the existing task/file, no "view it", no way to contact the named person from the message.
- **Evidence:** app/Http/Controllers/TaskController.php:3447-3481 (upload path), :3291-3312 (merge-batch path), :3511-3527 (flash assembly); resources/views/layouts/alert.blade.php:42-68
- **Verify note:** CORRECTED. Both code paths confirmed (the second citation was :3300-3312; the block actually runs :3291-3312). Frequency lowered from dead-common to occasional on measurement: 53 "already exists for supplier" rejections across ~10 months of retained prod laravel logs (Oct 2025 → Aug 2026), i.e. roughly 5/month, against 1,207 total upload rows from 11 distinct uploaders. The colleague-name leak is real.

#### 4.7.10 Merge-supplier batch PDF merge failure

- **Status:** - | **Frequency:** occasional
- **Trigger:** An agent uses the multi-batch upload for a merge supplier (TBO Air, TBO Car, or any supplier with has_hotel=1 except Amadeus — i.e. every hotel supplier) and one of the PDFs in a batch is corrupt, encrypted or not really a PDF.
- **Mechanism:** Per-file: Merger::addFile throws → collected into $failedFiles → flash 'error' "Batch N failed. Failed files: a.pdf, b.pdf". Whole-branch: a \Throwable is caught and returns 'Failed to merge TBO PDFs.' with the raw exception string pushed into the 'data' array, which the alert partial prints as-is.
- **User sees:** Red banner "Failed to merge TBO PDFs." followed by a raw library exception line (e.g. an FPDI compression/parser message). Or "Batch 2 failed. Failed files: voucher.pdf".
- **Way back:** × only; the batch UI is gone after the reload, all batches must be re-assembled
- **Evidence:** app/Http/Controllers/TaskController.php:3330-3345 (per-file addFile try/catch + "Batch N failed"), :3423-3433 (Throwable catch → 'Failed to merge TBO PDFs.' + $e->getMessage() in data); app/Models/Supplier.php:101-112 (isMergeSupplier); resources/views/layouts/alert.blade.php:48-64
- **Verify note:** CORRECTED — line numbers (:3330-3345 and :3423-3433, not :3327-3341/:3424-3434) and the supplier list: isMergeSupplier() is TBO Air, TBO Car, or any has_hotel=1 supplier that isn't Amadeus — the message says "TBO" for all of them, which is itself misleading copy.

#### 4.7.11 Passport OCR failure → native browser alert() with the raw provider error

- **Status:** 400 (JSON) → alert() | **Frequency:** occasional
- **Trigger:** Anyone using the "Upload passport" widget — creating a task, creating a user, creating a client from a payment link, or from task detail — presses "Process File" and the AI layer fails (provider down, out of quota, unreadable image, unsupported type, or AI config missing).
- **Mechanism:** fetch POST to tasks.upload.passport → TaskController@clientPassport catches and returns HTTP 400 JSON {success:false,message:…}. The JS branches on data.success and calls alert('Error processing file: ' + data.message). The message is the AI layer's string, passed through verbatim.
- **User sees:** A native, unstyled browser alert box titled with the site domain, e.g. "Error processing file: Failed to extract passport data using AI: Resayil passport extraction failed: Resayil HTTP 429: {\"error\":…}", or "…Resayil passport: unparseable or missing first_name", or "…Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).", or "…Unsupported file type. Only JPG, JPEG, PNG, and PDF files are supported." On a network/HTML-500 response the .catch fires instead: "An error occurred while processing the file."
- **Way back:** OK dismisses the alert; the modal and the chosen file survive and the button re-enables, so retry is possible — but the message gives no hint whether retrying will help.
- **Evidence:** resources/views/tasks/index.blade.php:3110-3165 (alert at :3153, catch at :3157-3160); resources/views/users/create.blade.php:968; resources/views/payment/link/create.blade.php:1084; resources/views/tasks/detail.blade.php:2598; app/Http/Controllers/TaskController.php:4879-4933 (new AIManager() at :4894, 400 JSON at :4909-4914 and :4919-4923); app/AI/Services/FallbackAIClient.php:45-68; app/AI/Services/ResayilClient.php:55, :76, :177, :181, :227, :232, :239-241; app/AI/Services/OpenAIClient.php:107, :134
- **Verify note:** CORRECTED — the headline example was wrong. FallbackAIClient::attempt seeds $last = AIResponse::error("All AI providers failed for {method}") but immediately overwrites it with each hop's real failure, so that string only surfaces if the chain array is empty. Real users get the LAST hop's provider error (Resayil HTTP nnn from ResayilClient.php:76, wrapped at :241) or the missing-config exception. All four call sites and both JSON branches confirmed.

#### 4.7.12 Passport OCR hangs the button for minutes, then dies

- **Status:** - | **Frequency:** occasional
- **Trigger:** Passport upload while the AI gateway is slow (the code's own note calls 40-121s tail latency normal).
- **Mechanism:** config('ai.chain') is 3 Resayil hops (the OpenAI hop is disabled by default); FallbackAIClient gives hop 0 1+retries(2)=3 attempts and hops 1-2 one each = 5 attempts, each with a 120s HTTP timeout, ≈600s worst case. The browser fetch sets no timeout and offers no cancel. Web max_execution_time is 600 and LiteSpeed usually cuts the connection first, returning HTML, so response.json() throws.
- **User sees:** The button reads "Processing...", greyed and disabled, for up to several minutes with no progress, no elapsed time and no cancel. Then a bare alert: "An error occurred while processing the file."
- **Way back:** OK on the alert; the button re-enables in the .finally
- **Evidence:** app/AI/Services/FallbackAIClient.php:45-68 (tries = index===0 ? 1+retries : 1); config/ai.php:6 (retries=2), :39-46 (chain), :80 (timeout=120); app/Services/AiHealthCheck.php:15 ("models routinely show 40-121s tail latency"); /opt/cpanel/ea-php82/root/etc/php.ini max_execution_time=600; resources/views/tasks/index.blade.php:3110-3116, :3157-3165
- **Verify note:** VERIFIED. Arithmetic re-checked against the code (5 attempts x 120s). php.ini values read directly. One nuance worth flagging for design: config/ai.php:33-37 says a Settings > AI Configuration DB row (aicfg.chain) can REPLACE the chain wholesale, so the hop count is not fixed by the file.

#### 4.7.13 AIR ingest API rejections to the desktop uploader

- **Status:** 401 / 400 / 404 / 500 (JSON) | **Frequency:** occasional
- **Trigger:** The City Travelers.exe uploader posts a filename to /api/process-air-file with a wrong/absent bearer secret, an odd filename, or after the FTP leg failed.
- **Mechanism:** AirIngestController self-auths and returns bare JSON: 401 {"error":"unauthorized"}; 400 {"error":"invalid_filename","filename":…}; 404 {"error":"file_not_found_in_uploaded","path":"/home/citycomm/…"}; 500 {"success":false,"error":"missing_company_or_supplier",…} or "staging_move_failed" with absolute from/to paths.
- **User sees:** Whatever the desktop .exe chooses to render — nothing in the web product. The 404/500 payloads echo absolute server filesystem paths back to the client.
- **Way back:** none in the web app
- **Evidence:** app/Http/Controllers/AirIngestController.php:37-41 (401), :43-47 (400), :49-52 (404), :55-63 (500 missing_company_or_supplier), :77-85 (500 staging_move_failed); routes/api.php:194-195
- **Verify note:** VERIFIED (line numbers shifted by ~1; routes/api.php:194 is /process-air-file, :195 /air-heartbeat).

#### 4.7.14 WhatsApp passport-photo flow when the AI is down

- **Status:** 400 / 422 / 500 (JSON to webhook), message to user | **Frequency:** occasional
- **Trigger:** An agent sends a passport photo to the WhatsApp bot while the AI gateway is unreachable, returning non-2xx, or returning unreadable output.
- **Mechanism:** IncomingMediaController branches on the extraction response, calls alertAdminsAiDown() (Cache-throttled WhatsApp alert to the agents whose emails are in config('ai.alert_agent_emails')) and sends the agent a plain message; returns 400/422/500 JSON to the Resayil webhook.
- **User sees:** "Sorry, I couldn't read the name clearly from the document. Please resend a clearer photo." / "Sorry, Civil ID is required for Kuwait nationals. Please resend with Civil ID." / "Sorry, I couldn't read the information from the document. Please ensure the document is clear and try again." / "Sorry — I couldn't read your document because the AI reading service appears to be down. I've informed the admin to take action; they will advise you when to try again." / "Sorry, there was an unexpected error processing your request…" / "The uploaded file could not be found. Please try uploading again."
- **Way back:** Resend in the same chat; admins are paged automatically.
- **Evidence:** app/Http/Controllers/IncomingMediaController.php:428 (name unreadable, 400), :454 (Civil ID required, 422), :659 (client creation failed, 500), :668 (AI extraction failed, 400), :679 (AI service down, 500 + alert), :691 (unexpected error + alert), :701 (file not found), :733-748 (alertAdminsAiDown, throttled); config/ai.php:91-92
- **Verify note:** VERIFIED. This is the best-designed error copy in the surface area and the only place that both tells the user AND escalates. Added three further real messages the original finding missed (Civil ID, client-creation failure, file-not-found).

#### 4.7.15 Dashboard AI status card fails with a message that says the wrong thing

- **Status:** - | **Frequency:** occasional
- **Trigger:** An admin/company/accountant loads the dashboard to check whether the AI extraction models are alive.
- **Mechanism:** Alpine component fetches /ai-health-status; DashboardController::aiHealthStatus abort(403)s anyone outside ADMIN/COMPANY/ACCOUNTANT and otherwise returns cached AiHealthCheck probes (ok / degraded / down / disabled / stale). On a 403 the JSON has no `success`, so the else branch clears probes; on a 500 the HTML breaks r.json() and the .catch does nothing but console.error, leaving probes empty. Both land on the same x-show fallback.
- **User sees:** When it works: three coloured tiles (passport / text / openai) with model name, seconds, state word and message, plus an amber "Status unknown — this result is older than 15 minutes" strip when stale. When the endpoint 403s or 500s: the card header stays and the body collapses to one line of grey 12px text, "No health check has run yet." — which reads as "nothing to report" when the truth is "we could not check".
- **Way back:** A "Check now" button re-runs probes live (admin/company only).
- **Evidence:** app/Http/Controllers/DashboardController.php:28-42; resources/views/dashboard.blade.php:40 (role gate), :56 (the empty-state line), :110-125 (load(), else branch at :117-120, catch at :121-123); app/Services/AiHealthCheck.php:25-40 (STATUS_TTL 30m, STALE_AFTER 15m)
- **Verify note:** CORRECTED — the original said the card renders "zero tiles and no explanation, an empty box". There is an explicit empty state at dashboard.blade.php:56; the defect is that it is the WRONG message for a failed fetch, not that the message is absent. The whole card is also role-gated in the blade at :40, so non-permitted users never see it and the 403 path only fires on a stale/role-changed session.

#### 4.7.16 Dropping more than 20 files silently discards the extras

- **Status:** 302 redirect + flash (success) | **Frequency:** occasional
- **Trigger:** An agent drags a folder of supplier PDFs — say 35 vouchers — onto the Add Task dropzone and submits.
- **Mechanism:** PHP's max_file_uploads is 20. Since PHP 5.3.9 the extras beyond that limit are silently discarded from $_FILES with no warning and no error, so Laravel validates and stores only the first 20. The dropzone JS imposes no count limit of its own (it just pushes into customFiles and rebuilds a DataTransfer), and the success flash lists only the names that actually arrived.
- **User sees:** A green banner "Files uploaded successfully: …" listing 20 filenames. Nothing anywhere says 15 files were dropped on the floor, and the agent would have to count the names to notice.
- **Way back:** none — there is no error and nothing to go back to
- **Evidence:** /opt/cpanel/ea-php82/root/etc/php.ini: max_file_uploads = 20; resources/views/tasks/index.blade.php:2828-2934 (dropzone builds task_file[] from customFiles with no length check anywhere in the block); app/Http/Controllers/TaskController.php:3530-3535 (success message is built from $successFiles, i.e. only what arrived)
- **Verify note:** ADDED IN VERIFY. Tripped over while confirming the 419/post_max_size finding. This is silent partial data loss reported as complete success — arguably worse than the 419, because the 419 at least stops the agent.

#### 4.7.17 AI chat and chat file upload on the unlinked /open-ai page

- **Status:** 500 (chat prompt on dev), or 400/200-with-error (upload) | **Frequency:** rare
- **Trigger:** Someone navigates directly to /open-ai (the page is not linked from any menu) and chats or uploads a passport there.
- **Mechanism:** GET /open-ai → OpenAiController@index renders ai/openai/index.blade.php, whose only content is <livewire:chat />. The chat prompt POSTs to chat.process (ChatController), which takes AIManager by constructor injection — so a bad AI config 500s that POST before the body runs, and the JS turns any non-ok response into a chat bubble. The passport upload in the same page POSTs to chat.handleFileUpload, which instantiates AIManager inside a try and returns 400 JSON on failure, rendered as inline text.
- **User sees:** For the prompt: a chat bubble reading "Error: HTTP error! status: 500" (or "Error: <server-supplied error string>"), or "No response from chatbot. Please try again." For the passport upload: inline text "Upload failed: <raw provider message>" or "Error uploading file. Please try again."
- **Way back:** none beyond retyping in the chat box; the page has app chrome so the sidebar is still there
- **Evidence:** resources/views/ai/openai/index.blade.php:48 (the only mount of <livewire:chat />); app/Http/Controllers/ChatController.php:42-46 (ctor injection), :914-969 (handleFileUpload, 400 JSON); resources/views/livewire/chat.blade.php:665-723 (chat.process fetch, throw at :681, "Error: " + response.error at :688, catch at :720-722), :1512-1565 (upload fetch, status text at :1553-1555, catch at :1561-1564); routes/web.php:399-413
- **Verify note:** CORRECTED. The original attributed the worst behaviour — raw upstream OpenAI JSON including an API-key prefix echoed into the page — to OpenAiController@store. That method does return `response()->json($decodedUpstreamBody)` verbatim (OpenAiController.php:56-79 via HttpRequestTrait::postRequest at :41-69), but POST /open-ai is called by NO view anywhere in the codebase (grep across resources/views finds no reference to open-ai.store), so it is dead and unreachable through the UI. Dropped that claim and replaced it with the two paths the page actually exercises. /open-ai itself has no menu entry — confirmed.

#### 4.7.18 GET /tasks/voucher — dead route, 500 with a debug page

- **Status:** 500 | **Frequency:** rare
- **Trigger:** Anyone following a bookmark or stale link to the Payment Voucher screen.
- **Mechanism:** routes/web.php registers tasks.voucher → TaskController@voucher, but no such method exists in the controller or its traits (Converter, CurrencyExchangeTrait, NotificationTrait). Laravel's Controller::__call throws BadMethodCallException.
- **User sees:** Laravel 11's debug page: "BadMethodCallException — Method App\Http\Controllers\TaskController::voucher does not exist." with the framework frame list, absolute server paths and source excerpts.
- **Way back:** none
- **Evidence:** routes/web.php:242; grep -rn 'function voucher' across app/ returns only RateHawkHotelParser::voucherDateTime; `artisan route:list --path=tasks` on prod prints "GET\|HEAD tasks/voucher … tasks.voucher › TaskController@voucher"
- **Verify note:** CORRECTED — route is at web.php:242, not :243; the rendering is Laravel's own debug page, not Symfony's. The dead-entry-point point stands and is worse than stated: resources/views/tasks/tasksVoucher.blade.php exists and carries an "Upload Task" button, and grep shows NO controller anywhere returns view('tasks.tasksVoucher') — so even a restored voucher() method has nothing wired to it.

#### 4.7.19 Oversized upload batch → 419 Page Expired, everything lost

- **Status:** 419 | **Frequency:** rare
- **Trigger:** An agent selects a large batch of scanned supplier PDFs (post_max_size is 256M; scanned hotel vouchers run 10-30MB each) and submits.
- **Mechanism:** Exceeding post_max_size makes PHP discard the entire POST body including the _token field, so VerifyCsrfToken raises TokenMismatchException → HTTP 419 → Laravel's stock vendor errors::419 view (resources/views/errors/ does not exist, ->withExceptions() is empty). Note upload_max_filesize is 320M — larger than post_max_size — so the friendlier per-file limit never trips first.
- **User sees:** Laravel's stock "419 \| PAGE EXPIRED" — thin grey text centred on white, no logo, no navigation, no explanation that the upload was too big.
- **Way back:** none — no link on the page. Browser Back returns to a stale form with all file selections cleared.
- **Evidence:** /opt/cpanel/ea-php82/root/etc/php.ini: post_max_size=256M, upload_max_filesize=320M, max_file_uploads=20, max_execution_time=600; resources/views/tasks/index.blade.php:69-71 (the multipart form with @csrf); resources/views/errors/ does not exist; bootstrap/app.php:45-47; vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/419.blade.php
- **Verify note:** CORRECTED. php.ini values verified directly. Frequency lowered occasional → rare for THIS cause: max_file_uploads=20 caps a batch at 20 files, so tripping post_max_size needs ~13MB average across a full 20-file batch. The same 419 page is reached far more often by ordinary session expiry on a long-open tab, which belongs to a different surface area.

#### 4.7.20 Deleted uploader account → 500 mid-upload

- **Status:** 500 | **Frequency:** theoretical
- **Trigger:** An agent re-uploads a file that was originally uploaded by a staff member whose user account has since been removed from the users table.
- **Mechanism:** $userUpload = $existingFileUpload->first()->user; then $userUpload->id. User has no SoftDeletes, so a removed user leaves a dangling user_id and the relation returns null → PHP Error "Attempt to read property \"id\" on null", uncaught here.
- **User sees:** Laravel 11's built-in debug exception page (APP_DEBUG=true on prod and dev): exception class + message as a heading, absolute /home/citycomm/… paths, a syntax-highlighted code frame with TaskController.php:3458 highlighted red, the full frame list, and Request/Application panels. Everything staged in the modal is gone.
- **Way back:** none — browser Back only, which re-posts
- **Evidence:** app/Http/Controllers/TaskController.php:3456-3460; app/Models/FileUpload.php:26-29 (belongsTo User); app/Models/User.php has no SoftDeletes trait
- **Verify note:** CORRECTED — surface is real in shape but NOT currently reachable. (1) There is no user hard-delete path in the application: UserController has no destroy/delete method and no `User::…delete()` / `$user->delete()` exists anywhere in app/. (2) Prod data has zero orphans: LEFT JOIN of 1,207 file_uploads rows against users returns 0 rows with a missing user. Frequency downgraded rare → theoretical. Also corrected the rendering: it is Laravel 11's own debug renderer, not the Symfony page (see the 'stack trace' finding). Note the sibling merge-batch path at :3300-3305 DOES null-guard ($matchUser && …), so only this branch is exposed.

#### 4.7.21 Duplicate POST /tasks/upload registration hijacks the task-upload form

- **Status:** 400 (JSON rendered as a page) | **Frequency:** theoretical
- **Trigger:** Would fire for anyone submitting the "Upload Task" form on the Payment Voucher page or the standalone tasksUpload page.
- **Mechanism:** routes/web.php registers POST tasks/upload twice — line 244 as tasks.upload → TaskController@upload and line 254 as tasks.upload.passport → TaskController@clientPassport. RouteCollection keys routes by method+URI, so the later registration silently overwrites the earlier one. Both blades still resolve route('tasks.upload') to the URL /tasks/upload, which now dispatches to clientPassport. That handler looks for an input named `file`; tasksVoucher sends `task_file` and tasksUpload sends `excel_file`, so either way it falls to the no-file branch.
- **User sees:** Because both forms do a full-page form.submit() (the voucher page even shows a loading overlay first), the browser would navigate to a bare white page displaying raw JSON: {"success":false,"message":"Error processing passport","errors":"No file uploaded."}
- **Way back:** none — no chrome, no link; browser Back only
- **Evidence:** routes/web.php:244 and :254; `artisan route:list --path=tasks` on PROD prints exactly one row for that URI — "POST tasks/upload tasks.upload.passport › TaskController@clientPassport" — and no row for tasks.upload; app/Http/Controllers/TaskController.php:4879-4881, :4925-4932 (no-file branch); resources/views/tasks/tasksVoucher.blade.php:102-131 (form → route('tasks.upload'), name="task_file", full-page submit behind a loading overlay); resources/views/tasks/tasksUpload.blade.php:5-13 (form → route('tasks.upload'), name="excel_file")
- **Verify note:** CORRECTED and hardened. The clobber is now proven from prod route:list, not inferred. Two fixes to the description: tasksUpload posts `excel_file`, not `task_file`; and NEITHER blade is rendered by any controller (no view('tasks.tasksUpload') / view('tasks.tasksVoucher') anywhere), so both entry points are doubly dead. TaskController@upload survives only as an internal call from supplierTaskForAgent (:4454, :4471). Kept because the collision is real and this is what a revived page would produce.

---

### 4.8 API, webhooks & infrastructure  (20 surfaces)

| # | Surface | Status | Frequency | Way back |
|---|---|---|---|---|
| 1 | Any API error renders a full HTML page unless the client sends Accept: application/json | 404 (same split applies to 405, 500, 419) | dead-common | none — the stock page contains no anchor |
| 2 | API validation failure returns a 302 redirect to the homepage instead of a 422 | 302 (should be 422) | dead-common | the 302 itself — but it lands on / which |
| 3 | GraphQL API is fully broken by a stale cached schema — every real query returns HTTP 200 with a | 200 (with an errors array — status does not reflect the failure) | dead-common | none — HTTP 200 means retry logic and up |
| 4 | payment/failed discards the specific error and its only link is auth-walled | 200 (inline app page, not an error page) | dead-common | one button, "Return to Homepage" → url(' |
| 5 | POST /api/chat/upload can never succeed — it always 500s on a config-name mismatch | 500 | dead-common | none |
| 6 | Stock error pages carry no branding, no navigation and no way back at all | 404 / 403 / 419 / 429 / 500 / 503 | dead-common | none whatsoever — there is not a single  |
| 7 | APP_DEBUG=true on the dev server exposes full stack traces, absolute paths and environment cont | 500 | dead-common | none — the debug page has no application |
| 8 | Queued and scheduled work silently never runs — no worker, no scheduler, no user-visible signal | n/a (inline) — the HTTP request that dispatched the job returns 200 | dead-common | none, because nothing indicates anything |
| 9 | External POSTs to the webhooks registered in routes/web.php are rejected with 419 Page Expired  | 419 on POST; GET passes through | occasional | none for the POST path — and nothing sur |
| 10 | /api/login and /api/forgot-password 500 with "Undefined variable $errors" | 500 | occasional | none |
| 11 | /api/cygnet-sync is publicly executable — the key check passes with no key | 500 (the intended 403 is unreachable when no key is supplied) | occasional | none |
| 12 | AirIngestController returns absolute server filesystem paths in its JSON error bodies | 404 / 400 / 500 | occasional | none, but the controller does move faile |
| 13 | Payment webhooks sitting behind auth middleware — providers get bounced | 419 (auth would give 302→/login if CSRF were exempted) | occasional | none |
| 14 | TaskWebhook echoes raw exception messages to the caller and misses PHP Errors | 500 (422 on validation, which is handled correctly) | occasional | none, but DB::rollBack() runs on both ca |
| 15 | `php artisan` is dead on this host — every CLI command aborts before it runs | n/a (CLI) | occasional | none — there is no artisan command that  |
| 16 | A customer whose hotel payment fails lands on a white page showing raw JSON | 200 (content-type application/json rendered in a browser) | rare | none — no link, no button, no redirect,  |
| 17 | POST /api/payment/importfatoorah 500s — the controller method does not exist | 500 | rare | none |
| 18 | Database unreachable — every page 500s, including login, because sessions and cache are both on | 500 | rare | none — and no link would work anyway, si |
| 19 | The webhook HMAC signature and rate-limit middleware are dead code, never registered | n/a (inline) — would be 401 / 429 / 400 if wired | theoretical | n/a |
| 20 | No rate limiting on the API — the 429 surface is unreachable | 429 (unreachable in practice) | theoretical | n/a |

#### 4.8.1 Any API error renders a full HTML page unless the client sends Accept: application/json

- **Status:** 404 (same split applies to 405, 500, 419) | **Frequency:** dead-common
- **Trigger:** Any integration — n8n, the mobile app, a curl script, a supplier's server — calls an /api/ URL that doesn't exist or errors, without setting an Accept header. Most HTTP clients don't set one by default.
- **Mechanism:** Laravel's exception renderer branches on $request->expectsJson(), which reads the Accept header. bootstrap/app.php never forces JSON for the api group, and no route or middleware in the api stack does either. A full route dump with resolved middleware shows every one of the 130 api/* routes carries Illuminate\Routing\Middleware\SubstituteBindings and nothing else. NotFoundHttpException therefore renders errors::404 as text/html.
- **User sees:** Re-probed live 2026-08-26. GET /api/does-not-exist with no Accept header: HTTP 404, content-type text/html; charset=UTF-8, exactly 6,603 bytes, <title>Not Found</title> — Laravel's stock grey "404 \| NOT FOUND" panel. The identical URL with Accept: application/json returns {"message":"The route api/does-not-exist could not be found.","exception":"Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException","file":"/home/citycomm/development.citycommerce.group/vendor/laravel/framework/src/Illuminate/Routing/AbstractRouteCollection.php","line":45,"trace":[...]}. A JSON parser fed the HTML variant throws on '<' and the integration reports a meaningless failure.
- **Way back:** none — the stock page contains no anchor elements at all, and a machine client has nowhere to go anyway
- **Evidence:** bootstrap/app.php:45-47 (->withExceptions() body is a bare `//` comment), bootstrap/app.php:18-23 (withRouting registers api: routes/api.php with no JSON-forcing middleware), bootstrap/app.php:24-44 (withMiddleware block adds only an alias map plus SetLocale on web), routes/api.php:1-199, resolved route dump: `POST /api/task/webhook :: task.webhook :: Illuminate\Routing\Middleware\SubstituteBindings`
- **Verify note:** VERIFIED. Every claim reproduced exactly, including the 6,603-byte figure. This is the structural finding that converts most of the rest of this census into an HTML page for any client that doesn't opt in.

#### 4.8.2 API validation failure returns a 302 redirect to the homepage instead of a 422

- **Status:** 302 (should be 422) | **Frequency:** dead-common
- **Trigger:** An integration POSTs to an API endpoint with a missing or malformed field — the single most common integration mistake.
- **Mechanism:** ValidationException outside an expectsJson() request redirects back with errors flashed to session. The api middleware group has no StartSession and the api routes have no 'previous' URL, so the redirect resolves to the request host root. HTTP 302, not 422.
- **User sees:** Re-probed live. POST /api/hesabe/transaction-enquiry with an empty body and no Accept header: HTTP 302, content-type text/html; charset=utf-8, 398 bytes, Location: https://development.citycommerce.group/, body is Laravel's <title>Redirecting to https://development.citycommerce.group</title> meta-refresh stub. The validation messages are flashed into a session the caller has no cookie for and are discarded. With Accept: application/json the same request correctly returns HTTP 422 and {"message":"The data field is required. (and 1 more error)","errors":{"data":["The data field is required."],"accessCode":["The access code field is required."]}}.
- **Way back:** the 302 itself — but it lands on / which is auth-walled, so a client following redirects gets the login page with HTTP 200 and may conclude the call succeeded
- **Evidence:** app/Http/Controllers/PaymentController.php:6634 (public function hesabeTransactionEnquiry), :6636-6640 ($request->validate([...]) as the first statement), routes/api.php:139 (Route::post('/hesabe/transaction-enquiry', ...)); same pattern across app/Http/Controllers/WhatsAppHotelController.php and app/Http/Controllers/APIController.php
- **Verify note:** VERIFIED. Byte-for-byte reproduction including the 398-byte redirect stub and the exact 422 body. Line numbers 6636-6640 confirmed exact. Worth restating: a client that follows redirects sees a final 200 and no error at all.

#### 4.8.3 GraphQL API is fully broken by a stale cached schema — every real query returns HTTP 200 with an internal error

- **Status:** 200 (with an errors array — status does not reflect the failure) | **Frequency:** dead-common
- **Trigger:** n8n / the WhatsApp AI agent runs its normal hotel search (searchDotwHotelRooms), or any client sends any GraphQL query that touches any field other than __typename.
- **Mechanism:** config/lighthouse.php:89-99 sets schema_cache.enable to env('LIGHTHOUSE_SCHEMA_CACHE_ENABLE', env('APP_ENV') !== 'local') — APP_ENV is local, which would default it OFF, but .env:212 sets LIGHTHOUSE_SCHEMA_CACHE_ENABLE=true and turns it back on. Lighthouse therefore serves bootstrap/cache/lighthouse-schema.php, a compiled schema dated 20 May that still carries a @field directive resolving to App\Modules\AkeedDotwAI\GraphQL\Queries\SearchDotwHotelRooms. That class does not exist and that string appears NOWHERE else in the tree — not in graphql/schema.graphql, not in graphql/dotw.graphql, not in app/. Lighthouse throws DefinitionException while resolving the directive and returns it inside the GraphQL errors array with HTTP 200.
- **User sees:** Re-probed live. POST /graphql {"query":"{ searchDotwHotelRooms(cityCode:\"KWI\") { id } }"}: HTTP 200, {"errors":[{"message":"Internal server error","extensions":{"debugMessage":"Failed to find class App\\Modules\\AkeedDotwAI\\GraphQL\\Queries\\SearchDotwHotelRooms in namespaces [App\\GraphQL\\Queries] for directive @field.","file":"/home/citycomm/.../vendor/nuwave/lighthouse/src/Schema/Directives/BaseDirective.php","line":190,"trace":[...]}}]}. A query for a field that does not exist ({ nope }) returns the identical directive error rather than a field-not-found error. Only { __typename } succeeds, returning {"data":{"__typename":"Query"}}. A WhatsApp end user just sees their hotel search never come back.
- **Way back:** none — HTTP 200 means retry logic and uptime monitors treat it as healthy
- **Evidence:** bootstrap/cache/lighthouse-schema.php:2328 ('value' => 'App\\Modules\\AkeedDotwAI\\GraphQL\\Queries\\SearchDotwHotelRooms') — 270,502 bytes, mtime May 20 10:52, vs a branch dated Aug 24; a repo-wide grep for SearchDotwHotelRooms across *.php and *.graphql returns that ONE line and nothing else; app/Modules/AkeedDotwAI/ contains Console, Database, Http, Jobs, Models, Routes, Services and no GraphQL directory; config/lighthouse.php:93 and .env:212; storage/logs/laravel.log — 57 occurrences of "Failed to find class ... SearchDotwHotelRooms", most recent 2026-08-26 10:25:42
- **Verify note:** CORRECTED (mechanism strengthened, claim upheld). The census asserted the stale cache was in play; that needed checking because APP_ENV=local would normally disable schema caching. Confirmed: .env:212 LIGHTHOUSE_SCHEMA_CACHE_ENABLE=true explicitly overrides the local default, and the offending class name exists ONLY inside the cache file — the current graphql/ source has no such field at all. So the endpoint is serving a three-month-old schema that references code deleted from the branch. Also confirmed the census's { nope } and { __typename } claims exactly. Severity stands: this is the entry point for the n8n/WhatsApp booking agent and it is down while reporting 200.

#### 4.8.4 payment/failed discards the specific error and its only link is auth-walled

- **Status:** 200 (inline app page, not an error page) | **Frequency:** dead-common
- **Trigger:** A KNET/MyFatoorah/Hesabe payment fails on any of the non-DotwAI flows and PaymentController redirects to the branded failure page. Also reached by a bare browser return from MyFatoorah.
- **Mechanism:** 33 call sites do redirect()->route('payment.failed'), about 32 of them chaining ->with('error', '<specific reason>'), but resources/views/payment/failed.blade.php never reads session('error') — a grep for 'session' across all 158 lines of that file returns nothing. The page's sole action is <a href="{{ url('/') }}">, and GET / resolves to DashboardController@index behind Illuminate\Auth\Middleware\Authenticate.
- **User sees:** Re-probed live. GET /payment/failed: HTTP 200, text/html, exactly 4,282 bytes, <title>Payment Failed</title>. A red gradient card, a ✗ mark, "Payment Failed!", "Unfortunately, your payment could not be processed. Please check your payment details and try again.", and one "Return to Homepage" button. The distinct reasons the controller computed — "Payment not found.", "Booking API failed.", "Failed to verify payment status.", "Payment was not completed.", "Hesabe response decryption failed", "Invalid failure response — missing reference number." — are all dropped, so every failure looks identical and unactionable. A script calls window.close() after 15s if window.opener is set.
- **Way back:** one button, "Return to Homepage" → url('/') → Authenticate middleware → login screen. A guest B2C payer following a payment link has no account, so the only exit is a dead end.
- **Evidence:** resources/views/payment/failed.blade.php:6 (<title>Payment Failed</title>), :144 (<a href="{{ url('/') }}" class="retry-button">), :153 (window.close()), and no session( anywhere in the 158-line file; app/Http/Controllers/PaymentController.php:6629-6632 (public function failed() { return view('payment.failed'); } — no data passed); the 33 redirect sites include :3876, :3892, :3938, :3943, :3994, :4086, :4094, :4101, :4107, :4344, :4347, :4356, :4374, :4384, :4392, :4409, :4415, :4668, :4735, :4742, :5355, :5362, :5389, :5617, :5669 (bare, no error), :5687, :5697, :5719, :5841, :5992, :6011, :6061, :6068; routes/web.php:635 (Route::get('/failed', ...)->name('failed')->withoutMiddleware(['auth'])) inside the group opened at :621-622 ('prefix' => 'payment', 'as' => 'payment.')
- **Verify note:** CORRECTED (call-site count 34 → 33) and strengthened. Exact count: grep -c "route('payment.failed')" in PaymentController returns 33, of which 30 chain ->with('error', ...) inline, 2 more (:4735, :4742) chain it on the following line, and 1 (:5669) passes no error at all. Everything else reproduces exactly, including the 4,282-byte page and the absence of session( in the view. Frequency upheld with new evidence: GET /payments/callback — MyFatoorah's browser return URL — 302s straight here, verified live; and the payments table (prod-synced) holds 4,518 rows of which 494 are pending and 169 initiate, i.e. ~663 payments that did not complete, against only 1 row marked failed. This is the most-visited error surface in the census.

#### 4.8.5 POST /api/chat/upload can never succeed — it always 500s on a config-name mismatch

- **Status:** 500 | **Frequency:** dead-common
- **Trigger:** Any client uploads a file to the chat endpoint. There is no input that makes this work.
- **Mechanism:** ChatController::__construct(AIManager $aiManager) is constructor-injected, so the container builds AIManager before any method runs. AIManager::createClient() takes the config('ai.chain') branch (fallback_enabled defaults true, chain is non-empty) and constructs a ResayilClient for the first hop. ResayilClient's constructor throws when 'resayil' appears in [ai.primary, ai.fallback, ai.default] and either url or key is empty. config/ai.php:67 reads env('RESAYIL_API_KEY'); .env defines RESAYIL_API_TOKEN instead — a different name — so the key resolves empty. AI_PRIMARY is unset so config/ai.php:4 defaults ai.primary to 'resayil', making the guard fire.
- **User sees:** Re-probed live. POST /api/chat/upload with Accept: application/json — HTTP 500 and {"message":"Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).","exception":"Exception","file":"/home/citycomm/development.citycommerce.group/app/AI/Services/ResayilClient.php","line":55,"trace":[{"file":".../app/AI/AIManager.php","line":31,"function":"__construct",...}]}. Without an Accept header: HTTP 500, text/html, ~320 KB of the full debug page. With APP_DEBUG=false it would be a bare "500 \| SERVER ERROR" line, or {"message":"Server Error"}.
- **Way back:** none
- **Evidence:** app/Http/Controllers/ChatController.php:43 (public function __construct(AIManager $aiManager)) and :914 (handleFileUpload); app/AI/Services/ResayilClient.php:53 ($inUse = in_array('resayil', [config('ai.primary'), config('ai.fallback'), config('ai.default')], true)), :54-56 (the throw); app/AI/AIManager.php:31 (new ResayilClient($model)) and :46 (the chain loop); config/ai.php:4 ('primary' => env('AI_PRIMARY', 'resayil')), :39-46 (chain, first hop resayil), :66-67 ('url' => env('RESAYIL_BASE', 'https://llmapi.resayil.io/v1'), 'key' => env('RESAYIL_API_KEY')); .env lines 186 (AI_PROVIDER=openai) and the RESAYIL_BASE_URL / RESAYIL_API_TOKEN block — no RESAYIL_BASE, no RESAYIL_API_KEY, no AI_PRIMARY anywhere; routes/api.php:106
- **Verify note:** VERIFIED, and worse than reported. Every line number confirmed exact (ChatController:43, ResayilClient:53-56, AIManager:31 and :46, config/ai.php:4 and :66-67). One nuance: only the KEY is actually missing — config/ai.php:66 gives RESAYIL_BASE a working default of https://llmapi.resayil.io/v1, so it is the empty($this->apiKey) half of the guard that fires. Also confirmed the census's note that TaskController.php:4894 and WhatsAppHotelController.php:2204 use `new AIManager()` inline and so fail only on the AI code path (plus two more the census missed: app/GraphQL/Mutations/CreateFullB2CBooking.php:157 and ChatController.php:930). laravel.log carries 32 occurrences of this message. The scope is larger than the census stated — see the ADDED finding: this same exception also kills every `php artisan` invocation on the host.

#### 4.8.6 Stock error pages carry no branding, no navigation and no way back at all

- **Status:** 404 / 403 / 419 / 429 / 500 / 503 | **Frequency:** dead-common
- **Trigger:** Any 404, 403, 419, 429, 500 or 503 reaching a human in a browser, once APP_DEBUG is false.
- **Mechanism:** resources/views/errors/ does not exist, so Laravel resolves errors::404 etc. from the framework's own views. All of them @extends('errors::minimal'), which contains no links.
- **User sees:** A single centred line: the status code in grey, a thin vertical rule, then the message in uppercase letter-spaced grey — "404 \| NOT FOUND", "403 \| FORBIDDEN", "419 \| PAGE EXPIRED", "500 \| SERVER ERROR". Inline normalize.css plus a Tailwind subset, light or dark by prefers-color-scheme. No logo, no product name, no navigation, no search, no support contact, no error reference the user could quote.
- **Way back:** none whatsoever — there is not a single anchor element on the page
- **Evidence:** resources/views/errors/ absent (ls returns 'No such file or directory'); vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/ contains 401, 402, 403, 404, 419, 429, 500, 503, layout and minimal blades; `grep -c 'href=' .../views/minimal.blade.php` returns 0; each status view is four lines, e.g. 404.blade.php is @extends('errors::minimal') + @section('title', __('Not Found')) + @section('code', '404') + @section('message', __('Not Found')), and 419.blade.php the same with 'Page Expired'
- **Verify note:** VERIFIED, with a third status confirmed. Measured live today: 404 → 6,603 bytes, <title>Not Found</title>; 419 → 6,609 bytes, <title>Page Expired</title>; and (new, from the cygnet wrong-key probe) 403 → 6,603 bytes, <title>Forbidden</title>. The census's key point holds and is important for the redesign: 404, 403 and 419 already render this stock page TODAY regardless of APP_DEBUG — only 500s are currently masked by the debug page. This is the blank canvas the redesign starts from.

#### 4.8.7 APP_DEBUG=true on the dev server exposes full stack traces, absolute paths and environment context to anonymous callers

- **Status:** 500 | **Frequency:** dead-common
- **Trigger:** Any 500 on development.citycommerce.group, from anyone, authenticated or not.
- **Mechanism:** .env sets APP_DEBUG=true and APP_ENV=local, so Laravel renders its full diagnostic page (and the equivalent JSON payload) instead of the stock 500.
- **User sees:** HTML: 310–346 KB, <title>City Tour</title> (config('app.name')), Figtree loaded from fonts.bunny.net — exception class, message, absolute path /home/citycomm/development.citycommerce.group/..., highlighted source excerpt, full frame-by-frame stack, and request/environment context. JSON: the same content as {"message":..,"exception":..,"file":..,"line":..,"trace":[..]}, roughly 19 KB for a one-line exception.
- **Way back:** none — the debug page has no application navigation
- **Evidence:** .env — APP_DEBUG=true, APP_ENV=local, APP_URL=http://127.0.0.1:8000; confirmed live across every 500 probed in this census (/api/login, /api/forgot-password, /api/pin, /api/chat/upload, /api/payment/importfatoorah)
- **Verify note:** VERIFIED. APP_DEBUG on dev is TRUE, confirmed in .env and by every probe. Two consequences for the redesign stand: nobody testing on dev will ever see the page real users get, so error-page work must be verified with APP_DEBUG=false explicitly; and if this .env pattern is copied to production the leak follows. The census's APP_URL observation is also correct and worth keeping — APP_URL is http://127.0.0.1:8000 while the site serves https://development.citycommerce.group, which is why the validation-failure 302 resolved to the public host (Laravel derived it from the request, not from config). One knock-on: APP_ENV=local also switches on the self-registration routes in routes/auth.php:17-20, so GET/POST /register and /api/register are live on this host — a security note rather than an error surface.

#### 4.8.8 Queued and scheduled work silently never runs — no worker, no scheduler, no user-visible signal

- **Status:** n/a (inline) — the HTTP request that dispatched the job returns 200 | **Frequency:** dead-common
- **Trigger:** Any code path that dispatches a job on dev: booking confirmation, document processing, notifications. Also the two weekly DOTW catalog syncs.
- **Mechanism:** QUEUE_CONNECTION=database. Jobs are inserted into the jobs table and nothing drains it — the only queue:work process on the host belongs to a different application. Separately, the two cron entries that DO target this site invoke /usr/local/bin/php, which is PHP 8.1.34, and Composer's platform check aborts before Laravel even boots.
- **User sees:** Nothing at all. The action reports success, and the queued outcome — the confirmation email, the processed document, the notification — simply never arrives. No error page, no toast, no failed state in the UI. This is the one surface in the census with no visible error to redesign, which is precisely the problem: silent failure with no signal.
- **Way back:** none, because nothing indicates anything went wrong
- **Evidence:** .env QUEUE_CONNECTION=database; `ps aux` shows exactly one queue:work on the box — PID 4191705, `artisan queue:work database --queue=zkbio,default`, running as root out of /home/resayili/challenge/main-file, a different app; the citycomm crontab runs `artisan schedule:run` ONLY for /home/citycomm/tour.citycommerce.group, and its only two development.citycommerce.group entries are `0 8 * * 0 ... artisan dotwai:sync-static` and `30 8 * * 0 ... artisan akeed-dotwai:sync-hotels --all`, both via /usr/local/bin/php; `/usr/local/bin/php -v` reports PHP 8.1.34 and `/usr/local/bin/php artisan --version` prints "Your Composer dependencies require a PHP version >= 8.2.0. You are running 8.1.34."; DB: SELECT COUNT(*) FROM failed_jobs = 9,453 with failed_at between 2025-06-11 17:07:00 and 2025-06-13 12:38:08, SELECT COUNT(*) FROM jobs = 0
- **Verify note:** CORRECTED (scheduler detail sharpened; a second independent failure found). The census's queue facts are all exact — 9,453 failed_jobs fossilised in a three-day burst in June 2025, 0 rows in jobs, and the sole worker belonging to /home/resayili/challenge/main-file with --queue=zkbio,default. But its scheduler description was incomplete in a way that makes things worse, not better: the two weekly dev cron entries do not merely sit idle, they cannot execute at all, because they call /usr/local/bin/php (8.1.34) against a codebase requiring >= 8.2 and die on Composer's platform check. And even with the right binary they would die on the Resayil exception (see the ADDED finding). So nothing scheduled has run against this site for as long as that crontab has stood.

#### 4.8.9 External POSTs to the webhooks registered in routes/web.php are rejected with 419 Page Expired before any controller runs

- **Status:** 419 on POST; GET passes through | **Frequency:** occasional
- **Trigger:** A provider POSTs to one of the web-group webhook URLs — Meta/WhatsApp message delivery, a Resayil WhatsApp event, a MyFatoorah server-to-server payment notification.
- **Mechanism:** These routes sit in the web middleware group, which includes Illuminate\Foundation\Http\Middleware\ValidateCsrfToken. There are NO CSRF exemptions anywhere: bootstrap/app.php never calls $middleware->validateCsrfTokens(except: [...]), and app/Http/Middleware/ contains no VerifyCsrfToken.php (the directory holds AccountantView, ApiCorsMiddleware, CheckFactorAuthentication, DotwAuditAccess, EnsureModuleEnabled, ResayilFrameHeaders, SetLocale, Verify2FA, VerifyWebhookSignature, WebhookRateLimiter). External POSTs carry no token so they are rejected pre-controller. GET is NOT CSRF-checked, so the GET half of each Route::match still works.
- **User sees:** Re-probed live. POST with {} and no token — /whatsapp/whatsapp-webhook, /webhook/resayil, /payments/callback ALL return HTTP 419, text/html, exactly 6,609 bytes, <title>Page Expired</title>, the stock grey "419 \| PAGE EXPIRED" panel, no Location header. With Accept: application/json they return {"message":"CSRF token mismatch.","exception":"Symfony\\Component\\HttpKernel\\Exception\\HttpException",...}. No human sees this — the provider does, retries, then disables the webhook. BUT the GET halves work: GET /payments/callback returns HTTP 302 to https://development.citycommerce.group/payment/failed, and GET /whatsapp/whatsapp-webhook returns HTTP 404 application/json (23 bytes) from inside the controller.
- **Way back:** none for the POST path — and nothing surfaces in the app to say deliveries are failing
- **Evidence:** bootstrap/app.php:24-44 (withMiddleware block, no validateCsrfTokens call), routes/web.php:394 (Route::match(['get','post'], '/whatsapp/whatsapp-webhook', ...)), :994 (Route::match(['get','post'], '/payments/callback', ...)), :1015 (Route::post('/webhook/resayil', ...)); resolved route dump shows all three carrying Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
- **Verify note:** CORRECTED (scope narrowed, frequency downgraded from dead-common). The census said these routes '419 to their provider' full stop; that is only true for POST. I verified that the GET halves of the two Route::match webhooks pass CSRF entirely: MyFatoorah's browser return to /payments/callback is a GET and works (302 → /payment/failed), and Meta's webhook-verification GET reaches the controller. Two further corrections to the frequency picture: (a) POST /myfatoorah/webhook resolves with a COMPLETELY EMPTY middleware array and works fine, so a working payment webhook already exists; (b) inbound WhatsApp is demonstrably still arriving — whatsapp_ingests has 86 rows, most recent 2026-08-25 14:53:42 — and it is written by App\Services\WhatsappPdfIngestService, reached via the api-group route POST /api/webhook/resayil/media, which has no CSRF. So the web-group /webhook/resayil may well be a legacy target rather than the live one. The 419 mechanism is 100% real and reproduced; whether any provider is actually POSTing to these specific URLs today is unconfirmed and should be checked against the gateway/Meta dashboards.

#### 4.8.10 /api/login and /api/forgot-password 500 with "Undefined variable $errors"

- **Status:** 500 | **Frequency:** occasional
- **Trigger:** A mobile app or integrator does the obvious thing and calls /api/login — the most guessable endpoint on any Laravel API.
- **Mechanism:** routes/api.php:177 does `require __DIR__ . '/auth.php'`, pulling the entire web authentication flow into the api group. Those controllers return Blade views that reference $errors, which is injected by Illuminate\View\Middleware\ShareErrorsFromSession — a member of the web group, absent here. Rendering throws Illuminate\View\ViewException.
- **User sees:** Re-probed live. GET /api/login: HTTP 500, text/html, 310,876 bytes, <title>City Tour</title> — the full debug page. GET /api/forgot-password: HTTP 500, 310,706 bytes. With Accept: application/json: {"message":"Undefined variable $errors (View: /home/citycomm/development.citycommerce.group/resources/views/auth/login.blade.php)","exception":"Illuminate\\View\\ViewException","file":"/home/citycomm/.../storage/framework/views/06035a7adbf8fe08b6426fcf3b9974a3.php","line":63,...}.
- **Way back:** none
- **Evidence:** routes/api.php:177; routes/auth.php:1-76, specifically :25 (Route::middleware('guest')->group), :27-28 (GET login → AuthenticatedSessionController@create), :38-39 (GET forgot-password); resources/views/auth/login.blade.php, resources/views/auth/forgot-password.blade.php; resolved route dump confirms api/login, api/forgot-password, api/reset-password/{token}, api/password, api/check_email all registered with [SubstituteBindings, Illuminate\Auth\Middleware\RedirectIfAuthenticated] and no session middleware
- **Verify note:** VERIFIED. Reproduced exactly, including the compiled-view path and line 63. HTML byte counts drift slightly between renders (I measured 310,876 for /api/login where the census measured 316,267) — the debug page embeds request context, so treat sizes as approximate. Confirmed the census's two sub-notes: POST /api/login would fail differently and worse (AuthenticatedSessionController.php:54 store() calls $request->session()->regenerate() at :58, and the api group starts no session); and GET /api/pin (routes/api.php:100-102) is the same class of bug — probed live, HTTP 500, 314,116 bytes, JSON body {"message":"Attempt to read property \"role_id\" on null (View: .../resources/views/auth/pin.blade.php)","exception":"Illuminate\\View\\ViewException","file":".../app/Helper/helper.php","line":8}.

#### 4.8.11 /api/cygnet-sync is publicly executable — the key check passes with no key

- **Status:** 500 (the intended 403 is unreachable when no key is supplied) | **Frequency:** occasional
- **Trigger:** Anyone on the internet requests the URL. It is also the intended target of an external scheduler every 15 minutes.
- **Mechanism:** The guard is `if (!hash_equals((string) config('cygnet.sync_key'), (string) $request->query('key'))) abort(403);`. CYGNET_SYNC_KEY is absent from .env, so config('cygnet.sync_key') is null. Casting both sides gives hash_equals('', '') — which PHP 8.2 on this host returns as bool(true), verified directly. The guard passes and Artisan::call('app:sync-cygnet-insurance') runs. Booting the console kernel constructs every discovered command, two of which (ProcessAirFiles, FixFlightDetails) constructor-inject AIManager, which throws the ResayilClient exception — so the request 500s instead of doing anything.
- **User sees:** With a wrong key the guard works and is reachable — probed live, GET /api/cygnet-sync?key=zzzz returns HTTP 403, text/html, 6,603 bytes, <title>Forbidden</title>, the stock "403 \| FORBIDDEN" panel. With no key the guard passes and the request reaches Artisan::call, which throws: HTTP 500, text/html, ~346 KB debug page, or with Accept: application/json {"message":"Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).","exception":"Exception","file":".../app/AI/Services/ResayilClient.php","line":55,...} — ~19 KB of stack trace handed to an anonymous caller.
- **Way back:** none
- **Evidence:** routes/api.php:181 (Route::get('/cygnet-sync', ...)), :182-184 (the hash_equals guard and abort(403)), :185 (Artisan::call('app:sync-cygnet-insurance')); config/cygnet.php:15 ('sync_key' => env('CYGNET_SYNC_KEY')); `grep -c CYGNET .env` returns 0 — no CYGNET_* variable exists at all; app/Console/Commands/ProcessAirFiles.php:46 and app/Console/Commands/FixFlightDetails.php:42 (public function __construct(AIManager $aiManager)); direct proof of the console-boot failure: `/opt/cpanel/ea-php82/root/usr/bin/php artisan route:list` aborts with "Resayil configuration is missing" at ResayilClient.php:55, trace via AIManager.php:31 → :46
- **Verify note:** VERIFIED. Two defects stacked, both confirmed. The bypass: I verified hash_equals((string)null,(string)null) === true on this exact PHP binary rather than assuming it. The 403 branch is genuinely reachable (probed with a wrong key) which proves the guard executes and that only the empty-vs-empty case slips through. The masking 500 is proven by `artisan route:list` failing identically at ResayilClient.php:55 — the same console-kernel boot Artisan::call performs. I deliberately did NOT re-probe the keyless path, since that is the one request in this census that could actually mutate data if my analysis were wrong; the mechanism is established statically and by the census's own earlier probe. Also confirmed: the legitimate cygnet sync does not use this endpoint at all — the citycomm crontab runs `flock -n /tmp/cygnet-sync.lock /usr/local/bin/php /home/citycomm/tour.citycommerce.group/artisan app:sync-cygnet-insurance` every minute against production directly. Belongs in a security review as well as this census.

#### 4.8.12 AirIngestController returns absolute server filesystem paths in its JSON error bodies

- **Status:** 404 / 400 / 500 | **Frequency:** occasional
- **Trigger:** The agent-side uploader (City Travelers.exe) POSTs a filename that has not landed on disk yet, or a staging move fails.
- **Mechanism:** Error responses interpolate the resolved path variables directly into the JSON body.
- **User sees:** Well-formed JSON, but leaking infrastructure: {"error":"file_not_found_in_uploaded","path":"/home/citycomm/.../UPLOADED/<name>.air"} and {"success":false,"error":"staging_move_failed","from":"...","to":"..."}. Machine-readable snake_case codes with no human-facing message; if any of this is ever shown to an agent they see the literal string file_not_found_in_uploaded.
- **Way back:** none, but the controller does move failed files to NOT LOADED/ with a sidecar .error.json (:128-133), so nothing is silently lost — the only surface here with a real recovery story
- **Evidence:** app/Http/Controllers/AirIngestController.php:51 ('error' => 'file_not_found_in_uploaded', 'path' => $uploadedPath, 404), :80-83 ('error' => 'staging_move_failed' with 'from' and 'to', 500), :46 ('error' => 'invalid_filename', 'filename' => $filename, 400), :58-63 (missing_company_or_supplier, 500), :40 (unauthorized, 401); routes/api.php:194
- **Verify note:** CORRECTED (three line numbers off by one). Actual offsets: 401 unauthorized at :40 (census said 39-41), invalid_filename at :46 (census said :45), file_not_found_in_uploaded at :51 (census said :52). The :58-63 and :80-83 ranges were exact. The auth behaviour is confirmed as the best in the codebase: re-probed live, POST /api/process-air-file with no bearer returns HTTP 401, content-type application/json, and exactly {"error":"unauthorized"} — the only clean, correctly-typed auth rejection I found on this whole surface.

#### 4.8.13 Payment webhooks sitting behind auth middleware — providers get bounced

- **Status:** 419 (auth would give 302→/login if CSRF were exempted) | **Frequency:** occasional
- **Trigger:** MyFatoorah or the payment-link provider POSTs a payment notification.
- **Mechanism:** payment/webhook and payment/link/webhook resolve with the full web stack PLUS Illuminate\Auth\Middleware\Authenticate. A webhook provider has no session cookie. ValidateCsrfToken sits earlier in the chain and fires first, so the observed status is 419 rather than an auth redirect — but either way the request never reaches the handler.
- **User sees:** Re-probed live. POST /payment/webhook and POST /payment/link/webhook with a JSON body and no token: HTTP 419, text/html, 6,609 bytes, <title>Page Expired</title>, no Location header. The provider sees a hard rejection, retries, and eventually disables the endpoint. Nothing appears in the app to say payment notifications are being dropped.
- **Way back:** none
- **Evidence:** routes/web.php:632 (Route::post('/webhook', [PaymentController::class, 'webhook'])->name('webhook')) and :654 (Route::post('/webhook', [PaymentController::class, 'paymentLinkWebhook'])->name('webhook')), both inside the 'payment' prefix group opened at :621-622 which has no ->withoutMiddleware(['auth']) — unlike its siblings at :634 and :635 which do; resolved dump: `POST /payment/webhook :: payment.webhook :: EncryptCookies,AddQueuedCookiesToResponse,StartSession,ShareErrorsFromSession,ValidateCsrfToken,Illuminate\Auth\Middleware\Authenticate,SubstituteBindings,SetLocale` and the same for payment/link/webhook
- **Verify note:** VERIFIED. The Authenticate middleware on both routes is confirmed in the resolved dump, and the census's reasoning about ordering is correct — CSRF wins, so you observe 419 not 302. Worth adding: the sibling routes in the same group (:634 /payment/success, :635 /payment/failed) DO call ->withoutMiddleware(['auth']), which shows the exemption was applied deliberately and these two were simply missed. Whether these are live provider targets or legacy is still worth confirming with whoever configured the gateway dashboards — note that POST /myfatoorah/webhook, which resolves with an entirely empty middleware list, already works.

#### 4.8.14 TaskWebhook echoes raw exception messages to the caller and misses PHP Errors

- **Status:** 500 (422 on validation, which is handled correctly) | **Frequency:** occasional
- **Trigger:** n8n or a supplier integration POSTs a task payload that trips a database constraint, a null property access, or a type error deep in the creation pipeline.
- **Mechanism:** The handler wraps everything in try/catch with `catch (Exception $e)` and returns 'Task creation failed: ' . $e->getMessage() at HTTP 500. \Error and its subclasses (TypeError, ArgumentCountError) do not extend Exception, so they escape the catch entirely and fall through to the global renderer — which, per the first finding in this census, means an HTML page for any caller not sending Accept: application/json.
- **User sees:** For caught exceptions, well-formed JSON: {"status":"error","message":"Task creation failed: SQLSTATE[23000]: Integrity constraint violation: ..."} — the raw database error handed to the caller. For an uncaught TypeError, the generic 500 path instead: HTML debug page or {"message":"Server Error"} depending on Accept and APP_DEBUG.
- **Way back:** none, but DB::rollBack() runs on both catch branches so no partial task is written
- **Evidence:** app/Http/Webhooks/TaskWebhook.php:79 (} catch (Exception $e) {), :87 ('message' => 'Task creation failed: ' . $e->getMessage()), :88 (], 500);), :71-78 (catch (ValidationException $e) → clean 422 with an errors array), :61-70 (success 201), :80 (DB::rollBack()); routes/api.php:153
- **Verify note:** CORRECTED (line numbers). Actual: catch (Exception) opens at :79 and its block runs to :88, with the message interpolated at :87 — the census said 79-87 with the message at :85. The success-201 block is :61-70, not :59-69. The ValidationException 422 range :71-78 was exact. The Error-vs-Exception gap is real, not theoretical: laravel.log currently holds 4 TypeError and 1 ArgumentCountError. Also confirmed the census's comparison point — SupplierController@magicReserveWebhookCallback (app/Http/Controllers/SupplierController.php:947, error branch :955-968) really is the best-designed error surface in the codebase: an RFC 7807-shaped body with title, type, status and detail, X-RateLimit-Limit/Remaining/Reset headers, and Content-Type: application/problem+json, with type pointing at route('magic-webhook-docs') which I confirmed exists at routes/web.php:997.

#### 4.8.15 `php artisan` is dead on this host — every CLI command aborts before it runs

- **Status:** n/a (CLI) | **Frequency:** occasional
- **Trigger:** A developer or operator runs any artisan command on the dev server: route:list, migrate:status, cache:clear, queue:work, schedule:run, tinker.
- **Mechanism:** Laravel 11's Application::configure() auto-registers command discovery over app/Console/Commands. Booting the console kernel resolves every discovered command, and two of them constructor-inject AIManager, which constructs ResayilClient, which throws because RESAYIL_API_KEY is unset (the .env defines RESAYIL_API_TOKEN instead). The failure happens during kernel boot, so it is unconditional — the command you asked for is irrelevant.
- **User sees:** The Laravel CLI error renderer: a red "Exception" banner, "Resayil configuration is missing (RESAYIL_BASE / RESAYIL_API_KEY).", "at app/AI/Services/ResayilClient.php:55", a five-line source excerpt with the throw highlighted, and a two-frame trace (AIManager.php:31 → AIManager.php:46). No indication that the problem is an env-var NAME mismatch rather than a missing value, and no hint that it has nothing to do with the command being run.
- **Way back:** none — there is no artisan command that can diagnose or repair this, because none of them start. Recovery requires editing .env by hand.
- **Evidence:** app/Console/Commands/ProcessAirFiles.php:46 and app/Console/Commands/FixFlightDetails.php:42 (public function __construct(AIManager $aiManager)); app/AI/AIManager.php:31 and :46; app/AI/Services/ResayilClient.php:53-56; config/ai.php:4 and :66-67; reproduced directly: `/opt/cpanel/ea-php82/root/usr/bin/php artisan route:list` and `... artisan --version` both abort with the same trace
- **Verify note:** ADDED IN VERIFY. Tripped over this while trying to reproduce the census's route:list citations — which is itself telling, since several findings cite `route:list` output that could not have been produced on this host. This is the same root cause as the /api/chat/upload finding, but it is a distinct surface with a distinct audience (the operator, not an integration) and it is the mechanism behind two other findings: it is why /api/cygnet-sync 500s instead of running the sync, and it is the second reason nothing scheduled executes. It also means the standard recovery moves in CLAUDE.md — optimize:clear, config:cache, migrate:status — are all unavailable on dev. I enumerated routes for this verification with a throwaway /tmp script that boots the HTTP kernel instead of the console kernel.

#### 4.8.16 A customer whose hotel payment fails lands on a white page showing raw JSON

- **Status:** 200 (content-type application/json rendered in a browser) | **Frequency:** rare
- **Trigger:** A B2C customer books a hotel over WhatsApp, is sent to MyFatoorah, and the payment fails or is cancelled — or succeeds and MyFatoorah redirects the browser back.
- **Mechanism:** PaymentBridgeService registers /api/dotwai/payment_callback as BOTH MyFatoorah's CallBackUrl and its ErrorUrl (the latter with a ?error=1 query appended). That URL is a browser redirect target, but handleCallback() is typed : JsonResponse and every branch returns response()->json(...) with HTTP 200. The route is Route::any and the resolved middleware list is completely empty.
- **User sees:** Re-probed live at the exact URL MyFatoorah redirects a failed payer to. GET /api/dotwai/payment_callback?error=1 → HTTP 200, application/json, 35 bytes, browser displays literally: {"status":"payment_error_received"}. GET with no params → {"status":"ignored","reason":"no_payment_id"}. Other reachable bodies in the controller: {"status":"error","reason":"status_check_failed"}, {"status":"error","reason":"booking_not_found"}, {"status":"ignored","reason":"not_dotwai"}, {"status":"already_processing"}, {"status":"processing"}, {"status":"payment_failed"}, {"status":"error","reason":"internal_error"}. A paying customer sees unstyled monospace JSON on a white page with nothing telling them whether they were charged.
- **Way back:** none — no link, no button, no redirect, no WhatsApp deep-link back to the conversation they came from
- **Evidence:** app/Modules/DotwAI/Services/PaymentBridgeService.php:131 ('CallBackUrl' => url('/api/dotwai/payment_callback')), :132 ('ErrorUrl' => url('/api/dotwai/payment_callback') . '?error=1'); app/Modules/DotwAI/Http/Controllers/PaymentCallbackController.php:50 (public function handleCallback(Request $request): JsonResponse), :65, :79, :103, :115, :128, :157, :185, :194, :236 (all return response()->json(..., 200)); app/Modules/DotwAI/Routes/api.php:39 (Route::any, no middleware); resolved dump: `GET\|HEAD\|POST\|PUT\|PATCH\|DELETE\|OPTIONS /api/dotwai/payment_callback :: - ::` with an empty middleware column
- **Verify note:** CORRECTED (frequency downgraded from dead-common; ErrorUrl value fixed). Mechanism and probes reproduce exactly, and the design flaw is real — this genuinely is the terminal screen of the DotwAI WhatsApp booking funnel, success or failure. But the census's 'dead-common' does not survive the data: SELECT COUNT(*) FROM dotwai_bookings returns 0, MAX(created_at) is NULL, and the dev DB is an hourly mirror of production as of 2026-08-24 — so no customer has ever reached this page, on either environment. The 9 '[DotwAI][Callback]' lines in laravel.log are the census agent's probes plus mine. It is also unreachable-in-practice for a second reason: the GraphQL entry point that starts this funnel is broken (see the Lighthouse finding). Keep it on the designer's list as the intended terminal screen, but it is a latent surface, not a live one. Also corrected: ErrorUrl is url('/api/dotwai/payment_callback') . '?error=1', not the bare URL.

#### 4.8.17 POST /api/payment/importfatoorah 500s — the controller method does not exist

- **Status:** 500 | **Frequency:** rare
- **Trigger:** Anything that still calls this documented-looking payment import endpoint.
- **Mechanism:** routes/api.php:54 maps the route to PaymentController@importPaidFatoorah. That method is not defined anywhere in the 8,094-line controller — grep -c returns 0. Laravel's ControllerDispatcher throws Error: Call to undefined method.
- **User sees:** Re-probed live. POST /api/payment/importfatoorah with Accept: application/json — HTTP 500 and {"message":"Call to undefined method App\\Http\\Controllers\\PaymentController::importPaidFatoorah()","exception":"Error","file":"/home/citycomm/.../vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php","line":47,"trace":[...]}. Without Accept: the full HTML debug page.
- **Way back:** none
- **Evidence:** routes/api.php:54 (Route::post('payment/importfatoorah', [PaymentController::class, 'importPaidFatoorah'])->name('importfatoorah')), app/Http/Controllers/PaymentController.php (8,094 lines, zero occurrences of importPaidFatoorah), vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php:47; resolved dump confirms the route is live: `POST /api/payment/importfatoorah :: importfatoorah :: Illuminate\Routing\Middleware\SubstituteBindings`
- **Verify note:** VERIFIED exactly, including the 8,094-line count and the zero-match grep. A dead route that nonetheless advertises itself as a payment import. Safe to probe: route resolution fails before any controller body executes, so no side effect is possible.

#### 4.8.18 Database unreachable — every page 500s, including login, because sessions and cache are both on MySQL

- **Status:** 500 | **Frequency:** rare
- **Trigger:** MySQL restarts, hits max_connections, or the socket drops. Has genuinely happened on this host, nine times in the current log.
- **Mechanism:** SESSION_DRIVER=database and CACHE_STORE=database, so the framework touches MySQL before any controller runs. PDOException(2002) is wrapped as Illuminate\Database\QueryException and escapes uncaught — bootstrap/app.php:45-47 registers no handler for it and there is no app/Exceptions/Handler.php.
- **User sees:** With APP_DEBUG=true (current dev): the 300 KB+ debug page with the raw SQL statement, the connection name, the absolute vendor path and the full trace. With APP_DEBUG=false: a single grey line, "500 \| SERVER ERROR", on every URL simultaneously — including /login, so nobody can even sign in to check. JSON clients get {"message":"Server Error"}.
- **Way back:** none — and no link would work anyway, since every route is equally dead
- **Evidence:** storage/logs/laravel.log:79214 and :79589 — [2026-06-03 18:30:41] and [2026-06-10 08:05:46] local.ERROR: SQLSTATE[HY000] [2002] Connection refused (Connection: mysql, SQL: select * from `cache` where `key` in (illuminate:queue:restart)); laravel.log:79249 [previous exception] PDOException(code: 2002) at vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php; further incidents at :79666, :79743, :80248, :81213 and three more; .env SESSION_DRIVER=database, CACHE_STORE=database
- **Verify note:** CORRECTED (occurrence count clarified). Log lines 79214, 79249 and 79589 confirmed at exactly those offsets with exactly those timestamps and that SQL. The census said '9 total Connection refused occurrences'; the precise figure is 18 matching lines in the 92,335-line log, which is 9 distinct incidents each logged twice (the local.ERROR line plus its [previous exception] PDOException line) — dated 2026-06-03 through 2026-06-29. Two sub-claims verified: the failing query really is against the `cache` table, not business data, so even a cache read takes the DB-down path; and /up genuinely does NOT check the database — probed live, it returns HTTP 200 with an HTML page containing the string "Application up", so an uptime monitor pointed at /up would report healthy through a full MySQL outage.

#### 4.8.19 The webhook HMAC signature and rate-limit middleware are dead code, never registered

- **Status:** n/a (inline) — would be 401 / 429 / 400 if wired | **Frequency:** theoretical
- **Trigger:** Never fires. This is the security control a designer or reviewer would assume protects the inbound webhooks.
- **Mechanism:** App\Http\Middleware\VerifyWebhookSignature and App\Http\Middleware\WebhookRateLimiter are fully implemented (HMAC verify against WebhookClient secrets, audit logging, per-client RateLimiter buckets) but appear in no alias map and on no route. bootstrap/app.php's alias map lists 2fa, check2fa, role, permission, role_or_permission, accountant, dotw_audit_access, verify.resailai.token, dotwai.resolve, module, resayil.frame — and none of these.
- **User sees:** Nothing — these responses are unreachable. If wired they would emit {"status":"error","message":"Webhook signature verification failed"} at 401 (VerifyWebhookSignature.php:87-90), {"status":"error","message":"Rate limit exceeded","retry_after":N} at 429 with a Retry-After header (WebhookRateLimiter.php:56-60), and {"status":"error","message":"Invalid webhook client"} at 400 (WebhookRateLimiter.php:28-31).
- **Way back:** n/a
- **Evidence:** app/Http/Middleware/VerifyWebhookSignature.php (126 lines), app/Http/Middleware/WebhookRateLimiter.php (65 lines), app/Http/Middleware/ApiCorsMiddleware.php (23 lines); bootstrap/app.php:25-40 (the alias map); a repo-wide grep for VerifyWebhookSignature, WebhookRateLimiter and ApiCorsMiddleware outside app/Http/Middleware/ and outside vendor/ returns ZERO hits; my full 679-route middleware dump contains none of them
- **Verify note:** VERIFIED, and honestly flagged as unreachable by the census — that flagging holds up. All three response line-number ranges are exact. VerifyWebhookSignature.php:20-22 does fail open as described: `if (!$request->hasHeader(WebhookSigningService::SIGNATURE_HEADER)) { return $next($request); }`. App\Http\Controllers\Api\Webhooks\N8nCallbackController is dead in the same way, confirmed: 119 lines of well-formed JSON handling (404 at :40-43, 409 at :53-57, 422 at :103-106, 500 at :113-115), and the only references to it in the whole tree are tests/Feature/Api/Webhooks/N8nCallbackControllerTest.php and two docs Blade files — no route points at it. Only correction: WebhookRateLimiter is 65 lines, not 64.

#### 4.8.20 No rate limiting on the API — the 429 surface is unreachable

- **Status:** 429 (unreachable in practice) | **Frequency:** theoretical
- **Trigger:** Would fire on abusive or runaway API traffic. Does not fire today.
- **Mechanism:** Laravel 11 does not throttle the api group by default; it must be opted into with ->withMiddleware(fn ($m) => $m->throttleApi()). bootstrap/app.php never calls it.
- **User sees:** Nothing today. If throttling were added, unauthenticated abusers would get the stock "429 \| TOO MANY REQUESTS" page (or {"message":"Too Many Attempts."} for JSON clients), again with no link back and no Retry-After guidance in the body.
- **Way back:** n/a
- **Evidence:** bootstrap/app.php:24-44 (no throttleApi() call anywhere in the withMiddleware closure); my resolved dump of all 679 routes shows 130 api/* routes of which exactly 2 carry Illuminate\Routing\Middleware\ThrottleRequests — api/verify-email/{id}/{hash} and api/email/verification-notification, both at :6,1 and both already behind Authenticate — leaving 128 with no throttle of any kind
- **Verify note:** VERIFIED, counts exact (130 api routes, 2 throttled, 128 not). Honestly flagged by the census as not reachable rather than as a defect to design around, and that framing holds. The security consequence is confirmed: GET /api/agents is unauthenticated — probed live, HTTP 200, application/json, 8,722 bytes, returning full agent records including name, email, phone_number, amadeus_id, commission, salary and target for real staff. Unlimited enumeration is possible. That belongs in a security review, not the error-page redesign.

---

## 5. What this log is not

This is a census, not a design and not a build plan. No redesign decisions have been made and no
code has been changed. When the redesign is agreed, the decisions still to take are: the error
page template itself, the "way back" rule per error class, and how the leaks in 3.1 and 3.2 are
resolved.
