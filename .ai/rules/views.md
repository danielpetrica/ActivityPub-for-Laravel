---
paths:
  - 'resources/views/**/*.blade.php'
---

# Views

## Never use @latest for CDN dependencies
Pin CDN script/link tags to specific versions (e.g. `lucide@0.344.0`). Using `@latest` causes silent breakage when major versions ship breaking changes (Lucide v1 removed all brand icons). Better: bundle dependencies locally via npm/Vite instead of CDN.
