<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $projects = Page::orderByDesc('updated_at')->get();

        return view('dashboard', ['projects' => $projects]);
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $project = Page::create([
            'title' => $validated['title'],
            'html_content' => null,
            'css_content' => null,
            'json_content' => null,
        ]);

        return redirect('/editor/' . $project->id);
    }

    public function templates()
    {
        return view('templates');
    }

    public function settings()
    {
        return view('settings');
    }

    public function destroy($id)
    {
        $project = Page::findOrFail($id);
        $project->delete();

        return redirect('/')->with('status', 'Project deleted successfully.');
    }

    public function live($id)
    {
        $project = Page::findOrFail($id);

        return view('site', ['project' => $project]);
    }
}
