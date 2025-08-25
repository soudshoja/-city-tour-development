<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .checkmark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
            animation: bounce 0.8s ease-out 0.3s both;
        }

        @keyframes bounce {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .success-title {
            font-size: 32px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            animation: fadeIn 0.8s ease-out 0.5s both;
        }

        .success-message {
            font-size: 18px;
            color: #718096;
            line-height: 1.6;
            animation: fadeIn 0.8s ease-out 0.7s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 480px) {
            .success-container {
                padding: 40px 30px;
                margin: 20px;
            }
            
            .success-title {
                font-size: 28px;
            }
            
            .success-message {
                font-size: 16px;
            }
            
            .checkmark {
                width: 80px;
                height: 80px;
                font-size: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="checkmark">
            ✓
        </div>
        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-message">
            Your payment has been processed successfully.<br>
            Thank you for your business!
        </p>
    </div>

    <script>
        // Auto-close after 10 seconds if opened in popup
        if (window.opener) {
            setTimeout(function() {
                window.close();
            }, 10000);
        }
    </script>
</body>
</html>
