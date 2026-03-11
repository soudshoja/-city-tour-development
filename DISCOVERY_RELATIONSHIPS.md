# Relationships & Constraints Report

## Database Schema: laravel_testing

---

## Foreign Key Relationships

### Primary Tables

| Table | Column | References | Referenced Table | Referenced Column |
|-------|--------|-----------|------------------|-------------------|
| clients | agent_id | agents | id |
| clients | account_id | accounts | id |
| tasks | client_id | clients | id |
| tasks | agent_id | agents | id |
| tasks | company_id | companies | id |
| tasks | supplier_id | suppliers | id |
| invoices | client_id | clients | id |
| invoices | agent_id | agents | id |
| invoices | country_id | countries | id |
| invoice_details | invoice_id | invoices | id |
| invoice_details | task_id | tasks | id |
| agents | user_id | users | id |
| agents | account_id | accounts | id |
| agents | branch_id | branches | id |
| agents | type_id | agent_type | id |
| companies | user_id | users | id |
| companies | country_id | countries | id |
| suppliers | country_id | countries | id |
| accounts | company_id | companies | id |
| accounts | parent_id | accounts | id (self-referencing) |
| accounts | reference_id | accounts | id (self-referencing) |

### Extended Relationships

| Table | Column | References | Referenced Table | Referenced Column |
|-------|--------|-----------|------------------|-------------------|
| task_flight_details | task_id | tasks | id |
| task_flight_details | country_id_from | countries | id |
| task_flight_details | country_id_to | countries | id |
| task_hotel_details | task_id | tasks | id |
| task_email | agent_id | agents | id |
| task_email | client_id | clients | id |
| task_email | company_id | companies | id |
| supplier_credentials | company_id | companies | id |
| supplier_credentials | supplier_id | suppliers | id |
| supplier_companies | company_id | companies | id |
| supplier_companies | supplier_id | suppliers | id |
| supplier_companies | account_id | accounts | id |
| supplier_destinations | supplier_id | suppliers | id |
| invoice_partials | invoice_id | invoices | id |
| invoice_partials | client_id | clients | id |
| invoice_partials | payment_id | payments | id |
| payments | invoice_id | invoices | id |
| transactions | invoice_id | invoices | id |
| transactions | branch_id | branches | id |
| transactions | company_id | companies | id |
| general_ledgers | invoice_id | invoices | id |
| general_ledgers | invoice_detail_id | invoice_details | id |
| general_ledgers | branch_id | branches | id |
| refunds | branch_id | branches | id |

---

## Enum Values Discovered

### tasks table
- **type**: `flight`, `hotel`, `visa`, `insurance`, `tour`, `cruise`, `car`, `rail`, `esim`, `event`, `lounge`, `ferry`
- **status**: `refund`, `issued`, `reissued`, `void`, `ticketed`, `confirmed`, `emd`, `refunded`, `on hold`

### invoices table
- **status**: `paid`, `unpaid`, `partial`, `paid by refund`, `refunded`, `partial refund`

### clients table
- **status**: `active`, `inactive`

### task_emails table
- **type**: `flight`, `hotel`

### payment_methods table
- **type**: `myfatoorah`, `tap`, `hesabe`, `upayment`

### refund_clients table
- **status**: `pending`, `completed`, `failed`

### file_uploads table
- **status**: `pending`, `completed`, `failed`

### supplier_surcharges table
- **charge_mode**: `task`, `reference`

### supplier_surcharge_references table
- **charge_behavior**: `single`, `repetitive`

### accounts table
- **balance_must_be**: `debit`, `credit`
- **account_dimension**: `service`, `payment`, `both`
- **label**: (enum values) - see accounts table structure

### task_visa_details table
- **number_of_entries**: `single`, `double`, `multiple`

### transactions table
- **entity_type**: `company`, `branch`, `agent`, `client`
- **reference_type**: `Invoice`, `Payment`

### messages table
- **type**: `prompt`, `action`, `answer`
- **role**: `user`, `assistant`

### airlines table
- **airline_type**: `full_service`, `low_cost`, `charter`, `cargo`

---

## Unique Constraints

| Table | Columns | Constraint Type |
|-------|---------|-----------------|
| tasks | (supplier_id, reference) | Composite Unique |
| invoices | invoice_number | Unique |
| agents | tbo_reference | Unique |
| agents | amadeus_id | Unique |
| companies | code | Unique |
| suppliers | name | (from context) |

---

## Critical Field Names

### Client Fields
- **Mobile/Phone field**: `clients.phone` (string, required)
- **Email field**: `clients.email` (string, nullable)
- **Name field**: `clients.name` (string, required)
- **Full Name**: Composed from `first_name`, `middle_name`, `last_name` (appended)
- **Status field**: `clients.status` (enum: `active`, `inactive`)
- **Wallet/Account field**: `clients.account_id` (foreign key → accounts.id)
- **Country Code**: `clients.country_code` (string, nullable)
- **Identification**:
  - `clients.passport_no` (string, nullable)
  - `clients.old_passport_no` (string, nullable)
  - `clients.civil_no` (string, nullable)

### Agent Fields
- **Agent Identifier**: `agents.tbo_reference` (string, unique, nullable)
- **Amadeus ID**: `agents.amadeus_id` (string, unique, nullable)
- **Name field**: `agents.name` (string, required)
- **Email field**: `agents.email` (string)
- **Phone field**: `agents.phone_number` (string)
- **Country Code**: `agents.country_code` (string, nullable)
- **Account field**: `agents.account_id` (foreign key → accounts.id)
- **Branch field**: `agents.branch_id` (foreign key → branches.id)
- **Type field**: `agents.type_id` (foreign key → agent_type.id)
- **Commission/Salary/Target**:
  - `agents.commission` (decimal, nullable)
  - `agents.salary` (decimal, nullable)
  - `agents.target` (decimal, nullable)

### Task Fields
- **Reference number**: `tasks.reference` (string, required, unique per supplier)
- **Client link**: `tasks.client_id` (foreign key → clients.id)
- **Agent link**: `tasks.agent_id` (foreign key → agents.id)
- **Company link**: `tasks.company_id` (foreign key → companies.id)
- **Supplier link**: `tasks.supplier_id` (foreign key → suppliers.id)
- **Type**: `tasks.type` (enum: 12 values)
- **Status**: `tasks.status` (enum: 9 values)
- **Pricing**:
  - `tasks.price` (decimal 10,2)
  - `tasks.tax` (decimal 10,2)
  - `tasks.surcharge` (decimal 10,2)
  - `tasks.total` (decimal 10,2)
  - `tasks.invoice_price` (decimal 10,2)
- **Payment Type**: `tasks.payment_type` (string, nullable)
- **Voucher Status**: `tasks.voucher_status` (string)
- **Enabled**: `tasks.enabled` (boolean, default: true)

### Invoice Fields
- **Invoice Number**: `invoices.invoice_number` (string, unique)
- **Client link**: `invoices.client_id` (foreign key → clients.id)
- **Agent link**: `invoices.agent_id` (foreign key → agents.id)
- **Status**: `invoices.status` (enum: 6 values including `partial refund`)
- **Currency**: `invoices.currency` (string)
- **Amounts**:
  - `invoices.sub_amount` (decimal 15,2)
  - `invoices.amount` (decimal 15,2)
  - `invoices.tax` (decimal 15,2, nullable)
  - `invoices.discount` (decimal 15,2, nullable)
  - `invoices.shipping` (decimal 15,2, nullable)
- **Dates**:
  - `invoices.invoice_date` (date)
  - `invoices.due_date` (date)
  - `invoices.paid_date` (timestamp, nullable)
- **Banking Info**:
  - `invoices.account_number` (string, nullable)
  - `invoices.bank_name` (string, nullable)
  - `invoices.swift_no` (string, nullable)
  - `invoices.iban_no` (string, nullable)
- **Payment Type**: `invoices.payment_type` (string, nullable)
- **Accept Payment**: `invoices.accept_payment` (string, nullable)
- **Label**: `invoices.label` (string, nullable)

### Invoice Detail Fields
- **Invoice link**: `invoice_details.invoice_id` (foreign key → invoices.id)
- **Task link**: `invoice_details.task_id` (foreign key → tasks.id)
- **Invoice number**: `invoice_details.invoice_number` (string)
- **Pricing**:
  - `invoice_details.task_price` (decimal 10,2)
  - `invoice_details.supplier_price` (decimal 10,2)
  - `invoice_details.markup_price` (decimal 10,2)
- **Description**: `invoice_details.task_description` (string)
- **Remarks**: `invoice_details.task_remark` (string)
- **Client Notes**: `invoice_details.client_notes` (string)
- **Payment Status**: `invoice_details.paid` (boolean)

### Account Fields (Accounting)
- **Name**: `accounts.name` (string)
- **Level**: `accounts.level` (integer)
- **Company link**: `accounts.company_id` (foreign key → companies.id)
- **Parent Account**: `accounts.parent_id` (self-reference, nullable)
- **Reference Account**: `accounts.reference_id` (self-reference, nullable)
- **Balances**:
  - `accounts.actual_balance` (decimal 10,2)
  - `accounts.budget_balance` (decimal 10,2)
  - `accounts.variance` (decimal 10,2)
- **Code**: `accounts.code` (string, nullable)
- **Balance Must Be**: (enum: `debit`, `credit`)
- **Account Dimension**: (enum: `service`, `payment`, `both`)
- **Label**: (enum values for account classification)

### Company Fields
- **Code**: `companies.code` (string, unique)
- **Name**: `companies.name` (string)
- **User link**: `companies.user_id` (foreign key → users.id)
- **Country link**: `companies.country_id` (foreign key → countries.id)
- **Status**: `companies.status` (boolean, default: 1)
- **Contact Info**:
  - `companies.phone` (string, nullable)
  - `companies.email` (string, nullable)
  - `companies.address` (string, nullable)

### Supplier Fields
- **Name**: `suppliers.name` (string)
- **Country link**: `suppliers.country_id` (foreign key → countries.id)
- **Auth Method**: `suppliers.auth_method` (string, default: `basic`)
- **Contact Info**:
  - `suppliers.contact_person` (string, nullable)
  - `suppliers.email` (string, nullable)
  - `suppliers.phone` (string, nullable)
  - `suppliers.address` (string, nullable)
  - `suppliers.city` (string, nullable)
  - `suppliers.state` (string, nullable)
  - `suppliers.postal_code` (string, nullable)
- **Website**: `suppliers.website` (string, nullable)
- **Payment Terms**: `suppliers.payment_terms` (string, nullable)

---

## Relationship Verification

### Client → Agent Relationship
- Primary: `clients.agent_id` → `agents.id`
- Agents can have multiple clients (one-to-many)
- Agent identification: `tbo_reference` or `amadeus_id`
- Sample query:
```sql
SELECT c.id, c.name, c.phone, a.name AS agent_name, a.tbo_reference
FROM clients c
LEFT JOIN agents a ON a.id = c.agent_id
LIMIT 3;
```

### Client → Account Relationship (Wallet/Ledger)
- `clients.account_id` → `accounts.id`
- Each client can have one associated account
- Account tracks actual and budget balances
- Sample query:
```sql
SELECT c.id, c.name, c.phone, acc.name, acc.actual_balance
FROM clients c
LEFT JOIN accounts acc ON acc.id = c.account_id
LIMIT 3;
```

### Task → Client Relationship
- `tasks.client_id` → `clients.id`
- Tasks belong to clients (many-to-one)
- Each task has a unique supplier reference per supplier
- Sample query:
```sql
SELECT t.id, t.reference, t.type, t.status, c.name, c.phone
FROM tasks t
LEFT JOIN clients c ON c.id = t.client_id
LIMIT 3;
```

### Task → Agent Relationship
- `tasks.agent_id` → `agents.id`
- Tasks are assigned to agents (many-to-one)
- Agents can have multiple tasks

### Invoice → Client Relationship
- `invoices.client_id` → `clients.id`
- Invoices are issued to clients (many-to-one)
- Sample query:
```sql
SELECT i.id, i.invoice_number, i.status, c.name, c.phone
FROM invoices i
LEFT JOIN clients c ON c.id = i.client_id
LIMIT 3;
```

### Invoice → Agent Relationship
- `invoices.agent_id` → `agents.id`
- Invoices are created by agents (many-to-one)

### Invoice Detail → Task Relationship
- `invoice_details.task_id` → `tasks.id`
- Invoice details represent billing line items for tasks
- Each detail contains task pricing, supplier pricing, and markup
- Sample query:
```sql
SELECT id.id, id.invoice_number, id.task_id, t.reference, t.type
FROM invoice_details id
LEFT JOIN tasks t ON t.id = id.task_id
LIMIT 3;
```

### Invoice Detail → Invoice Relationship
- `invoice_details.invoice_id` → `invoices.id`
- Each detail belongs to one invoice (many-to-one)

### Agent → Account Relationship
- `agents.account_id` → `accounts.id`
- Agents can have associated accounting accounts
- Accounts used for agent commission tracking

### Company → Account Relationship (Chart of Accounts)
- `accounts.company_id` → `companies.id`
- Companies have hierarchical chart of accounts
- Accounts can reference parent accounts for hierarchy
- Sample query:
```sql
SELECT a.id, a.name, a.level, a.actual_balance,
       parent.name AS parent_account_name
FROM accounts a
LEFT JOIN accounts parent ON parent.id = a.parent_id
WHERE a.company_id = 1
LIMIT 5;
```

### Task → Supplier Relationship
- `tasks.supplier_id` → `suppliers.id`
- Tasks reference suppliers (hotel, airline, etc.)
- Unique constraint: (supplier_id, reference) per task

### Task → Company Relationship
- `tasks.company_id` → `companies.id`
- Multi-tenant isolation by company

---

## Data Integrity Observations

1. **Client Mobile Field**: Required field `clients.phone` - cannot be NULL
2. **Agent Identifier Options**:
   - `tbo_reference`: For TBO Holidays integration (unique, nullable)
   - `amadeus_id`: For Amadeus/GDS integration (unique, nullable)
   - Both can be NULL, use `agents.id` as fallback
3. **Client Account**: Optional (`clients.account_id` nullable) - wallet feature is optional
4. **Task Status Enum**: Updated to include `on hold` status (space not underscore)
5. **Invoice Status**: Extended to include `partial refund` for refund accounting
6. **Composite Unique**: Tasks identified by (supplier_id, reference) combination
7. **Soft Deletes**: Related tables have soft delete support
8. **Cascade Deletes**: Task flight/hotel details cascade on task deletion
9. **Self-Referencing Accounts**: Support hierarchical chart of accounts
10. **Multi-Currency**: Invoices track currency separately from default

---

## Critical Notes for Data Import/Export

- **Always check**: Client phone is required, cannot be NULL
- **Agent matching**: Use `tbo_reference` or `amadeus_id` if available, otherwise use `id`
- **Invoice linkage**: Must validate task_id exists in invoice_details
- **Account hierarchy**: Verify parent_id chains are not circular
- **Company context**: All queries should filter by company_id for tenant isolation
- **Status synchronization**: Task and invoice statuses must be coordinated during refunds
- **Currency conversion**: Handle multi-currency in invoices with exchange rates

