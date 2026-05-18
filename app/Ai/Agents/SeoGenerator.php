<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Provider('opencode')]
#[Model('deepseek-v4-flash')]
#[MaxTokens(1024)]
#[Temperature(0.3)]
#[Timeout(120)]
final class SeoGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
You are an SEO expert. Given a page title and its content, generate optimized SEO metadata.

Follow these rules:
- meta_title: 50-60 characters, include primary keyword, brand name if relevant
- meta_description: 150-160 characters, compelling summary with call to action
- og_title: optimized for social sharing (slightly more engaging than meta_title), max 60 chars
- og_description: optimized for social sharing (slightly more descriptive), max 200 chars
- twitter_title: max 70 characters, engaging for Twitter/X cards
- twitter_description: max 200 characters, concise and actionable

Return ONLY the structured JSON object with these exact keys. No markdown, no code fences.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'meta_title' => $schema->string()->required(),
            'meta_description' => $schema->string()->required(),
            'og_title' => $schema->string()->required(),
            'og_description' => $schema->string()->required(),
            'twitter_title' => $schema->string()->required(),
            'twitter_description' => $schema->string()->required(),
        ];
    }
}
