<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Holiday Webhook</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.3/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50 text-gray-900">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <h1 class="text-4xl font-extrabold mb-4 text-blue-700">Magic Holiday Webhook Documentation</h1>
        <p class="mb-6 text-lg">
            This page documents the structure and possible responses from the Magic Holiday webhook endpoint.
            The webhook expects a JSON payload with <code class="bg-gray-200 px-1 rounded text-sm">id</code>, <code class="bg-gray-200 px-1 rounded text-sm">event</code>, and <code class="bg-gray-200 px-1 rounded text-sm">data</code> fields.
        </p>

        <h2 class="text-2xl font-semibold mt-8 mb-2 text-blue-600">Expected Request Payload</h2>
        <pre class="bg-gray-100 p-4 rounded-lg mb-6 text-sm overflow-x-auto border border-gray-200">
{
    "id": "string",
    "event": "string",
    "data": { /* object */ }
}
        </pre>

        <h2 class="text-2xl font-semibold mt-8 mb-2 text-blue-600">Response Structure</h2>
        <div class="overflow-x-auto mb-8">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                <thead>
                    <tr class="bg-blue-50">
                        <th class="py-3 px-4 border-b font-semibold text-left">Status Code</th>
                        <th class="py-3 px-4 border-b font-semibold text-left">Content-Type</th>
                        <th class="py-3 px-4 border-b font-semibold text-left">Example Response</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-3 px-4 border-b align-top">200 OK</td>
                        <td class="py-3 px-4 border-b align-top">application/hal+json</td>
                        <td class="py-3 px-4 border-b">
                            <pre class="bg-gray-100 p-3 rounded text-xs overflow-x-auto border border-gray-200">{
    "received": true
}
// Headers:
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1717171717
Content-Type: application/hal+json
                            </pre>
                        </td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="py-3 px-4 border-b align-top">400 Bad Request</td>
                        <td class="py-3 px-4 border-b align-top">application/problem+json</td>
                        <td class="py-3 px-4 border-b">
                            <pre class="bg-gray-100 p-3 rounded text-xs overflow-x-auto border border-gray-200">{
    "title": "Invalid Webhook Data",
    "type": "https://your-domain.com/docs/webhook/magic-holiday",
    "status": 400,
    "detail": "Missing required fields: id, event, or data."
}
// Headers:
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1717171717
Content-Type: application/problem+json
                            </pre>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="text-2xl font-semibold mt-8 mb-2 text-blue-600">Notes</h2>
        <ul class="list-disc pl-6 space-y-2 text-base">
            <li>All responses include rate limit headers: <code class="bg-gray-200 px-1 rounded text-sm">X-RateLimit-Limit</code>, <code class="bg-gray-200 px-1 rounded text-sm">X-RateLimit-Remaining</code>, <code class="bg-gray-200 px-1 rounded text-sm">X-RateLimit-Reset</code>.</li>
            <li>On error, the <code class="bg-gray-200 px-1 rounded text-sm">type</code> field points to this documentation.</li>
            <li>All responses are JSON and use appropriate <code class="bg-gray-200 px-1 rounded text-sm">Content-Type</code> headers.</li>
            <li>For more details, refer to the API integration guide or contact support.</li>
        </ul>
    </div>
</body>

</html>