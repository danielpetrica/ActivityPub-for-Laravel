<?php

use App\Mail\ContactFormMail;
use Illuminate\Support\Facades\Mail;

it('submits the contact form successfully', function () {
    Mail::fake();

    $response = $this->post(route('contact.submit'), [
        'name' => 'Mario Rossi',
        'email' => 'mario@example.com',
        'message' => 'Ciao, vorrei discutere un progetto Laravel.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('contact_success', true);

    Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail) {
        return $mail->senderName === 'Mario Rossi'
            && $mail->senderEmail === 'mario@example.com'
            && $mail->userMessage === 'Ciao, vorrei discutere un progetto Laravel.';
    });
});

it('fails validation when name is missing', function () {
    Mail::fake();

    $response = $this->post(route('contact.submit'), [
        'email' => 'mario@example.com',
        'message' => 'Messaggio di test.',
    ]);

    $response->assertSessionHasErrors('name');
    Mail::assertNothingSent();
});

it('fails validation when email is invalid', function () {
    Mail::fake();

    $response = $this->post(route('contact.submit'), [
        'name' => 'Mario Rossi',
        'email' => 'not-an-email',
        'message' => 'Messaggio di test.',
    ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

it('fails validation when message is missing', function () {
    Mail::fake();

    $response = $this->post(route('contact.submit'), [
        'name' => 'Mario Rossi',
        'email' => 'mario@example.com',
    ]);

    $response->assertSessionHasErrors('message');
    Mail::assertNothingSent();
});

it('fails validation when message exceeds max length', function () {
    Mail::fake();

    $response = $this->post(route('contact.submit'), [
        'name' => 'Mario Rossi',
        'email' => 'mario@example.com',
        'message' => str_repeat('a', 5001),
    ]);

    $response->assertSessionHasErrors('message');
    Mail::assertNothingSent();
});
