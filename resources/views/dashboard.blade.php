<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard | AI CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="min-h-screen flex">
        <aside class="w-72 bg-slate-950 text-slate-100 p-6 hidden lg:flex lg:flex-col">
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-400">AI CMS</p>
                <h1 class="mt-2 text-2xl font-bold">Workspace</h1>
            </div>

            <nav class="mt-10 space-y-2">
                <a href="/" class="block rounded-xl bg-slate-800 px-4 py-3 text-sm font-medium">Dashboard</a>
                <a href="#" class="block rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-slate-900">Projects</a>
                <a href="/templates" class="block rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-slate-900">Templates</a>
                <a href="/settings" class="block rounded-xl px-4 py-3 text-sm text-slate-300 hover:bg-slate-900">Settings</a>
            </nav>

            <div class="mt-auto rounded-2xl border border-slate-800 bg-slate-900 p-4 text-sm text-slate-300">
                Build premium websites faster with your AI-powered editor.
            </div>
        </aside>

        <main class="flex-1 p-6 md:p-10">
            @if(session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <header class="rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <p class="text-sm text-slate-500">SaaS Dashboard</p>
                <h2 class="mt-1 text-3xl font-bold">Welcome back</h2>
                <p class="mt-2 text-slate-600">Create and manage all your website projects from one place.</p>
            </header>

            <section class="mt-8 rounded-2xl bg-white p-6 shadow-sm border border-slate-200">
                <h3 class="text-lg font-semibold">Create a New Project</h3>
                <form action="/project/create" method="POST" class="mt-4 flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input
                        type="text"
                        name="title"
                        placeholder="Enter project title"
                        required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                    >
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-700 transition"
                    >
                        Create Project
                    </button>
                </form>
            </section>

            <section class="mt-8">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xl font-semibold">Your Projects</h3>
                    <span class="text-sm text-slate-500">{{ $projects->count() }} total</span>
                </div>

                @forelse($projects as $project)
                    @if($loop->first)
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    @endif

                    <article class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-3">
                            <h4 class="text-lg font-semibold text-slate-900">{{ $project->title }}</h4>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">Project</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-500">Last edited {{ $project->updated_at->diffForHumans() }}</p>
                        <div class="mt-5 flex items-center gap-3">
                            <a
                                href="/editor/{{ $project->id }}"
                                class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition"
                            >
                                Open Editor
                            </a>

                            <a href="/p/{{ $project->id }}" target="_blank" class="px-3 py-1.5 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-md transition">Live Site ↗</a>

                            <form action="/project/{{ $project->id }}" method="POST" onsubmit="return confirm('Delete this project permanently?');">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100 transition"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>
                    </article>

                    @if($loop->last)
                        </div>
                    @endif
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-sm">
                        <div class="mx-auto w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 grid place-items-center text-2xl">✦</div>
                        <h4 class="mt-4 text-xl font-semibold text-slate-900">No projects yet</h4>
                        <p class="mt-2 text-slate-500">Create your first project above to start building with AI.</p>
                    </div>
                @endforelse
            </section>
        </main>
    </div>
</body>
</html>
