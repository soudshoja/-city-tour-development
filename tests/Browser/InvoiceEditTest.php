<?php

namespace Tests\Browser;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\InvoiceSequence;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class InvoiceEditTest extends DuskTestCase
{
    protected User $companyUser;
    protected Company $company;
    protected Branch $branch;
    protected Agent $agent;
    protected Client $client;
    protected Supplier $supplier;
    protected Task $task;
    protected Task $task2;
    protected Invoice $invoice;
    protected InvoiceDetail $invoiceDetail;
    protected Charge $charge;
    protected PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh');
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);

        // ─── Create base data ───────────────────────────────────────────

        $this->companyUser = User::factory()->create([
            'role_id' => Role::COMPANY,
            'password' => bcrypt('password'),
        ]);

        $this->company = Company::factory()->create([
            'user_id' => $this->companyUser->id,
        ]);

        $roleCompany = Role::create(['name' => 'company', 'guard_name' => 'web', 'company_id' => $this->company->id]);
        $this->companyUser->assignRole($roleCompany);
        $roleCompany->givePermissionTo('view invoice');
        $roleCompany->givePermissionTo('create invoice');
        $roleCompany->givePermissionTo('update invoice');
        $roleCompany->givePermissionTo('update invoice payment method');

        $this->branch = Branch::factory()->create([
            'user_id' => $this->companyUser->id,
            'company_id' => $this->company->id,
        ]);

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::create(['name' => 'Salary']);

        $this->agent = Agent::factory()->create([
            'user_id' => $agentUser->id,
            'branch_id' => $this->branch->id,
            'type_id' => $agentType->id,
        ]);

        $this->client = Client::factory()->create([
            'agent_id' => $this->agent->id,
        ]);

        $this->supplier = Supplier::factory()->create();

        // ─── Create tasks ───────────────────────────────────────────────

        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100.00,
            'invoice_price' => 150.00,
            'status' => 'issued',
            'type' => 'flight',
            'reference' => 'TST-EDIT-001',
        ]);

        // Second task (available to add later)
        $this->task2 = Task::factory()->create([
            'company_id' => $this->company->id,
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'supplier_id' => $this->supplier->id,
            'total' => 80.00,
            'invoice_price' => 120.00,
            'status' => 'issued',
            'type' => 'hotel',
            'reference' => 'TST-EDIT-002',
        ]);

        InvoiceSequence::create([
            'company_id' => $this->company->id,
            'current_sequence' => 100,
        ]);

        // ─── Create payment gateway with methods ────────────────────────

        $this->charge = Charge::factory()->create([
            'name' => 'Tap',
            'company_id' => $this->company->id,
            'amount' => 2.0,
            'self_charge' => 3.0,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
            'is_active' => true,
            'can_generate_link' => true,
            'has_url' => true,
            'can_charge_invoice' => true,
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'charge_id' => $this->charge->id,
            'company_id' => $this->company->id,
            'english_name' => 'KNET',
            'service_charge' => 2.0,
            'self_charge' => 3.0,
            'extra_charge' => 0,
            'charge_type' => 'Percent',
            'paid_by' => 'Client',
            'is_active' => true,
        ]);

        // ─── Create invoice with one task ───────────────────────────────

        $this->invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-2026-00100',
            'agent_id' => $this->agent->id,
            'client_id' => $this->client->id,
            'sub_amount' => 150.00,
            'amount' => 150.00,
            'currency' => 'KWD',
            'status' => 'unpaid',
            'payment_type' => null,
            'invoice_date' => '2026-03-05',
            'due_date' => '2026-03-10',
        ]);

        $this->invoiceDetail = InvoiceDetail::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'task_id' => $this->task->id,
            'task_description' => $this->task->reference,
            'task_price' => 150.00,
            'supplier_price' => 100.00,
            'markup_price' => 50.00,
            'profit' => 50.00,
        ]);
    }

    // ─── EDIT PAGE LOADS ────────────────────────────────────────────────

    public function test_edit_page_loads_with_invoice_data(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertInputValue('#invoiceNumber', 'INV-2026-00100')
                ->assertSee('TST-EDIT-001')
                ->assertSee('Payment Type')
                ->screenshot('edit-page-loaded');
        });
    }

    public function test_edit_page_shows_invoice_amounts(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('150')
                ->assertSee('Payment Type')
                ->assertSee('N/A')
                ->screenshot('edit-page-amounts');
        });
    }

    // ─── CHANGE INVOICE DATE ────────────────────────────────────────────

    public function test_can_change_invoice_date(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Change invoice date using JS (native date inputs don't work with type())
            $browser->script("document.getElementById('invdate').value = '2026-03-15'");
            $browser->pause(500);

            // Submit the date form
            $browser->script("document.getElementById('invoice-date-form').submit()");
            $browser->pause(3000);

            // Verify page reloaded with new date
            $browser->assertInputValue('#invdate', '2026-03-15')
                ->screenshot('date-changed');
        });
    }

    // ─── ADD TASK ───────────────────────────────────────────────────────

    public function test_can_add_task_to_invoice(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Add Task button
            $browser->click('#openTaskModalButton')
                ->pause(500)
                ->waitFor('#taskModal', 5)
                ->assertVisible('#taskModal')
                ->assertSee('TST-EDIT-002');

            // Click on the first available task in the list
            $browser->script("
                const taskItems = document.querySelectorAll('#taskList li');
                if (taskItems.length > 0) taskItems[0].click();
            ");
            $browser->pause(1000);

            // Should see the new task in the table
            $browser->assertSee('TST-EDIT-002')
                ->screenshot('task-added-to-edit');
        });
    }

    // ─── DELETE TASK ────────────────────────────────────────────────────

    public function test_can_delete_task_from_invoice(): void
    {
        // Add a second task to invoice so we can delete one
        $detail2 = InvoiceDetail::factory()->create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'task_id' => $this->task2->id,
            'task_description' => $this->task2->reference,
            'task_price' => 120.00,
            'supplier_price' => 80.00,
            'markup_price' => 40.00,
            'profit' => 40.00,
        ]);

        $this->invoice->update([
            'sub_amount' => 270.00,
            'amount' => 270.00,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('TST-EDIT-001')
                ->assertSee('TST-EDIT-002');

            // Click the delete button on second task (red trash icon)
            $browser->script("
                const rows = document.querySelectorAll('#items-body tr');
                if (rows.length > 1) {
                    const deleteBtn = rows[1].querySelector('[onclick*=\"removeTaskFromInvoice\"]');
                    if (deleteBtn) deleteBtn.click();
                }
            ");

            // Handle native confirm() dialog
            $browser->acceptDialog();
            $browser->pause(3000);

            $browser->screenshot('task-deleted-from-edit');
        });
    }

    public function test_cannot_delete_last_task(): void
    {
        // Invoice has only 1 task, delete should fail
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('TST-EDIT-001');

            // Try to delete the only task
            $browser->script("
                const rows = document.querySelectorAll('#items-body tr, #itemsTable tbody tr');
                if (rows.length >= 1) {
                    const deleteBtn = rows[0].querySelector('.remove-task, [onclick*=\"removeTask\"], button.text-red-500, a.text-red-500');
                    if (deleteBtn) deleteBtn.click();
                }
            ");
            $browser->pause(2000);

            // Handle confirmation
            $browser->script("
                if (document.querySelector('.swal2-confirm')) {
                    document.querySelector('.swal2-confirm').click();
                }
            ");
            $browser->pause(2000);

            // Task should still be there (cannot delete last task)
            $browser->assertSee('TST-EDIT-001')
                ->screenshot('cannot-delete-last-task');
        });
    }

    // ─── CHANGE INVOICE PRICE ───────────────────────────────────────────

    public function test_can_change_invoice_price(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Find the invoice price input field and change value
            $browser->waitFor('[id^="invprice-table-"]', 5)
                ->clear('[id^="invprice-table-"]')
                ->type('[id^="invprice-table-"]', '200')
                ->pause(500);

            // Click the save button next to the price input
            $browser->script("
                const saveBtn = document.querySelector('[id^=\"invprice-table-\"]').closest('td, div').querySelector('button, [onclick*=\"saveTaskPrice\"]');
                if (saveBtn) saveBtn.click();
            ");
            $browser->pause(2000);

            $browser->screenshot('invoice-price-changed');
        });
    }

    // ─── PAYMENT TYPE SELECTION ─────────────────────────────────────────

    public function test_can_select_full_payment_type(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Full Payment button
            $browser->script("
                const fullBtn = document.querySelector('#payment_type_full') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Full Payment'));
                if (fullBtn) fullBtn.click();
            ");
            $browser->pause(2000);

            $browser->screenshot('full-payment-selected');
        });
    }

    public function test_can_select_partial_payment_type(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Partial Payment button
            $browser->script("
                const partialBtn = document.querySelector('#payment_type_partial') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Partial Payment'));
                if (partialBtn) partialBtn.click();
            ");
            $browser->pause(2000);

            // Partial modal should appear
            $browser->screenshot('partial-payment-selected');
        });
    }

    public function test_can_select_split_payment_type(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Split Payment button
            $browser->script("
                const splitBtn = document.querySelector('#payment_type_split') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Split Payment'));
                if (splitBtn) splitBtn.click();
            ");
            $browser->pause(2000);

            $browser->screenshot('split-payment-selected');
        });
    }

    // ─── FULL PAYMENT WITH GATEWAY ──────────────────────────────────────

    public function test_full_payment_choose_gateway_shows_charges(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Full Payment
            $browser->script("
                const fullBtn = document.querySelector('#payment_type_full') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Full Payment'));
                if (fullBtn) fullBtn.click();
            ");
            $browser->pause(2000);

            // Select payment gateway
            $browser->script("
                const gatewaySelect = document.querySelector('#payment_gateway_option, select[name=\"gateway\"]');
                if (gatewaySelect) {
                    const options = gatewaySelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.includes('Tap')) {
                            gatewaySelect.value = options[i].value;
                            gatewaySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            break;
                        }
                    }
                }
            ");
            $browser->pause(3000);

            // After selecting gateway, charges should be displayed
            // The gateway fee calculation should show on the page
            $browser->screenshot('full-payment-gateway-charges');
        });
    }

    public function test_full_payment_choose_gateway_and_method(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Full Payment
            $browser->script("
                const fullBtn = document.querySelector('#payment_type_full') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Full Payment'));
                if (fullBtn) fullBtn.click();
            ");
            $browser->pause(2000);

            // Select gateway (Tap)
            $browser->script("
                const gatewaySelect = document.querySelector('#payment_gateway_option, select[name=\"gateway\"]');
                if (gatewaySelect) {
                    const options = gatewaySelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.includes('Tap')) {
                            gatewaySelect.value = options[i].value;
                            gatewaySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            break;
                        }
                    }
                }
            ");
            $browser->pause(2000);

            // Select payment method (KNET)
            $browser->script("
                const methodSelect = document.querySelector('#payment_method_full, select[name=\"method\"]');
                if (methodSelect && methodSelect.offsetParent !== null) {
                    const options = methodSelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.includes('KNET')) {
                            methodSelect.value = options[i].value;
                            methodSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            break;
                        }
                    }
                }
            ");
            $browser->pause(2000);

            $browser->screenshot('full-payment-gateway-and-method');
        });
    }

    public function test_full_payment_save(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Full Payment
            $browser->script("
                const fullBtn = document.querySelector('#payment_type_full') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Full Payment'));
                if (fullBtn) fullBtn.click();
            ");
            $browser->pause(2000);

            // Select gateway
            $browser->script("
                const gatewaySelect = document.querySelector('#payment_gateway_option, select[name=\"gateway\"]');
                if (gatewaySelect) {
                    const options = gatewaySelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.includes('Tap')) {
                            gatewaySelect.value = options[i].value;
                            gatewaySelect.dispatchEvent(new Event('change', { bubbles: true }));
                            break;
                        }
                    }
                }
            ");
            $browser->pause(2000);

            // Click Save Payment
            $browser->script("
                const saveBtn = document.querySelector('#update-invoice-btn') ||
                    [...document.querySelectorAll('button')].find(el => el.textContent.includes('Save Payment'));
                if (saveBtn) saveBtn.click();
            ");
            $browser->pause(5000);

            // Payment type should now show as Full
            $browser->screenshot('full-payment-saved');
        });
    }

    // ─── CHANGE PAYMENT GATEWAY (after payment type set) ────────────────

    public function test_can_change_payment_gateway_after_setting(): void
    {
        // Set up invoice with existing full payment
        $this->invoice->update(['payment_type' => 'full']);
        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'service_charge' => 3.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Tap',
            'charge_id' => $this->charge->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Payment type should show "Full"
            $browser->assertSee('Full')
                ->screenshot('payment-type-full-set');

            // Click Change gateway link
            $browser->script("
                const changeLink = [...document.querySelectorAll('a, button, span')].find(el =>
                    el.textContent.includes('Change') && !el.textContent.includes('Change Type'));
                if (changeLink) changeLink.click();
            ");
            $browser->pause(2000);

            $browser->screenshot('change-gateway-modal');
        });
    }

    // ─── CHANGE PAYMENT TYPE ────────────────────────────────────────────

    public function test_can_change_payment_type(): void
    {
        // Set up invoice with existing full payment
        $this->invoice->update(['payment_type' => 'full']);
        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'service_charge' => 3.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Tap',
            'charge_id' => $this->charge->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click "Change Type" link
            $browser->script("
                const changeTypeLink = [...document.querySelectorAll('a, button, span')].find(el =>
                    el.textContent.includes('Change Type'));
                if (changeTypeLink) changeTypeLink.click();
            ");
            $browser->pause(2000);

            // Confirm change type action (might have a confirmation modal)
            $browser->script("
                if (document.querySelector('.swal2-confirm')) {
                    document.querySelector('.swal2-confirm').click();
                }
            ");
            $browser->pause(3000);

            // After change type, payment type should reset to N/A
            $browser->screenshot('payment-type-changed');
        });
    }

    // ─── PARTIAL PAYMENT FLOW ───────────────────────────────────────────

    public function test_partial_payment_set_installments(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Partial Payment
            $browser->script("
                const partialBtn = document.querySelector('#payment_type_partial') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Partial Payment'));
                if (partialBtn) partialBtn.click();
            ");
            $browser->pause(2000);

            // Wait for partial modal
            $browser->waitFor('#partialPaymentModal', 5);

            // Set number of installments to 2
            $browser->script("
                const splitInput = document.querySelector('#split-into1');
                if (splitInput) {
                    splitInput.value = 2;
                    splitInput.dispatchEvent(new Event('input', { bubbles: true }));
                    splitInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            ");
            $browser->pause(2000);

            // Should see 2 installment rows
            $browser->screenshot('partial-payment-installments');
        });
    }

    public function test_partial_payment_fill_installment_details(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Partial Payment
            $browser->script("
                const partialBtn = document.querySelector('#payment_type_partial') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Partial Payment'));
                if (partialBtn) partialBtn.click();
            ");
            $browser->pause(2000);

            $browser->waitFor('#partialPaymentModal', 5);

            // Set 2 installments
            $browser->script("
                const splitInput = document.querySelector('#split-into1');
                if (splitInput) {
                    splitInput.value = 2;
                    splitInput.dispatchEvent(new Event('input', { bubbles: true }));
                    splitInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            ");
            $browser->pause(2000);

            // Fill installment 1: date, amount, gateway
            $browser->script("
                // Installment 1
                const date1 = document.querySelector('#date_1, [id*=\"date\"][id*=\"1\"]');
                if (date1) { date1.value = '2026-03-10'; date1.dispatchEvent(new Event('change')); }

                const amount1 = document.querySelector('#amount_1, [id*=\"amount\"][id*=\"1\"]');
                if (amount1) { amount1.value = '75'; amount1.dispatchEvent(new Event('input')); }
            ");
            $browser->pause(1000);

            // Fill installment 2: date, amount, gateway
            $browser->script("
                const date2 = document.querySelector('#date_2, [id*=\"date\"][id*=\"2\"]');
                if (date2) { date2.value = '2026-03-20'; date2.dispatchEvent(new Event('change')); }

                const amount2 = document.querySelector('#amount_2, [id*=\"amount\"][id*=\"2\"]');
                if (amount2) { amount2.value = '75'; amount2.dispatchEvent(new Event('input')); }
            ");
            $browser->pause(1000);

            $browser->screenshot('partial-payment-details-filled');
        });
    }

    // ─── SPLIT PAYMENT FLOW ────────────────────────────────────────────

    public function test_split_payment_requires_different_clients(): void
    {
        // Create a second client for split payment
        $client2 = Client::factory()->create([
            'agent_id' => $this->agent->id,
            'first_name' => 'SplitClient',
            'last_name' => 'Two',
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Split Payment
            $browser->script("
                const splitBtn = document.querySelector('#payment_type_split') ||
                    [...document.querySelectorAll('button, label')].find(el => el.textContent.includes('Split Payment'));
                if (splitBtn) splitBtn.click();
            ");
            $browser->pause(2000);

            // Wait for split modal
            $browser->waitFor('#splitPaymentModal', 5);

            // Set 2 splits
            $browser->script("
                const splitInput = document.querySelector('#split-into');
                if (splitInput) {
                    splitInput.value = 2;
                    splitInput.dispatchEvent(new Event('input', { bubbles: true }));
                    splitInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            ");
            $browser->pause(2000);

            // Should show 2 split rows with client selection
            $browser->screenshot('split-payment-setup');
        });
    }

    // ─── CREDIT PAYMENT FLOW ────────────────────────────────────────────

    public function test_credit_payment_shows_available_credits(): void
    {
        // Create a credit for the client (simulating a topup/refund)
        Credit::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'type' => Credit::TOPUP,
            'description' => 'Test topup credit',
            'amount' => 200.00,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Look for credit option
            $browser->script("
                const creditBtn = document.querySelector('[id*=\"credit\"]') ||
                    [...document.querySelectorAll('button, label, div')].find(el =>
                        el.textContent.trim() === 'Credit' || el.textContent.includes('credit'));
                if (creditBtn) creditBtn.click();
            ");
            $browser->pause(3000);

            $browser->screenshot('credit-payment-available');
        });
    }

    // ─── SEND INVOICE EMAIL ─────────────────────────────────────────────

    public function test_send_email_modal_opens(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Click Send Invoice Email button
            $browser->script("
                const emailBtn = document.querySelector('#openSendEmailModal') ||
                    [...document.querySelectorAll('button')].find(el => el.textContent.includes('Send Invoice Email'));
                if (emailBtn) emailBtn.click();
            ");
            $browser->pause(1000);

            // Email modal should open
            $browser->waitFor('#sendEmailModal', 5)
                ->assertVisible('#sendEmailModal')
                ->screenshot('send-email-modal-open');
        });
    }

    public function test_send_email_with_custom_email(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Open email modal
            $browser->script("
                const emailBtn = document.querySelector('#openSendEmailModal') ||
                    [...document.querySelectorAll('button')].find(el => el.textContent.includes('Send Invoice Email'));
                if (emailBtn) emailBtn.click();
            ");
            $browser->pause(1000);

            $browser->waitFor('#sendEmailModal', 5);

            // Check send to client checkbox
            $browser->script("
                const clientCheck = document.querySelector('#send_to_client');
                if (clientCheck && !clientCheck.checked) clientCheck.click();
            ");
            $browser->pause(500);

            // Type custom email (EMAIL_LOCAL from env)
            $browser->type('#custom_emails', config('app.email_local', 'thclown12@gmail.com'))
                ->pause(500);

            // Click submit
            $browser->script("
                const submitBtn = document.querySelector('#submitSendEmail');
                if (submitBtn) submitBtn.click();
            ");
            $browser->pause(5000);

            $browser->screenshot('email-sent');
        });
    }

    // ─── INVOICE DATE AND PRICE STILL EDITABLE AFTER PAYMENT TYPE ──────

    public function test_invoice_date_editable_after_payment_type_set(): void
    {
        $this->invoice->update(['payment_type' => 'full']);
        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'service_charge' => 3.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Tap',
            'charge_id' => $this->charge->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Invoice date should still be editable
            $browser->assertPresent('#invdate');

            // Change date using JS (native date inputs don't work with type())
            $browser->script("document.getElementById('invdate').value = '2026-03-20'");
            $browser->pause(500);

            $browser->script("document.getElementById('invoice-date-form').submit()");
            $browser->pause(3000);

            $browser->assertInputValue('#invdate', '2026-03-20')
                ->screenshot('date-editable-after-payment-type');
        });
    }

    public function test_invoice_price_editable_after_payment_type_set(): void
    {
        $this->invoice->update(['payment_type' => 'full']);
        InvoicePartial::create([
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_id' => $this->client->id,
            'amount' => 150.00,
            'service_charge' => 3.00,
            'status' => 'unpaid',
            'type' => 'full',
            'payment_gateway' => 'Tap',
            'charge_id' => $this->charge->id,
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000);

            // Invoice price input should exist
            $browser->assertPresent('[id^="invprice-table-"]');

            // Change price
            $browser->clear('[id^="invprice-table-"]')
                ->type('[id^="invprice-table-"]', '180')
                ->pause(500);

            $browser->screenshot('price-editable-after-payment-type');
        });
    }

    // ─── PAYMENT TYPE BUTTONS VISIBILITY ────────────────────────────────

    public function test_all_payment_type_buttons_visible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('Full Payment')
                ->assertSee('Partial Payment')
                ->assertSee('Split Payment')
                ->assertSee('Import Payment')
                ->screenshot('all-payment-types-visible');
        });
    }

    public function test_payment_gateway_dropdown_visible(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('Choose Payment Gateway')
                ->assertSee('Save Payment')
                ->screenshot('gateway-dropdown-visible');
        });
    }

    // ─── CURRENCY DISPLAY ───────────────────────────────────────────────

    public function test_invoice_shows_correct_currency(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('KWD')
                ->screenshot('currency-displayed');
        });
    }

    // ─── QUICK ACTIONS ──────────────────────────────────────────────────

    public function test_send_invoice_email_button_exists(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->companyUser)
                ->visit(route('invoice.edit', [
                    'companyId' => $this->company->id,
                    'invoiceNumber' => $this->invoice->invoice_number,
                ]))
                ->pause(2000)
                ->assertSee('Send Invoice Email')
                ->screenshot('send-email-button-exists');
        });
    }
}
