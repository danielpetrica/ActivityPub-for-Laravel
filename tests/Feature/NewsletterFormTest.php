<?php

use App\Models\NewsletterForm;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('newsletter component renders default content when slug is missing', function () {
    $view = $this->view('components.forms.newsletter');

    $view->assertSee('Subscribe');
});

test('newsletter component renders dynamic content from database', function () {
    $form = NewsletterForm::create([
        'name' => 'Custom Form',
        'slug' => 'custom-slug',
        'title' => 'Custom Title',
        'description' => 'Custom Description',
        'button_text' => 'Custom Button',
        'success_message' => 'Custom Success',
        'is_active' => true,
    ]);

    $view = $this->view('components.forms.newsletter', ['slug' => 'custom-slug', 'layout' => 'inline']);

    $view->assertSee('Custom Title');
    $view->assertSee('Custom Description');
    $view->assertSee('Custom Button');
});

test('newsletter component falls back to default if slug not found', function () {
    $view = $this->view('components.forms.newsletter', ['slug' => 'non-existent']);

    $view->assertSee('Subscribe');
});

test('homepage uses dynamic newsletter form', function () {
    NewsletterForm::create([
        'name' => 'Homepage',
        'slug' => 'homepage',
        'title' => 'Homepage Title',
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertStatus(200)
        ->assertSee('Homepage Title');
});
