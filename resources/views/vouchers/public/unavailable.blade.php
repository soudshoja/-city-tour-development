{{--
    The public voucher link's dead-end page (Step 4 item 2, plan section
    13-BIS.C / section 11.1). Shown for a token that does not resolve at all,
    or resolves to a voucher whose status is one of
    TravelVoucher::PUBLICLY_DEAD_STATUSES (void_pending, cancelled,
    superseded). Deliberately says nothing internal: no voucher number,
    no "Cancel V", no reason, no stale booking data -- a customer holding
    a dead link only ever sees this, per the owner's own instruction
    ("we don't show any details of void or old data for it").

    THIS MUST STAY A BLADE-SYNTAX COMMENT, NEVER a raw HTML angle-bracket
    one -- a raw comment compiles straight into the response body and is
    readable by any customer via View Source (found doing exactly that on
    2026-08-27: it named "Cancel V", listed every PUBLICLY_DEAD_STATUSES
    value, and quoted the owner). A Blade-syntax comment is stripped at
    compile time and never reaches the client at all -- but see this
    file's own git history for why even that guarantee needs a second
    warning: do not nest an example of the comment delimiters themselves
    inside comment prose like this one, or paste this paragraph's own
    wording back in verbatim -- Blade's comment regex is non-greedy and
    closes on the FIRST closing delimiter it finds, so a delimiter pair
    typed as a literal example inside the text ends the comment early and
    leaks everything after it straight into the page, which is exactly
    what happened here the first time this note was written.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Voucher unavailable</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #eef2f6;
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            color: #1f2933;
        }
        .wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            max-width: 420px;
            width: 100%;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            padding: 36px 30px;
            text-align: center;
        }
        .icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 18px;
            border-radius: 999px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #64748b;
        }
        h1 {
            font-size: 17px;
            margin: 0 0 8px;
            color: #1f2933;
        }
        p {
            font-size: 13px;
            line-height: 1.6;
            color: #64748b;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="icon">&#9888;</div>
            <h1>This document is no longer available</h1>
            <p>Please contact the agency that sent you this link if you need a copy.</p>
        </div>
    </div>
</body>
</html>
