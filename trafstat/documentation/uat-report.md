# TrafStat — User Acceptance Testing (UAT) Report

**Status:** Template — completed by the office's designated UAT
participants.
**Purpose:** Confirm TrafStat supports the office's operational needs
as intended, in business language rather than technical terms.

---

## 0. Summary

| Field | Value |
|---|---|
| UAT period | <YYYY-MM-DD> to <YYYY-MM-DD> |
| Participants | <names and roles> |
| Environment | <staging / pre-prod URL> |
| Overall result | <ACCEPTED / ACCEPTED WITH CONDITIONS / REJECTED> |
| Signed off by | <name / role / date> |

---

## 1. Scenarios

Each scenario reflects a real operational task. Participants perform
the task end-to-end and record the outcome.

### 1.1 Login and password change

**Task:** Log in with a newly provisioned account. Change the password
to a new strong one. Log out.

| Check | Result |
|---|---|
| Temporary password is accepted | ☐ |
| System forces a password change | ☐ |
| New password is accepted | ☐ |
| Logout works | ☐ |

Notes: <free text>

---

### 1.2 Record a violator with a vehicle

**Task:** Add a new violator who was apprehended riding a motorcycle,
then link the motorcycle to that violator.

| Check | Result |
|---|---|
| Violator creation succeeds with automatic code | ☐ |
| Vehicle creation succeeds with plate number | ☐ |
| Vehicle is linked to the correct violator | ☐ |
| Duplicate violator attempt is rejected with a helpful message | ☐ |

Notes: <free text>

---

### 1.3 Record an apprehension with location

**Task:** Record an apprehension for the violator created above, at
"Rizal Avenue corner F.S. Pajares Ave", with coordinates if a phone
with GPS is available.

| Check | Result |
|---|---|
| Violator and category dropdowns are usable | ☐ |
| Vehicle dropdown filters to that violator | ☐ |
| Recording succeeds with automatic reference number | ☐ |
| Location text is preserved | ☐ |
| Coordinates (if provided) are saved | ☐ |
| Apprehension appears immediately in list | ☐ |

Notes: <free text>

---

### 1.4 View violation history

**Task:** Record a second apprehension for the same violator, then open
their violation history.

| Check | Result |
|---|---|
| Both apprehensions appear in chronological order | ☐ |
| Violator is flagged with "Multiple recorded incidents" | ☐ |
| Warning text clarifies this is not a legal finding | ☐ |

Notes: <free text>

---

### 1.5 Analytics for a chosen period

**Task:** Open Analytics, set the range to the last 30 days, apply.

| Check | Result |
|---|---|
| Summary cards show plausible counts | ☐ |
| Charts render without error | ☐ |
| Changing the range updates the figures | ☐ |
| Filtering by category updates figures | ☐ |

Notes: <free text>

---

### 1.6 Hotspot map

**Task:** Open Hotspot Map. Zoom and click a marker.

| Check | Result |
|---|---|
| Map shows Pagadian City with markers | ☐ |
| Clicking a marker shows details | ☐ |
| Cluster behavior works when markers are close | ☐ |
| Missing-coordinates warning appears when expected | ☐ |

Notes: <free text>

---

### 1.7 Generate a report

**Task:** Produce a repeat-incident summary for the last 90 days, print
to PDF, and export CSV.

| Check | Result |
|---|---|
| Preview shows the expected rows | ☐ |
| Print view renders cleanly | ☐ |
| "Save as PDF" produces a readable file | ☐ |
| CSV opens in Excel with correct columns | ☐ |

Notes: <free text>

---

### 1.8 Public website

**Task:** Open the public site in a browser (not logged in).

| Check | Result |
|---|---|
| Homepage loads with branding and disclaimer | ☐ |
| Statistics page shows aggregated figures only | ☐ |
| Violation information shows only published categories | ☐ |
| About page loads | ☐ |
| Mobile layout is usable | ☐ |

Notes: <free text>

---

### 1.9 Activity log review

**Task:** Open Activity Logs and inspect recent actions.

| Check | Result |
|---|---|
| Your recent actions appear | ☐ |
| Every action is attributed to the correct administrator | ☐ |
| Verify chain integrity returns green | ☐ |

Notes: <free text>

---

## 2. Operational fit questions

Answer in writing. These drive the go-live decision more than the
scenarios above.

1. **Does the apprehension recording form match what your officers
   actually record in the field?**
   <free text>

2. **Are the violation categories correct and complete for Pagadian
   City? If not, which should be added, removed, or renamed?**
   <free text>

3. **Are the fields on the violator form the ones you actually need?
   Are any fields you need missing? Are any you don't need?**
   <free text>

4. **Are the reports useful to the office? Is anything missing that
   would make them more useful?**
   <free text>

5. **Are the public website statistics and category descriptions
   acceptable for public disclosure, subject to your DPO's final
   approval?**
   <free text>

6. **Does the retention period proposed for the data match your
   office's policy?**
   <free text>

7. **Do the administrator roles match how your office actually
   assigns responsibilities?**
   <free text>

8. **What is the biggest risk if TrafStat goes live next week?**
   <free text>

---

## 3. Issues raised during UAT

| # | Scenario | Issue | Severity | Resolution |
|---|---|---|---|---|
| | | | | |

---

## 4. Acceptance

| Acceptance criteria | Met? |
|---|---|
| All critical scenarios passed | ☐ |
| No high-severity open issues | ☐ |
| DPO has reviewed the privacy aspects | ☐ |
| Office has approved the public site content | ☐ |

**Determination:**
- ☐ ACCEPTED — proceed to deployment
- ☐ ACCEPTED WITH CONDITIONS — proceed after items listed are addressed
- ☐ REJECTED — do not deploy

**Conditions (if applicable):**
<free text>

**Signatures:**

- UAT participant: _______________________ Date: ___________
- Office head: _______________________ Date: ___________
- DPO: _______________________ Date: ___________