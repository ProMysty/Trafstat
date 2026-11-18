# TrafStat — User Guide

Audience: authorized personnel of the Pagadian City traffic management
office. If you have not been given an individual TrafStat account, you
must not use the system.

---

## 1. Logging in

1. Open https://<your-office-url>/admin/login.php
2. Enter your username and password.
3. If this is your first login, you will be asked to set a new password.
   Choose a password you don't use anywhere else — at least 12 characters
   with uppercase, lowercase, a number and a special character.
4. If you see "Too many failed login attempts", wait 15 minutes. Your
   account will unlock automatically.

**Never share your password.** Every action in TrafStat is recorded
against your account by name.

---

## 2. Dashboard

After login you land on the Dashboard. It shows live figures:

- Total traffic violators
- Total registered vehicles
- Total apprehension records
- Repeat recorded incidents (violators with ≥ 2 recorded apprehensions)
- Charts: monthly trend, category distribution, yearly comparison
- A short preview of the most recent apprehensions

The "Most Common" card tells you which violation category has the highest
count.

---

## 3. Recording a new violator

1. Sidebar → **Violators** → **New violator**
2. Fill in: first name, last name, address. Middle name, suffix and
   contact number are optional.
3. Click **Create violator**.
4. A unique code (VIO-YYYY-NNNNN) is assigned automatically.

**Do not create a duplicate.** If the person already exists, use
**Search** first. The system warns you when the same name + address is
already on file.

---

## 4. Adding a vehicle

1. Sidebar → **Vehicles** → **New vehicle**
2. Enter the plate number (required), type, make/model, and color.
3. Assign the vehicle to a violator from the dropdown.
4. Click **Create vehicle**.

You can also add vehicles directly from the violator's edit screen.

---

## 5. Recording an apprehension

1. Sidebar → **Apprehensions** → **Record apprehension**
2. Select the violator, the category, and the date.
3. The vehicle dropdown is filtered to that violator's vehicles — leave
   it blank if no vehicle was involved.
4. Enter the location. If you have a GPS coordinate, add latitude AND
   longitude. If you don't have both, leave both blank — **never invent
   coordinates**.
5. Add any neutral operational notes in "General remarks".
6. Click **Record apprehension**. A unique reference (APP-YYYY-NNNNN) is
   generated automatically.

**A recorded apprehension is not a legal finding of guilt.** TrafStat
records what was observed, not what was decided.

---

## 6. Viewing violation history

1. Sidebar → **Violation History**
2. The list is ranked by number of recorded incidents.
3. A yellow "Multiple recorded incidents" badge appears for violators
   with two or more recordings.
4. Click **View history** to see the full timeline for one violator.

The badge is a system record indicator — it is **not** proof of guilt
and **not** a legal finding of recidivism. Do not present it as such.

---

## 7. Analytics

Sidebar → **Analytics**. Choose a date range and (optionally) a category.
The page shows:

- Total apprehensions in range
- Number of distinct violators
- Average per month
- Most and least common categories
- Year-over-year comparison
- Charts: monthly trend, category distribution, yearly bar, top 10 locations

All figures come from real records. Archived records are excluded.

---

## 8. Hotspot map

Sidebar → **Hotspot Map**. Shows apprehensions with coordinates, grouped
into clusters. Click a cluster to zoom in; click a marker to see that
record.

If a location has no coordinates, it does not appear on the map. The
panel above the map tells you how many records in the range have no
coordinates.

---

## 9. Reports

Sidebar → **Reports**. Six report types:

- Apprehension records
- Traffic violators
- Registered vehicles
- Repeat incident summary
- Location summary

Choose one, then use the filter panel. Three actions are available:

- **Print / Save as PDF** — opens a clean print view and asks the browser
  to print. Use "Save as PDF" in the print dialog if you need a file.
- **Export CSV** — downloads a CSV that opens in Excel.
- **Reset** — clears filters.

Exports are single-use and are automatically deleted after download or
after one hour if not downloaded.

**Exports contain personal information. Handle them according to the
office's data-handling rules.** Do not email them outside the office.

---

## 10. Activity logs

Sidebar → **Activity Logs**. Shows every administrative action: logins,
record creations, edits, archives, exports. Every entry is attributed to
the administrator who performed it.

The **Verify chain integrity** button checks whether the log has been
tampered with. A green result means no tampering was detected; a red
result means the chain is broken at a specific entry and must be
escalated to the DPO immediately.

---

## 11. Editing and archiving

Any list view has an **Edit** (pencil) icon and an **Archive** (box) icon
per row. Archiving hides the record from active lists but does not
delete it. Restore an archived record by switching the status filter to
"Archived" and clicking **Restore**.

**Never archive as a substitute for correction.** If a record is wrong,
edit it. Only archive when the record should no longer appear in
operational views.

---

## 12. Logging out

Top-right account menu → **Log out**. Always log out when you finish,
especially on a shared computer. Idle sessions expire automatically after
30 minutes.

---

## 13. Reporting a problem

If something behaves unexpectedly, take note of:

- What you were doing
- What you expected
- What actually happened
- The exact time (the office can cross-reference activity logs)

Report to your TrafStat administrator or the office DPO. **Do not** try
to fix data issues directly in the database.