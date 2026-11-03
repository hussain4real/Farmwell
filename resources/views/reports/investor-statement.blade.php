<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Investor statement</title>
<style>body{font-family:DejaVu Sans,sans-serif;color:#20372b;margin:2em;font-size:12px}h1{font-size:25px}h2{margin-top:2em}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:7px;border-bottom:1px solid #d6e5dc}</style>
</head><body>
<p>Farmwell &middot; Investor reporting</p><h1>Investor statement</h1>
<h2>{{ $agreement->title }}</h2><p>{{ $agreement->farm->name }} &middot; {{ $start }} through {{ $end }} (inclusive)</p>
<table><thead><tr><th>Financial position</th><th>{{ $agreement->currency }}</th></tr></thead><tbody>
@foreach ($amounts as $label => $amount)
<tr><td>{{ $label }}</td><td>{{ $amount }}</td></tr>
@endforeach
</tbody></table>
<p>The funding balance is released funds less approved spend. Sales and distributions are shown separately. Amounts include only records in {{ $agreement->currency }} approved for this agreement.</p>
<h2>Evidence</h2><p>Receipts attached to approved expenses in this period: {{ $receipts }}.</p>
<h2>Harvest output</h2>
@forelse ($harvests as $harvest)
<p>{{ $harvest->harvested_on->toDateString() }} &middot; {{ $harvest->commodity->name }} &middot; {{ $harvest->quantity }} {{ $harvest->quantity_unit }}</p>
@empty
<p>No approved harvest output in this period.</p>
@endforelse
@if ($summary !== null)
<h2>Reviewed AI progress summary</h2>
{!! strip_tags(\App\Support\SummaryMarkdown::render($summary), '<p><strong><em><ul><ol><li><h1><h2><h3><h4><blockquote><br>') !!}
@endif
<p>Generated {{ $generatedAt }}.</p></body></html>
