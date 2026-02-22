<?php

namespace App\Http\Controllers;

use App\Classes\Business\NewsletterBusiness;
use App\Http\Requests\SubscribeNewsletterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class NewsletterSubscriptionController
{
    public function store(SubscribeNewsletterRequest $request): Response|JsonResponse|RedirectResponse
    {
        $email = (string) $request->string('email');

        try {
            NewsletterBusiness::subscribe(email: $email);
        } catch (\Throwable $e) {
            // For JSON callers, return a proper error response
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Subscription failed.',
                ], 422);
            }

            // For simple form posts, redirect with error query param (no sessions / flashes)
            return redirect()->back(303)->withHeaders([
                'Cache-Control' => 'no-store',
            ])->withInput(
                // No session usage: avoid ->withErrors / flashes; instead pass via query string
                // We'll append ?subscribed=0 to the URL below
            )->withFragment('newsletter-error');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Subscribed successfully.',
            ]);
        }

        // Redirect back (or to provided redirect URL) with a cache-busting success query
        $referer = (string) $request->headers->get('referer', '');
        $redirectTo = (string) $request->input('redirect_to', $referer !== '' ? $referer : route('welcome'));

        $url = str_contains($redirectTo, '?')
            ? $redirectTo.'&subscribed=1'
            : $redirectTo.'?subscribed=1';

        return redirect()->to($url, status: 303);
    }
}
