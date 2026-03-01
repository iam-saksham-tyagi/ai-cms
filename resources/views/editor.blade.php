<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI CMS Editor</title>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    <script src="https://unpkg.com/grapesjs"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body, html { height: 100%; margin: 0; }
        .gjs-pn-panel {
            background-color: #0f172a !important;
            border: none !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25);
        }
        .gjs-block {
            border-radius: 8px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .gjs-block:hover {
            border-color: #a5b4fc;
            background: #818cf41a;
            transform: translateY(-1px);
        }
        .gjs-one-bg { background-color: #0f172a !important; }
        .gjs-two-color { color: #cbd5e1 !important; }
        .gjs-three-bg { background-color: #312e81 !important; }
        .gjs-four-color,
        .gjs-color-warn,
        .gjs-pn-btn.gjs-pn-active,
        .gjs-pn-btn:hover {
            color: #a5b4fc !important;
        }
        .gjs-cv-canvas {
            top: 0;
            background-color: #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            margin: 16px;
            width: calc(100% - 32px) !important;
            height: calc(100% - 32px) !important;
        }
        .gjs-frame { border: none !important; }
        
        #action-buttons {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 100;
            display: flex;
            gap: 10px;
        }

        #save-btn {
            padding: 10px 20px;
            background-color: #6366f1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #save-btn:hover { background-color: #4f46e5; }

        #back-btn {
            padding: 10px 16px;
            background-color: #111827;
            color: white;
            border: 1px solid #374151;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #back-btn:hover { background-color: #1f2937; }

        #ai-controls {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 220px;
            z-index: 100;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            display: flex;
            gap: 10px;
        }

        @media (max-width: 960px) {
            #ai-controls {
                right: 20px;
                bottom: 72px;
            }

            #action-buttons {
                bottom: 16px;
            }
        }
    </style>
</head>
<body>
    <div id="action-buttons">
        <a id="back-btn" href="/">← Dashboard</a>
        <button id="save-btn" onclick="saveContent()">💾 Save Page</button>
    </div>
    <div id="ai-controls">
        <input type="text" id="ai-prompt" placeholder="E.g., Build a dark mode pricing table with 3 tiers..." style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; outline: none; color: black;">
        <button id="ai-btn" onclick="generateAI()" style="background: #818cf8; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s;">✨ Generate UI</button>
    </div>
    <div id="gjs" style="height:100vh;"></div>

    <script>
        const editor = grapesjs.init({
            container: '#gjs',
            height: '100vh',
            width: 'auto',
            storageManager: false, 
            fromElement: false,
            plugins: ['gjs-blocks-basic'],
            pluginsOpts: { 'gjs-blocks-basic': { flexGrid: true } },
            canvas: {
                scripts: [
                    'https://cdn.tailwindcss.com',
                    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
                ]
            },
            style: {!! json_encode($page->css ?? $page->css_content ?? '') !!},
            components: {!! json_encode($page->html ?? $page->html_content ?? '') !!},
        });

        editor.BlockManager.add('my-first-block', {
            label: '<b>Simple Block</b>',
            content: '<div class="p-4 bg-blue-500 text-white text-center rounded">I am a Block! Edit me.</div>',
        });

        editor.BlockManager.add('hero-banner', {
            label: '<b>Hero Banner</b>',
            content: `
                <section class="py-20 px-6 text-center bg-indigo-50">
                    <h1 class="text-4xl font-bold text-slate-900">Build modern pages faster</h1>
                    <p class="mt-4 text-slate-600 max-w-2xl mx-auto">Drag, drop, and customize sections using AI and visual editing.</p>
                    <div class="mt-8 flex justify-center gap-3">
                        <a href="#" class="px-5 py-3 rounded-md bg-indigo-500 text-white font-semibold">Get started</a>
                        <a href="#" class="px-5 py-3 rounded-md border border-slate-300 text-slate-700 font-semibold">Learn more</a>
                    </div>
                </section>
            `,
        });

        editor.BlockManager.add('feature-grid', {
            label: '<b>Feature Grid</b>',
            content: `
                <section class="py-16 px-6 bg-white">
                    <div class="max-w-6xl mx-auto grid md:grid-cols-3 gap-6">
                        <div class="p-6 rounded-xl border border-slate-200"><h3 class="text-lg font-semibold">Fast Editing</h3><p class="mt-2 text-slate-600">Visual drag-and-drop with clean output.</p></div>
                        <div class="p-6 rounded-xl border border-slate-200"><h3 class="text-lg font-semibold">Reusable Blocks</h3><p class="mt-2 text-slate-600">Build once and reuse across pages.</p></div>
                        <div class="p-6 rounded-xl border border-slate-200"><h3 class="text-lg font-semibold">AI Assist</h3><p class="mt-2 text-slate-600">Generate sections from plain language.</p></div>
                    </div>
                </section>
            `,
        });

        editor.BlockManager.add('pricing-cards', {
            label: '<b>Pricing Cards</b>',
            content: `
                <section class="py-16 px-6 bg-slate-50">
                    <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-6">
                        <div class="p-6 bg-white border border-slate-200 rounded-xl"><h3 class="font-bold text-xl">Starter</h3><p class="mt-2 text-slate-600">$9/mo</p><button class="mt-4 px-4 py-2 bg-indigo-500 text-white rounded">Choose</button></div>
                        <div class="p-6 bg-white border-2 border-indigo-300 rounded-xl"><h3 class="font-bold text-xl">Pro</h3><p class="mt-2 text-slate-600">$29/mo</p><button class="mt-4 px-4 py-2 bg-indigo-500 text-white rounded">Choose</button></div>
                        <div class="p-6 bg-white border border-slate-200 rounded-xl"><h3 class="font-bold text-xl">Team</h3><p class="mt-2 text-slate-600">$79/mo</p><button class="mt-4 px-4 py-2 bg-indigo-500 text-white rounded">Choose</button></div>
                    </div>
                </section>
            `,
        });

        editor.BlockManager.add('contact-section', {
            label: '<b>Contact Form</b>',
            content: `
                <section class="py-16 px-6 bg-white">
                    <div class="max-w-3xl mx-auto">
                        <h2 class="text-3xl font-bold text-slate-900">Contact Us</h2>
                        <form class="mt-6 grid gap-4">
                            <input class="p-3 border border-slate-300 rounded" placeholder="Your name" />
                            <input class="p-3 border border-slate-300 rounded" placeholder="Email" />
                            <textarea class="p-3 border border-slate-300 rounded" rows="4" placeholder="Message"></textarea>
                            <button type="button" class="px-4 py-3 bg-indigo-500 text-white rounded font-semibold">Send message</button>
                        </form>
                    </div>
                </section>
            `,
        });

        editor.BlockManager.add('testimonial-strip', {
            label: '<b>Testimonials</b>',
            content: `
                <section class="py-14 px-6 bg-indigo-50">
                    <div class="max-w-5xl mx-auto grid md:grid-cols-2 gap-6">
                        <blockquote class="p-6 bg-white rounded-xl border border-indigo-100">“This editor cut our page build time in half.”<footer class="mt-3 text-sm text-slate-500">— Product Team</footer></blockquote>
                        <blockquote class="p-6 bg-white rounded-xl border border-indigo-100">“The drag-and-drop flow is smooth and easy to teach.”<footer class="mt-3 text-sm text-slate-500">— Marketing Lead</footer></blockquote>
                    </div>
                </section>
            `,
        });

        function saveContent() {
            const html = editor.getHtml();
            const css = editor.getCss();
            const components = JSON.stringify(editor.getComponents());

            fetch('/save-page/{{ $page->id }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ html: html, css: css, components: components })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message); 
            })
            .catch(error => console.error('Error:', error));
        }
        async function generateAI() {
            const btn = document.getElementById('ai-btn');
            const promptInput = document.getElementById('ai-prompt');
            const prompt = promptInput.value;

            if (!prompt) {
                alert('Type a prompt first!');
                return;
            }
            
            // Disable the button and show loading state
            btn.innerText = '⏳ Generating...';
            btn.style.background = '#4f46e5';
            btn.disabled = true;
            const selectedComponent = editor.getSelected();
            const existingHtml = editor.getHtml();
            const existingCss = editor.getCss();
            const selectedHtml = selectedComponent ? selectedComponent.toHTML() : '';

            try {
                const response = await fetch('/generate-ui', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        existing_html: existingHtml,
                        existing_css: existingCss,
                        selected_html: selectedHtml
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'AI request failed');
                }

                if (!data || typeof data.html !== 'string' || !data.html.trim()) {
                    throw new Error('AI returned empty or invalid HTML.');
                }

                let parsedData = null;

                try {
                    parsedData = JSON.parse(data.html);
                } catch (error) {
                    parsedData = null;
                }

                if (parsedData && typeof parsedData === 'object' && parsedData.attributes && typeof parsedData.attributes === 'object') {
                    if (!selectedComponent) {
                        throw new Error('Select a component first to update attributes.');
                    }

                    selectedComponent.addAttributes(parsedData.attributes);
                } else {
                    if (selectedComponent) {
                        selectedComponent.replaceWith(data.html);
                    } else {
                        editor.setComponents(data.html);
                    }
                }

                if (typeof data.css === 'string') {
                    editor.setStyle(data.css);
                }
                
                // Reset the UI
                promptInput.value = '';
                btn.innerText = '✨ Generate UI';
                btn.style.background = '#818cf8';
                btn.disabled = false;
            } catch (error) {
                console.error('Error:', error);
                btn.innerText = '✨ Generate UI';
                btn.style.background = '#818cf8';
                btn.disabled = false;
                alert(error?.message || 'Connection to AI failed.');
            }
        }
    </script>
</body>
</html>