
<?php
// =====================================================
// NEEDIFY - GEMINI AI CLIENT
// Secure API Request + Automatic Retry
// =====================================================

require_once __DIR__ . '/../config/gemini.php';

function gemini_query(string $prompt)
{
    // CHECK API CONFIGURATION
    if (
        !defined('GEMINI_API_KEY') ||
        !defined('GEMINI_MODEL') ||
        trim(GEMINI_API_KEY) === '' ||
        GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' ||
        GEMINI_API_KEY === 'YOUR_ACTUAL_API_KEY'
    ) {
        error_log('Needify AI: Gemini API configuration missing.');
        return false;
    }

    // CHECK CURL
    if (!function_exists('curl_init')) {
        error_log('Needify AI: PHP cURL extension is unavailable.');
        return false;
    }

    // GEMINI API URL
    $model = GEMINI_MODEL;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key='
        . urlencode(GEMINI_API_KEY);

    // REQUEST PAYLOAD
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ];

    $json = json_encode($payload);

    if ($json === false) {
        error_log('Needify AI: Failed to encode Gemini request.');
        return false;
    }

    // AUTOMATIC RETRY SETTINGS
    $maxAttempts = 3;

    // RETRY ON TEMPORARY SERVER ERRORS
    $retryableHttpCodes = [
        429, 500, 502, 503, 504
    ];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {

        // INITIALIZE CURL
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,

            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],

            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        ]);

        // EXECUTE REQUEST
        $response = curl_exec($ch);

        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        // HANDLE CURL / NETWORK ERRORS
        if ($response === false) {

            error_log(
                'Needify Gemini cURL error (Attempt '
                . $attempt . '): ' . $curlError
            );

            // RETRY TEMPORARY CONNECTION ERRORS
            $temporaryCurlErrors = [
                CURLE_OPERATION_TIMEOUTED,
                CURLE_COULDNT_CONNECT,
                CURLE_RECV_ERROR,
                CURLE_SEND_ERROR,
                CURLE_GOT_NOTHING
            ];

            if (
                in_array($curlErrno, $temporaryCurlErrors, true) &&
                $attempt < $maxAttempts
            ) {
                sleep($attempt);
                continue;
            }

            return false;
        }

        // DECODE RESPONSE
        $data = json_decode($response, true);

        // HANDLE HTTP ERRORS
        if ($httpCode < 200 || $httpCode >= 300) {

            $apiError = $data['error']['message']
                ?? 'Unknown Gemini API error';

            error_log(
                'Needify Gemini API error ('
                . $httpCode . '): ' . $apiError
                . ' | Attempt: ' . $attempt
            );

            // RETRY TEMPORARY API ERRORS
            if (
                in_array($httpCode, $retryableHttpCodes, true) &&
                $attempt < $maxAttempts
            ) {
                sleep($attempt);
                continue;
            }

            return false;
        }

        // VALIDATE RESPONSE
        if (!is_array($data)) {
            error_log('Needify AI: Invalid Gemini API response.');
            return false;
        }

        // EXTRACT AI REPLY
        $reply = $data['candidates'][0]['content']['parts'][0]['text']
            ?? '';

        if (!is_string($reply) || trim($reply) === '') {
            error_log('Needify AI: Gemini returned an empty response.');
            return false;
        }

        // SUCCESS
        return trim($reply);
    }

    error_log('Needify AI: All Gemini API retry attempts failed.');

    return false;
}
