# Schema & Tables Discovery Report

**Generated:** 2026-02-12
**Project:** Soud Laravel - Bulk Invoice Upload System
**Database Connection:** laravel_testing (MySQL)

## Database Connection Info
- **Database Name:** laravel_testing
- **Host:** 127.0.0.1
- **Port:** 3306
- **Username:** laravel_user
- **Password:** laravel_user_password

---

## Tables Found

### Core Business Tables (19 tables)

#### Invoice-Related Tables (5 tables)
- **invoices** - Main invoice records
- **invoice_details** - Line items/details for invoices
- **invoice_partials** - Partial payment records for invoices
- **invoice_sequence** - Invoice numbering sequence tracking
- **invoice_receipt** - Relationship between invoices and transactions

#### Task-Related Tables (6 tables)
- **tasks** - Main task records (flights, hotels, visas, etc.)
- **task_emails** - Email-sourced task data
- **task_flight_details** - Flight-specific task details
- **task_hotel_details** - Hotel booking details
- **task_insurance_details** - Insurance details
- **task_visa_details** - Visa processing details

#### Client/Agent Tables (3 tables)
- **clients** - Client master records
- **agents** - Travel agent records
- **companies** - Company/organization records

#### Payment Tables (5 tables)
- **payments** - Payment records
- **payment_applications** - Payment application to invoices
- **payment_items** - Line items for payments
- **payment_files** - Uploaded payment-related files
- **payment_methods** - Payment method definitions

#### Supporting Financial Tables (7 tables)
- **accounts** - Chart of accounts for accounting system
- **branches** - Company branch records
- **suppliers** - Supplier/vendor records
- **supplier_companies** - Company-supplier relationships
- **general_ledgers** - Accounting journal entries
- **credits** - Credit records for clients
- **refunds** - Refund transactions
- **charges** - Charge/fee definitions

#### Other Business Tables (6 tables)
- **file_uploads** - Uploaded file tracking
- **transactions** - Financial transaction records
- **document_processing_logs** - AI document processing audit trail
- **users** - User account records
- **currency_exchanges** - Currency exchange rates

---

## Detailed Table Structures

### Core Invoice Tables

#### Table: invoices
Primary invoice records for tracking client invoicing.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| invoice_number | varchar(255) | NO | UNI | NULL | |
| client_id | bigint unsigned | NO | FK | NULL | constrained |
| agent_id | bigint unsigned | NO | FK | NULL | constrained |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| currency | varchar(255) | NO | | NULL | |
| sub_amount | decimal(15,2) | NO | | NULL | |
| amount | decimal(15,2) | NO | | NULL | |
| status | enum('paid','unpaid','partial','draft','cancelled','sent','overdue','approved') | NO | | NULL | |
| invoice_date | date | NO | | NULL | |
| due_date | date | YES | | NULL | |
| paid_date | timestamp | YES | | NULL | |
| label | varchar(255) | YES | | NULL | |
| account_number | varchar(255) | YES | | NULL | |
| bank_name | varchar(255) | YES | | NULL | |
| swift_no | varchar(255) | YES | | NULL | |
| iban_no | varchar(255) | YES | | NULL | |
| country_id | bigint unsigned | YES | FK | NULL | |
| tax | decimal(15,2) | YES | | NULL | |
| discount | decimal(15,2) | YES | | NULL | |
| shipping | decimal(15,2) | YES | | NULL | |
| accept_payment | varchar(255) | YES | | NULL | |
| payment_type | varchar(255) | YES | | NULL | |
| client_credit | decimal(15,2) | YES | | 0.00 | |
| payment_settings | json | YES | | NULL | |
| partial_refund_status | varchar(255) | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: invoice_details
Line items for invoices linking to tasks.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| invoice_id | bigint unsigned | NO | FK | NULL | constrained |
| invoice_number | varchar(255) | NO | | NULL | |
| task_id | bigint unsigned | NO | FK | NULL | constrained |
| task_description | varchar(255) | NO | | NULL | |
| task_remark | varchar(255) | NO | | NULL | |
| client_notes | varchar(255) | NO | | NULL | |
| task_price | decimal(10,2) | NO | | NULL | |
| supplier_price | decimal(10,2) | NO | | NULL | |
| markup_price | decimal(10,2) | NO | | NULL | |
| profit_margin | decimal(10,2) | YES | | NULL | |
| profit_amount | decimal(10,2) | YES | | NULL | |
| paid | boolean | NO | | false | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: invoice_partials
Partial payment tracking for invoices.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| invoice_id | bigint unsigned | NO | FK | NULL | |
| invoice_number | varchar(255) | NO | | NULL | |
| client_id | bigint unsigned | NO | FK | NULL | |
| amount | decimal(15,2) | NO | | NULL | |
| status | varchar(255) | NO | | NULL | |
| expiry_date | date | NO | | NULL | |
| type | varchar(255) | NO | | NULL | |
| payment_gateway | varchar(255) | NO | | NULL | |
| payment_id | bigint unsigned | YES | FK | NULL | |
| payment_method_id | bigint unsigned | YES | FK | NULL | |
| service_charge | decimal(15,2) | YES | | NULL | |
| charge_payer | varchar(50) | YES | | NULL | |
| base_amount | decimal(15,2) | YES | | NULL | |
| has_payment_link | boolean | YES | | false | |
| receipt_voucher_id | bigint unsigned | YES | FK | NULL | |
| gateway_fee | decimal(15,2) | YES | | 0.00 | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: invoice_sequence
Tracks invoice number sequences per company.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | YES | FK | NULL | |
| current_sequence | int | YES | | 1 | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: invoice_receipt
Many-to-many relationship between invoices and transactions.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| invoice_id | bigint unsigned | NO | | NULL | |
| transaction_id | bigint unsigned | NO | | NULL | |
| receipt_voucher_id | bigint unsigned | YES | FK | NULL | |
| invoice_partial_id | bigint unsigned | YES | FK | NULL | |
| amount | decimal(15,2) | YES | | NULL | |
| is_used | boolean | YES | | false | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### Core Task Tables

#### Table: tasks
Main task records for travel bookings and services.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| client_id | bigint unsigned | NO | FK | NULL | constrained |
| agent_id | bigint unsigned | NO | FK | NULL | constrained |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| supplier_id | bigint unsigned | NO | | NULL | |
| type | varchar(50) | NO | | NULL | enum: flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry |
| status | varchar(50) | NO | | NULL | enum: issued, reissued, refund, void, confirmed, on_hold, ticketed, etc. |
| client_name | varchar(255) | NO | | NULL | |
| reference | varchar(255) | NO | | NULL | unique with supplier_id |
| duration | varchar(255) | NO | | NULL | |
| payment_type | varchar(255) | NO | | NULL | |
| price | decimal(14,3) | NO | | NULL | |
| tax | decimal(14,3) | NO | | NULL | |
| surcharge | decimal(14,3) | NO | | NULL | |
| total | decimal(14,3) | NO | | NULL | |
| cancellation_policy | text | YES | | NULL | |
| additional_info | text | YES | | NULL | |
| venue | varchar(255) | YES | | NULL | |
| invoice_price | decimal(14,3) | NO | | NULL | |
| voucher_status | varchar(255) | YES | | NULL | |
| ticket_number | varchar(255) | YES | | NULL | |
| original_ticket_number | varchar(255) | YES | | NULL | |
| original_reference | varchar(255) | YES | | NULL | |
| gds_reference | varchar(255) | YES | | NULL | |
| passenger_name | varchar(255) | YES | | NULL | |
| iata_number | varchar(50) | YES | | NULL | |
| original_price | decimal(14,3) | YES | | NULL | |
| original_total | decimal(14,3) | YES | | NULL | |
| original_tax | decimal(14,3) | YES | | NULL | |
| original_surcharge | decimal(14,3) | YES | | NULL | |
| supplier_status | varchar(255) | YES | | NULL | |
| supplier_created_date | datetime | YES | | NULL | |
| supplier_pay_date | datetime | YES | | NULL | |
| exchange_rate | decimal(10,6) | YES | | NULL | |
| default_penalty_fee | decimal(14,3) | YES | | NULL | |
| emd_status | varchar(255) | YES | | NULL | |
| cancellation_deadline | date | YES | | NULL | |
| issued_date | date | YES | | NULL | |
| expiry_date | date | YES | | NULL | |
| payment_method | varchar(255) | YES | | NULL | |
| file_name | varchar(255) | YES | | NULL | |
| taxes_record | json | YES | | NULL | |
| refund_charge | decimal(14,3) | YES | | NULL | |
| refund_date | date | YES | | NULL | |
| is_ancillary | boolean | YES | | false | |
| supplier_surcharge_id | bigint unsigned | YES | FK | NULL | |
| supplier_surcharge_amount | decimal(14,3) | YES | | NULL | |
| supplier_surcharge_references | json | YES | | NULL | |
| n8n_execution_id | varchar(255) | YES | | NULL | |
| n8n_workflow_id | varchar(255) | YES | | NULL | |
| enabled | boolean | NO | | true | |
| deleted_at | timestamp | YES | | NULL | soft delete |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |
| indexes | | | | | company_id, agent_id, client_id, supplier_id, status, created_at |

#### Table: task_emails
Task data extracted from emails.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| email_id | varchar(255) | NO | | NULL | |
| client_id | bigint unsigned | YES | FK | NULL | |
| client_name | varchar(255) | YES | | NULL | |
| agent_id | bigint unsigned | YES | FK | NULL | |
| agent_name | varchar(255) | YES | | NULL | |
| company_id | bigint unsigned | YES | FK | NULL | |
| company_name | varchar(255) | YES | | NULL | |
| type | enum('flight','hotel') | NO | | NULL | |
| status | varchar(255) | YES | | NULL | |
| reference | varchar(255) | YES | | NULL | |
| duration | int | YES | | NULL | |
| payment_type | varchar(255) | YES | | NULL | |
| price | decimal(10,2) | YES | | NULL | |
| tax | decimal(10,2) | YES | | NULL | |
| surcharge | decimal(10,2) | YES | | NULL | |
| total | decimal(10,2) | YES | | NULL | |
| cancellation_policy | text | YES | | NULL | |
| additional_info | text | YES | | NULL | |
| destination | varchar(255) | YES | | NULL | |
| vendor_name | varchar(255) | YES | | NULL | |
| supplier_id | bigint unsigned | YES | FK | NULL | |
| supplier_name | varchar(255) | YES | | NULL | |
| venue | varchar(255) | YES | | NULL | |
| invoice_price | decimal(10,2) | YES | | NULL | |
| voucher_status | varchar(255) | YES | | NULL | |
| enabled | boolean | NO | | false | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: task_flight_details
Flight-specific details for task type.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| task_id | bigint unsigned | NO | FK | NULL | constrained |
| airline | varchar(255) | YES | | NULL | |
| airline_id | bigint unsigned | YES | FK | NULL | |
| departure_airport | varchar(255) | YES | | NULL | |
| departure_airport_id | bigint unsigned | YES | FK | NULL | |
| arrival_airport | varchar(255) | YES | | NULL | |
| arrival_airport_id | bigint unsigned | YES | FK | NULL | |
| departure_date | date | YES | | NULL | |
| arrival_date | date | YES | | NULL | |
| flight_number | varchar(255) | YES | | NULL | |
| booking_reference | varchar(255) | YES | | NULL | |
| cabin_class | varchar(100) | YES | | NULL | |
| seat_number | varchar(255) | YES | | NULL | |
| is_ancillary | boolean | YES | | false | |
| deleted_at | timestamp | YES | | NULL | soft delete |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: task_hotel_details
Hotel booking details for task type.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| task_id | bigint unsigned | NO | FK | NULL | constrained |
| hotel_name | varchar(255) | YES | | NULL | |
| location | varchar(255) | YES | | NULL | |
| check_in_date | date | YES | | NULL | |
| check_out_date | date | YES | | NULL | |
| number_of_rooms | int | YES | | NULL | |
| number_of_nights | int | YES | | NULL | |
| room_type | varchar(255) | YES | | NULL | |
| board_type | varchar(100) | YES | | NULL | |
| confirmation_number | varchar(255) | YES | | NULL | |
| rate_per_night | decimal(10,2) | YES | | NULL | |
| total_cost | decimal(10,2) | YES | | NULL | |
| currency | varchar(10) | YES | | NULL | |
| cancellation_policy | text | YES | | NULL | |
| special_requests | text | YES | | NULL | |
| deleted_at | timestamp | YES | | NULL | soft delete |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: task_insurance_details
Insurance details for task type.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| task_id | bigint unsigned | NO | FK | NULL | constrained |
| insurance_type | varchar(255) | YES | | NULL | |
| provider | varchar(255) | YES | | NULL | |
| policy_number | varchar(255) | YES | | NULL | |
| coverage_amount | decimal(12,2) | YES | | NULL | |
| premium | decimal(10,2) | YES | | NULL | |
| start_date | date | YES | | NULL | |
| end_date | date | YES | | NULL | |
| terms_conditions | text | YES | | NULL | |
| deleted_at | timestamp | YES | | NULL | soft delete |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: task_visa_details
Visa processing details for task type.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| task_id | bigint unsigned | NO | FK | NULL | constrained |
| visa_type | varchar(255) | YES | | NULL | |
| destination_country | varchar(255) | YES | | NULL | |
| visa_issued_date | date | YES | | NULL | |
| visa_expiry_date | date | YES | | NULL | |
| visa_number | varchar(255) | YES | | NULL | |
| entry_permit_number | varchar(255) | YES | | NULL | |
| status | varchar(100) | YES | | NULL | |
| notes | text | YES | | NULL | |
| deleted_at | timestamp | YES | | NULL | soft delete |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### Client & Agent Tables

#### Table: clients
Client master records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | YES | FK | NULL | |
| name | varchar(255) | NO | | NULL | |
| first_name | varchar(255) | YES | | NULL | |
| middle_name | varchar(255) | YES | | NULL | |
| last_name | varchar(255) | YES | | NULL | |
| agent_id | bigint unsigned | YES | FK | NULL | |
| account_id | bigint unsigned | YES | | NULL | |
| email | varchar(255) | YES | | NULL | |
| phone | varchar(255) | NO | UNI | NULL | |
| address | varchar(255) | YES | | NULL | |
| passport_no | varchar(255) | YES | | NULL | |
| old_passport_no | varchar(255) | YES | | NULL | |
| civil_no | varchar(255) | YES | | NULL | |
| date_of_birth | date | YES | | NULL | |
| country_code | varchar(10) | YES | | NULL | |
| status | enum('active','inactive') | NO | | active | |
| credit | decimal(15,2) | YES | | 0.00 | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: agents
Travel agent records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| user_id | bigint unsigned | NO | FK | NULL | constrained to users |
| account_id | bigint unsigned | YES | FK | NULL | |
| branch_id | bigint unsigned | NO | FK | NULL | constrained |
| type_id | bigint unsigned | NO | FK | NULL | constrained |
| name | varchar(255) | NO | | NULL | |
| email | varchar(255) | NO | | NULL | |
| phone_number | varchar(255) | NO | | NULL | |
| tbo_reference | varchar(255) | YES | UNI | NULL | |
| amadeus_id | varchar(255) | YES | | NULL | |
| country_code | varchar(10) | YES | | NULL | |
| target | decimal(15,2) | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: companies
Company/organization master records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| user_id | bigint unsigned | NO | FK | NULL | constrained to users |
| country_id | bigint unsigned | NO | FK | NULL | |
| code | varchar(255) | NO | UNI | NULL | |
| name | varchar(255) | NO | | NULL | |
| address | varchar(255) | YES | | NULL | |
| phone | varchar(255) | YES | | NULL | |
| email | varchar(255) | YES | | NULL | |
| iata_code | varchar(10) | YES | | NULL | |
| status | boolean | NO | | true | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### Payment Tables

#### Table: payments
Payment records and history.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| voucher_number | varchar(255) | NO | | NULL | |
| payment_reference | varchar(255) | NO | | NULL | |
| invoice_id | bigint unsigned | YES | FK | NULL | |
| account_id | bigint unsigned | YES | FK | NULL | |
| from | varchar(255) | NO | | NULL | |
| pay | varchar(255) | NO | | NULL | |
| amount | decimal(15,3) | NO | | NULL | |
| base_amount | decimal(15,3) | YES | | NULL | |
| service_charge | decimal(15,3) | YES | | NULL | |
| currency | varchar(10) | NO | | NULL | |
| payment_date | datetime | NO | | NULL | |
| payment_method | varchar(255) | NO | | NULL | |
| status | varchar(50) | NO | | NULL | |
| account_number | varchar(255) | YES | | NULL | |
| bank_name | varchar(255) | YES | | NULL | |
| swift_no | varchar(255) | YES | | NULL | |
| iban_no | varchar(255) | YES | | NULL | |
| country | varchar(255) | YES | | NULL | |
| tax | decimal(15,3) | YES | | NULL | |
| shipping | decimal(15,3) | YES | | NULL | |
| payment_gateway | varchar(100) | YES | | NULL | |
| payment_url | text | YES | | NULL | |
| expiry_date | datetime | YES | | NULL | |
| completed | boolean | NO | | false | |
| is_book | boolean | YES | | false | |
| is_freeze | boolean | YES | | false | |
| created_by | bigint unsigned | YES | | NULL | |
| send_payment_receipt | boolean | YES | | false | |
| terms_conditions | json | YES | | NULL | |
| language | varchar(10) | YES | | en | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: payment_applications
Tracks application of payments to invoices.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| payment_id | bigint unsigned | NO | FK | NULL | constrained |
| invoice_id | bigint unsigned | NO | FK | NULL | constrained |
| invoice_partial_id | bigint unsigned | YES | FK | NULL | |
| credit_id | bigint unsigned | YES | FK | NULL | |
| amount | decimal(15,3) | NO | | NULL | |
| applied_by | bigint unsigned | YES | FK | NULL | constrained to users |
| applied_at | timestamp | NO | | CURRENT_TIMESTAMP | |
| notes | text | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |
| indexes | | | | | payment_id, invoice_id; invoice_partial_id |

#### Table: payment_items
Line items for payments.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| payment_id | bigint unsigned | NO | FK | NULL | constrained |
| product_name | varchar(255) | NO | | NULL | |
| quantity | decimal(10,2) | NO | | NULL | |
| unit_price | decimal(10,3) | NO | | NULL | |
| extended_amount | decimal(10,3) | NO | | NULL | |
| currency | varchar(10) | NO | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: payment_files
Uploaded payment-related files.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| payment_id | bigint unsigned | NO | | NULL | |
| file_id | varchar(255) | NO | | NULL | comment: File ID from resayil API |
| expiry_date | datetime | NO | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: payment_methods
Payment method definitions.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| name | varchar(255) | NO | | NULL | |
| type | varchar(50) | YES | | NULL | enum: card, bank_transfer, etc. |
| description | longtext | YES | | NULL | |
| charge_id | bigint unsigned | YES | FK | NULL | |
| group | bigint unsigned | YES | FK | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### Financial & Accounting Tables

#### Table: accounts
Chart of accounts for double-entry bookkeeping.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| parent_id | bigint unsigned | YES | FK | NULL | self-referencing |
| reference_id | bigint unsigned | YES | FK | NULL | self-referencing |
| name | varchar(255) | NO | | NULL | |
| code | varchar(255) | YES | | NULL | |
| level | int | NO | | NULL | account hierarchy level |
| actual_balance | decimal(15,2) | NO | | NULL | |
| budget_balance | decimal(15,2) | NO | | NULL | |
| variance | decimal(15,2) | NO | | NULL | |
| account_dimension | varchar(100) | YES | | NULL | |
| supplier_company_id | bigint unsigned | YES | FK | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: general_ledgers
General ledger entries for accounting.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| branch_id | bigint unsigned | NO | FK | NULL | constrained |
| account_id | bigint unsigned | NO | FK | NULL | constrained |
| transaction_id | bigint unsigned | YES | FK | NULL | |
| invoice_id | bigint unsigned | YES | FK | NULL | |
| invoice_detail_id | bigint unsigned | YES | FK | NULL | |
| type_reference_id | bigint unsigned | YES | | NULL | |
| task_id | bigint unsigned | YES | FK | NULL | |
| name | varchar(255) | NO | | NULL | |
| transaction_date | datetime | NO | | NULL | |
| description | varchar(255) | NO | | NULL | |
| debit | decimal(15,2) | NO | | 0.00 | |
| credit | decimal(15,2) | NO | | 0.00 | |
| balance | decimal(15,2) | NO | | 0.00 | |
| voucher_number | varchar(255) | YES | | NULL | |
| receipt_reference_number | varchar(255) | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: transactions
Financial transaction records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | YES | FK | NULL | |
| invoice_id | bigint unsigned | YES | FK | NULL | |
| account_id | bigint unsigned | YES | FK | NULL | |
| from | varchar(255) | YES | | NULL | |
| to | varchar(255) | YES | | NULL | |
| amount | decimal(15,2) | YES | | NULL | |
| status | varchar(50) | YES | | NULL | |
| currency | varchar(10) | YES | | NULL | |
| payment_reference | varchar(255) | YES | | NULL | |
| transaction_date | datetime | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: credits
Client credit records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | NO | | NULL | |
| client_id | bigint unsigned | NO | | NULL | |
| invoice_id | bigint unsigned | YES | FK | NULL | |
| invoice_partial_id | bigint unsigned | YES | FK | NULL | |
| payment_id | bigint unsigned | YES | FK | NULL | |
| refund_id | bigint unsigned | YES | FK | NULL | |
| account_id | bigint unsigned | YES | FK | NULL | |
| branch_id | bigint unsigned | YES | FK | NULL | |
| type | varchar(50) | YES | | NULL | |
| description | varchar(255) | YES | | NULL | |
| amount | decimal(15,2) | NO | | 0.00 | |
| topup_by | bigint unsigned | YES | FK | NULL | |
| gateway_fee | decimal(15,2) | YES | | 0.00 | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: refunds
Refund transaction records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| invoice_id | bigint unsigned | YES | FK | NULL | constrained |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| branch_id | bigint unsigned | NO | FK | NULL | constrained |
| agent_id | bigint unsigned | NO | FK | NULL | constrained |
| task_id | bigint unsigned | YES | FK | NULL | |
| amount | decimal(15,2) | NO | | NULL | |
| reason | text | NO | | NULL | |
| method | varchar(255) | NO | | NULL | |
| account_id | bigint unsigned | NO | FK | NULL | constrained |
| date | date | NO | | NULL | |
| reference | varchar(255) | YES | | NULL | |
| status | enum('pending','approved','rejected') | NO | | pending | |
| created_by | bigint unsigned | YES | FK | NULL | constrained to users |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: charges
Charge/fee definitions.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| name | varchar(255) | NO | | NULL | |
| type | varchar(255) | NO | | NULL | |
| description | varchar(255) | YES | | NULL | |
| amount | float | NO | | NULL | |
| can_generate_link | boolean | YES | | NULL | |
| system_default | boolean | YES | | false | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### Supplier & Company Tables

#### Table: suppliers
Supplier/vendor master records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| country_id | bigint unsigned | NO | FK | NULL | constrained |
| name | varchar(255) | NO | | NULL | |
| auth_method | varchar(100) | NO | | basic | |
| contact_person | varchar(255) | YES | | NULL | |
| email | varchar(255) | YES | | NULL | |
| phone | varchar(255) | YES | | NULL | |
| address | varchar(255) | YES | | NULL | |
| city | varchar(255) | YES | | NULL | |
| state | varchar(255) | YES | | NULL | |
| postal_code | varchar(255) | YES | | NULL | |
| website | varchar(255) | YES | | NULL | |
| payment_terms | varchar(255) | YES | | NULL | |
| is_online | boolean | YES | | true | |
| is_manual | boolean | YES | | false | |
| has_flight_api | boolean | YES | | false | |
| has_hotel_api | boolean | YES | | false | |
| has_visa_api | boolean | YES | | false | |
| has_insurance_api | boolean | YES | | false | |
| extra_categories | varchar(255) | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: supplier_companies
Relationship between suppliers and companies.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| supplier_id | bigint unsigned | NO | FK | NULL | |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| account_id | bigint unsigned | NO | FK | NULL | |
| group_id | bigint unsigned | YES | FK | NULL | |
| is_active | boolean | YES | | true | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: branches
Company branch records.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| user_id | bigint unsigned | NO | FK | NULL | constrained |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| name | varchar(255) | NO | | NULL | |
| email | varchar(255) | YES | | NULL | |
| phone | varchar(255) | YES | | NULL | |
| address | varchar(255) | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

---

### File Management & Processing Tables

#### Table: file_uploads
Uploaded file tracking and processing status.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| file_name | varchar(255) | NO | | NULL | |
| destination_path | varchar(255) | NO | | NULL | |
| user_id | bigint unsigned | NO | FK | NULL | constrained |
| supplier_id | bigint unsigned | NO | FK | NULL | constrained |
| task_id | bigint unsigned | YES | FK | NULL | constrained |
| status | enum('pending','completed','failed') | NO | | pending | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |

#### Table: document_processing_logs
AI document processing audit trail and tracking.

| Column | Type | Null | Key | Default | Extra |
|--------|------|------|-----|---------|-------|
| id | bigint unsigned | NO | PRI | NULL | auto_increment |
| company_id | bigint unsigned | NO | FK | NULL | constrained |
| supplier_id | bigint unsigned | YES | | NULL | |
| document_id | varchar(36) | NO | UNI | NULL | UUID |
| document_type | enum('air','pdf','image','email') | NO | | NULL | |
| file_path | varchar(500) | NO | | NULL | |
| file_size_bytes | bigint unsigned | YES | | NULL | |
| file_hash | varchar(64) | YES | | NULL | SHA256 hash |
| status | enum('queued','processing','completed','failed') | NO | | queued | |
| n8n_execution_id | varchar(255) | YES | | NULL | |
| n8n_workflow_id | varchar(255) | YES | | NULL | |
| extraction_result | json | YES | | NULL | N8n callback data |
| error_code | varchar(50) | YES | | NULL | |
| error_message | text | YES | | NULL | |
| error_context | json | YES | | NULL | |
| hmac_signature | varchar(255) | YES | | NULL | |
| callback_received_at | timestamp | YES | | NULL | |
| processing_duration_ms | int unsigned | YES | | NULL | |
| created_at | timestamp | YES | | NULL | |
| updated_at | timestamp | YES | | NULL | |
| indexes | | | | | document_id, company_id, status, created_at |

---

## Summary

### Overview
- **Total Core Tables:** 45+ tables
- **Primary Database:** laravel_testing (MySQL)
- **Multi-Tenant:** Yes (company_id present in most tables)
- **Soft Deletes:** Implemented for task-related tables
- **Accounting:** Double-entry bookkeeping with chart of accounts

### Table Categories

| Category | Count | Purpose |
|----------|-------|---------|
| Invoice Tables | 5 | Invoice generation, tracking, and partial payments |
| Task Tables | 7 | Travel bookings (flights, hotels, visas, insurance, etc.) |
| Payment Tables | 5 | Payment processing and application tracking |
| Client/Agent Tables | 3 | Entity master records |
| Accounting Tables | 6 | General ledger, accounts, credits, refunds, charges |
| Supplier Tables | 3 | Supplier/vendor management |
| File Management | 2 | File upload and document processing tracking |
| Supporting Tables | 9+ | Currency, transactions, configurations, etc. |

### Key Features Identified

1. **Multi-Tenant Architecture**
   - company_id in all major tables
   - Per-company invoice sequences
   - Isolated financial data

2. **Payment Processing**
   - Multiple payment gateways support
   - Partial invoice payments via invoice_partials
   - Payment applications linking payments to invoices
   - Payment method configuration

3. **Document Processing**
   - document_processing_logs table tracks AI processing
   - N8n workflow integration (execution_id, workflow_id)
   - Multiple document types (AIR, PDF, image, email)
   - HMAC signature validation

4. **Task Management**
   - 12 task types with specific detail tables
   - Status tracking (issued, reissued, refund, void, etc.)
   - Multi-supplier reference with unique constraint
   - Soft deletes for audit trail

5. **Financial Accounting**
   - Double-entry bookkeeping via general_ledgers
   - Chart of accounts with hierarchical structure
   - Transaction tracking and balance calculations
   - Credit and refund management

6. **Audit Trail**
   - Soft deletes on tasks and details
   - created_by/created_at timestamps
   - document_processing_logs for AI operations
   - Payment application tracking

---

## Critical Relationships

### Invoice Flow
`invoices` → `invoice_details` (line items) → `tasks` (source items)
`invoices` → `invoice_partials` (payment segments) → `payments` (payment records)
`payments` → `payment_applications` (tracking)

### Task Processing
`tasks` (main record) → `task_*_details` (type-specific: flight, hotel, visa, insurance)
`tasks` → `file_uploads` (source files)
`tasks` → `document_processing_logs` (AI processing audit)

### Financial
`payments` → `general_ledgers` (accounting entries)
`refunds` → `general_ledgers` (refund entries)
`invoices` → `general_ledgers` (invoice accounting)

### Party Relationships
`companies` ← `branches`, `suppliers`, `accounts`
`agents` ← `clients` (assignment)
`agents` ← `tasks` (processing)

---

Generated: February 12, 2026
Report Type: Schema Discovery & Documentation
Status: Complete
