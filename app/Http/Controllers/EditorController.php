<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Page;

class EditorController extends Controller
{
    // UPDATED: Now we fetch the saved data before serving the page
    public function index()
    {
        // Look for Page #1. If it doesn't exist yet, create a blank placeholder.
        $page = Page::firstOrCreate(
            ['id' => 1],
            ['title' => 'Home Page', 'json_content' => null]
        );

        // Pass that $page data into our view!
        return view('editor', ['page' => $page]);
    }

    public function save(Request $request)
    {
        $page = Page::updateOrCreate(
            ['id' => 1],
            [
                'html_content' => $request->input('html'),
                'css_content' => $request->input('css'),
                'json_content' => $request->input('components'),
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Page saved successfully!']);
    }
    public function generate(Request $request)
{
    $prompt = $request->input('prompt');
    // NEW: Capture the current HTML from the editor
    $existingHtml = $request->input('existing_html', ''); 
        $hasExistingHtml = trim($existingHtml) !== '';

        $editKeywords = ['change', 'edit', 'update', 'modify', 'replace', 'remove', 'delete', 'color', 'text', 'size', 'padding', 'margin', 'font', 'align', 'hide'];
        $isEditRequest = $hasExistingHtml && preg_match('/\b(' . implode('|', $editKeywords) . ')\b/i', $prompt);
    
    $apiKey = config('services.gemini.key');
    $model = config('services.gemini.model', 'gemini-2.5-flash-lite');

    if (!$apiKey) {
        return response()->json([
            'html' => '<div class="p-4 bg-red-100 text-red-700">Missing GEMINI_API_KEY in your environment.</div>'
        ], 500);
    }

    // Designer mode instruction: keep full live-canvas HTML while applying requested updates.
    $instruction = "You are an Expert UI/UX Designer.
    1. You are modifying a live canvas. ALWAYS return the FULL HTML containing the untouched EXISTING HTML plus requested updates.
    2. For change/edit/delete requests, modify only the targeted parts and keep all unrelated structure/content unchanged.
    3. For add requests, append or insert complete modern components using Tailwind CSS.
    4. Use generous padding, rounded corners, shadows, and flex/grid layouts. Never return plain unstyled text.
    5. If adding an image, ALWAYS use a real placeholder src (for example: https://placehold.co/600x400 or https://picsum.photos/600/400). Never leave src blank.
    6. Return ONLY raw HTML elements. No markdown code fences or explanation text.
    7. Strictly forbidden in output: <!DOCTYPE html>, <html>, <head>, <style>, and <body> tags.

    EXISTING HTML:
    {$existingHtml}";

    $strictRetryInstruction = "You previously regenerated content. Fix this now.
    Apply ONLY the user's requested edit to the EXISTING HTML below.
    Preserve all other elements, text, and structure exactly.
    Allowed changes: only what the user explicitly requested (e.g., color change, delete one element, class update).
    Forbidden: rewriting the component, redesigning layout, adding unrelated sections, full webpage tags, markdown.
    Return only the final raw edited HTML snippet.";

    try {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $requestGemini = function (string $instructionText) use ($prompt, $url) {
            $requestBody = [
                'contents' => [
                    ['parts' => [['text' => $instructionText . "\n\nUser Request: " . $prompt]]]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                ],
            ];

            return Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $requestBody);
        };

        $response = $requestGemini($instruction);

        if ($response->failed()) {
            $errorDetail = $response->json()['error']['message'] ?? 'Unknown API Error';
            if ($response->status() === 404) {
                $errorDetail .= " (Model: {$model})";
            }
            return response()->json(['html' => "<div class='p-4 bg-red-100 text-red-600'>Google Error ({$response->status()}): {$errorDetail}</div>"]);
        }

        $data = $response->json();
        $aiHtml = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

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
            $normalizedExisting = preg_replace('/\s+/', ' ', trim($existingHtml));
            $normalizedGenerated = preg_replace('/\s+/', ' ', trim($aiHtml));

            similar_text($normalizedExisting, $normalizedGenerated, $similarityPercent);

            // If the model appears to regenerate instead of edit-in-place, retry once with stricter constraints.
            if ($similarityPercent < 45) {
                $retryResponse = $requestGemini($strictRetryInstruction . "\n\nEXISTING HTML:\n" . $existingHtml);

                if ($retryResponse->ok()) {
                    $retryData = $retryResponse->json();
                    $retryHtml = $retryData['candidates'][0]['content']['parts'][0]['text'] ?? null;

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