<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de usuarios</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .subtitle { color: #6b7280; margin: 0 0 12px; font-size: 10px; }
        .filters { margin: 0 0 14px; padding: 8px 10px; background: #f3f4f6; border-radius: 4px; }
        .filters span { margin-right: 16px; }
        .filters strong { color: #374151; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #1f2937; color: #fff; text-align: left;
            padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .03em;
        }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 9.5px; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .badge {
            display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8.5px; font-weight: bold;
        }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        .badge-unknown { background: #f3f4f6; color: #6b7280; }
        .footer { margin-top: 10px; color: #9ca3af; font-size: 8.5px; }
    </style>
</head>
<body>
    <h1>Reporte de usuarios</h1>
    <p class="subtitle">Generado el {{ $generatedAt->translatedFormat('d \d\e F \d\e Y, H:i') }}</p>

    <div class="filters">
        <span><strong>Búsqueda:</strong> {{ $search ?: 'Todos' }}</span>
        <span><strong>Sistema:</strong> {{ $systemName ?: 'Todos' }}</span>
        <span><strong>Estado:</strong> {{ ['active' => 'Solo activos', 'inactive' => 'Solo de baja'][$status] ?? 'Activos/Baja' }}</span>
        <span><strong>Total de cuentas:</strong> {{ $accounts->count() }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Sistema</th>
                <th>Correo</th>
                <th>Alias</th>
                <th>Roles</th>
                <th>Estado</th>
                <th>Creado en sistema</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account['name'] }}</td>
                    <td>{{ $account['system_name'] }}</td>
                    <td>{{ $account['email'] }}</td>
                    <td>{{ $account['alias'] ?: '—' }}</td>
                    <td>{{ $account['roles'] ?: '—' }}</td>
                    <td>
                        @if ($account['active'] === true)
                            <span class="badge badge-active">Activo</span>
                        @elseif ($account['active'] === false)
                            <span class="badge badge-inactive">De baja</span>
                        @else
                            <span class="badge badge-unknown">Sin datos</span>
                        @endif
                    </td>
                    <td>{{ $account['created_at'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding: 20px; color: #9ca3af;">
                        No hay usuarios que coincidan con el filtro.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">IAM System — Reporte generado automáticamente.</p>
</body>
</html>
