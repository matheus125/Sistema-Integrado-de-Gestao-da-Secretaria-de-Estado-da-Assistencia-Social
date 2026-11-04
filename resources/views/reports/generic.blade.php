<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #172033; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #526070; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #16324f; color: #fff; text-align: left; }
        th, td { border: 1px solid #d8e2ef; padding: 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #f4f7fb; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Gerado em {{ now()->format('d/m/Y H:i') }} - SIGES</div>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}">Nenhum registro encontrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
