@props([
    'status',
    'type' => 'order',
])

@php
    $orderTones = [
        'pending_payment' => 'amber',
        'awaiting_confirmation' => 'orange',
        'accepted' => 'blue',
        'preparing' => 'violet',
        'ready_for_pickup' => 'sky',
        'completed' => 'primary',
        'rejected' => 'rose',
        'cancelled' => 'slate',
        'expired' => 'slate',
    ];

    $paymentTones = [
        'unpaid' => 'slate',
        'pending' => 'amber',
        'paid' => 'primary',
        'failed' => 'rose',
        'expired' => 'slate',
        'cancelled' => 'slate',
        'refund_pending' => 'orange',
        'partially_refunded' => 'orange',
        'refunded' => 'blue',
        'chargeback' => 'rose',
    ];

    $tone = $type === 'payment'
        ? ($paymentTones[$status] ?? 'slate')
        : ($orderTones[$status] ?? 'slate');

    $label = $type === 'payment'
        ? __('petshop.payment_status.' . $status)
        : __('petshop.status.' . $status);
@endphp

<x-admin.badge :label="$label" :tone="$tone" dot />
