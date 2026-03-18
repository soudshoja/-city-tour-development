<?php

return [
    'settings' => 'الإعدادات',
    'dashboard' => 'لوحة التحكم',
    
    //Payment Section
        'payment' => 'الدفع',
        'payment_settings' => 'إعدادات الدفع',
        'payment_settings_description' => 'تكوين إعدادات الدفع الافتراضية',
        'payment_whatsapp_notification' => 'إشعار واتساب للدفع',
        'payment_whatsapp_notification_description' => 'إرسال إشعار واتساب للعميل عند نجاح الدفع',

    //Terms & Regulations Section
        'terms_regulations' => 'الشروط والأحكام',
        'terms_regulations_description' => 'إدارة قوالب الشروط والأحكام للعملاء قبل المتابعة إلى بوابة الدفع',
        'filter_by_language' => 'تصفية حسب اللغة',
        'all' => 'الكل',
        'english' => 'الإنجليزية',
        'arabic' => 'العربية',
        'english_default' => 'الإنجليزية الافتراضية',
        'arabic_default' => 'العربية الافتراضية',
        'add_template' => 'إضافة قالب',
        'no_templates' => 'لا توجد قوالب بعد',
        'create_first_template' => 'أنشئ أول قالب للشروط والأحكام',
        'not_set' => 'غير محدد',
        'set_as_default' => 'تعيين كافتراضي',
        'set_as_default_description' => 'سيكون هذا القالب الافتراضي للغة المحددة',
        //Edit Modal Content
            'edit_template' => 'تعديل القالب',
            'edit_template_description' => 'تحديث قالب الشروط والأحكام',
            'template_name' => 'اسم القالب',
            'terms_conditions_content' => 'محتوى الشروط والأحكام',
            'tip' => 'نصيحة',
            'tip_description' => 'استخدم القوائم المرقمة والعناوين الواضحة لسهولة القراءة',
            'default_description' => 'هذا هو القالب الافتراضي لـ',
            'require_t&c_acceptance' => 'طلب الموافقة على الشروط والأحكام',
            'require_t&c_acceptance_description' => 'مطالبة العملاء بقبول الشروط قبل الدفع',
        //Delete Modal Content
            'delete_template' => 'حذف القالب',
            'delete_template_description' => 'هل أنت متأكد من حذف',
            'action_cannot_be_undone' => 'لا يمكن التراجع عن هذا الإجراء.',

    //Payment Gateway Section
        'payment_gateways' => 'بوابات الدفع',
        'payment_gateways_description' => 'إدارة بوابات الدفع المتاحة في النظام',
        'system' => 'النظام',
        'client' => 'العميل',
        'percent' => 'نسبة مئوية',
        'flatrate' => 'سعر ثابت',
        'create_new_gateway' => 'إنشاء بوابة جديدة',
        'enable_this_gateway' => 'تفعيل هذه البوابة',
        'enable_this_payment_method' => 'تفعيل طريقة الدفع هذه',
        //Modal Gateway API Settings
            'gateway_api_settings' => 'إعدادات API للبوابة',
            'api_key' => 'مفتاح API',
            'activate_gateway' => 'تفعيل البوابة',
            'gateway_activation_description' => 'تمكين أو تعطيل هذه البوابة',
            'can_generate_link' => 'يمكن إنشاء رابط',
            'can_generate_link_description' => 'السماح لهذه البوابة بإنشاء روابط الدفع',
        //Modal Edit Payment Method
            'edit_payment_method' => 'تعديل طريقة الدفع',
            'arabic_name' => 'الاسم بالعربية',
            'english_name' => 'الاسم بالإنجليزية',
            'service_charge' => 'رسوم الخدمة',
            'description' => 'الوصف',
            'activate_method' => 'تفعيل الطريقة',
            'method_activation_description' => 'تمكين أو تعطيل طريقة الدفع هذه',
        //Error Validation Messages
            'no_payment_gateways' => 'لم يتم تكوين بوابة دفع',
            'no_payment_methods' => 'لا توجد طرق دفع لهذه البوابة',
        //Loading Message
            'loading_payment_gateways' => 'جاري تحميل بوابات الدفع...',
        //Placeholder
            'optional' => 'اختياري',
            'optional_description' => 'وصف اختياري',
            'enter_gateway_name' => 'أدخل اسم البوابة',
            'paste_secret_key' => 'الصق مفتاحك السري',

    //Payment Methods Section
        'payment_methods' => 'طرق الدفع',
        'payment_method_selection' => 'اختيار طريقة الدفع',
        'payment_method_selection_description' => 'إدارة البوابة لكل طريقة دفع',
        'select_method' => 'اختر طريقة للتفعيل',
        'activate_first' => 'قم بالتفعيل أولاً',
        'configure_gateway_first' => 'قم بتكوين بوابة الدفع أولاً في تبويب بوابات الدفع',
        //Error Validation Messages
        'no_payment_method_groups' => 'لا توجد مجموعات طرق دفع متاحة',
        //Loading Message
            'loading_payment_methods' => 'جاري تحميل طرق الدفع...',

    //Agent Charges Section
        'agent_charges' => 'رسوم الوكيل',
        'loading_agent_charges' => 'جاري تحميل رسوم الوكيل',
        'agent_extra_charge_settings' => 'إعدادات الرسوم الإضافية للوكيل',
        'agent_extra_charge_description' => 'تكوين من يتحمل الرسوم الإضافية (رسوم البوابة) لحساب الأرباح',
        'profit_calculation_works' => 'كيف يعمل حساب الأرباح',
        'agent_charge_deduction' => 'خصم رسوم الوكيل',

    //Sidebar Navigation
        'agent_loss' => 'خسارة الوكيل',
        'notifications' => 'الإشعارات',

    //Terms Modal
        'no_templates_for' => 'لا توجد قوالب لـ',
        'add_template_description' => 'إضافة قالب شروط وأحكام جديد',

    //Table of Contents
        'template_name' => 'اسم القالب',
        'language' => 'اللغة',
        'status' => 'الحالة',
        'default' => 'افتراضي',
        'created_by' => 'أنشئ بواسطة',
        'created_at' => 'تاريخ الإنشاء',
        'actions' => 'الإجراءات',
        'gateway_name' => 'اسم البوابة',
        'amount' => 'المبلغ',
        'paid_by' => 'الدفع بواسطة',
        'self_charge' => 'الرسوم الذاتية',
        'charge_type' => 'نوع الرسوم',

    //Status Options
        'active' => 'نشط',
        'inactive' => 'غير نشط',

    //Buttons
        'cancel' => 'إلغاء',
        'update_template' => 'تحديث القالب',
        'save_changes' => 'حفظ التغييرات',
        'save_selection' => 'حفظ الاختيار',
        'enabled' => 'مفعّل',
        'disabled' => 'معطّل',
        'add_first_gateway' => 'أضف بوابتك الأولى',
        'add_gateway' => 'إضافة بوابة',
        'create_template' => 'إنشاء قالب',
        'delete' => 'حذف',
        'close' => 'إغلاق',
        'payment_method' => 'طريقة الدفع',

    //Dropdown
        'select' => 'اختر',

    //Payment Gateway Charges
        'charges' => 'الرسوم',
        'contract' => 'العقد',
        'back_office' => 'المكتب الخلفي',
        'extra' => 'إضافي',
        'see_payment_methods_below' => 'انظر طرق الدفع أدناه',
        'contract_charge' => 'رسوم العقد',
        'back_office_charge' => 'رسوم المكتب الخلفي',
        'extra_charge_flat_rate' => 'رسوم إضافية (سعر ثابت)',
        'actual_gateway_fee' => 'رسوم البوابة الفعلية (رسوم API)',
        'back_office_charge_tooltip' => 'ما تفرضه على العميل (العقد + هامش الربح). يجب أن يكون ≥ رسوم العقد',
        'extra_charge_tooltip' => 'رسوم ثابتة إضافية (بالدينار الكويتي) تضاف إلى الرسوم',
        'can_charge_invoice' => 'يمكن تحصيل الفاتورة',
        'can_charge_invoice_description' => 'السماح بتحصيل الفواتير من هذه البوابة',
        'allow_payment_link_generation' => 'السماح بإنشاء رابط الدفع',
        'whatsapp_notification_updated' => 'تم تحديث إعداد إشعار واتساب بنجاح',

    //Agent Charges Detail
        'charge_settings' => 'إعدادات الرسوم',
        'configure_who_bears_for' => 'تكوين من يتحمل الرسوم الإضافية لـ',
        'company_bears_all' => 'الشركة تتحمل الكل',
        'company_bears_all_description' => 'الوكيل يحتفظ بكامل هامش الربح',
        'agent_bears_all' => 'الوكيل يتحمل الكل',
        'agent_bears_all_description' => 'خصم كامل الرسوم من الربح',
        'split_charges' => 'تقسيم الرسوم حسب إعداد النسبة المئوية',
        'split_description' => 'مشاركة الرسوم بالنسبة المئوية',
        'agent_percentage' => 'نسبة الوكيل',
        'percentage_must_sum' => 'يجب أن يكون مجموع نسبة الوكيل والشركة 100%',
        'notes_optional' => 'ملاحظات (اختياري)',
        'notes_placeholder' => 'أي ملاحظات حول هذا الإعداد...',
        'reset_to_default' => 'إعادة تعيين للافتراضي',
        'saving' => 'جاري الحفظ...',
        'configured' => 'مُكوّن',
        'who_bears_extra_charges' => 'من يتحمل الرسوم الإضافية؟',
        'search_agents' => 'البحث عن وكلاء...',
        'no_agents_found' => 'لم يتم العثور على وكلاء',
        'bulk_update_charge_settings' => 'تحديث جماعي لإعدادات الرسوم',
        'bulk_update_charge_description' => 'تحديث إعدادات الرسوم الإضافية لعدة وكلاء دفعة واحدة',
        'select_agents_first' => 'يرجى اختيار الوكلاء من الجدول أولاً',
        'updating_settings_for' => 'تحديث الإعدادات لـ',
        'selected_agents' => 'وكيل(وكلاء) محدد',
        'update_all' => 'تحديث الكل',
        'updating' => 'جاري التحديث...',
        'reset_confirm' => 'إعادة تعيين هذا الوكيل للإعدادات الافتراضية (الشركة تتحمل الكل)؟',

    //Terms Condition
        'enter_terms_placeholder' => 'أدخل الشروط والأحكام هنا...',
        'activate' => 'تفعيل',
        'deactivate' => 'إلغاء التفعيل',
];