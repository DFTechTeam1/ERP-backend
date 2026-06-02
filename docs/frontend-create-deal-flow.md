# Create Deal — Frontend Flow Illustration

A step-by-step walkthrough of what the **user** does and what the **system**
fetches/computes during deal creation, from page load to final submit.

Components:
- [CreateDeals.vue](../src/pages/admin/production/deals/CreateDeals.vue) — stepper orchestrator
- [ProjectDetailForm.vue](../src/pages/admin/production/deals/ProjectDetailForm.vue) — Step 1
- [CalculationForm.vue](../src/pages/admin/production/deals/CalculationForm.vue) — Step 2
- [QuotationPreview.vue](../src/pages/admin/production/deals/QuotationPreview.vue) — Step 3

```
            ┌─────────────────────────────────────────────────────────┐
            │  Stepper:  (1) Detail Event → (2) Calculation → (3) Summary │
            └─────────────────────────────────────────────────────────┘
```

---

## Stage 0 — Page load (before the user touches anything)

`onMounted → prepareData()` (`CreateDeals.vue:222`) flips `loading = true`
(skeleton shows) and fires **all reference fetches in parallel**:

```
prepareData() ── Promise.all ──┬─ getQuotationNumber()  GET /production/project/getQuotationNumber
                               ├─ getProjectCalendar()  GET /dashboard/projectCalendar      (date picker)
                               ├─ getCompanySetting()   GET /setting?code=company           (quotation header)
                               ├─ getQuotationItems()   GET /production/quotations?all=1
                               ├─ getCalculation()      GET /setting/calculation            (PRICE GUIDE)
                               ├─ initEventType()       GET /production/eventTypes
                               ├─ initClassList()       GET /projectClass/getAll
                               ├─ initMarketing()       GET /production/project/marketings
                               ├─ initCountries()       GET /world/countries
                               ├─ getCustomer()         GET /production/project/customer/list
                               ├─ getProjectCount()     GET /production/project/initProjectCount
                               └─ getDetail()           (edit mode only)
                                        │
                              loading = false → Step 1 form renders
```

> All dropdown options + the price-guide config are now in the Pinia store.
> The agent equivalent is the `get_deal_form_options` MCP tool.

---

## Stage 1 — Step 1: Detail Event (user input)

```
┌─ Customer ─────────────────────────────────────────────┐
│ user picks customer from dropdown (listOfAllCustomer)   │
│   └─ OR clicks "Add Customer"                           │
│        → CustomerForm dialog opens                      │
│        → POST /production/project/customer/add          │
│        → on success: getCustomer() refreshes the list   │
│        → new customer selectable                        │
├─ Event name ───────────────────────────────────────────┤
│ user types `name`                                       │
│   └─ watch(name) auto-fills `client_portal` (slug)      │
├─ Event type ── user selects (listOfEventTypes)          │
├─ Location cascade ─────────────────────────────────────┤
│ user selects Country                                    │
│   └─ watch(country_id) → initStates()                   │
│        GET /world/states?country_id=..                  │
│ user selects State                                      │
│   └─ watch(state_id) → initCities()                     │
│        GET /world/cities?state_id=..                    │
│ user selects City                                       │
├─ Venue ── user types (optional autocomplete menu)       │
├─ Event date ── user opens calendar picker → picks date  │
├─ Event class ── user selects (listOfAllClasses)         │
├─ Marketing ────────────────────────────────────────────┤
│ defaults to logged-in user's employee uid               │
│ (or user multi-selects from listOfMarketings)           │
├─ Collaboration ── optional free text                    │
├─ LED detail ───────────────────────────────────────────┤
│ user adds LED rows (main / prefunction) via LedDetailForm│
│   each row: width × height × qty                        │
│   └─ updateLedArea() sets `led_area` (total m²)         │
│        and `led_detail[]` (per-area breakdown)          │
│   *** this is what drives the pricing in Step 2 ***     │
├─ Note ── optional rich text (Quill)                     │
└─────────────────────────────────────────────────────────┘
                          │
            user clicks  [ Next ]
                          │
        validateData (vee-validate handleSubmit)
          - all required fields validated
          - stashes customer + event summary into store
          - emit('next-event') → step = 2
```

If validation fails, inline errors show and the step does **not** advance.

---

## Stage 1→2 transition — system prepares the calculation

`watch(step)` in `CreateDeals.vue:82` (≈500ms after entering step 2):

```
calculationFormRef.setPreview(detailForm.getValues())
   ├─ copies name / date / venue / location into read-only preview fields
   ├─ sums led_detail → ledArea.main, ledArea.prefunction
   └─ defaults event_location, include_tax, with_accommodation

calculationFormRef.checkHighSeason()
   └─ POST /production/project/checkHighSeason { project_date }
        → sets high_season (the "High Season" radio, DISABLED for the user)
```

---

## Stage 2 — Step 2: Calculation (mostly auto, a few inputs)

```
LEFT (read-only preview)            RIGHT (inputs + live totals)
┌───────────────────────┐          ┌──────────────────────────────────┐
│ Event name  (locked)  │          │ Event Location  ◉ Surabaya ○ ...  │ ← user
│ Project date(locked)  │          │   (options from calculation.area) │
│ Location    (locked)  │          │ High Season   ◉ No   (DISABLED)   │ ← system
│ Venue       (locked)  │          │ Equipment     ◉ Lasika ○ ...      │ ← user
│                       │          │ Include Tax?  ○ Yes ◉ No          │ ← user
│ Note (Quill)          │ ← user   │ Accommodation?○ Yes ◉ No          │ ← user
│ Item (autocomplete)   │ ← user   │                                   │
│ LED summary (locked)  │          │  ── Auto-calculated totals ──     │
│ + Add Interactive     │ ← user   │  Main Ballroom    Rp ...  (calc)  │
│   (optional dialog)   │          │  Prefunction      Rp ...  (calc)  │
└───────────────────────┘          │  High Season Fee  Rp ...  (calc)  │
                                    │  Equipment Fee    Rp ...  (calc)  │
  Every input change re-runs the    │  Interactive Fee  Rp ...  (calc)  │
  computed() formulas live using    │  Normal Price     Rp ...  (calc)  │
  calculation (price guide):        │  Max Discount     Rp ...  (calc)  │
   main_ballroom, prefunction,      │  Max Price UP     Rp ...  (calc)  │
   high_season_fee, equipment_fee,  │  ─────────────────────────────   │
   interactive_fee, sub_total,      │  Fix Price  [ editable input ]    │ ← user
   maximum_discount, total,         │     (defaults to sub_total)       │
   maximum_markup_price             └──────────────────────────────────┘
                          │
            user clicks  [ Next ]
                          │
        validateData (CalculationForm:477)
          - requires ≥1 item AND an event_location
          - saves equipment / location / price / note / items to store
          - emit('next-event') → step = 3
```

> Nothing is fetched here on "Next" — the fees are all client-side `computed`
> off the price guide already loaded in Stage 0.

---

## Stage 3 — Step 3: Summary (review + submit)

```
QuotationPreview renders the full quotation document from store state:
  office logo/address (company setting), customer, quotation number,
  design job, event detail, LED sizes, items, note, rules.

        ┌─ buttons depend on mode ─────────────────────┐
        │ CREATE (no route.params.id):                 │
        │     [ Save ]      → submitProject('save')     │
        │     [ Proceed as Final ] → submitProject('final') │
        │ EDIT:   [ Update ] (confirmation modal)       │
        │ ADD QUOTATION: [ Add Quotation ]              │
        └───────────────────────────────────────────────┘
                          │
        emit('next-event', { type }) → CreateDeals.submitData(type)
```

### `submitData` assembles + sends (`CreateDeals.vue:231`)

```
projectDetail   = detailFormRef.getPayload()
quotationDetail = calculationFormRef.getPayload()
interactive     = calculationFormRef.getInteractiveValue()   // optional

completePayload = { ...projectDetail, ...quotationDetail, ...interactive }

  type → status / is_final:
     draft            → status 0
     save | save_and_download → status 2
     final            → status 1, quotation.is_final = 1

  + copy equipment_type / is_high_season to top level
  + quotation.design_job = designJob (system)
  + project_date → 'YYYY-MM-DD'

  POST /production/project/deals
        │
   success → showNotification + router.push('/admin/finance/deals')
   error   → showNotification(error, 'error')
```

---

## One-line summary of the whole journey

```
load page → system fetches all lookups + price guide
  → Step 1: user fills customer (or creates one), region cascade,
            event detail, LED sizes, marketing → Next (validate)
  → transition: system sums LED + checks high season
  → Step 2: user picks event location / equipment / tax / items;
            system live-computes every fee from the price guide → Next
  → Step 3: review quotation → Save or Proceed as Final
  → POST /production/project/deals → redirect to deals list
```

### Where the MCP tool fits
- **Stage 0 fetches**  → `get_deal_form_options`
- **Stages 1–3 inputs + the final POST** → `create_deal`
  (see `mcp-create-deal-tools.md`)
