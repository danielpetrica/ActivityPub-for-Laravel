<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fediverse Dashboard</title>
    <style>
        /* =========================================================
           CSS Custom Properties
           ========================================================= */
        :root {
            --color-primary: #ea580c;
            --color-primary-light: #fb923c;
            --color-primary-dark: #c2410c;
            --color-danger: #dc2626;
            --color-danger-light: #fecaca;
            --color-success: #16a34a;
            --color-success-light: #dcfce7;
            --color-success-border: #86efac;
            --color-warning: #d97706;
            --color-warning-light: #fef3c7;
            --color-warning-border: #fcd34d;

            --color-gray-50: #f9fafb;
            --color-gray-100: #f3f4f6;
            --color-gray-200: #e5e7eb;
            --color-gray-300: #d1d5db;
            --color-gray-400: #9ca3af;
            --color-gray-500: #6b7280;
            --color-gray-600: #4b5563;
            --color-gray-700: #374151;
            --color-gray-800: #1f2937;
            --color-gray-900: #111827;
            --color-gray-950: #030712;

            --color-white: #ffffff;
            --color-black: #000000;

            --color-blue: #2563eb;
            --color-blue-light: #dbeafe;
            --color-green: #16a34a;
            --color-green-light: #dcfce7;
            --color-yellow: #ca8a04;
            --color-yellow-light: #fef9c3;
            --color-red: #dc2626;
            --color-red-light: #fee2e2;
            --color-purple: #9333ea;
            --color-purple-light: #f3e8ff;
            --color-pink: #db2777;
            --color-pink-light: #fce7f3;
            --color-indigo: #4f46e5;
            --color-indigo-light: #e0e7ff;

            --sidebar-width: 240px;
            --radius: 0.5rem;
            --radius-sm: 0.375rem;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --transition: 150ms ease;
        }

        /* =========================================================
           Reset & Base
           ========================================================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.5;
            color: var(--color-gray-900);
            background: var(--color-gray-50);
            min-height: 100vh;
        }

        a {
            color: var(--color-indigo);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        /* =========================================================
           Layout
           ========================================================= */
        .app {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: var(--sidebar-width);
            background: var(--color-gray-900);
            color: var(--color-white);
            display: flex;
            flex-direction: column;
            z-index: 10;
            transition: transform var(--transition);
        }

        .main {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .main.expanded {
            margin-left: 0;
        }

        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 20;
            background: var(--color-gray-900);
            color: var(--color-white);
            border: none;
            border-radius: var(--radius-sm);
            width: 2.5rem;
            height: 2.5rem;
            font-size: 1.25rem;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
        }

        /* =========================================================
           Responsive: Mobile
           ========================================================= */
        @media (max-width: 767px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar:not(.hidden) {
                transform: translateX(0);
            }

            .sidebar.hidden {
                transform: translateX(-100%);
            }

            .sidebar-toggle {
                display: flex;
            }

            .main {
                margin-left: 0;
                padding: 1rem;
                padding-top: 4rem;
            }

            .main.expanded {
                margin-left: 0;
            }
        }

        @media (min-width: 768px) {
            .sidebar {
                transform: translateX(0);
            }

            .sidebar.hidden {
                transform: translateX(0);
            }

            .sidebar-toggle {
                display: none;
            }

            .main {
                margin-left: var(--sidebar-width);
            }
        }

        /* =========================================================
           Sidebar
           ========================================================= */
        .sidebar-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--color-gray-700);
        }

        .sidebar-header h1 {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: var(--color-gray-400);
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            overflow-y: auto;
        }

        .sidebar-nav hr {
            border: none;
            border-top: 1px solid var(--color-gray-700);
            margin: 0.5rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--color-gray-300);
            text-decoration: none;
            transition: background var(--transition), color var(--transition);
        }

        .nav-link:hover {
            background: var(--color-gray-800);
            color: var(--color-white);
            text-decoration: none;
        }

        .nav-link.active {
            background: var(--color-gray-700);
            color: var(--color-white);
        }

        .nav-link svg {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--color-gray-700);
        }

        .sidebar-footer p {
            font-size: 0.75rem;
            color: var(--color-gray-500);
        }

        /* =========================================================
           Typography
           ========================================================= */
        .h1 { font-size: 1.875rem; font-weight: 700; line-height: 1.2; }
        .h2 { font-size: 1.5rem; font-weight: 700; line-height: 1.3; }
        .h3 { font-size: 1.25rem; font-weight: 600; line-height: 1.4; }
        .h4 { font-size: 1.125rem; font-weight: 600; line-height: 1.4; }
        .h5 { font-size: 1rem; font-weight: 600; line-height: 1.5; }
        .h6 { font-size: 0.875rem; font-weight: 600; line-height: 1.5; }
        .text { font-size: 1rem; line-height: 1.5; }
        .text-muted { color: var(--color-gray-500); }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-center { text-align: center; }

        /* =========================================================
           Cards
           ========================================================= */
        .card {
            background: var(--color-white);
            border: 1px solid var(--color-gray-200);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--color-gray-200);
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* =========================================================
           Buttons
           ========================================================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            line-height: 1.5;
            border-radius: var(--radius);
            border: 1px solid transparent;
            cursor: pointer;
            transition: background var(--transition), border-color var(--transition), color var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }

        .btn:hover {
            text-decoration: none;
        }

        .btn-primary {
            background: var(--color-primary);
            color: var(--color-white);
            border-color: var(--color-primary);
        }

        .btn-primary:hover {
            background: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
        }

        .btn-danger {
            background: var(--color-danger);
            color: var(--color-white);
            border-color: var(--color-danger);
        }

        .btn-danger:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }

        .btn-secondary {
            background: var(--color-white);
            color: var(--color-gray-700);
            border-color: var(--color-gray-300);
        }

        .btn-secondary:hover {
            background: var(--color-gray-50);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }

        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-outline {
            background: transparent;
            color: var(--color-primary);
            border-color: var(--color-primary);
        }

        .btn-outline:hover {
            background: var(--color-primary);
            color: var(--color-white);
        }

        /* =========================================================
           Forms
           ========================================================= */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-gray-700);
        }

        .form-input,
        .form-select {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: var(--color-gray-900);
            background: var(--color-white);
            border: 1px solid var(--color-gray-300);
            border-radius: var(--radius-sm);
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        textarea.form-input {
            min-height: 5rem;
            resize: vertical;
        }

        /* =========================================================
           Grid
           ========================================================= */
        .grid {
            display: grid;
        }

        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .grid-4 { grid-template-columns: repeat(4, 1fr); }

        @media (max-width: 767px) {
            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }
        }

        .gap-1 { gap: 0.25rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-3 { gap: 0.75rem; }
        .gap-4 { gap: 1rem; }
        .gap-5 { gap: 1.25rem; }
        .gap-6 { gap: 1.5rem; }

        /* =========================================================
           Spacing
           ========================================================= */
        .p-1 { padding: 0.25rem; }
        .p-2 { padding: 0.5rem; }
        .p-3 { padding: 0.75rem; }
        .p-4 { padding: 1rem; }
        .p-5 { padding: 1.25rem; }
        .p-6 { padding: 1.5rem; }
        .p-7 { padding: 1.75rem; }
        .p-8 { padding: 2rem; }

        .px-1 { padding-left: 0.25rem; padding-right: 0.25rem; }
        .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
        .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .px-5 { padding-left: 1.25rem; padding-right: 1.25rem; }
        .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }

        .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .py-5 { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }

        .m-1 { margin: 0.25rem; }
        .m-2 { margin: 0.5rem; }
        .m-3 { margin: 0.75rem; }
        .m-4 { margin: 1rem; }
        .m-5 { margin: 1.25rem; }
        .m-6 { margin: 1.5rem; }

        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 0.75rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-5 { margin-bottom: 1.25rem; }
        .mb-6 { margin-bottom: 1.5rem; }
        .mb-0 { margin-bottom: 0; }

        .mt-1 { margin-top: 0.25rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-3 { margin-top: 0.75rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-5 { margin-top: 1.25rem; }
        .mt-6 { margin-top: 1.5rem; }
        .mt-7 { margin-top: 1.75rem; }
        .mt-8 { margin-top: 2rem; }

        /* =========================================================
           Height & Width Utilities
           ========================================================= */
        .h-4 { height: 1rem; }
        .h-5 { height: 1.25rem; }
        .h-6 { height: 1.5rem; }
        .h-8 { height: 2rem; }
        .h-10 { height: 2.5rem; }
        .h-12 { height: 3rem; }
        .h-16 { height: 4rem; }
        .w-4 { width: 1rem; }
        .w-5 { width: 1.25rem; }
        .w-6 { width: 1.5rem; }
        .w-8 { width: 2rem; }
        .w-10 { width: 2.5rem; }
        .w-12 { width: 3rem; }
        .w-16 { width: 4rem; }

        /* =========================================================
           Flexbox
           ========================================================= */
        .flex { display: flex; }
        .flex-1 { flex: 1; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .flex-col { flex-direction: column; }
        .flex-wrap { flex-wrap: wrap; }

        /* =========================================================
           Badges
           ========================================================= */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
            white-space: nowrap;
        }

        .badge-blue {
            background: var(--color-blue-light);
            color: var(--color-blue);
        }

        .badge-green {
            background: var(--color-green-light);
            color: var(--color-green);
        }

        .badge-yellow {
            background: var(--color-yellow-light);
            color: var(--color-yellow);
        }

        .badge-red {
            background: var(--color-red-light);
            color: var(--color-red);
        }

        .badge-purple {
            background: var(--color-purple-light);
            color: var(--color-purple);
        }

        .badge-pink {
            background: var(--color-pink-light);
            color: var(--color-pink);
        }

        .badge-indigo {
            background: var(--color-indigo-light);
            color: var(--color-indigo);
        }

        .badge-gray {
            background: var(--color-gray-100);
            color: var(--color-gray-600);
        }

        /* =========================================================
           Tables
           ========================================================= */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.875rem;
        }

        .table th {
            font-weight: 600;
            color: var(--color-gray-500);
            border-bottom: 1px solid var(--color-gray-200);
            background: var(--color-gray-50);
        }

        .table td {
            border-bottom: 1px solid var(--color-gray-100);
        }

        .table-row:hover {
            background: var(--color-gray-50);
        }

        /* =========================================================
           Alerts
           ========================================================= */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: var(--color-success-light);
            color: var(--color-success);
            border-color: var(--color-success-border);
        }

        .alert-error {
            background: var(--color-danger-light);
            color: var(--color-danger);
            border-color: #fca5a5;
        }

        .alert-warning {
            background: var(--color-warning-light);
            color: var(--color-warning);
            border-color: var(--color-warning-border);
        }

        /* =========================================================
           Utilities
           ========================================================= */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .hidden { display: none; }
        .block { display: block; }
        .inline-block { display: inline-block; }
        .w-full { width: 100%; }
        .rounded { border-radius: var(--radius); }
        .shadow { box-shadow: var(--shadow); }
        .border { border: 1px solid var(--color-gray-200); }
        .overflow-auto { overflow: auto; }
        .overflow-hidden { overflow: hidden; }
        .relative { position: relative; }
        .absolute { position: absolute; }

        /* =========================================================
           ActivityPub Post Content
           ========================================================= */
        .post-content p { margin-bottom: 0.5em; }
        .post-content p:last-child { margin-bottom: 0; }
        .post-content a { color: var(--color-indigo); text-decoration: none; }
        .post-content a:hover { text-decoration: underline; }
        .post-content blockquote {
            border-left: 3px solid var(--color-gray-200);
            padding-left: 1em;
            margin: 0.5em 0;
            color: var(--color-gray-500);
        }
        .post-content pre {
            background: var(--color-gray-50);
            border: 1px solid var(--color-gray-200);
            border-radius: var(--radius);
            padding: 0.75rem;
            overflow-x: auto;
            font-size: 0.875rem;
        }
        .post-content code {
            background: var(--color-gray-100);
            padding: 0.125rem 0.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.875em;
        }
        .post-content pre code {
            background: none;
            padding: 0;
        }
        .post-content ul, .post-content ol {
            margin: 0.5em 0;
            padding-left: 1.5em;
        }
        .post-content li { margin-bottom: 0.25em; }
        .post-content h1, .post-content h2, .post-content h3,
        .post-content h4, .post-content h5, .post-content h6 {
            font-weight: 600;
            margin: 0.75em 0 0.25em;
        }

        /* =========================================================
           Mastodon Microformat Classes
           ========================================================= */
        .h-card { display: inline; }
        .u-url { color: var(--color-indigo); text-decoration: none; }
        .u-url:hover { text-decoration: underline; }
        .mention { color: var(--color-indigo); }
        .invisible {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        .ellipsis::after { content: "\2026"; }
        .invisible + .ellipsis::after { content: none; }

        /* =========================================================
           Footer
           ========================================================= */
        .footer {
            margin-top: 2rem;
            padding: 1rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--color-gray-500);
            border-top: 1px solid var(--color-gray-200);
        }

        .footer a {
            color: var(--color-indigo);
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="app">
        <button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('hidden')">☰</button>

        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Fediverse</h1>
                <p>{{ $actor->getPreferredUsername() }}</p>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('fediverse.dashboard') }}"
                   class="nav-link {{ request()->routeIs('fediverse.dashboard') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <a href="{{ route('fediverse.timeline') }}"
                   class="nav-link {{ request()->routeIs('fediverse.timeline') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    Timeline
                </a>

                <a href="{{ route('fediverse.inbox') }}"
                   class="nav-link {{ request()->routeIs('fediverse.inbox') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    Inbox
                </a>

                <a href="{{ route('fediverse.following') }}"
                   class="nav-link {{ request()->routeIs('fediverse.following') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Following
                </a>

                <a href="{{ route('fediverse.servers') }}"
                   class="nav-link {{ request()->routeIs('fediverse.servers') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    Servers
                </a>

                <a href="{{ route('fediverse.discover') }}"
                   class="nav-link {{ request()->routeIs('fediverse.discover*') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Discover
                </a>

                <a href="{{ route('fediverse.outbox') }}"
                   class="nav-link {{ request()->routeIs('fediverse.outbox') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    Outbox
                </a>

                <hr>

                <a href="{{ route('fediverse.profile') }}"
                   class="nav-link {{ request()->routeIs('fediverse.profile') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </a>

                <a href="{{ route('fediverse.status') }}"
                   class="nav-link {{ request()->routeIs('fediverse.status') ? 'active' : '' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Status
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>activitypub v1.0</p>
            </div>
        </aside>

        <main class="main">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            @yield('content')

            <footer class="footer">
                <p>Looking for a Laravel developer? <a href="https://danielpetrica.com/work-with-me/">Work with me</a></p>
                <p>Made with love by Daniel Petrica</p>
            </footer>
        </main>
    </div>
</body>
</html>
