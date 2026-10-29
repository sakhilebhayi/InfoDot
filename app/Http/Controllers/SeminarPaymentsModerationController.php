<?php

namespace App\Http\Controllers;

use App\Models\SeminarRegistration;
use App\Models\SeminarTicketEvent;
use Illuminate\Contracts\View\View;

/** For moderators: ticket payments that need a person. Disputes (answered in the Paystack dashboard), refunds asked for but not confirmed, and callbacks that did not match. */
class SeminarPaymentsModerationController extends Controller
{
    public function index(): View
    {
        abort_unless(config('seminars.paid.enabled'), 404);

        return view('seminars.moderation-payments', [
            'disputes' => SeminarRegistration::with('seminar')->whereIn('dispute_status', ['open', 'lost'])->orderByDesc('disputed_at')->limit(50)->get(),
            'stuck' => SeminarRegistration::with('seminar')->whereIn('payment_status', ['paid', 'partially_refunded'])->whereNotNull('refund_requested_at')->where('refund_requested_at', '<', now()->subHour())->orderBy('refund_requested_at')->limit(50)->get(),
            'odd' => SeminarTicketEvent::whereIn('outcome', ['mismatch', 'unknown'])->where('created_at', '>', now()->subDays(30))->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
