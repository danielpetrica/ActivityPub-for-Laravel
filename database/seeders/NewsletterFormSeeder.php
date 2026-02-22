<?php

namespace Database\Seeders;

use App\Models\NewsletterForm;
use Illuminate\Database\Seeder;

class NewsletterFormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NewsletterForm::updateOrCreate(
            ['slug' => 'homepage'],
            [
                'name' => 'Homepage Newsletter',
                'title' => 'Stay ahead of the curve',
                'description' => 'Subscribe to my newsletter for deep dives into tech, engineering management, and future-ready analysis. No spam, just high-signal content.',
                'button_text' => 'Subscribe',
                'success_message' => 'Thanks for subscribing!',
                'is_active' => true,
            ]
        );

        NewsletterForm::updateOrCreate(
            ['slug' => 'newsletter-page'],
            [
                'name' => 'Newsletter Page Form',
                'title' => 'Join the Community',
                'description' => 'Get the latest articles and insights directly in your inbox.',
                'button_text' => 'Join Now',
                'success_message' => 'Welcome to the community!',
                'is_active' => true,
            ]
        );
    }
}
