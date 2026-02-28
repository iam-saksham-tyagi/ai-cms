<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI CMS Editor</title>
    <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body, html { height: 100%; margin: 0; }
        .gjs-cv-canvas { top: 0; width: 100%; height: 100%; }
        .gjs-block { width: auto; height: auto; min-height: auto; }
        
        /* Style for our new Save Button */
        #save-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 100;
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #save-btn:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>
    <button id="save-btn" onclick="saveContent()">💾 Save Page</button>
    <div style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 100; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; gap: 10px; width: 600px;">
        <input type="text" id="ai-prompt" placeholder="E.g., Build a dark mode pricing table with 3 tiers..." style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; outline: none; color: black;">
        <button id="ai-btn" onclick="generateAI()" style="background: #a855f7; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s;">✨ Generate UI</button>
    </div>
    <div id="gjs" style="height:100vh;"></div>

    <script src="https://unpkg.com/grapesjs"></script>
    <script>
        // Grab the saved JSON from Laravel. If it's empty, use 'null'.
        const savedComponents = {!! $page->json_content ?: 'null' !!};

        const editor = grapesjs.init({
            container: '#gjs',
            height: '100vh',
            width: 'auto',
            storageManager: false, 
            fromElement: false,
            canvas: {
                scripts: ['https://cdn.tailwindcss.com']
            },
            // LOGIC: If we have saved data, load it. If not, load the default welcome message.
            components: savedComponents || `
                <div style="padding: 50px; text-align: center;">
                    <h1 style="font-size: 2rem; color: #555;">Welcome to your AI CMS</h1>
                    <p>Drag a block from the right, then hit Save!</p>
                </div>
            `,
        });

        editor.BlockManager.add('my-first-block', {
            label: '<b>Simple Block</b>',
            content: '<div class="p-4 bg-blue-500 text-white text-center rounded">I am a Block! Edit me.</div>',
        });

        function saveContent() {
            const html = editor.getHtml();
            const css = editor.getCss();
            const components = JSON.stringify(editor.getComponents());

            fetch('/save-page', {
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
            btn.style.background = '#6b21a8';
            btn.disabled = true;
            const currentHtml = editor.getHtml();

            try {
                const response = await fetch('/generate-ui', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        prompt: prompt,
                        existing_html: currentHtml
                    })
                });

                const data = await response.json();
                /// This tells GrapesJS to REPLACE the canvas with the newly edited code
                editor.setComponents(data.html);
                
                // Reset the UI
                promptInput.value = '';
                btn.innerText = '✨ Generate UI';
                btn.style.background = '#a855f7';
                btn.disabled = false;
            } catch (error) {
                console.error('Error:', error);
                btn.innerText = '✨ Generate UI';
                btn.style.background = '#a855f7';
                btn.disabled = false;
                alert('Connection to AI failed.');
            }
        }
    </script>
</body>
</html>