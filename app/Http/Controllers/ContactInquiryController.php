<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactInquiryRequest;
use App\Mail\ContactInquiryReceipt;
use App\Mail\ContactInquiryReceived;
use App\Models\ContactInquiry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactInquiryController extends Controller
{
    public function create()
    {
        return response()->view('public.contact')->header('Cache-Control', 'private, no-store');
    }

    public function store(ContactInquiryRequest $request)
    {
        try {
            $inquiry = ContactInquiry::create($request->safe()->only(['name', 'email', 'subject', 'message']));
        } catch (QueryException $e) {
            Log::error('Contact inquiry storage failed.', ['exception_class' => $e::class]);

            return redirect()->route('public.contact')->withInput($request->safe()->only(['name', 'email', 'subject', 'message']))
                ->withErrors(['contact' => 'お問い合わせを受け付けられませんでした。時間をおいて再度お試しください。'])
                ->header('Cache-Control', 'private, no-store');
        }

        try {
            Mail::to(config('jobdd.contact_notification_email'))->send(new ContactInquiryReceived($inquiry));
            $inquiry->notification_sent_at = now();
            $inquiry->save();
        } catch (Throwable $e) {
            Log::error('Contact inquiry notification failed; inquiry remains stored.', ['contact_inquiry_id' => $inquiry->id, 'exception_class' => $e::class]);
        }

        try {
            Mail::to($inquiry->email)->send(new ContactInquiryReceipt($inquiry));
        } catch (Throwable $e) {
            Log::error('Contact inquiry receipt failed; inquiry remains stored.', ['contact_inquiry_id' => $inquiry->id, 'exception_class' => $e::class]);
        }

        return redirect()->route('public.contact')->with('contact_sent', true)->header('Cache-Control', 'private, no-store');
    }
}
