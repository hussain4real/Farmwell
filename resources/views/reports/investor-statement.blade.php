<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Investor statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1b2e24; margin: 0; padding: 24px 28px; font-size: 11.5px; line-height: 1.5; }
        .header { background-color: #1b4d3e; color: #ffffff; padding: 18px 22px; border-radius: 6px; margin-bottom: 18px; }
        .eyebrow { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.08em; color: #b7e1cd; margin: 0 0 4px 0; }
        h1 { font-size: 21px; margin: 0 0 4px 0; font-weight: bold; }
        .subtitle { margin: 0; font-size: 11.5px; color: #e2f3ea; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; background: #f4f8f5; border: 1px solid #d6e5dc; }
        .meta td { padding: 8px 12px; border-bottom: 1px solid #e1ede6; font-size: 11px; vertical-align: top; }
        .meta .label { color: #4c6858; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 2px; }
        .meta .value { font-weight: bold; color: #172b20; }
        h2 { font-size: 13.5px; color: #1b4d3e; margin: 20px 0 8px 0; border-bottom: 1.5px solid #d6e5dc; padding-bottom: 4px; }
        table.report { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
        table.report th { text-align: left; padding: 8px 10px; background: #eaf3ee; color: #1b4d3e; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1.5px solid #b9d4c4; }
        table.report th:last-child, table.report td:last-child { text-align: right; }
        table.report td { text-align: left; padding: 7px 10px; border-bottom: 1px solid #e2ede6; }
        table.report tr:nth-child(even) td { background: #fbfdfc; }
        .note { color: #4c6858; font-size: 10.5px; margin: 6px 0 14px 0; }
        .callout { background: #f4f8f5; border-left: 3px solid #2e7d5b; padding: 10px 14px; margin: 10px 0 14px 0; }
        .summary-box { border: 1px solid #d6e5dc; background: #fbfdfc; padding: 12px 16px; border-radius: 4px; }
        .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #d6e5dc; color: #5b7465; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">Farmwell &middot; Investor reporting</p>
        <h1>Investor statement</h1>
        <p class="subtitle">{{ $agreement->title }} &middot; {{ $agreement->farm->name }}</p>
    </div>

    <table class="meta">
        <tr>
            <td>
                <span class="label">Farm &amp; cycle</span>
                <span class="value">{{ $agreement->farm->name }}{{ $agreement->productionCycle ? ' · '.$agreement->productionCycle->name : '' }}</span>
            </td>
            <td>
                <span class="label">Reporting period</span>
                <span class="value">{{ $start }} through {{ $end }} (inclusive)</span>
            </td>
            <td>
                <span class="label">Profit share &amp; recovery</span>
                <span class="value">{{ $agreement->investor_profit_share_percentage }}% / {{ $agreement->farm_profit_share_percentage }}% &middot; {{ $agreement->capital_recovery_rule->label() }}</span>
            </td>
        </tr>
    </table>

    <h2>Financial position</h2>
    <table class="report">
        <thead>
            <tr>
                <th>Financial position</th>
                <th>{{ $agreement->currency }}</th>
            </tr>
        </thead>
        <tbody>
@foreach ($amounts as $label => $amount)
            <tr><td>{{ $label }}</td><td>{{ $amount }}</td></tr>
@endforeach
        </tbody>
    </table>
    <p class="note">The funding balance is released funds less approved spend. Sales and distributions are shown separately. Amounts include only records in {{ $agreement->currency }} approved for this agreement.</p>

    <h2>Evidence</h2>
    <div class="callout">Receipts attached to approved expenses in this period: <strong>{{ $receipts }}</strong>.</div>

    <h2>Harvest output</h2>
    @if ($harvests->isNotEmpty())
        <table class="report">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Commodity</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($harvests as $harvest)
                    <tr>
                        <td>{{ $harvest->harvested_on->toDateString() }}</td>
                        <td>{{ $harvest->commodity->name }}</td>
                        <td>{{ $harvest->quantity }} {{ $harvest->quantity_unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="note">No approved harvest output in this period.</p>
    @endif

    @if ($summary !== null)
        <h2>Reviewed AI progress summary</h2>
        <div class="summary-box">
            {!! strip_tags(\App\Support\SummaryMarkdown::render($summary), '<p><strong><em><ul><ol><li><h1><h2><h3><h4><blockquote><br>') !!}
        </div>
    @endif

    <p class="footer">Generated {{ $generatedAt }} &middot; Farmwell Investor Statement ({{ $agreement->currency }}).</p>
</body>
</html>
