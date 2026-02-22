<?php

namespace Database\Seeders;

use App\Enums\LinkPosition;
use App\Models\Link;
use Illuminate\Database\Seeder;

final class LinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing links to avoid duplicates
        Link::truncate();

        $links = [
            // Header Links
            [
                'position' => LinkPosition::Header,
                'label' => 'Home',
                'url' => '/',
                'sort_order' => 1,
                'is_external' => false,
            ],
            [
                'position' => LinkPosition::Header,
                'label' => 'LaraPlugins.io',
                'url' => 'https://laraplugins.io',
                'sort_order' => 2,
                'is_external' => true,
            ],

            // Footer Links
            [
                'position' => LinkPosition::Footer,
                'label' => 'Home',
                'url' => '/',
                'sort_order' => 1,
                'is_external' => false,
            ],
            [
                'position' => LinkPosition::Footer,
                'label' => 'LaraPlugins.io',
                'url' => 'https://laraplugins.io',
                'sort_order' => 2,
                'is_external' => true,
            ],
            [
                'position' => LinkPosition::Footer,
                'label' => 'coz.jp',
                'url' => 'https://coz.jp',
                'sort_order' => 3,
                'is_external' => true,
            ],
        ];

        foreach ($links as $linkData) {
            Link::create($linkData);
        }
    }
}
