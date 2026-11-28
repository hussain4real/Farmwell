<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $farmName }} — {{ $cycleName }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #162b22; margin: 2em; font-size: 11px; line-height: 1.45; }
        .header { background: #1b4d3e; color: #ffffff; padding: 16px 20px; border-radius: 8px; margin-bottom: 18px; }
        .eyebrow { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: #b7e4cf; margin: 0 0 4px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { font-size: 11px; color: #dcf5e8; margin: 0; }
        h2 { font-size: 13px; color: #1b4d3e; border-bottom: 1px solid #d5e4db; padding-bottom: 4px; margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e4efe8; }
        th { background: #f3f8f5; font-weight: bold; }
        .warn { background: #fef3c7; border: 1px solid #f59e0b; padding: 8px 10px; border-radius: 6px; margin-top: 10px; }
        .footer { margin-top: 22px; font-size: 9px; color: #5b7469; }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">{{ $teamName }} &middot; Cycle Operations Summary</p>
        <h1>{{ $cycleName }}</h1>
        <p class="meta">{{ $farmName }} &middot; {{ $farmTypeLabel }} &middot; {{ $statusLabel }} &middot; Day {{ $telemetry['ageDays'] }}</p>
    </div>

    <table>
        <tbody>
            <tr><th>Planned start</th><td>{{ $plannedStartOn }}</td><th>Planned end</th><td>{{ $plannedEndOn ?? 'Open' }}</td></tr>
            <tr><th>Units</th><td>{{ $units ?: 'None assigned' }}</td><th>Commodities</th><td>{{ $commodities ?: 'None assigned' }}</td></tr>
        </tbody>
    </table>

    <h2>Operational Telemetry</h2>
    <table>
        <tbody>
            @if ($domain === 'poultry')
                <tr><th>Current birds</th><td>{{ $telemetry['stock']['currentCount'] ?? 'N/A' }}</td><th>Mortality / culls</th><td>{{ $telemetry['stock']['totalMortality'] }} / {{ $telemetry['stock']['totalCulled'] }} ({{ $telemetry['stock']['mortalityRatePercent'] ?? 0 }}%)</td></tr>
                <tr><th>Total feed</th><td>{{ $telemetry['feeding']['totalFeedKg'] }} kg</td><th>FCR / Eggs</th><td>{{ $telemetry['feeding']['fcr'] ?? '—' }} / {{ $telemetry['poultry']['totalEggs'] }} ({{ $telemetry['poultry']['totalCrates'] }} crates)</td></tr>
            @elseif ($domain === 'aquaculture')
                <tr><th>Current fish</th><td>{{ $telemetry['stock']['currentCount'] ?? 'N/A' }}</td><th>Estimated biomass</th><td>{{ $telemetry['feeding']['estimatedBiomassKg'] ?? '—' }} kg</td></tr>
                <tr><th>Total feed</th><td>{{ $telemetry['feeding']['totalFeedKg'] }} kg (FCR: {{ $telemetry['feeding']['fcr'] ?? '—' }})</td><th>Latest water</th><td>pH {{ $telemetry['aquaculture']['latestPh'] ?? '—' }}, DO {{ $telemetry['aquaculture']['latestDissolvedOxygen'] ?? '—' }} mg/L ({{ strtoupper($telemetry['aquaculture']['waterStatus']) }})</td></tr>
            @elseif ($domain === 'crop')
                <tr><th>Growth stage</th><td>{{ $telemetry['crop']['currentGrowthStageLabel'] ?? 'In progress' }} ({{ $telemetry['crop']['stageProgressPercent'] }}%)</td><th>Rainfall / Irrigation</th><td>{{ $telemetry['crop']['totalRainfallMm'] }} mm / {{ $telemetry['crop']['irrigationCount'] }} events</td></tr>
                <tr><th>Pest pressure</th><td>{{ $telemetry['crop']['latestPestPressureLabel'] ?? 'None' }}</td><th>Total harvested</th><td>{{ $telemetry['crop']['totalHarvestedQuantity'] }}</td></tr>
            @else
                <tr><th>Daily logs recorded</th><td>{{ $telemetry['logCount'] }}</td><th>Cycle age</th><td>{{ $telemetry['ageDays'] }} days</td></tr>
            @endif
        </tbody>
    </table>

    @if ($activeWithdrawals !== [])
        <div class="warn">
            <strong>Active Withdrawal Hold:</strong>
            @foreach ($activeWithdrawals as $withdrawal)
                {{ $withdrawal['title'] }} (safe harvest on/after {{ $withdrawal['endsOn'] ?? 'TBD' }})@if (!$loop->last), @endif
            @endforeach
        </div>
    @endif

    @if ($profitability !== null)
        <h2>Cycle P&amp;L ({{ $profitability['currency'] }})</h2>
        <table>
            <tbody>
                <tr><th>Direct expenses</th><td>{{ $profitability['directExpenses'] }}</td><th>Inventory inputs</th><td>{{ $profitability['inventoryUsage'] }}</td></tr>
                <tr><th>Health &amp; vaccines</th><td>{{ $profitability['healthCosts'] }}</td><th>Harvest labour</th><td>{{ $profitability['harvestLabour'] }}</td></tr>
                <tr><th>Total cost</th><td><strong>{{ $profitability['totalCost'] }}</strong></td><th>Net sales revenue</th><td><strong>{{ $profitability['netRevenue'] }}</strong></td></tr>
                <tr><th>Gross profit</th><td><strong>{{ $profitability['grossProfit'] }}</strong></td><th>Margin</th><td>{{ $profitability['marginPercent'] !== null ? $profitability['marginPercent'].'%' : '—' }}</td></tr>
            </tbody>
        </table>
    @endif

    @if ($recentLogs !== [])
        <h2>Recent Daily Logs</h2>
        <table>
            <thead>
                <tr><th>Date</th><th>Unit</th><th>Summary</th><th>By</th></tr>
            </thead>
            <tbody>
                @foreach ($recentLogs as $log)
                    <tr>
                        <td>{{ $log['logDate'] }}</td>
                        <td>{{ $log['productionUnitName'] ?? 'Cycle-wide' }}</td>
                        <td>
                            @if ($log['mortalityCount'] > 0) Mortality: {{ $log['mortalityCount'] }}. @endif
                            @if ($log['feedUsedKg']) Feed: {{ $log['feedUsedKg'] }} kg. @endif
                            @if ($log['eggsCollected']) Eggs: {{ $log['eggsCollected'] }}. @endif
                            @if ($log['waterPh'] !== null) pH: {{ $log['waterPh'] }}. @endif
                            @if ($log['rainfallMm']) Rain: {{ $log['rainfallMm'] }} mm. @endif
                            {{ $log['notes'] }}
                        </td>
                        <td>{{ $log['recordedBy'] ?? 'Team' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer">Generated by Farmwell on {{ $generatedAt }}.</p>
</body>
</html>
