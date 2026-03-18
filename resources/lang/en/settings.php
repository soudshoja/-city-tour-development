<?php

return [
    'settings' => 'Settings',
    'dashboard' => 'Dashboard',

    //Payment Section
        'payment' => 'Payment',
        'payment_settings' => 'Payment Settings',
        'payment_settings_description' => 'Configure default payment settings',
        'payment_whatsapp_notification' => 'Payment WhatsApp Notification',
        'payment_whatsapp_notification_description' => 'Send WhatsApp notification to client upon successful payment',

    //Terms & Regulations Section
        'terms_regulations' => 'Terms & Regulations',
        'terms_regulations_description' => 'Manage terms and conditions templates for clients before proceeding to payment gateway',
        'filter_by_language' => 'Filter by language',
        'all' => 'All',
        'english' => 'English',
        'arabic' => 'Arabic',
        'english_default' => 'English Default',
        'arabic_default' => 'Arabic Default',
        'add_template' => 'Add Template',
        'no_templates' => 'No templates yet',
        'no_templates_for' => 'No templates found for',
        'create_first_template' => 'Create your first terms and conditions template',
        'not_set' => 'Not set',
        //Edit Modal Content
            'edit_template' => 'Edit Template',
            'edit_template_description' => 'Update your terms and conditions template',
            'template_name' => 'Template Name',
            'terms_conditions_content' => 'Terms & Conditions Content',
            'tip' => 'Tip',
            'tip_description' => 'Use numbered lists and clear headings for better readability',
            'default_description' => 'This is the default template for',
            'require_t&c_acceptance' => 'Require T&C Acceptance',
            'require_t&c_acceptance_description' => 'Require clients to accept terms before payment',
        //Delete Modal Content
            'delete_template' => 'Delete Template',
            'delete_template_description' => 'Are you sure you want to delete',
            'action_cannot_be_undone' => 'This action cannot be undone.',
   
    //Payment Gateway Section
        'payment_gateways' => 'Payment Gateways',
        'payment_gateways_description' => 'Manage available payment gateways for the system',
        'system' => 'System',
        'client' => 'Client',
        'percent' => 'Percent',
        'flatrate' => 'Flat Rate',
        'create_new_gateway' => 'Create New Gateway',
        'enable_this_gateway' => 'Enable this gateway',
        'enable_this_payment_method' => 'Enable this payment method',
        //Modal Gateway API Settings
            'gateway_api_settings' => 'Gateway API Settings',
            'api_key' => 'API Key',
            'activate_gateway' => 'Activate Gateway',
            'gateway_activation_description' => 'Enable or disable this gateway',
            'can_generate_link' => 'Can Generate Link',
            'can_generate_link_description' => 'Allow this gateway to generate payment links',
        //Modal Edit Payment Method
            'edit_payment_method' => 'Edit Payment Method',
            'arabic_name' => 'Arabic Name',
            'english_name' => 'English Name',
            'service_charge' => 'Service Charge',
            'description' => 'Description',
            'activate_method' => 'Activate Method',
            'method_activation_description' => 'Enable or disable this payment method',
        //Error Validation Messages
            'no_payment_gateways' => 'No payment gateway configured',
            'no_payment_methods' => 'No payment methods for this gateway',
        //Loading Message
            'loading_payment_gateways' => 'Loading payment gateways...',
        //Placeholder
            'optional' => 'Optional',
            'optional_description' => 'Optional description',
            'enter_gateway_name' => 'Enter gateway name',
            'paste_secret_key' => 'Paste your secret key',

    //Payment Methods Section
        'payment_methods' => 'Payment Methods',
        'payment_method_selection' => 'Payment Method Selection',
        'payment_method_selection_description' => 'Manage gateway for each payment method',
        'select_method' => 'Select a method to enable',
        'activate_first' => 'Activate first',
        'configure_gateway_first' => 'Configure payment gateway first in the Payment Gateways tab',
        //Error Validation Messages
        'no_payment_method_groups' => 'No payment method groups available',
        //Loading Message
            'loading_payment_methods' => 'Loading payment methods...',
    
    //Agent Charges Section
        'agent_charges' => 'Agent Charges',
        'loading_agent_charges' => 'Loading agent charges',
        'agent_extra_charge_settings' => 'Agent Extra Charge Settings',
        'agent_extra_charge_description' => 'Configure who bears extra charges (gateway fees) for profit calculation',
        'profit_calculation_works' => 'How profit calculation works',
        'agent_charge_deduction' => "Agent's Charge Deduction",
        
    //Table of Contents
        'template_name' => 'Template Name',
        'language' => 'Language',
        'status' => 'Status',
        'default' => 'Default',
        'created_by' => 'Created By',
        'created_at' => 'Created At',
        'actions' => 'Actions',
        'gateway_name' => 'Gateway Name',
        'amount' => 'Amount',
        'paid_by' => 'Paid By',
        'self_charge' => 'Self Charge',
        'charge_type' => 'Charge Type',

    //Status Options
        'active' => 'Active',
        'inactive' => 'Inactive',

    //Buttons
        'cancel' => 'Cancel',
        'update_template' => 'Update Template',
        'save_changes' => 'Save Changes',
        'save_selection' => 'Save Selection',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'add_first_gateway' => 'Add your first gateway',
        'add_gateway' => 'Add Gateway',
        'create_template' => 'Create Template',
        'delete' => 'Delete',
        'close' => 'Close',
        'payment_method' => 'Payment Method',

    //Sidebar Navigation
        'agent_loss' => 'Agent Loss',
        'notifications' => 'Notifications',

    //Terms Modal
        'set_as_default' => 'Set as default template',
        'set_as_default_description' => 'This will be the default template for the selected language',
        'add_template_description' => 'Add a new terms and conditions template',

    //Dropdown
        'select' => 'Select',

    //Payment Gateway Charges
        'charges' => 'Charges',
        'contract' => 'Contract',
        'back_office' => 'Back Office',
        'extra' => 'Extra',
        'see_payment_methods_below' => 'See payment methods below',
        'contract_charge' => 'Contract Charge',
        'back_office_charge' => 'Back Office Charge',
        'extra_charge_flat_rate' => 'Extra Charge (Flat Rate)',
        'actual_gateway_fee' => 'Actual gateway fee (API charge)',
        'back_office_charge_tooltip' => 'What you charge client (Contract + Markup). Must be ≥ Contract Charge',
        'extra_charge_tooltip' => 'Additional flat fee (in KWD) added to charges',
        'can_charge_invoice' => 'Can Charge Invoice',
        'can_charge_invoice_description' => 'Allow charging invoices with this gateway',
        'allow_payment_link_generation' => 'Allow payment link generation',
        'whatsapp_notification_updated' => 'WhatsApp notification setting updated successfully',

    //Agent Charges Detail
        'charge_settings' => 'Charge Settings',
        'configure_who_bears_for' => 'Configure who bears extra charges for',
        'company_bears_all' => 'Company Bears All',
        'company_bears_all_description' => 'Agent keeps full markup as profit',
        'agent_bears_all' => 'Agent Bears All',
        'agent_bears_all_description' => 'Full charges deducted from profit',
        'split_charges' => 'Charges shared based on percentage setting',
        'split_description' => 'Share charges by percentage',
        'agent_percentage' => 'Agent Percentage',
        'percentage_must_sum' => 'Agent and company percentages must sum to 100%',
        'notes_optional' => 'Notes (optional)',
        'notes_placeholder' => 'Any notes about this setting...',
        'reset_to_default' => 'Reset to Default',
        'saving' => 'Saving...',
        'configured' => 'Configured',
        'who_bears_extra_charges' => 'Who Bears Extra Charges?',
        'search_agents' => 'Search agents...',
        'no_agents_found' => 'No agents found',
        'bulk_update_charge_settings' => 'Bulk Update Charge Settings',
        'bulk_update_charge_description' => 'Update extra charge settings for multiple agents at once',
        'select_agents_first' => 'Please select agents from the table first',
        'updating_settings_for' => 'Updating settings for',
        'selected_agents' => 'selected agent(s)',
        'update_all' => 'Update All',
        'updating' => 'Updating...',
        'reset_confirm' => 'Reset this agent to default settings (Company Bears All)?',

    //Terms Condition
        'enter_terms_placeholder' => 'Enter your terms and conditions here...',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
];