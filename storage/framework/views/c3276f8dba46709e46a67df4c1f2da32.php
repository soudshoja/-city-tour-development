<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .failed-container {
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

        .error-mark {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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

        .failed-title {
            font-size: 32px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
            animation: fadeIn 0.8s ease-out 0.5s both;
        }

        .failed-message {
            font-size: 18px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease-out 0.7s both;
        }

        .retry-button {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            animation: fadeIn 0.8s ease-out 0.9s both;
        }

        .retry-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 480px) {
            .failed-container {
                padding: 40px 30px;
                margin: 20px;
            }
            
            .failed-title {
                font-size: 28px;
            }
            
            .failed-message {
                font-size: 16px;
            }
            
            .error-mark {
                width: 80px;
                height: 80px;
                font-size: 40px;
            }

            .retry-button {
                padding: 12px 25px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="failed-container">
        <div class="error-mark">
            ✗
        </div>
        <h1 class="failed-title">Payment Failed!</h1>
        <p class="failed-message">
            Unfortunately, your payment could not be processed.<br>
            Please check your payment details and try again.
        </p>
        <a href="<?php echo e(url('/')); ?>" class="retry-button">
            Return to Homepage
        </a>
    </div>

    <script>
        // Auto-close after 15 seconds if opened in popup
        if (window.opener) {
            setTimeout(function() {
                window.close();
            }, 15000);
        }
    </script>
</body>
</html>
<?php /**PATH /home/soudshoja/soud-laravel/resources/views/payment/failed.blade.php ENDPATH**/ ?>