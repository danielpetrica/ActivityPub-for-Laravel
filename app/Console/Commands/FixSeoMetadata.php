<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class FixSeoMetadata extends Command
{
    protected $signature = 'app:fix-seo-metadata
                            {--force : Apply fixes (default is dry-run)}';

    protected $description = 'Fix long, short, or empty SEO meta titles and descriptions.';

    /** Google-ish guidance: titles should stay under ~60 chars. */
    private const MAX_TITLE_LENGTH = 60;

    /** Meta descriptions render best under ~160 chars. */
    private const MAX_DESCRIPTION_LENGTH = 160;

    /** Truncated descriptions target ~155 chars so the ellipsis keeps us under 160. */
    private const TRUNCATED_DESCRIPTION_LENGTH = 155;

    /** Descriptions shorter than this are only reported, never auto-fixed. */
    private const MIN_DESCRIPTION_LENGTH = 120;

    /** User decision: this page must never be auto-touched. */
    private const EXCLUDED_PAGE_SLUG = 'random_image';

    /**
     * Model classes scanned by this command, keyed by a short type label.
     *
     * @var array<string, class-string<Post|Page|Tag>>
     */
    private const MODEL_TYPES = [
        'post' => Post::class,
        'page' => Page::class,
        'tag' => Tag::class,
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        try {
            if ($force) {
                $this->info('Applying SEO metadata fixes...');
            } else {
                $this->info('Dry-run: no changes will be written. Re-run with --force to apply fixes.');
            }

            $rows = [];
            $notes = [];
            $cannotFix = [];
            $scanned = 0;
            $valid = 0;

            foreach (self::MODEL_TYPES as $type => $modelClass) {
                foreach ($this->recordsFor($modelClass) as $record) {
                    $scanned++;

                    $result = $this->fixRecord($record, $type, $force);

                    if ($result['changes'] === [] && $result['notes'] === [] && $result['cannot_fix'] === []) {
                        $valid++;

                        continue;
                    }

                    foreach ($result['changes'] as $change) {
                        $rows[] = [
                            $result['label'],
                            $change['field'],
                            $change['issue'],
                            $this->displayValue($change['old']),
                            $this->displayValue($change['new']),
                        ];
                    }

                    $notes = array_merge($notes, $result['notes']);
                    $cannotFix = array_merge($cannotFix, $result['cannot_fix']);
                }
            }

            if ($rows !== []) {
                $this->table(
                    ['Record', 'Field', 'Issue', 'Current', 'Will become'],
                    $rows
                );
            } else {
                $this->info('No fixable issues found.');
            }

            foreach ($notes as $note) {
                $this->line("  [info] {$note}");
            }

            foreach ($cannotFix as $note) {
                $this->warn("  [warning] {$note}");
            }

            $this->newLine();

            if ($force) {
                $this->info("Done. Scanned {$scanned} records ({$valid} already valid), applied ".count($rows).' fix(es).');
            } else {
                $this->info("Done. Scanned {$scanned} records ({$valid} already valid), found ".count($rows).' fixable issue(s). Nothing was written.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('FixSeoMetadata: unexpected error', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Published posts/pages plus all tags. Tags have no status column.
     *
     * @param  class-string<Post|Page|Tag>  $modelClass
     * @return Collection<int, Model>
     */
    private function recordsFor(string $modelClass): Collection
    {
        $query = $modelClass::query();

        // Only published content is public-facing, so only it needs SEO fixes.
        if ($modelClass !== Tag::class) {
            $query->where('status', PostStatus::Published);
        }

        return $query->get();
    }

    /**
     * Classify a single record's metadata issues and, when $force is set,
     * persist fixes via ->save(). Records with valid metadata are skipped.
     *
     * @param  Post|Page|Tag  $record
     * @return array{label: string, changes: list<array{field: string, issue: string, old: string, new: string}>, notes: list<string>, cannot_fix: list<string>}
     */
    private function fixRecord(Model $record, string $type, bool $force): array
    {
        $label = $this->labelFor($record, $type);
        $changes = [];
        $notes = [];
        $cannotFix = [];

        // Hard exclusion: the user decided this page is never auto-touched,
        // so we don't even classify it as fixable, just note that it exists.
        if ($type === 'page' && $record->getAttribute('slug') === self::EXCLUDED_PAGE_SLUG) {
            return [
                'label' => $label,
                'changes' => $changes,
                'notes' => ["{$label}: page slug '".self::EXCLUDED_PAGE_SLUG."' is excluded by user decision, left untouched."],
                'cannot_fix' => $cannotFix,
            ];
        }

        $title = $record->getAttribute('meta_title');

        if ($this->isEmpty($title)) {
            $newTitle = $this->fallbackTitleFor($record, $type);
            $changes[] = [
                'field' => 'meta_title',
                'issue' => 'empty',
                'old' => '',
                'new' => $newTitle,
            ];
            if ($force) {
                $record->meta_title = $newTitle;
            }
        } elseif (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $newTitle = $this->truncateTo($title, self::MAX_TITLE_LENGTH);
            $changes[] = [
                'field' => 'meta_title',
                'issue' => 'too_long',
                'old' => $title,
                'new' => $newTitle,
            ];
            if ($force) {
                $record->meta_title = $newTitle;
            }
        }

        $description = $record->getAttribute('meta_description');

        if ($this->isEmpty($description)) {
            $source = $this->descriptionSourceFor($record);

            if ($source === null) {
                // No usable source: never invent copy, leave empty and tell the user.
                $cannotFix[] = "{$label}: meta_description is empty and no excerpt/description source exists (left untouched).";
            } else {
                $changes[] = [
                    'field' => 'meta_description',
                    'issue' => 'empty',
                    'old' => '',
                    'new' => $source,
                ];
                if ($force) {
                    $record->meta_description = $source;
                }
            }
        } elseif (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $newDescription = $this->truncateTo($description, self::TRUNCATED_DESCRIPTION_LENGTH);
            $changes[] = [
                'field' => 'meta_description',
                'issue' => 'too_long',
                'old' => $description,
                'new' => $newDescription,
            ];
            if ($force) {
                $record->meta_description = $newDescription;
            }
        } elseif (mb_strlen($description) < self::MIN_DESCRIPTION_LENGTH) {
            // Intentionally short copy is left alone; we only report it.
            $notes[] = "{$label}: meta_description is only ".mb_strlen($description).' chars (< '.self::MIN_DESCRIPTION_LENGTH.') - left as-is.';
        }

        if ($force && $changes !== []) {
            $record->save();

            foreach ($changes as $change) {
                Log::info('FixSeoMetadata: applied SEO metadata fix', [
                    'type' => $type,
                    'id' => $record->getKey(),
                    'field' => $change['field'],
                    'issue' => $change['issue'],
                    'old' => $change['old'],
                    'new' => $change['new'],
                ]);
            }
        }

        return [
            'label' => $label,
            'changes' => $changes,
            'notes' => $notes,
            'cannot_fix' => $cannotFix,
        ];
    }

    /**
     * Human-readable label used in output tables and notes.
     *
     * @param  Post|Page|Tag  $record
     */
    private function labelFor(Model $record, string $type): string
    {
        $name = $type === 'tag'
            ? (string) $record->getAttribute('name')
            : (string) $record->getAttribute('title');

        return ucfirst($type).' #'.$record->getKey().' ('.$name.')';
    }

    /**
     * Default meta title used when a record has none. Tags get a descriptive
     * "Posts tagged with X" pattern; posts/pages reuse their own title.
     *
     * @param  Post|Page|Tag  $record
     */
    private function fallbackTitleFor(Model $record, string $type): string
    {
        if ($type === 'tag') {
            return $this->truncateTo(
                text: 'Posts tagged with '.$record->getAttribute('name').' - Daniel Petrica',
                maxLength: self::MAX_TITLE_LENGTH,
            );
        }

        return $this->truncateTo(
            text: (string) $record->getAttribute('title'),
            maxLength: self::MAX_TITLE_LENGTH,
        );
    }

    /**
     * Build a clean plain-text description from the best available source.
     * Returns null when no source exists so the caller never invents copy.
     *
     * @param  Post|Page|Tag  $record
     */
    private function descriptionSourceFor(Model $record): ?string
    {
        $source = $record instanceof Tag
            ? $record->getAttribute('description')
            : $record->getAttribute('excerpt');

        if ($this->isEmpty($source)) {
            return null;
        }

        // Strip markup and collapse whitespace/newlines into single spaces.
        $text = strip_tags(string: (string) $source);
        $text = preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $text) ?? $text;

        return $this->truncateTo(
            text: trim($text),
            maxLength: self::TRUNCATED_DESCRIPTION_LENGTH,
        );
    }

    /**
     * Truncate text to at most $maxLength characters, cutting at a word
     * boundary when possible and appending an ellipsis when truncated.
     */
    private function truncateTo(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        // Cut to the limit, then back off to the last space to avoid splitting words.
        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut).'…';
    }

    /**
     * Shorten a value for terminal display without affecting the stored data.
     */
    private function displayValue(string $value): string
    {
        if (mb_strlen($value) <= 50) {
            return $value === '' ? '(empty)' : $value;
        }

        return mb_substr($value, 0, 50).'…';
    }

    private function isEmpty(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
