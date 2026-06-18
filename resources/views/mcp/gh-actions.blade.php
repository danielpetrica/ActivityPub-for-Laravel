<x-layouts.app
    title="GitHub Actions Version Checker MCP Server | Daniel Petrica"
    description="MCP server that gives LLMs fast, structured access to GitHub Actions version data — no more guessing stale versions."
>
    <div class="py-12 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <div class="inline-flex items-center justify-center p-3 bg-primary-100 rounded-2xl mb-6">
                    <x-ui.icon name="git-pull-request" size="10" class="text-primary-600" />
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-neutral-900 tracking-tight mb-4">
                    GitHub Actions <span class="text-primary-600">Version Checker</span>
                </h1>
                <p class="text-xl text-neutral-600 max-w-2xl mx-auto">
                    An MCP server that gives AI agents fast, structured access to GitHub Actions version data — no more guessing or recommending stale versions.
                </p>
                <div class="flex flex-wrap justify-center gap-3 mt-8">
                    <x-ui.badge size="sm">Free for All</x-ui.badge>
                    <x-ui.badge size="sm" variant="success">v1.0.0</x-ui.badge>
                    <x-ui.badge size="sm">No Auth Required</x-ui.badge>
                    <x-ui.badge size="sm">Redis Cached</x-ui.badge>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6 mb-20">
                <x-ui.card class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-primary-50 rounded-lg">
                            <x-ui.icon name="zap" size="5" class="text-primary-600" />
                        </div>
                        <h3 class="font-bold text-neutral-900">Blazing Fast</h3>
                    </div>
                    <p class="text-neutral-600 text-sm">
                        All API requests fire concurrently via <code class="text-primary-600 font-mono text-xs bg-primary-50 px-1 py-0.5 rounded">Http::async()</code>. No sequential loops — get version data for 20 popular actions in under a second.
                    </p>
                </x-ui.card>

                <x-ui.card class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-green-50 rounded-lg">
                            <x-ui.icon name="check-circle" size="5" class="text-green-600" />
                        </div>
                        <h3 class="font-bold text-neutral-900">Curated Data</h3>
                    </div>
                    <p class="text-neutral-600 text-sm">
                        Hand-picked list of popular GitHub Actions organized by category — CI, deployment, security, Docker, Node.js, and utility. Updated through a daily background command.
                    </p>
                </x-ui.card>

                <x-ui.card class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-amber-50 rounded-lg">
                            <x-ui.icon name="shield" size="5" class="text-amber-600" />
                        </div>
                        <h3 class="font-bold text-neutral-900">Resilient</h3>
                    </div>
                    <p class="text-neutral-600 text-sm">
                        24-hour Redis caching across categories, 6-hour per-action caching, rate-limit detection with graceful degradation, and negative caching for 404s. Individual failures never block the full response.
                    </p>
                </x-ui.card>

                <x-ui.card class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-purple-50 rounded-lg">
                            <x-ui.icon name="brain" size="5" class="text-purple-600" />
                        </div>
                        <h3 class="font-bold text-neutral-900">Zero Hallucination</h3>
                    </div>
                    <p class="text-neutral-600 text-sm">
                        AI agents get validated JSON results straight from the GitHub API instead of guessing outdated versions from training data. Every version is real and up to date.
                    </p>
                </x-ui.card>
            </div>

            <div class="mb-20">
                <h2 class="text-3xl font-extrabold text-neutral-900 mb-8">Available Tools</h2>

                <div class="space-y-6">
                    <x-ui.card class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-neutral-900 text-lg">GetPopularActions</h3>
                                    <x-ui.badge size="xs" variant="secondary">GET</x-ui.badge>
                                </div>
                                <p class="text-sm text-neutral-500">Browse curated popular actions by category</p>
                            </div>
                        </div>
                        <pre class="bg-neutral-900 text-neutral-100 rounded-xl p-4 text-sm overflow-x-auto"><code class="text-green-400">{
  "category": "ci",
  "limit": 5
}</code></pre>
                        <p class="text-sm text-neutral-500 mt-3">Categories: <code class="text-primary-600 font-mono text-xs">ci</code>, <code class="text-primary-600 font-mono text-xs">deployment</code>, <code class="text-primary-600 font-mono text-xs">security</code>, <code class="text-primary-600 font-mono text-xs">utility</code>, <code class="text-primary-600 font-mono text-xs">docker</code>, <code class="text-primary-600 font-mono text-xs">node</code>, <code class="text-primary-600 font-mono text-xs">all</code></p>
                    </x-ui.card>

                    <x-ui.card class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-neutral-900 text-lg">GetActionVersions</h3>
                                    <x-ui.badge size="xs" variant="secondary">GET</x-ui.badge>
                                </div>
                                <p class="text-sm text-neutral-500">Fetch latest version and full release history for any GitHub Action</p>
                            </div>
                        </div>
                        <pre class="bg-neutral-900 text-neutral-100 rounded-xl p-4 text-sm overflow-x-auto"><code class="text-green-400">{
  "action": "actions/checkout"
}</code></pre>
                        <p class="text-sm text-neutral-500 mt-3">Accepts <code class="text-primary-600 font-mono text-xs">owner/repo</code> format or full GitHub URL</p>
                    </x-ui.card>
                </div>
            </div>

            <div class="bg-white border border-neutral-200 rounded-3xl p-8 md:p-12 shadow-sm">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-neutral-900 mb-4">Quick Installation</h2>
                    <p class="text-neutral-600">
                        Add this configuration to your MCP client (Cursor, Claude Desktop, Windsurf, etc.)
                    </p>
                </div>

                <div class="bg-neutral-900 rounded-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 bg-neutral-800 border-b border-neutral-700">
                        <span class="text-xs text-neutral-400 font-mono">mcp-servers.json</span>
                        <span class="inline-flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        </span>
                    </div>
                    <pre class="p-4 text-sm overflow-x-auto"><code class="text-green-400">{
  "mcpServers": {
    "gh-actions": {
      "type": "streamable-http",
      "url": "{{ url('/mcp/gh-actions') }}",
      "note": "GitHub Actions Version Checker MCP"
    }
  }
}</code></pre>
                </div>

                <div class="mt-8 p-4 bg-amber-50 border border-amber-100 rounded-xl">
                    <p class="text-sm text-amber-800">
                        <strong>Tip:</strong> No authentication key is required. This server is free for the community. After adding, your client will list tools like <code class="text-amber-900 font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">get-popular-actions</code> and <code class="text-amber-900 font-mono text-xs bg-amber-100 px-1 py-0.5 rounded">get-action-versions</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>