<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Page;

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
    $prompt = $request->input('prompt');
    $rawHtml = $request->input('existing_html', '');

    $cleanHtml = preg_replace('/<svg\b[^>]*>.*?<\/svg>/is', '', $rawHtml);
    $cleanHtml = preg_replace('/src="data:image\/[^"]+;base64,[^"]*"/i', 'src="..."', $cleanHtml);

    if (strlen($cleanHtml) > 6000) {
        $cleanHtml = "\n...[TRUNCATED]\n" . substr($cleanHtml, -6000);
    }

        $hasExistingHtml = trim($cleanHtml) !== '';

        $editKeywords = ['change', 'edit', 'update', 'modify', 'replace', 'remove', 'delete', 'color', 'text', 'size', 'padding', 'margin', 'font', 'align', 'hide'];
        $isEditRequest = $hasExistingHtml && preg_match('/\b(' . implode('|', $editKeywords) . ')\b/i', $prompt);
    
    $apiKey = config('services.groq.key');
    $primaryModel = config('services.groq.model', 'moonshotai/kimi-k2-instruct-0905');
    $fallbackModels = config('services.groq.fallback_models', []);
    $models = array_values(array_unique(array_filter(array_merge([$primaryModel], $fallbackModels))));

    if (!$apiKey) {
        return response()->json([
            'html' => '<div class="p-4 bg-red-100 text-red-700">Missing GROQ_API_KEY in your environment.</div>'
        ], 500);
    }

    // Designer mode instruction: keep full live-canvas HTML while applying requested updates.
    $instruction = "You are an Expert UI/UX Designer.
    1. You are modifying a live canvas. ALWAYS return the FULL HTML containing the untouched EXISTING HTML plus requested updates.
    2. For change/edit/delete requests, modify only the targeted parts and keep all unrelated structure/content unchanged.
    3. For add requests, append or insert complete modern components using Tailwind CSS.
    4. Build top-class premium UI: polished visual hierarchy, balanced whitespace, refined typography scale, consistent spacing rhythm, and clean responsive layouts.
    5. Use production-quality component styling (cards, buttons, badges, inputs, navbars, heroes, sections) with strong contrast and clear affordances.
    5. If adding an image, ALWAYS use a real placeholder src (for example: https://placehold.co/600x400 or https://picsum.photos/600/400). Never leave src blank.
    6. Return ONLY raw HTML elements. No markdown code fences or explanation text.
    7. Strictly forbidden in output: <!DOCTYPE html>, <html>, <head>, <style>, and <body> tags.

    EXISTING HTML:
    {$cleanHtml}";

    $strictRetryInstruction = "You previously regenerated content. Fix this now.
    Apply ONLY the user's requested edit to the EXISTING HTML below.
    Preserve all other elements, text, and structure exactly.
    Allowed changes: only what the user explicitly requested (e.g., color change, delete one element, class update).
    Forbidden: rewriting the component, redesigning layout, adding unrelated sections, full webpage tags, markdown.
    Return only the final raw edited HTML snippet.";

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
                'html' => "<div class='p-4 bg-red-100 text-red-600'>Groq Error ({$status}): {$message}. Tried models: {$triedModels}</div>",
            ]);
        }

        $data = $response->json();
        $aiHtml = $data['choices'][0]['message']['content'] ?? null;

        if (!$aiHtml) {
            return response()->json(['html' => '<div class="p-4 bg-yellow-100 text-yellow-700">AI reached but no content returned.</div>']);
        }

        // If a full page is returned, extract only the body inner HTML.
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $aiHtml, $matches)) {
            $aiHtml = $matches[1];
        }

        // Strip markdown fences like ```html ... ```.
        $aiHtml = preg_replace('/```(?:html)?\s*/i', '', $aiHtml);
        $aiHtml = preg_replace('/```/', '', $aiHtml);

        // Final safety fallback: remove document wrapper tags if still present.
        $aiHtml = preg_replace('/<!DOCTYPE[^>]*>/i', '', $aiHtml);
        $aiHtml = preg_replace('/<\/?html\b[^>]*>/i', '', $aiHtml);

        $aiHtml = trim($aiHtml);

        if ($isEditRequest) {
            $normalizedExisting = preg_replace('/\s+/', ' ', trim($cleanHtml));
            $normalizedGenerated = preg_replace('/\s+/', ' ', trim($aiHtml));

            similar_text($normalizedExisting, $normalizedGenerated, $similarityPercent);

            // If the model appears to regenerate instead of edit-in-place, retry once with stricter constraints.
            if ($similarityPercent < 45) {
                $retryAttempt = $requestGroq($strictRetryInstruction . "\n\nEXISTING HTML:\n" . $cleanHtml);
                $retryResponse = $retryAttempt['response'];

                if ($retryResponse && $retryResponse->ok()) {
                    $retryData = $retryResponse->json();
                    $retryHtml = $retryData['choices'][0]['message']['content'] ?? null;

                    if ($retryHtml) {
                        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $retryHtml, $matches)) {
                            $retryHtml = $matches[1];
                        }

                        $retryHtml = preg_replace('/```(?:html)?\s*/i', '', $retryHtml);
                        $retryHtml = preg_replace('/```/', '', $retryHtml);
                        $retryHtml = preg_replace('/<!DOCTYPE[^>]*>/i', '', $retryHtml);
                        $retryHtml = preg_replace('/<\/?html\b[^>]*>/i', '', $retryHtml);
                        $retryHtml = trim($retryHtml);

                        if ($retryHtml !== '') {
                            $aiHtml = $retryHtml;
                        }
                    }
                }
            }
        }
        
        return response()->json(['html' => $aiHtml]);

    } catch (\Exception $e) {
        return response()->json(['html' => '<div class="p-4 bg-red-200">System Crash: ' . $e->getMessage() . '</div>']);
    }
}
}