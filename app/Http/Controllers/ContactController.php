<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

final class ContactController extends Controller
{
    public function submit(ContactFormRequest $request): RedirectResponse
    {
        Mail::to(config('mail.contact_address', config('mail.from.address')))
            ->send(new ContactFormMail(
                senderName: $request->validated('name'),
                senderEmail: $request->validated('email'),
                userMessage: $request->validated('message'),
            ));

        return back()->with('contact_success', true);
    }
}
