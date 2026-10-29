<?php

namespace App\Http\Controllers;

use App\Models\SeminarCommissionInvoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** A host's own commission invoices from InfoDot. Scoped to the signed-in person; none takes a user id. */
class SeminarInvoicesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(config('seminars.paid.enabled'), 404);

        return view('seminars.invoices', ['invoices' => SeminarCommissionInvoice::where('host_id', $request->user()->id)->orderByDesc('period')->get()]);
    }

    public function show(Request $request, SeminarCommissionInvoice $invoice): View
    {
        abort_unless(config('seminars.paid.enabled') && $invoice->host_id === $request->user()->id, 404);

        return view('seminars.invoice', ['invoice' => $invoice, 'host' => $request->user()]);
    }
}
