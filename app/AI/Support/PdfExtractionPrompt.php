<?php

namespace App\AI\Support;

use App\Models\Airport;
use App\Models\Supplier;
use App\Schema\TaskSchema;
use App\Schema\TaskFlightSchema;
use App\Schema\TaskHotelSchema;
use App\Schema\TaskInsuranceSchema;
use App\Schema\TaskVisaSchema;

/**
 * Single source of truth for the supplier-PDF extraction prompt.
 *
 * The prompt body below is lifted VERBATIM (exact bytes) from
 * OpenAIClient::extractPdfFiles() so the OpenAI and Resayil providers emit an
 * identical instruction set + output schema. It is data-driven: it reads
 * TaskSchema/Flight/Hotel/Insurance/Visa::getSchema() plus the live Supplier +
 * Airport lists at call time, so schema changes propagate automatically.
 *
 * NOTE: OpenAIClient still carries its own inline copy (left untouched to avoid
 * editing that 100KB+ critical-path file). If you change the prompt, update
 * BOTH places, or refactor OpenAIClient to call build() too.
 *
 * Added 2026-06-08 for the Resayil AI fallback (Plan B). The model must return
 * {"result": [ {task...}, ... ]}.
 */
class PdfExtractionPrompt
{
    public static function build(): string
    {
        $taskFields = TaskSchema::getSchema();
        $flightFields = TaskFlightSchema::getSchema();
        $hotelFields = TaskHotelSchema::getSchema();
        $insuranceFields = TaskInsuranceSchema::getSchema();
        $visaFields = TaskVisaSchema::getSchema();

        $suppliers = Supplier::all();

        $supplierList =$suppliers->pluck('name')->toArray();
   
        $supplierList = json_encode($supplierList);

        $airportList = json_encode(Airport::all()->toArray());

        // Build comprehensive prompt for PDF extraction
        $prompt = "You are an assistant for processing uploaded PDF documents to extract structured travel booking data.\n\n";
        $prompt .= "HARD CURRENCY RULES:\n";
        $prompt .= "- If no explicit KWD base fare is shown → `price` = 0.0.\n";
        $prompt .= "- If both KWD and foreign currency exist: put KWD into price/tax/surcharge/total; put foreign amounts into original_* + original_currency.\n";
        $prompt .= "- If only foreign currency exists: set price/tax/surcharge/total = 0.0; fill original_* + original_currency.\n";
        $prompt .= "- If only KWD exists: fill price/tax/surcharge/total in KWD; set all original_* and original_currency = null.\n";
        $prompt .= "- Fallback (no taxes/fees): If tax and surcharge are blank or 0 AND a KWD Total is present, set `price = total` when `price` is 0/missing.\n";
        $prompt .= "CURRENCY CAPTURE (component-wise):\n";
        $prompt .= "- Create original_* only when that component is shown in a non-KWD currency.\n";
        $prompt .= "- Do NOT create original_* for KWD-only components.\n";
        $prompt .= "- Missing KWD base fare only zeros `price`, not other components.\n";
        $prompt .= "- Example: Fare 72 USD; Charges 105.20 USD; Total 177.20 USD and 54.90 KWD → price=0, tax=0, surcharge=0, total=54.90; original_price=72 USD; original_tax=105.20 USD; original_total=177.20 USD; exchange_currency=KWD; original_currency=USD; is_exchanged=false.\n";
        $prompt .= "Extract data following these models:\n\n";
        $prompt .= "1. `tasks` model with the following fields:\n";
        foreach ($taskFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['desc']}\n";
        }
        $prompt .= "\n2. `task_flight_details` model (for flights) - THIS IS AN ARRAY that can contain multiple flight details:\n";
        foreach ($flightFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n3. `task_hotel_details` model (for hotels):\n";
        foreach ($hotelFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n4. `task_insurance_details` model (for insurances) - THIS IS AN ARRAY that can contain multiple insurance details:\n";
        foreach ($insuranceFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\n5. `task_visa_details` model (for visa):\n";
        foreach ($visaFields as $field => $meta) {
            $prompt .= "   - `$field`: {$meta['description']}\n";
        }
        $prompt .= "\nINSURANCE TASK COLLAPSING RULE (CRITICAL):\n";
        $prompt .= "- Do NOT create additional tasks for spouse/children/relatives listed on the certificate.\n";
        $prompt .= "- Do NOT record or output any list of covered relatives/members. Ignore extra names.\n";
        $prompt .= "- Set client_name to the buyer/policyholder (name nearest to the policy header or explicitly labeled).\n";
        $prompt .= "- If currency symbols (e.g., KD, $, €) are found in the files, replace them with the proper ISO currency code (e.g., KWD, USD, EUR).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (FIRST TAKAFUL INSURANCE): If the supplier or insurer is 'First Takaful' (case-insensitive), set issued_by to 'First Takaful' and agent_name to null.\n";
        $prompt .= "HOTEL TASK COLLAPSING RULE (CRITICAL):\n";
        $prompt .= "- For all hotel suppliers except Magic Holiday: if additional structured room information is present (e.g., name, board, passengers, etc), insert it into task_hotel_details.room_details as JSON. For Magic Holiday: always use task_hotel_details.room_details for the room information.\n";
        $prompt .= "- When setting room_details, normalize Board/BoardBasis as follows:\n";
        $prompt .= "  • Recognize these codes: RO=Room Only, SC=Self Catering, BB=Bed and Breakfast, HB=Half Board, FB=Full Board, AI=All Inclusive.\n";
        $prompt .= "  • If only board text is given (e.g., 'Room Only'), set boardBasis using the matching code (e.g., 'RO').\n";
        $prompt .= "  • If only a code is given (e.g., 'BB'), set board using its full name (e.g., 'BED AND BREAKFAST'). If both exist, make them consistent.\n";
        $prompt .= "  • If the value is unrecognized, keep board as-is and leave boardBasis null.\n";
        $prompt .= "- Example: {\"name\":\"Deluxe Room\",\"board\":\"Room Only\",\"boardBasis\":\"RO\",\"info\":null,\"type\":\"TWN.ST\",\"passengers\":[\"Ali Ahmed\"]}\n";

        $prompt .= "\nIMPORTANT INSTRUCTIONS:\n";
        $prompt .= "- The PDF may contain multiple passengers/bookings. Return an array of task objects.\n";
        $prompt .= "- Each passenger should be a separate task object with their own ticket/booking details.\n";
        $prompt .= "- If multiple passengers share the same flight/booking, they may have the same flight details but different ticket numbers and passenger names.\n";
        $prompt .= "- task_flight_details and task_hotel_details are ARRAYS that can contain multiple flight/hotel segments for each task.\n";
        $prompt .= "- For INSURANCE: follow the INSURANCE TASK COLLAPSING RULE (do NOT create a task per covered person).\n";
        $prompt .= "- Extract all available data, set missing fields to null.\n";
        $prompt .= "- All dates should be in 'Y-m-d H:i:s' format.\n";
        $prompt .= "- For supplier name, refer to this list: $supplierList\n";
        $prompt .= "- Airport codes should be matched against: $airportList\n";
        $prompt .= "- If amounts are shown in a currency other than KWD, record them in additional_info as plain text. Example: 'Original price: 71.33 USD, Original tax: 7 USD'.\n";
        $prompt .= "- HOTEL MEAL/BOARD RULES:\n";
        $prompt .= "  • If the document mentions a meal plan (e.g., 'board', 'free breakfast', 'half board', 'full board'), copy the wording exactly as shown into task_hotel_details[*].meal_type.\n";
        $prompt .= "  • If you're unsure which room line it belongs to, include the phrase in tasks.additional_info instead.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Como Travels):\n";
        $prompt .= "  • Create ONE task per ROOM (never per passenger, never one combined task for all rooms). Always set tasks.issued_by and tasks.created_by to Como Travels.\n";
        $prompt .= "  • For each ROOM task, set client_name to the FIRST passenger listed under that room’s guest list (put extra name into tasks.additional_info).\n";
        $prompt .= "  • PRICE/TOTAL SOURCE: Use ONLY the nightly values in the “Total (net)” column for that room. Sum those nights for that room and set BOTH tasks.price and tasks.total to that sum.\n";
        $prompt .= "  • Example: If R1 shows 10 nights at net 28.74 for 5 nights and 28.73 for 5 nights → tasks.price = tasks.total = 5*28.74 + 5*28.73 = 287.35 KWD. \n";
        $prompt .= "  • STATUS: Read the value labeled 'Reservation status' in the document.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (SMILE HOLIDAYS):\n";
        $prompt .= "  • For Smile Holidays proforma/invoices that have a 'Pax' column, copy that value into tasks.additional_info, e.g., 'Pax: 1'.\n";
        $prompt .= "  • ADDITIONAL REQUESTS → ROOM DETAILS: If the document contains 'Additional Requests', 'Special Instructions', 'Remarks' or similar booking notes, append a concise version to task_hotel_details[*].room_details (for single-room bookings append to that room; for multi-room bookings, either repeat for each room or put it into tasks.additional_info with room labels).\n";
        $prompt .= "  • STATUS RULES: If the uploaded file contains a proforma invoice → status = 'issued'. If it contains only a hotel voucher → status = 'confirmed'. If it contains both a proforma invoice and a hotel voucher → status = 'issued'.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (BAHRAIN E-VISA):\n";
        $prompt .= "  • Set tasks.reference to the Visa Number from the document.\n";
        $prompt .= "  • Store the Application Number and other important visa details (e.g., Visa Expiry, Period of Stay, Number of Entries) in tasks.additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (TBO CAR):\n";
        $prompt .= "  • If the file shows 'Net Amount' and 'Agent Markup': set price and total with Net Amount exactly as showen (ignore markup value).\n";
        $prompt .= "  • Put the Agent Markup value in tasks.additional_info (e.g., 'Agent Markup: KWD 12.00').\n";
        $prompt .= "  • If Net Amount is shown in another currency (e.g., 'KWD 209.45 (USD 685.25)'), store that other-currency value (e.g., USD 685.25) in original_price/original_currency.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (FLY DUBAI):\n";
        $prompt .= "  • Set tasks.issued_by and tasks.created_by to the first invoice name from the document. Set agent to null if the agent in the document is not in the agent list.\n";
        $prompt .= "  • Set tasks.price to the 'Base fare' from the document that is found on the left column (e.g. KWD 100.00).\n";
        $prompt .= "  • Set tasks.total to the 'Booking total' from the document that is found on the right column with bold font(e.g. KWD 957.64).\n";
        $prompt .= "  • If the document contains multiple passengers, always use the Booking total as the basis and divide it equally among all passengers to compute each passenger’s price. Do NOT assign the full total to each passenger.\n";
        $prompt .= "  • Place all other monetary details (e.g., Optional extras, Transaction fee, Admin fees, Taxes/fees, etc.) into tasks.additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Cebu Pacific):\n";
        $prompt .= "  • Set reference = Booking Reference No. and issued_date = Booking Date. Set agent, created_by and issued_by to null.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Indigo):\n";
        $prompt .= "  • Set the reference and ticket_number using 'PNR/Booking Ref' value (e.g. G5BQFJ).\n";
        $prompt .= "  • Set issued_date and supplier_pay_date to use the value of 'Date of Booking' (e.g. 09Aug25) to the format yyyy-mm-dd.\n";
        $prompt .= "  • In table Fare Summary that is found at the end of the page, find 'Airfare Charges' and get the value. Use it to set the price.\n";
        $prompt .= "  • In table Fare Summary that is found at the end of the page, set the total using the value of 'Total Fare' that is in the footer of the table.\n";
        $prompt .= "  • Set the value of tax with sum up of the list under 'Airfare Charges'. The value of sum between the tax and price should be the same with 'Total Fare' and total.\n";
        $prompt .= "  • Fetch the information of taxes_record with flight from and flight to. Embed them all into additional_info.\n";
        $prompt .= "  • Set created_by and issued_by to the Company Name that is in Personal Information table at the end of the page.\n";
        $prompt .= "  • Departure terminal number or identifier. Look for terminal information associated with departure details, often labeled as 'T' with digit after it.\n";
        $prompt .= "  • Arrival terminal number or identifier. Look for terminal information associated with arrival details, often labeled as 'T' with digit after it.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Cebu Pacific and Indigo):\n";
        $prompt .= "  • Set status to issued if the task file shows 'Confirmed'. Else if the task file showed 'On Hold', the status should be set to confirmed.\n";
        $prompt .= "  • Set task.original_price to the per-passenger share of 'Amount in Booking Currency' (total ÷ passenger_count). Set task.price and task.total to the same amount after conversion using exchange_rate.\n";
        $prompt .= "  • Store fee breakdown: set surcharge = Admin Fee + Fuel Surcharge; set tax = sum of VATs + passenger/service/security charges; penalty_fee = 0 unless stated.\n";
        $prompt .= "  • Copy all labeled amounts into additional_info as 'Label: Amount' pairs (e.g., Base Fare, Administrative Fee, Fuel Surcharge, VAT for Admin Fees, and so on).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Fly Cham, Cham Wings Airlines and Air Arabia):\n";
        $prompt .= "  • Set tasks.ticket_number = full E-Ticket Number exactly as shown (e.g. 3862304374206/1). Set issued_by and created_by to Como Travels.\n";
        $prompt .= "  • Set tasks.reference = last 10 digits of the E-Ticket Number, before the slash (e.g. 3862304374206/1 → 2304374206).\n";
        $prompt .= "  • For every non-KWD amount (Fare/Charges/Taxes/etc.), append to additional_info exactly as 'Label: CUR 999.99' (e.g., 'Fare: AED 278.17'); keep the document’s grand original in original_price/original_total/original_tax/original_currency. Map the itinerary column 'Charges' to tax only.\n";
        $prompt .= "  • When multiple passengers are listed, create a separate task for each passenger:\n";
        $prompt .= "      – tasks.original_total/total = that passenger’s Paid Amount (e.g. 636.06 AED/54.90 KWD).\n";
        $prompt .= "      – tasks.original_price = that passenger’s Fare amount (e.g. 335.50 AED).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Bella Vita, World of Luxury, Travel Collection and Heysam Group):\n";
        $prompt .= "  • SEGMENTATION: Treat each Accomodation block as EXACTLY ONE task. NEVER merge blocks even if Voucher/Hotel/guests are the same.\n";
        $prompt .= "  • TASK COUNT ASSERTION: tasks.length MUST equal the number of Accomodation occurrences found in the text.\n";
        $prompt .= "  • TOTALS PER BLOCK: Read that block’s own 'Grand Total :' (ignore page-level 'VOUCHER INVOICE TOTALS' / 'GRAND TOTALS'). Compute per_room_total = block_grand_total / room_count. Set tasks.price = tasks.total = per_room_total.\n";
        $prompt .= "  • Set reference to the Voucher number; set issued_by and created_by to the Tour Operator name only (without country, if have); set agent to null.\n";
        $prompt .= "  • Populate task_hotel_details with Hotel, Room, Type, Board, Nights, Check-in, Check-out, and the segment total.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Bedzinn):\n";
        $prompt .= "  • Create EXACTLY ONE task per ROOM (NEVER per passenger). If the file has N rooms, output N tasks; if it has 1 room, output 1 task.\n";
        $prompt .= "  • Bedzinn vouchers that say something like “Booking confirmed”, set `status` = 'issued', set `issued_by`and `created_by` = 'Ojeen Travel'.\n";
        $prompt .= "  • Set the client to the first passenger; if there are additional passengers, list them in additional_details.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Supreme Services):\n";
        $prompt .= "  • Create ONE task per accommodation line (room type), never per room or passenger.\n";
        $prompt .= "  • Example: '3 ROOM(S) × 184.00 × 6 NIGHT(S)' = 1 task, price=3312.00, additional_info='Rooms:3; Nights:6; Calc:3×184×6=3312'.\n";
        $prompt .= "  • Set tasks.client_name from the 'Ref.' line. Set tasks.reference and tasks.ticket_number from the 'File No.' line. Set tasks.status = 'issued'.\n";
        $prompt .= "  • Set tasks.issued_date from the 'Date' line; parse dd/mm/yyyy to 'YYYY-MM-DD 00:00:00'. Set tasks.issued_by and tasks.created_by from the 'Client' line.\n";
        $prompt .= "  • For each line: tasks.price=rooms×rate×nights, total=price. tax/surcharge from VAT lines; original_* match document currency. taxes_record = raw VAT line.\n";
        $prompt .= "  • task_hotel_details: room_type, check_in/out, rate, room_amount=price, meal_type, hotel_name. Put quantity/nights in additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (NDC SUPPLIERS): If the supplier has 'NDC' in its name (case-insensitive), set created_by to exactly match issued_by.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (EMIRATES NDC): Set issued_by to the agency/office name that appears immediately next to the 'IATA:' number.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (LONDON VISA):\n";
        $prompt .= "  • For task that is uploaded by Outlook, find the details of the sender at 'From' field. Use that information as indicator for if it is from 'UK Visas and Immigration Home Office', automatically store London Visa as the issued_by.\n";
        $prompt .= "  • For task that is uploaded by Outlook, find the 'Date' field in sender details, use the date as issued_date and supplier_pay_date.\n";
        $prompt .= "  • For task that is uploaded by Outlook, the status of the task is default to issued.\n";
        $prompt .= "  • For task that is uploaded by Outlook, it doesn't have created_by, expiry_date, cancellation_policy and cancellation_deadline.\n";
        $prompt .= "  • For venue, use United Kingdom.\n";
        $prompt .= "  • Fetch the bank name (e.g. World Bank) and the bank information (e.g. ETAWEB00005361649) with the original_price with original_currency and embed it into additional_info. Different task should have the bank information as an unique value.\n";   
        $prompt .= "  • The reference and ticket_number hold the same value, that is the value of ETA reference number (e.g. 2021-2506-1004-1787). Different task should have the values as an unique value.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (BLS SPAIN VISA):\n";
        $prompt .= "  • For task that is from Appointment Letter, set the reference and ticket_number using the value Reference Number in table Appointment Details.\n";
        $prompt .= "  • For task that is from Appointment Letter, fetch the value in Amount as it is in USD, then set the value for original_price using the fetched value. For price and total in database should be the converted value of original_price in KWD.\n";
        $prompt .= "  • For task that is from Appointment Letter, the status should be set to 'issued' by default.\n";
        $prompt .= "  • Fetch the value of Payment Order No, Amount and Payment Date. Embed them all into additional_info.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Enlite):\n";
        $prompt .= "  • If only a booking voucher page exists, set status = confirmed. If both voucher and invoice pages exist, set status = issued.\n";
        $prompt .= "  • Set issued_by and created_by to null. Extract only the text before the first hyphen '-' from the given room name (e.g., 'Deluxe Courtyard - Breakfast', room_type = 'Deluxe Courtyard', meal_type = 'Breakfast').\n";
        $prompt .= "  • When assigning amounts: if each accommodation already has its own amount, use that value. If only a total amount is provided for multiple rooms, then divide the total equally among them (e.g., total 1245 USD for 2 rooms → each task.amount = 622.50 USD). Always round to two decimal places.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (TBO Holiday):\n";
        $prompt .= "  • Set tasks.reference from the TBOH Confirmation No line.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Restel):\n";
        $prompt .= "  • Set tasks.reference from the Ref. Number in the documents. Set tasks.issued_date from the header date next to 'FROM' sections.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Rate Hawk):\n";
        $prompt .= "  • Store transfer details (from, to, and date/time) in tasks.additional_info as plain text. Example: 'Transfer from Hilton Abu Dhabi Yas Island Resort to Sharjah International Airport (SHJ) on 2025-09-01 11:30'. Do not use JSON for this; keep it as readable text for display only.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Webbeds):\n";
        $prompt .= "  • Set tasks.reference from the Booking Reference No by taking everything after the last '-' (e.g., WBD-658484445 → reference = 658484445). Set tasks.ticket_number to the full Booking Reference No (e.g., ticket_number = WBD-658484445).\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Alpha Maldives):\n";
        $prompt .= "  • Create only ONE task per accommodation/voucher (do not split by nights or pax).\n";
        $prompt .= "  • If text says 'All Government Taxes and 10% Accommodation service charge by the resort', treat tax & service charge as included (tax_amount = 0, service_charge_included = true, service_charge_rate = 0.10). 'Bank/Credit Card Charge' is not tax; capture it separately as bank_charge and store it into additional_info.\n";
        $prompt .= "  • Use 'Total in XXX' and 'Net Total in XXX' for original price and original total; currency from these lines.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (AirCairo):\n";
        $prompt .= "  • reference = the 'Transaction ID' (13-digit number) exactly as shown. ticket_number = the same 'Transaction ID' (13-digit number) exactly as shown.\n";
        $prompt .= "  • Prices: total = 'Total fare'; tax = SUM of all lines under 'Taxes/fees/carrier-imposed charges'; price = total − tax. If 'Fare' > 0, set price = Fare.\n";
        $prompt .= "  • Ancillary services (e.g., EXCESS BAGGAGE) must be treated as separate tasks with their own ticket_number = the 'Transaction ID' shown. Do NOT include them inside 'Additional info' or 'surcharge'.\n";
        $prompt .= "  • If the service is Ancillary (e.g., contains 'Ancillary:' in Service Name), then set is_ancillary in table task_flight_details = true (1).\n";
        $prompt .= "  • Transaction Status mapping: if 'confirmed' → task status = 'Issued'; if 'on hold' → task status = 'Confirmed'.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Salam Air):\n";
        $prompt .= "  • Set the reference, ticket_number using the 'Booking Reference'.\n";
        $prompt .= "  • Set the terminal_from and terminal_to using the numeric value that found under the text Departure and Arrival. Respectively, terminal_from will using the value under the Departure and terminal_to will use the value under the Arrival. If none, only then make it null.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Wizz Air):\n";
        $prompt .= "  • Create ONE task per passenger listed in the Passenger info table (top-to-bottom order = task order).\n";
        $prompt .= "  • Set baggage_allowed = value from the “Checked-in bag” column for that passenger (e.g., '1/32kg'). Put value from the “Cabin baggage” in additional_info\n";
        $prompt .= "  • PRICE MAPPING (per passenger): under 'Payment summary' there are multiple 'Fare price' lines. Map the FIRST 'Fare price' to the FIRST passenger task, the SECOND to the SECOND passenger task, etc. Store these as original_price (currency as shown). Do NOT sum them; do NOT use the page 'Grand total' as a task total.\n";
        $prompt .= "  • SURCHARGE/TAX SPLIT: 'Administration fee' and 'Plus Fare fee' are booking-level surcharges. If they appear once for the whole booking, split them EQUALLY across all passenger tasks and store in tasks.original_surcharge (and tasks.surcharge = 0 when no KWD). If explicit per-passenger allocation is shown, use that instead. If any Tax/VAT lines exist, split them the same way across tasks and store in tasks.original_tax (not surcharge).\n";
        $prompt .= "  • PER-PASSENGER TOTAL: For each passenger task, compute original_total = original_price + original_surcharge + original_tax.\n";
        $prompt .= "  • CURRENCY RULES: Wizz docs are usually in non-KWD (e.g., BAM/USD). When no KWD is shown: set price/tax/surcharge/total = 0.0; fill original_price/original_tax/original_surcharge/original_total + original_currency; set exchange_currency = 'KWD'; is_exchanged = false. If both KWD and a foreign currency exist, put KWD amounts into price/tax/surcharge/total and foreign amounts into the original_* fields.\n";
        $prompt .= "  • PAYMENT LINES: If a 'Payment in selected currency' is shown (e.g., 421.64 USD), copy it into additional_info as plain text (e.g., 'Payment: 421.64 USD').\n";
        $prompt .= "  • STATUS: If payment status is 'confirmed' and a payment exists, set tasks.status = 'issued' and supplier_status = 'confirmed'.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Pilot Tours/Pailot Tours):\n";
        $prompt .= "  • ROOM DETAILS JSON: – passengers = array with ONLY the guest name BEFORE any parentheses (e.g., 'Mr Abdulrahman Alazemi').\n";
        $prompt .= "    – info = pax text INSIDE the parentheses as 'Pax: ...' (e.g., '(2 Adult + 2 Child)' → 'Pax: 2 Adult + 2 Child').\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Trendy Travel, Alam Al Raya Travel & Tourism Co):\n";
        $prompt .= "  • HARD RULE: Always set task.status = 'issued'. Do NOT copy the document status into task.status.\n";
        $prompt .= "  • Put the document status (e.g., Confirmed/Issued/etc.) into supplier_status exactly as shown in the document.\n";
        $prompt .= "  • Use the printed on/date/voucher date as issued_date (fallback to today if missing).\n";
        $prompt .= "  • Always extract flight class/cabin into task_flight_details.class_type.\n";
        $prompt .= "  • For airline tickets, use the PNR as the reference. For hotel bookings, use the booking number or voucher number as the reference.\n";
        $prompt .= "  • Create ONE task per passenger listed under Passenger Details table.\n";
        $prompt .= "  • Create ONE task per ROOM (room type), never per passenger, never a single combined task for all rooms.\n";
        $prompt .= "- SUPPLIER-SPECIFIC HINTS (Sky Rooms):\n";
        $prompt .= "  • Set status: confirmed if ONLY a voucher is present; issued if an INVOICE is present (with or without a voucher).\n";
        $prompt .= "  • Create ONE task per ROOM (room type), never per passenger, never a single combined task for all rooms.\n";
        $prompt .= "  • Set room_type and name in room_details to the EXACT room title from the documents — copy verbatim (keep punctuation/duplicates), join wrapped lines with a single space, and trim ends. (e.g., Deluxe Room, 1 King Bed, Non Smoking , 1 King Bed , Room Only (Package Deal)).\n";
        $prompt .= "  • If a nightly calendar is shown and the amounts differ by night, keep the normal totals, and also append a concise nightly breakdown to additional_info, e.g.: 'Nightly rates (KWD): 24-Oct-2025: 64.71; 25-Oct-2025: 67.54; 26-Oct-2025: 56.26'.\n";

        $prompt .= "- Return the result in this JSON format:\n\n";

        $prompt .= "{\n";
        $prompt .= "  \"result\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"additional_info\": \"relevant booking info\",\n";
        $prompt .= "      \"ticket_number\": \"document/ticket number\",\n";
        $prompt .= "      \"gds_reference\": \"booking reference/PNR\",\n";
        $prompt .= "      \"airline_reference\": \"airline confirmation code\",\n";
        $prompt .= "      \"status\": \"issued/confirmed/cancelled/refunded\",\n";
        $prompt .= "      \"supplier_status\": \"same as status\",\n";
        $prompt .= "      \"refund_date\": \"2025-06-01 10:00:00\",\n";
        $prompt .= "      \"price\": 30.52,\n";
        $prompt .= "      \"exchange_currency\": \"KWD\",\n";
        $prompt .= "      \"original_price\": 100.00,\n";
        $prompt .= "      \"original_currency\": \"USD\",\n";
        $prompt .= "      \"total\": 35.10,\n";
        $prompt .= "      \"original_total\": 115.00,\n";
        $prompt .= "      \"original_surcharge\": 10.00,\n";
        $prompt .= "      \"surcharge\": 3.05,\n";
        $prompt .= "      \"penalty_fee\": 0.00,\n";
        $prompt .= "      \"original_tax\": 5.00,\n";
        $prompt .= "      \"tax\": 1.53,\n";
        $prompt .= "      \"taxes_record\": \"tax breakdown if available\",\n";
        $prompt .= "      \"refund_charge\": 0.00,\n";
        $prompt .= "      \"reference\": \"main reference number\",\n";
        $prompt .= "      \"created_by\": \"agent/office code\",\n";
        $prompt .= "      \"issued_by\": \"issuing agent/office\",\n";
        $prompt .= "      \"issued_by\": \"IATA wallet\", \n";
        $prompt .= "      \"type\": \"flight/hotel/package\",\n";
        $prompt .= "      \"agent_name\": \"agent name\",\n";
        $prompt .= "      \"agent_email\": \"agent email\",\n";
        $prompt .= "      \"agent_amadeus_id\": \"agent system id\",\n";
        $prompt .= "      \"client_name\": \"passenger/customer name\",\n";
        $prompt .= "      \"supplier_name\": \"supplier/vendor name\",\n";
        $prompt .= "      \"supplier_country\": \"supplier country\",\n";
        $prompt .= "      \"cancellation_policy\": \"cancellation terms\",\n";
        $prompt .= "      \"cancellation_deadline\": \"2025-06-01 10:00:00\",\n";
        $prompt .= "      \"venue\": \"service location\",\n";
        $prompt .= "      \"issued_date\": \"2025-07-03 00:00:00\",\n";
        $prompt .= "      \"is_exchanged\": false,\n";
        $prompt .= "      \"task_flight_details\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"farebase\": 20.00,\n";
        $prompt .= "          \"departure_time\": \"2025-07-03 14:00:00\",\n";
        $prompt .= "          \"country_id_from\": \"departure country\",\n";
        $prompt .= "          \"airport_from\": \"departure airport code\",\n";
        $prompt .= "          \"terminal_from\": \"departure terminal\",\n";
        $prompt .= "          \"arrival_time\": \"2025-07-03 16:00:00\",\n";
        $prompt .= "          \"duration_time\": \"2h 30m\",\n";
        $prompt .= "          \"country_id_to\": \"arrival country\",\n";
        $prompt .= "          \"airport_to\": \"arrival airport code\",\n";
        $prompt .= "          \"terminal_to\": \"arrival terminal\",\n";
        $prompt .= "          \"airline_id\": \"airline name\",\n";
        $prompt .= "          \"flight_number\": \"flight number\",\n";
        $prompt .= "          \"class_type\": \"economy/business/first\",\n";
        $prompt .= "          \"baggage_allowed\": \"baggage allowance\",\n";
        $prompt .= "          \"equipment\": \"aircraft type\",\n";
        $prompt .= "          \"ticket_number\": \"flight ticket number\",\n";
        $prompt .= "          \"flight_meal\": \"meal service\",\n";
        $prompt .= "          \"seat_no\": \"seat assignment\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ],\n";
        $prompt .= "      \"task_hotel_details\": [\n";
        $prompt .= "        {\n";
        $prompt .= "          \"hotel_name\": \"hotel name\",\n";
        $prompt .= "          \"booking_time\": \"2025-07-03 10:00:00\",\n";
        $prompt .= "          \"check_in\": \"2025-07-03 15:00:00\",\n";
        $prompt .= "          \"check_out\": \"2025-07-05 11:00:00\",\n";
        $prompt .= "          \"room_reference\": \"room booking reference\",\n";
        $prompt .= "          \"room_number\": \"room number\",\n";
        $prompt .= "          \"room_type\": \"room type\",\n";
        $prompt .= "          \"room_amount\": 150.00,\n";
        $prompt .= "          \"room_details\": \"room details and amenities\",\n";
        $prompt .= "          \"room_promotion\": \"special offers or discounts\",\n";
        $prompt .= "          \"rate\": 150.00,\n";
        $prompt .= "          \"meal_type\": \"breakfast/half-board/full-board\",\n";
        $prompt .= "          \"is_refundable\": true,\n";
        $prompt .= "          \"supplements\": \"additional services\"\n";
        $prompt .= "        }\n";
        $prompt .= "      ],\n";
        $prompt .= "        \"task_insurance_details\": [\n";
        $prompt .= "          {\n";
        $prompt .= "            \"insurance_type\": \"Tr\",\n";
        $prompt .= "            \"destination\": \"Worldwide\",\n";
        $prompt .= "            \"plan_type\": \"Family Plan\",\n";
        $prompt .= "            \"duration\": \"Up to 30 days\",\n";
        $prompt .= "            \"package\": \"Worldwide (Silver) Plan\",\n";
        $prompt .= "            \"document_reference\": \"policy/certificate reference\",\n";
        $prompt .= "            \"date\": \"2025\",\n"; 
        $prompt .= "            \"paid_leaves\": 0,\n";
        $prompt .= "          }\n";
        $prompt .= "        ]\n";
        $prompt .= "      \"task_visa_details\": {\n";
        $prompt .= "          \"visa_type\": \"common\",\n";
        $prompt .= "          \"application_number\": \"8637300\",\n";
        $prompt .= "          \"expiry_date\": \"2026-07-03\",\n";
        $prompt .= "          \"number_of_entries\": \"single\",\n";
        $prompt .= "          \"stay_duration\": 14,\n";
        $prompt .= "          \"issuing_country\": \"Kuwait\",\n";
        $prompt .= "      }\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";
        $prompt .= "Remember: Always return an array of objects, even for single passengers. Analyze the document carefully for multiple bookings/passengers.";

        return $prompt;
    }
}
