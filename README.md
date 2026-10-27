# Medstore: setup

1. `mysql -u root -p < schema.sql`, create a MySQL user, edit `config.php`.
2. Serve over **HTTPS** with PHP 8.1+ (needed for location, secure cookies and `str_ends_with`). Enable the HSTS line in `includes/security.php`.
3. Move `RX_DIR` (prescription photos) **outside the web root** and update `config.php`.
4. Create staff: `php tools/create_staff.php <username> <owner|pharmacist|worker>` (staff sign in at `/staff/login.php`).
5. Set `USE_SAMPLE_DATA = false` in `config.php` once MySQL is running.
6. Start the SOS worker: `php worker/escalate.php daemon` (systemd/supervisor).
7. Replace the stubs `send_sms()` and `place_fallback_call()` in `includes/lib.php` with a real gateway.
8. Replace sample providers with real, verified partners. Have a doctor approve `includes/firstaid.php`.

## Ambulance booking with live tracking
- Add each ambulance: `php tools/create_ambulance.php <plate> "<driver name>" <driver phone> [provider_id]`. It prints the driver's private code once.
- The driver opens `/driver.php`, signs in, taps **Go on duty**, and keeps the page open with the screen on. It shares GPS every 5 seconds and rings when a request arrives.
- The patient presses the button on `sos.php`. The nearest on-duty ambulance (fresh GPS, within `MAX_AMBULANCE_KM`) gets the offer. If it is not accepted in 30 seconds, the worker offers it to the next one, then to phone providers, then the fallback call.
- After acceptance the patient sees the driver's name, plate and phone, an ETA estimate (straight-line distance x1.3 at 30 km/h) and a live map.
- Browsers cannot track location once the page is closed or the screen is off. For reliable all-day tracking, a native driver app is the next step.

## Patient-side features
- **Nepali and English** on `sos.php` and `follow.php` (switch with the link at the top, Nepali by default). Nepali first-aid text is a draft: have a doctor who is a native speaker approve it.
- **Book for someone else:** the requester can enter the patient's name, phone and address. If they are not with the patient (or GPS is off) they type the address; the incident goes straight to the control room as `needs_staff` and staff get an SMS.
- **Family tracking link:** `follow.php?s=...` is read-only (no cancel, no requester phone), works for 24 hours, and is sent by the share button.
- **Hospital choice and fare estimate:** while the ambulance is being found, the patient picks a hospital and sees an estimated fare (set `FARE_*` in `config.php` to your partners' real rates). The hospital gets an SMS and the driver sees the destination.
- **Control room:** `staff/control.php` (roles `owner` and `dispatcher`; create with `tools/create_staff.php <user> dispatcher`). Shows every active incident, flags those needing a person (sound alert), and lets staff assign an ambulance by distance, push to the next responder, or close an incident.
- Set `SITE_URL` in `config.php` to your real domain so SMS links work.

## Pages
- `index.php` home, `checkout.php`, `order_place.php`, `track.php?t=...` (customer)
- `staff/` queue (emergency orders first), order review, prescription file viewer
- `sos.php` (patient), `driver.php` (ambulance driver) and `api/` emergency flow

## Rules built in
- Prices and stock come from the server, never the browser.
- Prescription items cannot be ordered without an uploaded prescription.
- Only roles in `RX_APPROVER_ROLES` can approve (default: owner, pharmacist). Approval records the prescriber, registration number and how it was checked. Every action is in `audit_log`.
- Products flagged `controlled` cannot be bought online.
- Nothing here has been run yet: test the full flow before real customers use it.
