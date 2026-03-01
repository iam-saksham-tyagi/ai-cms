<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->title }}</title>
    @if($project->css_content)
        <style>{!! $project->css_content !!}</style>
    @endif
</head>
<body>
    {!! $project->html_content ?: '<div style="padding:40px;font-family:Arial,sans-serif;color:#334155;">This project does not have published content yet.</div>' !!}
</body>
</html>
