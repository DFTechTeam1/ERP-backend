<?php

namespace Modules\Finance\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;
use Modules\Finance\Models\Invoice;

class EditInvoiceAmountRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $invoiceId = Crypt::decryptString(request('invoice_id'));

        $currentInvoice = Invoice::find($invoiceId);

        if ($currentInvoice->amount == $value && date('Y-m-d', strtotime($currentInvoice->payment_date)) == request('payment_date')) {
            $fail('No changes are submitted');
        }
    }
}
