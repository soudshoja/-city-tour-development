<nav class="w-full">
    <menu class="flex flex-wrap gap-8 mx-4">
        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full shadow-md">
            <x-icons.tasks class="w-4 h-4" />
            <span class="px-2 text-sm">Tasks</span>

            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            @can('viewAny', 'App\Models\Task')
            <menuitem><a href="{{ route('tasks.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Tasks
                list</a></menuitem>
            @endcan
            @can('viewAny', App\Models\Payment::class)
            <menuitem>
                <a href="{{ route('payment.outstanding') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Outstanding
                    </div>
                </a>
            </menuitem>
            @endcan
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <x-icons.finances class="w-4 h-4" />
            <span class="px-2 text-sm">Finances</span>
            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem><a href="{{ route('coa.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Chart Of Account</a></menuitem>
            @endcan
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem><a href="{{ route('bank-payments.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payment Voucher</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem><a href="{{ route('receipt-voucher.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Receipt 
                Voucher</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem><a href="{{ route('receivable-details.receivable-create') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Receivable</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem><a href="{{ route('payable-details.payable-create') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payable</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\Charge')
            <menuitem><div
                data-tooltip="This feature has been relocated to Settings."
                class="rounded-lg shadow-lg text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow cursor-not-allowed">Manage Charges</div>
            </menuitem>
            @endcan
            <!-- @can('viewAny', 'App\Models\Account')
            <menuitem><a href="{{ route('accounting.transaction') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Transactions</a>
            </menuitem>
            @endcan -->
            @can('viewCompanySummary', 'App\Models\Account')
            <menuitem><a href="{{ route('accounting.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Accounting</a>
            </menuitem>
            @endcan
            @can('manageLocks', 'App\Models\User')
            <menuitem><a href="{{ route('lock-management.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Lock Management</a>
            </menuitem>
            @endcan
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <x-icons.invoices class="w-4 h-4" />
            <span class="px-2 text-sm">Invoices</span>
            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            @can('viewAny', 'App\Models\Invoice')
            <menuitem>
            <a href="{{ route('invoices.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Invoices List</a>
            </menuitem>
            <menuitem><a href="{{ route('invoices.link') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Invoices Link</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\Payment')
            <menuitem><a href="{{ route('payment.link.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Payment Link</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\Refund')
            <menuitem><a href="{{ route('refunds.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Refund</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\AutoBilling')
            <menuitem><a href="{{ route('auto-billing.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Auto Billing</a>
            </menuitem>
            @endcan
            <menuitem><a href="{{ route('reminder.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Reminder</a>
            </menuitem>
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <x-icons.users class="w-4 h-4" />
            <span class="px-2 text-sm">Users</span>
            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            @can('viewAny', 'App\Models\User')
            <menuitem>
            <a href="{{ route('users.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Users List</a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\Company')
            <menuitem>
            <a href="{{ route('companies.list') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Companies List</a>
            </menuitem>
            @endcan
            @can('viewAny', App\Models\Branch::class)
            <menuitem><a href="{{ route('branches.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Branches List</a></menuitem>
            @endcan
            @can('viewAny', App\Models\Agent::class)
            <menuitem><a href="{{ route('agents.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Agents List</a></menuitem>
            @endcan
            @can('viewAny', App\Models\Client::class)
            <menuitem><a href="{{ route('clients.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Clients List</a></menuitem>
            @endcan

        </menu>
        </menuitem>

        <!-- <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="none">
                    <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Zm-4.562 7.902a3 3 0 1 0 3 5.195a3 3 0 0 0-3-5.196Zm15.124 0a2.999 2.999 0 1 1-2.998 5.194a2.999 2.999 0 0 1 2.998-5.194Z" />
                    <path fill="currentColor" fill-rule="evenodd" d="M9.003 6.125a3 3 0 0 1 .175-1.143a8.5 8.5 0 0 0-5.031 4.766a8.5 8.5 0 0 0-.502 4.817a3 3 0 0 1 .902-.723a7.5 7.5 0 0 1 4.456-7.717m5.994 0a7.5 7.5 0 0 1 4.456 7.717q.055.028.11.06c.3.174.568.398.792.663a8.5 8.5 0 0 0-5.533-9.583a3 3 0 0 1 .175 1.143m2.536 13.328a3 3 0 0 1-1.078-.42a7.5 7.5 0 0 1-8.91 0l-.107.065a3 3 0 0 1-.971.355a8.5 8.5 0 0 0 11.066 0" clip-rule="evenodd" />
                </g>
            </svg>
            <span class="px-2 text-sm">Branches</span>

            <svg class="h-4 w-4 rotate-90" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </a>
        <menu>
            <menuitem><a href="{{ route('branches.index') }}" class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Branches List</a></menuitem>

        </menu>
        </menuitem> -->

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <x-icons.reports class="w-4 h-4" />
            <span class="px-2 text-sm">Reports</span>
            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            <!-- <menuitem><a href="{{ route('reports.summary') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Summary</a>
            </menuitem>
            <menuitem><a href="{{ route('reports.accsummary') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Accounts</a>
            </menuitem>
            <menuitem><a href="{{ route('reports.performance') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Performance</a>
            </menuitem>
            <menuitem><a href="{{ route('reports.agent') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Agent
                Reports</a>
            </menuitem>
            <menuitem><a href="{{ route('reports.client') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Client
                Reports</a>
            </menuitem> -->
            @can('viewAny', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.paid-report') }}"
                class="text-xs p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow w-full text-center break-words whitespace-normal">
                Paid Acc Pay/Receive
            </a>
            </menuitem>
            <menuitem>
            <a href="{{ route('reports.unpaid-report') }}"
                class="text-xs p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow w-full text-center break-words whitespace-normal">
                Unpaid Acc Pay/Receive
            </a>
            </menuitem>
            @endcan
          <!--   @can('viewReconcile', 'App\Models\Report')
            <menuitem><a href="{{ route('reports.acc-reconcile') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Acc Reconcile</a>
            </menuitem>
            @endcan -->
            @can('viewProfitLoss', 'App\Models\Report')
            <menuitem><a href="{{ route('reports.profit-loss') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Profit & Loss</a>
            </menuitem>
            @endcan
            @can('viewSettlement', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.settlements') }}"
                class="block text-xs text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white shadow">
                Bank Settlement
            </a>
            </menuitem>
            @endcan
            @can('viewAny', 'App\Models\CoaCategory')
            <menuitem>
            <a href="{{ route('coa.transaction') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Transaction List</a>
            </menuitem>
            @endcan
            @can('viewCreditors', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.creditors') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Creditors Report</a>
            </menuitem>
            @endcan
            @can('viewDailySales', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.daily-sales') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Daily Sales</a>
            </menuitem>
            @endcan
            @can('viewTaskReport', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.tasks') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Task Report</a>
            </menuitem>
            @endcan
            @can('viewClientReport', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.client') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Client Report</a>
            </menuitem>
            @endcan
            @can('viewPaymentGatewaysReport', 'App\Models\Report')
            <menuitem>
            <a href="{{ route('reports.payment-gateways') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow break-words whitespace-normal">
                Payment Gateways Report
            </a>
            </menuitem>
            @endcan 
        </menu>
        </menuitem>

        <menuitem>
        <a class="bg-gray-200 dark:bg-gray-700 dark:text-white p-2 flex justify-center items-center w-full BoxShadow">
            <x-icons.settings class="w-4 h-4" />
            <span class="px-2 text-sm">Settings</span>
            <x-icons.chevron-down class="w-4 h-4" />
        </a>
        <menu>
            <menuitem>
            <a href="{{ route('settings.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Settings</a>
            @can('manage-system-settings')
            <menu class="flex px-2">
                <menuitem>
                <a href="{{ route('system-settings.index') }}"
                    class="text-xs justify-center text-center   px-4 py-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    System Setting
                </a>
                </menuitem>
            </menu>
            @endcan
            </menuitem>
            @can('viewAny', App\Models\Supplier::class)
            <menuitem>
            <a href="{{ route('suppliers.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Suppliers</a>
            </menuitem>
            @endcan
            @can('viewAny', App\Models\Role::class)
            <menuitem>
            <a href="{{ route('role.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Manage Roles</a>
            </menuitem>
            @endcan
            <menuitem>
            <a href="#"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Documentations</a>
            </menuitem>
            <menuitem>
            <a href="#"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Help
            </a>
            </menuitem>
            <!-- Main Menu Item -->
            <menuitem>
            @can('viewAny', App\Models\CurrencyExchange::class)
            <a href="{{ route('exchange.index') }}"
                class="text-xs justify-center text-center p-3 my-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow">Currency
                Exchange</a>
            <menu class="flex px-2">
                <menuitem>
                <a href="{{ route('exchange.histories.all') }}"
                    class="text-xs justify-center text-center   px-4 py-3 bg-white text-gray-600 dark:bg-gray-700 dark:text-white BoxShadow rounded-md hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                    Exchange History
                </a>
                </menuitem>
            </menu>
            @endcan
            </menuitem>

            <!-- Sub Menu -->


        </menu>
        </menuitem>

    </menu>
</nav>