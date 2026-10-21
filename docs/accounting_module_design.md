# Dfactory ERP — Accounting Module Navigation

Design reference for the accounting module of the Dfactory ERP (an AV / event production company). Base currency is IDR, standard is PSAK. This document lists every screen in the module, grouped by the sidebar navigation, with a description and the primary screen type so the layout can be designed per item.

The sidebar has collapsible sections. Each `##` below is a top-level nav group; the tables list its child screens.

Screen type legend: **List** (searchable/filterable table), **Form** (create/edit), **Detail** (read-only record view), **Report** (generated output with export/print), **Wizard** (multi-step process), **Dashboard** (widgets/charts).

---

## Dashboard

Landing screen after login. High-level financial health at a glance.

| Screen | Description | Type |
|---|---|---|
| Dashboard | Cash & bank position across all accounts, AR and AP aging summary, month-to-date profit & loss snapshot, top outstanding invoices and bills, and quick shortcuts to create a journal, invoice, or payment. | Dashboard |

---

## General Ledger (Buku Besar)

The double-entry core. Everything that touches money eventually lands here as a balanced journal.

| Screen | Description | Type |
|---|---|---|
| Chart of Accounts (Bagan Akun) | Hierarchical tree of all accounts (Asset, Liability, Equity, Revenue, Expense) with code, name, type, and current balance. Expand/collapse parent groups; add, edit, activate/deactivate accounts. | List (tree) |
| Journal Entries (Jurnal Umum) | List of all journals with number, date, type, description, debit/credit totals, and status (draft/posted/void). Create a manual journal with multiple debit/credit lines that must balance before posting. Post and void actions. | List + Form |
| General Ledger (Buku Besar) | Drill into a single account and see every posted line affecting it over a date range, with a running balance. Filter by account, period, and cost center. | Report |
| Trial Balance (Neraca Saldo) | All accounts with opening balance, period debit, period credit, and closing balance for a selected period. Totals for debit and credit must match. Export to Excel/PDF. | Report |
| Recurring Journals | Templates for entries that repeat (rent, subscriptions, standard depreciation batches). Set frequency and next run date; system generates a draft journal each cycle. | List + Form |
| Opening Balances | One-time entry of starting balances when the system goes live, per account and per cost center. | Form |

---

## Sales & Receivables (Penjualan & Piutang)

The customer-owes-us side. Extends the existing invoices and payments.

| Screen | Description | Type |
|---|---|---|
| Invoices | List of customer invoices linked to project deals, with amount, paid amount, due date, and status. Create invoice (including down-payment invoices), view, and see its posting journal. | List + Form + Detail |
| Customer Receipts / Payments | Record incoming payments from customers, choosing which cash/bank account received the money. Attach payment proof. | List + Form |
| Receipt Allocation | Match a received payment to one or more open invoices. Shows the payment amount, a list of the customer's unpaid invoices, and lets the user split the payment across them until fully allocated. | Form (split allocation) |
| Credit Notes (Nota Kredit) | Reduce what a customer owes after an invoice is posted, without editing the original. Used for post-deal price negotiation, service shortfall, cancellation, or billing error. Shows reason type, amount, tax portion, and the invoice being adjusted. | List + Form |
| Revenue Recognition | Recognize project revenue when an event is delivered (accrual). Converts a customer deposit / down payment into recognized revenue and books the related cost (HPP) in the same step. | List + Form |
| AR Aging Report (Umur Piutang) | Outstanding receivables bucketed by age (current, 1–30, 31–60, 61–90, 90+ days), grouped by customer. Drill into the invoices behind each bucket. | Report |

---

## Purchases & Payables (Pembelian & Utang)

The we-owe-suppliers side. Entirely new; there was no AP before.

| Screen | Description | Type |
|---|---|---|
| Suppliers (Pemasok) | Master list of vendors with code, name, NPWP, PKP status, payment terms, and bank details. Add and edit suppliers. | List + Form |
| Purchase Orders | Orders sent to suppliers before a bill arrives, with line items, quantities, prices, and tax. Track status (draft → approved → received). Optional step. | List + Form |
| Bills (Faktur Pembelian) | Supplier invoices we owe, with line items, PPN (input tax), PPh withholding, due date, and status. Can be created from a purchase order. Shows outstanding vs paid. | List + Form + Detail |
| Bill Payments | Pay one or more supplier bills from a chosen cash/bank account, allocating the payment across the selected bills. | List + Form |
| Debit Notes (Nota Debit) | Reduce what we owe a supplier after a bill is posted (purchase return or adjustment). Mirror of a credit note on the purchase side. | List + Form |
| AP Aging Report (Umur Utang) | Outstanding payables bucketed by age, grouped by supplier, so upcoming obligations are visible. | Report |

---

## Cash & Bank (Kas & Bank)

Everything involving movement of actual cash and bank balances.

| Screen | Description | Type |
|---|---|---|
| Cash / Bank Accounts | List of all cash and bank accounts (cash drawer, BCA, e-wallet, etc.), each linked to a ledger account, with current balance. Add and edit accounts. | List + Form |
| Expenses (Pengeluaran) | Direct cash-out that does not go through a supplier bill (petty cash, reimbursements). Multiple expense lines by category account, with optional tax and approval flow. | List + Form |
| Cash Advances (Kasbon) | Money advanced to an employee before spending, then settled later against actual expenses with any unused cash returned. Tracks requested, disbursed, and settled amounts. | List + Form |
| Bank Transfers (Transfer Antar Kas) | Move money between two of the company's own cash/bank accounts, with an optional admin fee line. | List + Form |
| Bank Reconciliation (Rekonsiliasi Bank) | Match imported bank statement lines against the system's recorded transactions to confirm the book balance agrees with the bank. Side-by-side matching view with unmatched items highlighted and a running difference. | Wizard / matching screen |

---

## Fixed Assets (Aset Tetap)

Company-owned equipment (LED panels, projectors, sound gear, vehicles) and its depreciation.

| Screen | Description | Type |
|---|---|---|
| Asset Categories | Groupings that define default depreciation method, useful life, and the ledger accounts each asset in the category posts to. | List + Form |
| Asset Register (Daftar Aset) | All fixed assets with acquisition cost, date, accumulated depreciation, current book value, and status (active, fully depreciated, disposed). Add and edit assets. | List + Form + Detail |
| Depreciation (Penyusutan) | Run periodic depreciation for a period, review the calculated amounts per asset, and post the batch to the ledger. | Report + action |
| Asset Disposals (Pelepasan Aset) | Record the sale, write-off, or loss of an asset, calculating gain or loss against remaining book value. | List + Form |

---

## Tax (Perpajakan)

Indonesian tax handling: PPN (VAT) and PPh (withholding income tax).

| Screen | Description | Type |
|---|---|---|
| Tax Codes (PPN & PPh) | Configure tax types and rates (e.g. PPN 11%, PPh 23 2%), each mapped to a payable/receivable ledger account. | List + Form |
| Output Tax Invoices (Faktur Pajak Keluaran) | Tax invoices we issue to customers on sales, with tax base (DPP) and PPN amount, and reporting status for SPT Masa PPN. | List + Detail |
| Input Tax Invoices (Faktur Pajak Masukan) | Tax invoices we receive from suppliers on purchases, tracked for input-tax crediting. | List + Detail |
| Withholding Slips (Bukti Potong PPh) | Records of PPh withheld, both when we withhold from a supplier and when a customer withholds from our income. | List + Form |
| Tax Reports | Period summaries that help prepare SPT Masa filings: output vs input PPN, and PPh withheld by type, per month. | Report |

---

## Reports (Laporan Keuangan)

Generated financial statements and account-level reports. All exportable to PDF/Excel with period and comparison-period selection.

| Screen | Description | Type |
|---|---|---|
| Balance Sheet (Neraca) | Statement of financial position: assets, liabilities, and equity as of a chosen date, grouped by account subtype, with prior-period comparison. | Report |
| Income Statement (Laba Rugi) | Revenue, cost of revenue (HPP), gross profit, operating expenses, and net income for a period, with comparison and margins. | Report |
| Cash Flow (Arus Kas) | Cash movement grouped into operating, investing, and financing activities (indirect method). | Report |
| Changes in Equity (Perubahan Ekuitas) | Movement in equity over the period: opening balance, net income, drawings/injections, closing balance. | Report |
| Customer Statements | Per-customer summary over a date range: every invoice, every payment, and the running outstanding balance. Sent to clients. | Report |
| Journal / GL Detail Listings | Raw journal list and general-ledger detail exports for audit and review. | Report |

---

## Closing (Tutup Buku)

Locking finished periods so figures cannot change after the fact.

| Screen | Description | Type |
|---|---|---|
| Period Management | View all accounting periods and their status (open, closed, locked). Open a new period, soft-close, or hard-lock a period. | List + action |
| Month-End Closing | Checklist-driven close of a monthly period: confirm reconciliations done, depreciation posted, then close. | Wizard |
| Year-End Closing | Close the fiscal year: move net income to retained earnings via a closing journal and open the next year. | Wizard |

---

## Settings (Pengaturan)

Configuration that the rest of the module depends on.

| Screen | Description | Type |
|---|---|---|
| Fiscal Years & Periods | Define fiscal years and generate their monthly periods. | List + Form |
| Cost Centers (Divisi / Departemen) | Departments, divisions, projects, or branches used to tag transactions for segmented reporting. | List + Form |
| Default Account Mapping | Assign which ledger account is used for each system role (AR control, AP control, PPN output/input, retained earnings, rounding, discounts, etc.). This drives all automatic posting. | Form |
| Currencies & Exchange Rates | Manage currencies and daily exchange rates for any non-IDR transactions. | List + Form |
| Payment Methods | Define payment methods (cash, transfer, QRIS, card) and their default settlement account. | List + Form |
| Audit Log | Immutable trail of accounting actions (created, posted, voided, reversed) with user and timestamp. | List (read-only) |
