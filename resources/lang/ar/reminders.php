<?php

/**
 * P2.5.I (p2_5-brief.md §P2.5.I) -- template registry, Arabic variant. Same keys/placeholders as
 * lang/en/reminders.php; see that file's own docblock for the registry contract.
 */
return [

    'overdue_invoice' => [
        'subject' => 'فاتورة متأخرة السداد :invoice_number',
        'client' => "نود تذكيركم بوجود مبلغ مستحق للفاتورة رقم :invoice_number بقيمة :currency :amount والذي تجاوز تاريخ الاستحقاق في :due_date.:additional\n\nيرجى الضغط على الرابط التالي لسداد الفاتورة:\n:link\n\nلأي استفسار، لا تترددوا بالتواصل مع فريق الدعم.",
        'agent' => "تذكير بوجود مبلغ مستحق على عميلك للفاتورة رقم :invoice_number بقيمة :currency :amount تجاوز تاريخ الاستحقاق في :due_date.:additional\n\nرابط الفاتورة:\n:link\n\nيرجى متابعة العميل بخصوص هذا المبلغ.",
    ],

    'statement_balance' => [
        'subject' => 'رصيد مستحق في كشف الحساب',
        'client' => "يوجد لديكم رصيد مستحق في كشف الحساب بقيمة :currency :amount.:additional\n\nلأي استفسار أو للحصول على نسخة من كشف الحساب، لا تترددوا بالتواصل مع فريق الدعم.",
        'agent' => "تذكير بوجود رصيد مستحق على عميلك في كشف الحساب بقيمة :currency :amount.:additional\n\nرابط كشف الحساب:\n:link\n\nيرجى متابعة العميل بخصوص هذا الرصيد.",
    ],

    'ticketing_deadline' => [
        'subject' => 'اقتراب الموعد النهائي لإصدار التذكرة -- :reference',
        'agent' => "تذكير: يجب إصدار تذكرة الحجز رقم :reference للمسافر :passenger_name قبل :deadline (:deposit_text).:additional\n\nيرجى إتمام إجراءات هذا الحجز قبل الموعد النهائي لتجنب فقدان الحجز.",
        'client' => "نود تذكيركم بأن حجزكم (رقم :reference) يستحق إصدار التذكرة قبل :deadline.:additional\n\nيرجى التواصل معنا لإتمام حجزكم قبل الموعد النهائي.",
    ],

    'commission_unearned' => [
        'subject' => 'إلغاء استحقاق عمولة نتيجة استرداد',
        'agent' => "تم إلغاء استحقاق عمولة بقيمة :currency :amount كانت مستحقة سابقاً على الفاتورة رقم :invoice_number نتيجة استرداد على هذا الحجز.:additional\n\nتم خصم هذا المبلغ من رصيد عمولتكم.",
        'client' => '',
    ],

    'payment_link_uninvoiced' => [
        'subject' => 'رابط الدفع :voucher_number لم يتم إصدار فاتورة له بعد',
        'agent' => "تم سداد سند الدفع رقم :voucher_number بقيمة :currency :amount ولم يتم إصدار فاتورة له بعد.:additional\n\nيرجى إصدار الفاتورة لهذا السداد في أقرب وقت ممكن.",
        'client' => '',
    ],

    'custom' => [
        'subject' => 'تذكير',
        'client' => ':message',
        'agent' => ':message',
    ],

    // Internal fallback only -- see lang/en/reminders.php's own docblock on this key.
    'payment_due' => [
        'subject' => 'مبلغ مستحق -- سند رقم :voucher_number',
        'client' => "نود تذكيركم بوجود مبلغ مستحق لسند الدفع رقم :voucher_number بقيمة :currency :amount.:additional\n\nيرجى الضغط على الرابط التالي للسداد:\n:link\n\nلأي استفسار، لا تترددوا بالتواصل مع فريق الدعم.",
        'agent' => "تذكير بوجود مبلغ مستحق على عميلك لسند الدفع رقم :voucher_number بقيمة :currency :amount.:additional\n\nرابط السداد:\n:link\n\nيرجى متابعة العميل بخصوص هذا المبلغ.",
    ],

];
