<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Page;
use DOMDocument;
use DOMElement;

class EditorController extends Controller
{
    public function index($id)
    {
        $page = Page::findOrFail($id);

        return view('editor', ['page' => $page]);
    }

    public function save(Request $request, $id)
    {
        $page = Page::findOrFail($id);

        $page->update([
            'html_content' => $request->input('html'),
            'css_content' => $request->input('css'),
            'json_content' => $request->input('components'),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Page saved successfully!']);
    }
    public function generate(Request $request)
    {
        $prompt = trim((string) $request->input('prompt', ''));
        $existingHtml = (string) $request->input('existing_html', '');
        $existingCss = (string) $request->input('existing_css', '');
        $selectedHtml = (string) $request->input('selected_html', '');

        if ($prompt === '') {
            return response()->json([
                'message' => 'Type a prompt first.',
                'html' => $existingHtml,
            ], 422);
        }

        if (!trim($existingHtml)) {
            return response()->json([
                'message' => 'Current page HTML is empty, so there is nothing to edit yet.',
                'html' => $existingHtml,
            ], 422);
        }

        $apiKey = config('services.groq.key');
        $primaryModel = config('services.groq.model', 'moonshotai/kimi-k2-instruct-0905');
        $fallbackModels = config('services.groq.fallback_models', []);
        $models = array_values(array_unique(array_filter(array_merge([$primaryModel], $fallbackModels))));

        if (!$apiKey) {
            return response()->json([
                'message' => 'Missing GROQ_API_KEY in your environment.',
                'html' => $existingHtml,
            ], 500);
        }

        $focusBlock = trim($selectedHtml)
            ? "FOCUS AREA (if relevant, prioritize edits here first):\n{$selectedHtml}\n"
            : '';

        $isComponentEdit = trim($selectedHtml) !== '';
        $outputScopeRule = $isComponentEdit
            ? "OUTPUT SCOPE: Return ONLY the updated HTML for the selected component (focus area), not the entire page."
            : "OUTPUT SCOPE: Return the updated HTML for the full page content.";

        $instruction = "You are an Expert Frontend Developer editing a specific selected HTML component. Your ONLY job is to produce production-quality layout improvements while preserving structure.

    STRICT RULES:
    1) Return ONLY raw valid HTML. Never return markdown, code fences, JSON, explanations, or comments.
    2) Preserve the exact DOM hierarchy, tag order, IDs, links, images, child elements, and nested wrappers unless the user explicitly says 'delete', 'remove', or 'hide'.
    3) Prefer targeted edits. Do not rewrite the whole component when a small class/text change solves the request.
    4) For requests like 'clean', 'modernize', or 'make responsive', use Tailwind classes only (layout, spacing, typography, responsive breakpoints, alignment, sizing).
    5) Preserve existing content and meaning. Only change text copy when the user explicitly asks for text/content changes.
    6) Keep all interactive elements intact (buttons, anchors, forms, inputs). Never drop href/src/alt/name/value/aria attributes unless explicitly requested.
    7) Maintain semantic correctness: headings stay headings, lists stay lists, buttons stay buttons, links stay links.
    8) Ensure responsive behavior with mobile-first classes: use sensible `sm:`, `md:`, `lg:` adjustments for spacing, grid/flex, and typography when relevant.
    9) Improve visual consistency using Tailwind scale values (e.g., spacing like `p-4`, `px-6`, `gap-4`; radius like `rounded-lg`; shadows and borders where appropriate).
    10) Keep class naming clean: remove contradictory or duplicate utility classes when editing, but do not remove required structural classes.
    11) Do not add inline styles, custom CSS, `<style>` tags, `<script>` tags, or external libraries.
    12) Preserve accessibility: keep alt text, labels, and readable contrast classes where possible; do not remove accessibility attributes.
    13) If a selected block is provided, prioritize edits inside that block first while still preserving the full HTML structure.
    14) If the user request conflicts with these rules, follow the user intent with the safest minimal change and still preserve structure where possible.

    OUTPUT CONTRACT:
    - Output must be only the final updated raw HTML fragment.
    - No preface, no explanation, no markdown.
    {$outputScopeRule}

    REFERENCE CONTEXT (for grounding; do not echo this text):
    {$focusBlock}
    CURRENT PAGE HTML:
    {$existingHtml}

    CURRENT PAGE CSS:
    {$existingCss}";

        try {
            $requestGroq = function (string $instructionText) use ($prompt, $apiKey, $models) {
                $requestBody = [
                    'messages' => [
                        ['role' => 'user', 'content' => $instructionText . "\n\nUser Request: " . $prompt],
                    ],
                    'model' => $models[0] ?? 'moonshotai/kimi-k2-instruct-0905',
                    'temperature' => 0.1,
                ];

                $errors = [];

                foreach ($models as $modelName) {
                    $requestBody['model'] = $modelName;
                    $url = 'https://api.groq.com/openai/v1/chat/completions';

                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$apiKey}",
                    ])->post($url, $requestBody);

                    if ($response->ok()) {
                        return [
                            'response' => $response,
                            'model' => $modelName,
                            'errors' => $errors,
                        ];
                    }

                    $errors[] = [
                        'model' => $modelName,
                        'status' => $response->status(),
                        'message' => $response->json()['error']['message'] ?? 'Unknown API Error',
                    ];
                }

                return [
                    'response' => null,
                    'model' => null,
                    'errors' => $errors,
                ];
            };

            $attempt = $requestGroq($instruction);
            $response = $attempt['response'];

            if (!$response) {
                $lastError = end($attempt['errors']);
                $status = $lastError['status'] ?? 500;
                $message = $lastError['message'] ?? 'Unknown API Error';
                $triedModels = implode(', ', array_column($attempt['errors'], 'model'));

                return response()->json([
                    'message' => "Groq Error ({$status}): {$message}. Tried models: {$triedModels}",
                    'html' => $existingHtml,
                    'css' => $existingCss,
                ], 502);
            }

            $data = $response->json();
            $aiContent = $data['choices'][0]['message']['content'] ?? null;

            if (!$aiContent) {
                return response()->json([
                    'message' => 'AI reached but no content returned.',
                    'html' => $existingHtml,
                    'css' => $existingCss,
                ], 502);
            }

            $cleanAiContent = preg_replace('/```(?:json|html|css)?\s*/i', '', (string) $aiContent);
            $cleanAiContent = preg_replace('/```/', '', (string) $cleanAiContent);
            $cleanAiContent = trim((string) $cleanAiContent);
            $attributePayload = json_decode($cleanAiContent, true);

            if (is_array($attributePayload) && isset($attributePayload['attributes']) && is_array($attributePayload['attributes'])) {
                return response()->json([
                    'html' => json_encode(['attributes' => $attributePayload['attributes']]),
                    'css' => $existingCss,
                ]);
            }

            [$aiHtml, $aiCss] = $this->parseAiHtmlCssPayload($aiContent, $existingCss);

            if (!$aiHtml) {
                return response()->json([
                    'message' => 'AI returned invalid payload. Original layout preserved.',
                    'html' => $existingHtml,
                    'css' => $existingCss,
                ], 422);
            }

            $originalForSafetyCheck = $isComponentEdit ? $selectedHtml : $existingHtml;
            [$isSafe, $safetyMessage] = $this->passesLayoutSafetyChecks($originalForSafetyCheck, $aiHtml, $prompt);
            if (!$isSafe) {
                return response()->json([
                    'message' => $safetyMessage,
                    'html' => $existingHtml,
                    'css' => $existingCss,
                ], 422);
            }

            return response()->json([
                'html' => $aiHtml,
                'css' => $aiCss,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'System Crash: ' . $e->getMessage(),
                'html' => $existingHtml,
                'css' => $existingCss,
            ], 500);
        }
    }

    private function parseAiHtmlCssPayload(string $content, string $fallbackCss): array
    {
        $clean = preg_replace('/```(?:json|html|css)?\s*/i', '', $content);
        $clean = preg_replace('/```/', '', (string) $clean);
        $clean = trim((string) $clean);

        $decoded = json_decode($clean, true);
        if (is_array($decoded) && isset($decoded['html'])) {
            $html = trim((string) ($decoded['html'] ?? ''));
            $css = (string) ($decoded['css'] ?? '');

            [$htmlFromStyle, $cssFromStyle] = $this->extractInlineStyleBlocks($html);
            $html = $htmlFromStyle;
            if (trim($css) === '' && trim($cssFromStyle) !== '') {
                $css = $cssFromStyle;
            }
            if (trim($css) === '') {
                $css = $fallbackCss;
            }

            return [$html, $css];
        }

        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['html'])) {
                $html = trim((string) ($decoded['html'] ?? ''));
                $css = (string) ($decoded['css'] ?? '');

                [$htmlFromStyle, $cssFromStyle] = $this->extractInlineStyleBlocks($html);
                $html = $htmlFromStyle;
                if (trim($css) === '' && trim($cssFromStyle) !== '') {
                    $css = $cssFromStyle;
                }
                if (trim($css) === '') {
                    $css = $fallbackCss;
                }

                return [$html, $css];
            }
        }

        [$htmlWithoutStyle, $extractedCss] = $this->extractInlineStyleBlocks($clean);
        $finalCss = trim($extractedCss) !== '' ? $extractedCss : $fallbackCss;

        return [$htmlWithoutStyle, $finalCss];
    }

    private function extractInlineStyleBlocks(string $html): array
    {
        $styles = [];
        $htmlWithoutStyles = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function ($matches) use (&$styles) {
            $styleContent = trim((string) ($matches[1] ?? ''));
            if ($styleContent !== '') {
                $styles[] = $styleContent;
            }

            return '';
        }, $html);

        $htmlWithoutStyles = trim((string) $htmlWithoutStyles);
        $combinedCss = trim(implode("\n\n", $styles));

        return [$htmlWithoutStyles, $combinedCss];
    }

    private function passesLayoutSafetyChecks(string $originalHtml, string $updatedHtml, string $prompt): array
    {
        $originalBody = $this->extractBodyElement($originalHtml);
        $updatedBody = $this->extractBodyElement($updatedHtml);

        if (!$originalBody || !$updatedBody) {
            return [false, 'AI response is invalid HTML. Original layout preserved.'];
        }

        $allowDeletion = $this->isExplicitDeletionPrompt($prompt);

        $originalCount = $this->countElements($originalBody);
        $updatedCount = $this->countElements($updatedBody);

        if (!$allowDeletion && $updatedCount < max(1, (int) floor($originalCount * 0.75))) {
            return [false, 'AI removed too many elements unexpectedly. Original layout preserved.'];
        }

        if (!$allowDeletion) {
            $originalIds = $this->collectIds($originalBody);
            $updatedIds = $this->collectIds($updatedBody);
            $missingIds = array_values(array_diff($originalIds, $updatedIds));

            if (count($missingIds) > 0) {
                return [false, 'AI removed existing identified elements unexpectedly. Original layout preserved.'];
            }

            $originalImages = $this->collectAttributeValues($originalBody, 'img', 'src');
            $updatedImages = $this->collectAttributeValues($updatedBody, 'img', 'src');
            $missingImages = array_values(array_diff($originalImages, $updatedImages));

            if (count($missingImages) > 0) {
                return [false, 'AI removed existing images unexpectedly. Original layout preserved.'];
            }
        }

        return [true, ''];
    }

    private function extractBodyElement(string $html): ?DOMElement
    {
        if (!trim($html)) {
            return null;
        }

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if (!$loaded || !$document->documentElement) {
            return null;
        }

        $bodyList = $document->getElementsByTagName('body');
        if ($bodyList->length === 0) {
            return null;
        }

        return $bodyList->item(0);
    }

    private function countElements(DOMElement $container): int
    {
        return $container->getElementsByTagName('*')->length;
    }

    private function collectIds(DOMElement $container): array
    {
        $ids = [];

        foreach ($container->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement && $element->hasAttribute('id')) {
                $id = trim($element->getAttribute('id'));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function collectAttributeValues(DOMElement $container, string $tagName, string $attribute): array
    {
        $values = [];

        foreach ($container->getElementsByTagName($tagName) as $element) {
            if ($element instanceof DOMElement && $element->hasAttribute($attribute)) {
                $value = trim($element->getAttribute($attribute));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function isExplicitDeletionPrompt(string $prompt): bool
    {
        return (bool) preg_match('/\b(delete|remove|erase|drop|discard)\b/i', $prompt);
    }
}