{{--
    BLOCKER B2 -- the honest ARB-PDF fallback (PublicVoucherController::pdf()).
    dompdf cannot shape Arabic (see vouchers/partials/styles.blade.php for
    the full evidence), so an Arabic voucher is never rendered to PDF --
    neither stored at issue time (VoucherService::renderPdf()) nor
    generated live here. This page tells the visitor plainly that the
    document is a web page, not a download, and hands them the working
    link -- never a garbled PDF passed off as fine.

    Expects: $link (string, the voucher's own public HTML url).
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>السند متاح كصفحة ويب</title>
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
            max-width: 440px;
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
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        h1 {
            font-size: 17px;
            margin: 0 0 8px;
            color: #1f2933;
        }
        p {
            font-size: 13px;
            line-height: 1.7;
            color: #64748b;
            margin: 0 0 20px;
        }
        .btn {
            display: inline-block;
            background: #1d3f91;
            color: #ffffff;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: bold;
            padding: 11px 26px;
            border-radius: 6px;
        }
        .en {
            margin-top: 18px;
            font-size: 11px;
            color: #94a3b8;
            direction: ltr;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="icon">&#128196;</div>
            <h1>هذا السند متاح كصفحة ويب</h1>
            <p>يتم عرض السند العربي كصفحة ويب لضمان وضوح النص العربي. لا يتوفر ملف PDF لهذا السند.</p>
            <a class="btn" href="{{ $link }}">عرض السند</a>
            <p class="en">This voucher is provided as a web page. A PDF version is not available for Arabic vouchers.</p>
        </div>
    </div>
</body>
</html>
