<?php

namespace App\Enums\Accounting;

enum AccountSubtype: string
{
    // Assets (Aset)
    case CashAndBank = 'cash_and_bank';
    case AccountsReceivable = 'accounts_receivable';
    case OtherReceivable = 'other_receivable';
    case Inventory = 'inventory';
    case PrepaidExpense = 'prepaid_expense';
    case AdvanceToSupplier = 'advance_to_supplier';
    case OtherCurrentAsset = 'other_current_asset';
    case FixedAsset = 'fixed_asset';
    case AccumulatedDepreciation = 'accumulated_depreciation';
    case IntangibleAsset = 'intangible_asset';
    case OtherNonCurrentAsset = 'other_non_current_asset';

    // Liabilities (Liabilitas)
    case AccountsPayable = 'accounts_payable';
    case AccruedExpense = 'accrued_expense';
    case TaxPayable = 'tax_payable';
    case CustomerDeposit = 'customer_deposit';
    case ShortTermDebt = 'short_term_debt';
    case OtherCurrentLiability = 'other_current_liability';
    case LongTermDebt = 'long_term_debt';
    case OtherNonCurrentLiability = 'other_non_current_liability';

    // Equity (Ekuitas)
    case ShareCapital = 'share_capital';
    case RetainedEarnings = 'retained_earnings';
    case CurrentYearEarnings = 'current_year_earnings';
    case OwnerDrawing = 'owner_drawing';
    case OtherEquity = 'other_equity';

    // Revenue (Pendapatan)
    case OperatingRevenue = 'operating_revenue';
    case OtherIncome = 'other_income';

    // Expense (Beban)
    case CostOfRevenue = 'cost_of_revenue';
    case OperatingExpense = 'operating_expense';
    case OtherExpense = 'other_expense';
    case TaxExpense = 'tax_expense';
}
