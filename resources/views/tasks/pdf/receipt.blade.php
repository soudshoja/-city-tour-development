<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <title>Payment Receipt: 510888027013</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #fdfaf6;
            display: flex;
            justify-content: center;
            padding: 30px;
        }

        .container {
            width: 600px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        header {
            background-color: #d4b996;
            color: #1e3a8a;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            font-size: 16px;
        }

        header img {
            height: 50px;
        }

        header h1 {
            font-size: 18px;
            font-weight: normal;
        }

        main {
            padding: 20px;
        }

        .section {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 16px;
            background-color: #fafafa;
        }

        .section h2 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #1e3a8a;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .items {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin: 6px 0;
        }

        footer {
            background: #f0f0f0;
            font-size: 12px;
            padding: 12px;
            text-align: center;
            color: #666;
        }

        .highlight {
            font-weight: bold;
        }

        a {
            color: #1e3a8a;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <img src="{{ asset('images/CityLogo.png')}}" alt="City Travelers">
            <h1>Payment Receipt: <strong>510888027013</strong></h1>
        </header>

        <main>
            <div class="section">
                <h2>Transaction Details</h2>
                <div class="items"><span class="highlight">Payment Gateway:</span><span>Knet</span></div>
                <div class="items"><span class="highlight">Result:</span><span>Captured</span></div>
                <div class="items"><span class="highlight">Amount:</span><span>KWD 44.000</span></div>
                <div class="items"><span class="highlight">Transaction ID:</span><span>510888007288140</span></div>
                <div class="items"><span class="highlight">Merchant Track ID:</span><span>847756_flight_22909</span></div>
                <div class="items"><span class="highlight">Reference ID:</span><span>510888027013</span></div>
                <div class="items"><span class="highlight">Payment ID:</span><span>109510874000285086</span></div>
                <div class="items"><span class="highlight">Date:</span><span>18/04/2025 07:12:08 PM</span></div>
                <div class="items"><span class="highlight">Authorize ID:</span><span>079699</span></div>
                <div class="items"><span class="highlight">Mobile Number:</span><span><a href="tel:+96551117579">51117579</a></span></div>
            </div>
        </main>

        <footer>
            If you have any questions or concerns, please do not hesitate to contact our customer care number at
            <a href="tel:+96522204264">+965 22204264</a><br>
            Thank you — <a href="https://citytour.com">citytour.com</a>
        </footer>
    </div>
</body>

</html>