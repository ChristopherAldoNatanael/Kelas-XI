<?php

namespace App\Support;

/**
 * Single source of truth for admin status labels and badge tones.
 *
 * Status strings are stored by several flows (app booking, Midtrans webhook,
 * manual payment, medical-record extra payment). Keeping the mapping here
 * prevents one page from silently showing an untranslated key such as
 * "dp_pending" while another page shows the correct label.
 */
final class AdminStatus
{
    public static function bookingLabel(?string $status): string
    {
        return match ($status) {
            'pending' => __('common.pending'),
            'confirmed' => __('common.confirmed'),
            'completed' => __('common.completed'),
            'cancelled' => __('common.cancelled'),
            default => self::humanize($status),
        };
    }

    public static function bookingTone(?string $status): string
    {
        return match ($status) {
            'pending' => 'amber',
            'confirmed' => 'sky',
            'completed' => 'primary',
            'cancelled' => 'rose',
            default => 'slate',
        };
    }

    public static function paymentLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('common.paid'),
            'unpaid' => __('common.unpaid'),
            'dp_paid' => __('common.dp_paid'),
            'dp_pending' => __('common.dp_pending'),
            'partial' => __('common.partial'),
            'pending' => __('common.pending'),
            'failed' => __('common.failed'),
            default => self::humanize($status),
        };
    }

    public static function paymentTone(?string $status): string
    {
        return match ($status) {
            'paid' => 'primary',
            'dp_paid' => 'blue',
            'dp_pending' => 'amber',
            'partial' => 'orange',
            'pending' => 'amber',
            'failed' => 'rose',
            'unpaid' => 'slate',
            default => 'slate',
        };
    }

    public static function paymentTypeLabel(?string $type): string
    {
        return match ($type) {
            'dp' => __('admin.type_dp'),
            'full' => __('admin.type_full'),
            default => '—',
        };
    }

    public static function paymentTypeTone(?string $type): string
    {
        return $type === 'dp' ? 'blue' : ($type === 'full' ? 'primary' : 'slate');
    }

    public static function extraPaymentLabel(?string $status): string
    {
        return match ($status) {
            'not_required' => __('admin.extra_not_required'),
            'paid' => __('common.paid'),
            'partial' => __('common.partial'),
            'unpaid' => __('common.unpaid'),
            'pending' => __('common.pending'),
            'failed' => __('common.failed'),
            default => self::humanize($status),
        };
    }

    public static function extraPaymentTone(?string $status): string
    {
        return match ($status) {
            'not_required' => 'slate',
            'paid' => 'primary',
            'partial' => 'sky',
            'unpaid' => 'amber',
            'pending' => 'amber',
            'failed' => 'rose',
            default => 'slate',
        };
    }

    public static function auditActionLabel(?string $action): string
    {
        return match ($action) {
            'booking.confirm' => __('admin.audit_action_booking_confirm'),
            'booking.complete' => __('admin.audit_action_booking_complete'),
            'booking.cancel' => __('admin.audit_action_booking_cancel'),
            'booking.send_reminder' => __('admin.audit_action_booking_send_reminder'),
            'doctor.create' => __('admin.audit_action_doctor_create'),
            'doctor.update' => __('admin.audit_action_doctor_update'),
            'doctor.delete' => __('admin.audit_action_doctor_delete'),
            'doctor.schedule_exception.save' => __('admin.audit_action_schedule_exception_save'),
            'doctor.schedule_exception.delete' => __('admin.audit_action_schedule_exception_delete'),
            'payment.confirm' => __('admin.audit_action_payment_confirm'),
            'service.create' => __('admin.audit_action_service_create'),
            'service.update' => __('admin.audit_action_service_update'),
            'service.import' => __('admin.audit_action_service_import'),
            'service.delete' => __('admin.audit_action_service_delete'),
            'user.create' => __('admin.audit_action_user_create'),
            'user.update' => __('admin.audit_action_user_update'),
            'user.delete' => __('admin.audit_action_user_delete'),
            'admin.password.change' => __('admin.audit_action_admin_password_change'),
            default => self::humanize($action),
        };
    }

    private static function humanize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
