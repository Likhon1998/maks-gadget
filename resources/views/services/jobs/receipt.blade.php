<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service Receipt {{ $job->invoice_no }}</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; color: #0f172a; margin: 0; padding: 16px; background: #f8fafc; }
        .slip { max-width: 320px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 16px; }
        h1 { font-size: 15px; margin: 0 0 4px; text-align: center; }
        .muted { color: #64748b; text-align: center; margin: 0 0 12px; font-size: 11px; }
        .row { display: flex; justify-content: space-between; gap: 8px; margin: 4px 0; }
        .label { color: #64748b; }
        .hr { border: 0; border-top: 1px dashed #cbd5e1; margin: 10px 0; }
        .total { font-size: 14px; font-weight: 800; }
        .desc { white-space: pre-wrap; line-height: 1.4; margin: 8px 0; }
        .actions { text-align: center; margin-top: 14px; }
        button { border: 0; background: #0f172a; color: #fff; border-radius: 8px; padding: 8px 14px; font-weight: 700; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .actions { display: none; } .slip { border: 0; } }
    </style>
</head>
<body>
    <div class="slip">
        <h1>{{ $job->shop?->name ?? config('app.name') }}</h1>
        <p class="muted">Service receipt</p>
        <div class="row"><span class="label">Invoice</span><strong>{{ $job->invoice_no }}</strong></div>
        <div class="row"><span class="label">Date</span><span>{{ optional($job->served_at)->format('Y-m-d H:i') }}</span></div>
        <div class="row"><span class="label">Technician</span><span>{{ $job->technician?->name }}</span></div>
        @if($job->customer_name || $job->customer_phone)
            <div class="row"><span class="label">Customer</span><span>{{ $job->customer_name ?: '—' }} {{ $job->customer_phone ? '· '.$job->customer_phone : '' }}</span></div>
        @endif
        @if(filled($job->job_description))
            <hr class="hr">
            <div class="label">Job description</div>
            <div class="desc">{{ $job->job_description }}</div>
        @endif
        <hr class="hr">

        @if($job->items->isNotEmpty())
            <div class="label">Shop parts</div>
            @foreach($job->items as $item)
                <div class="row">
                    <span>{{ $item->product_name }} ×{{ $item->quantity }}</span>
                    <span>Tk {{ number_format((float) $item->subtotal, 2) }}</span>
                </div>
            @endforeach
            <div class="row"><span class="label">Shop parts total</span><span>Tk {{ number_format((float) $job->shop_parts_amount, 2) }}</span></div>
            <hr class="hr">
        @endif

        @if($job->relationLoaded('outsideItems') ? $job->outsideItems->isNotEmpty() : (float) $job->materials_cost > 0)
            <div class="label">Products</div>
            @forelse($job->outsideItems as $out)
                <div class="row">
                    <span>{{ $out->name }}</span>
                    <span>Tk {{ number_format((float) $out->amount, 2) }}</span>
                </div>
            @empty
                <div class="row"><span class="label">Products</span><span>Tk {{ number_format((float) $job->materials_cost, 2) }}</span></div>
            @endforelse
            <hr class="hr">
        @endif
        <div class="row"><span class="label">Service amount</span><span>Tk {{ number_format((float) $job->labor_cost, 2) }}</span></div>
        <div class="row total"><span>Total</span><span>Tk {{ number_format((float) $job->total_amount, 2) }}</span></div>
        @php
            $cash = (float) ($job->cash_amount ?? 0);
            $card = (float) ($job->card_amount ?? 0);
            $mobile = (float) ($job->mobile_amount ?? 0);
            $credit = (float) ($job->credit_amount ?? 0);
            $commPaid = (float) ($job->commission_paid_amount ?? 0);
            $advApplied = (float) ($job->commission_advance_applied ?? 0);
            $techBaki = max(0, round((float) $job->commission_amount - $commPaid, 2));
        @endphp
        @if($cash > 0)
            <div class="row"><span class="label">Cash</span><span>Tk {{ number_format($cash, 2) }}</span></div>
        @endif
        @if($card > 0)
            <div class="row"><span class="label">Card</span><span>Tk {{ number_format($card, 2) }}</span></div>
        @endif
        @if($mobile > 0)
            <div class="row"><span class="label">bKash</span><span>Tk {{ number_format($mobile, 2) }}</span></div>
        @endif
        <div class="row"><span class="label">Paid</span><span>Tk {{ number_format((float) $job->paid_amount, 2) }}</span></div>
        @if($credit > 0)
            <div class="row"><span class="label">Customer due / baki</span><strong>Tk {{ number_format($credit, 2) }}</strong></div>
        @endif
        <hr class="hr">
        <div class="row"><span class="label">Commission ({{ number_format((float) $job->commission_percent, 1) }}% of service)</span><span>Tk {{ number_format((float) $job->commission_amount, 2) }}</span></div>
        @if($advApplied > 0.009)
            <div class="row"><span class="label">Advance deducted (admin)</span><span>Tk {{ number_format($advApplied, 2) }}</span></div>
        @endif
        @if($commPaid > 0.009)
            <div class="row"><span class="label">Covered by admin pay</span><span>Tk {{ number_format($commPaid, 2) }}</span></div>
        @endif
        <div class="row"><span class="label">Tech baki</span><strong>Tk {{ number_format($techBaki, 2) }}</strong></div>
        <p class="muted" style="margin:6px 0 0;font-size:10px">Advance is deducted only when admin confirms in Service → Pay</p>
        <p class="muted" style="margin-top:14px">Billed by {{ $job->creator?->name ?? 'Staff' }}</p>
        <div class="actions"><button type="button" onclick="window.print()">Print</button></div>
    </div>
</body>
</html>
