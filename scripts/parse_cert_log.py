import os, re, sys

LOG = "storage/logs/dotw_certification_server.log"
BASE = "docs/dotw-certification-submission"

tests = [
    (1, 46, 30947, "Book 2 Adults", "book_2_adults"),
    (2, 30948, 54282, "Book 2A + 1 Child (11yo)", "book_2a_1c"),
    (3, 54283, 64760, "Book 2A + 2 Children (8,9yo)", "book_2a_2c"),
    (4, 64761, 110114, "Book 2 Rooms (1 Single + 1 Double)", "book_2_rooms"),
    (5, 110115, 133326, "Cancel Outside Deadline (Free)", "cancel_free"),
    (6, 133327, 181233, "Cancel Within Deadline (Penalty)", "cancel_penalty"),
    (7, 181234, 203426, "productsLeftOnItinerary Check", "products_left"),
    (8, 203427, 227751, "Tariff Notes Display", "tariff_notes"),
    (9, 227752, 251125, "Cancellation Rules Display", "cancellation_rules"),
    (10, 251126, 251148, "Passenger Name Restrictions", "passenger_names"),
    (11, 251149, 270860, "Minimum Selling Price (MSP)", "msp"),
    (12, 270861, 271765, "Gzip Compression", "gzip"),
    (13, 271766, 295667, "Blocking Step Validation", "blocking_validation"),
    (14, 295668, 301632, "Changed Occupancy", "changed_occupancy"),
    (15, 301633, 302002, "Special Promotions", "special_promotions"),
    (16, 302003, 328725, "APR Booking", "apr_booking"),
    (17, 328726, 407532, "Restricted Cancellation", "restricted_cancel"),
    (18, 407533, 603105, "Minimum Stay", "minimum_stay"),
    (19, 603106, 625872, "Special Requests", "special_requests"),
    (20, 625873, 649070, "Taxes & Fees", "taxes_fees"),
]

print("Reading log file...")
with open(LOG, "r", errors="replace") as f:
    all_lines = f.readlines()
print(f"Read {len(all_lines)} lines")

# Parse results from summary section (last 30 lines)
test_results = {}
for line in all_lines[-30:]:
    s = line.strip()
    if "PASS" in s and "Test" in s:
        m = re.search(r"Test (\d+):", s)
        if m:
            test_results[int(m.group(1))] = "PASS"
    elif "SKIP" in s and "Test" in s:
        m = re.search(r"Test (\d+):", s)
        if m:
            test_results[int(m.group(1))] = "SKIP"
print(f"Found results for {len(test_results)} tests: {test_results}")

for num, start, end, name, slug in tests:
    folder = f"{BASE}/test_{str(num).zfill(2)}_{slug}"
    rq_dir = f"{folder}/request_response"
    os.makedirs(rq_dir, exist_ok=True)

    test_lines = all_lines[start-1:end]

    current_type = None
    current_id = None
    current_xml = []
    pairs = {}

    for line in test_lines:
        rq_match = re.search(r"REQUEST \[([^\]]+)\]", line)
        rs_match = re.search(r"RESPONSE \[([^\]]+)\]", line)

        if rq_match:
            if current_type and current_id and current_xml:
                pairs[f"{current_id}_{current_type}"] = "".join(current_xml)
            current_type = "RQ"
            current_id = rq_match.group(1)
            current_xml = []
            continue
        elif rs_match:
            if current_type and current_id and current_xml:
                pairs[f"{current_id}_{current_type}"] = "".join(current_xml)
            current_type = "RS"
            current_id = rs_match.group(1)
            current_xml = []
            continue

        if current_type:
            stripped = line.strip()
            if stripped and (stripped[0] in "\u2714\u2718\u23ed" or stripped.startswith("RESULT") or re.match(r"Step \d", stripped)):
                if current_id and current_xml:
                    pairs[f"{current_id}_{current_type}"] = "".join(current_xml)
                current_type = None
                current_id = None
                current_xml = []
            else:
                current_xml.append(line)

    if current_type and current_id and current_xml:
        pairs[f"{current_id}_{current_type}"] = "".join(current_xml)

    for key, xml_content in pairs.items():
        clean = xml_content.strip()
        if len(clean) > 10:
            with open(f"{rq_dir}/{key}.xml", "w", encoding="utf-8") as f:
                f.write(clean)

    full_text = "".join(test_lines)
    result = test_results.get(num, "NOT RUN")
    booking_codes = re.findall(r"bookingCode[:\s]*(\d+)", full_text)

    evidence_items = []

    for tag, label in [
        (r"<tariffNotes>(.*?)</tariffNotes>", "tariffNotes"),
        (r"<cancellationRules>(.*?)</cancellationRules>", "cancellationRules"),
        (r"<specials>(.*?)</specials>", "specials"),
        (r"<specialsApplied>(.*?)</specialsApplied>", "specialsApplied"),
        (r"<propertyFees>(.*?)</propertyFees>", "propertyFees"),
        (r"<totalMinimumSelling>(.*?)</totalMinimumSelling>", "totalMinimumSelling"),
        (r"<changedOccupancy>(.*?)</changedOccupancy>", "changedOccupancy"),
        (r"<validForOccupancy>(.*?)</validForOccupancy>", "validForOccupancy"),
        (r"<cancelRestricted>(.*?)</cancelRestricted>", "cancelRestricted"),
        (r"<amendRestricted>(.*?)</amendRestricted>", "amendRestricted"),
        (r"<minStay>(.*?)</minStay>", "minStay"),
        (r'nonrefundable="(.*?)"', "nonRefundable"),
        (r"<paymentGuaranteedBy>(.*?)</paymentGuaranteedBy>", "paymentGuaranteedBy"),
        (r"<allocationDetails>(.*?)</allocationDetails>", "allocationDetails"),
        (r"<specialRequests.*?>(.*?)</specialRequests>", "specialRequests"),
    ]:
        matches = re.findall(tag, full_text, re.DOTALL)
        if matches:
            val = matches[0].strip()
            if val and val != "0" and val != "no":
                evidence_items.append((label, val[:800]))

    with open(f"{folder}/README.md", "w", encoding="utf-8") as f:
        f.write(f"# Test {num}: {name}\n\n")
        f.write(f"**Result:** {result}\n\n")
        f.write(f"## Steps\n\n")
        steps = re.findall(r"Step (\w+)[:\s]+(.*?)(?:\n|$)", full_text)
        for sid, sdesc in steps:
            f.write(f"- **Step {sid}:** {sdesc.strip()}\n")
        if booking_codes:
            f.write(f"\n## Booking Codes\n\n")
            for bc in set(booking_codes):
                f.write(f"- {bc}\n")
        f.write(f"\n## RQ/RS Files\n\n")
        f.write(f"Total: {len(pairs)} XML files in `request_response/`\n\n")
        for key in sorted(pairs.keys()):
            if len(pairs[key].strip()) > 10:
                f.write(f"- `{key}.xml`\n")

    with open(f"{folder}/evidence.md", "w", encoding="utf-8") as f:
        f.write(f"# Test {num}: {name} - Mandatory Feature Evidence\n\n")
        f.write(f"**Result:** {result}\n\n")
        if not evidence_items:
            f.write("No mandatory display features found in this test's responses.\n")
        else:
            for feat_name, feat_val in evidence_items:
                f.write(f"## {feat_name}\n\n")
                f.write(f"```xml\n{feat_val}\n```\n\n")

    rq_count = sum(1 for k in pairs if k.endswith("_RQ"))
    rs_count = sum(1 for k in pairs if k.endswith("_RS"))
    print(f"Test {num:2d}: {result:4s} | {rq_count} RQ / {rs_count} RS | {len(evidence_items)} evidence items | {len(set(booking_codes))} bookings")

print("\nDone!")
