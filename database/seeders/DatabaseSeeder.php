<?php

namespace Database\Seeders;

use App\Classes\Business\Import\Ghost\GhostImportBusiness;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        $user = User::updateOrCreate(
            ['email' => 'admin@danielpetrica.com'],
            [
                'name' => 'Daniel Petrica',
                'username' => 'daniel',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // send email verification to admin user for safety.
        $user->sendEmailVerificationNotification();

        $ghostExportPath = $this->findLatestGhostExport();

        if ($ghostExportPath) {
            $this->command->info(string: "Ghost export found at {$ghostExportPath}. Importing...");

            $importer = new GhostImportBusiness(
                ghostBaseUrl: 'https://danielpetrica.com',
                dryRun: false
            );

            $importer->run(jsonPath: $ghostExportPath);
        } else {
            $this->command->info(string: 'No Ghost export found. Seeding with fake data...');
            $this->seedFakeData();
        }

        // Seed Services
        $this->call(ServiceSeeder::class);

        // Seed Case Studies
        $this->call(CaseStudySeeder::class);
    }

    /**
     * Seed the database with fake data.
     */
    protected function seedFakeData(): void
    {
        // Create Tags
        $tags = Tag::factory()->count(10)->create();

        // Create 10 Posts
        Post::factory()
            ->count(10)
            ->create()
            ->each(function (Post $post) use ($tags) {
                // Attach random tags
                $post->tags()->attach(
                    $tags->random(rand(1, 3))->pluck('id')->toArray()
                );

                // Create comments
                Comment::factory()->count(rand(2, 5))->create([
                    'post_id' => $post->id,
                ]);

                // Create likes
                Like::factory()->count(rand(5, 15))->create([
                    'post_id' => $post->id,
                ]);

                // Create page views
                PageView::factory()->count(rand(50, 200))->create([
                    'viewable_id' => $post->id,
                    'viewable_type' => Post::class,
                ]);
            });

        // Create Static Pages
        Page::factory()
            ->count(3)
            ->create()
            ->each(function (Page $page) {
                PageView::factory()->count(rand(20, 50))->create([
                    'viewable_id' => $page->id,
                    'viewable_type' => Page::class,
                ]);
            });
    }

    /**
     * Find the latest Ghost export file in storage/app/.
     */
    protected function findLatestGhostExport(): ?string
    {
        $files = glob(pattern: storage_path(path: 'app/daniel-petrica.ghost.*.json'));

        if (empty($files)) {
            return null;
        }

        // Sort by filename descending (assuming standard Ghost export format: .ghost.YYYY-MM-DD-...)
        rsort(array: $files);

        return $files[0];
    }
}
