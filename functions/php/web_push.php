<?php
// functions/php/web_push.php
/**
 * PHMX Web Push & VAPID Engine (RFC 8292 / RFC 8291)
 * Native PHP Implementation (Zero External Dependencies)
 */

if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}

function phmx_find_openssl_cnf() {
    // 1. Cek jika sudah diset di environment system (Linux/Docker/VPS)
    $envCnf = getenv('OPENSSL_CONF');
    if ($envCnf && file_exists($envCnf)) return $envCnf;

    $candidates = [
        // Linux / VPS (Ubuntu, Debian, CentOS, RHEL, Alpine, Docker)
        '/etc/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
        '/usr/local/ssl/openssl.cnf',
        '/opt/lampp/etc/openssl.cnf',

        // Windows (XAMPP & Laragon)
        'D:/xampp/php/extras/ssl/openssl.cnf',
        'D:/xampp/apache/conf/openssl.cnf',
        'D:/xampp/php/extras/openssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
        'C:/laragon/etc/ssl/openssl.cnf',
        'D:/laragon/etc/ssl/openssl.cnf'
    ];
    
    foreach ($candidates as $c) {
        if (file_exists($c)) return $c;
    }
    
    // Pada Linux VPS standar, jika return null OpenSSL otomatis menggunakan default OS
    return null;
}

/**
 * Generate VAPID P-256 Key Pair
 */
function generateVapidKeys() {
    $cnf = phmx_find_openssl_cnf();
    if ($cnf) putenv("OPENSSL_CONF=$cnf");

    $config = [
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC
    ];
    if ($cnf) $config['config'] = $cnf;

    $res = openssl_pkey_new($config);
    if (!$res) return false;

    $details = openssl_pkey_get_details($res);
    $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $d = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

    // Uncompressed point: 0x04 || X || Y (65 bytes)
    $publicKeyBinary = "\x04" . $x . $y;
    $publicKey = base64url_encode($publicKeyBinary);
    $privateKey = base64url_encode($d);

    return [
        'public_key'  => $publicKey,
        'private_key' => $privateKey
    ];
}

function phmx_pem_from_private_scalar($privateKeyBase64Url, $publicKeyBase64Url = null) {
    $d = base64url_decode($privateKeyBase64Url);
    // EC private key DER for prime256v1
    $der = "\x30\x77\x02\x01\x01\x04\x20" . $d . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";
    if ($publicKeyBase64Url) {
        $pub = base64url_decode($publicKeyBase64Url);
        $der .= "\xa1\x44\x03\x42\x00" . $pub;
    }
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

function phmx_der_to_raw_sig($der) {
    if (ord($der[0]) !== 0x30) return false;
    $pos = 2;
    if (ord($der[1]) & 0x80) {
        $pos += (ord($der[1]) & 0x7F);
    }
    
    // Read R
    if (ord($der[$pos]) !== 0x02) return false;
    $rLen = ord($der[$pos + 1]);
    $r = substr($der, $pos + 2, $rLen);
    $pos += 2 + $rLen;
    
    // Read S
    if (ord($der[$pos]) !== 0x02) return false;
    $sLen = ord($der[$pos + 1]);
    $s = substr($der, $pos + 2, $sLen);
    
    $r = ltrim($r, "\0");
    $s = ltrim($s, "\0");
    $r = str_pad($r, 32, "\0", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\0", STR_PAD_LEFT);
    
    return $r . $s;
}

function phmx_hkdf($salt, $ikm, $info, $length) {
    $prk = hash_hmac('sha256', $ikm, empty($salt) ? str_repeat("\0", 32) : $salt, true);
    $okm = '';
    $t = '';
    $i = 1;
    while (strlen($okm) < $length) {
        $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
        $i++;
    }
    return substr($okm, 0, $length);
}

/**
 * Encrypt Payload using AES-128-GCM (RFC 8291)
 */
function encryptWebPushPayload($payloadString, $userP256dhBase64, $userAuthBase64) {
    $userPublicKey = base64url_decode($userP256dhBase64);
    $userAuthSecret = base64url_decode($userAuthBase64);

    if (strlen($userPublicKey) !== 65 || strlen($userAuthSecret) < 16) {
        return false;
    }

    $cnf = phmx_find_openssl_cnf();
    if ($cnf) putenv("OPENSSL_CONF=$cnf");
    $config = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    if ($cnf) $config['config'] = $cnf;

    $localKeyResource = openssl_pkey_new($config);
    if (!$localKeyResource) return false;

    $localDetails = openssl_pkey_get_details($localKeyResource);
    $localPublicKey = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

    // ECDH Shared Secret
    $userKeyDer = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $userPublicKey;
    $userKeyPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($userKeyDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $userKeyRes = openssl_pkey_get_public($userKeyPem);
    if (!$userKeyRes) return false;

    $sharedSecret = openssl_pkey_derive($userKeyRes, $localKeyResource, 32);
    if (!$sharedSecret) return false;

    // HKDF key derivation (RFC 8291)
    $authInfo = "WebPush: info\0" . $userPublicKey . $localPublicKey;
    $ikm = phmx_hkdf($userAuthSecret, $sharedSecret, $authInfo, 32);

    $salt = random_bytes(16);
    $cekInfo = "Content-Encoding: aes128gcm\0";
    $nonceInfo = "Content-Encoding: nonce\0";

    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $cek = substr(hash_hmac('sha256', $cekInfo . "\x01", $prk, true), 0, 16);
    $nonce = substr(hash_hmac('sha256', $nonceInfo . "\x01", $prk, true), 0, 12);

    // Padding: record delimiter "\x02"
    $padded = $payloadString . "\x02";

    $tag = '';
    $ciphertext = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) return false;

    // Format aes128gcm body: salt (16) || rs (4) || idlen (1) || key (65) || ciphertext+tag
    $rs = pack('N', 4096);
    $idLen = chr(65);
    $body = $salt . $rs . $idLen . $localPublicKey . $ciphertext . $tag;

    return $body;
}

/**
 * Menyiapkan request Web Push (Validasi SSRF, pembuatan VAPID header, enkripsi payload)
 *
 * @param array $subscription ['endpoint' => '...', 'p256dh' => '...', 'auth' => '...']
 * @param array|string $payload
 * @param array|null $vapidConfig
 * @param array &$jwtCache Cache JWT per audience agar tidak mengulang penandatanganan kriptografi
 * @return array ['success' => bool, 'endpoint' => string, 'headers' => array, 'body' => string, 'error' => string|null]
 */
function phmx_prepare_web_push_request($subscription, $payload = [], $vapidConfig = null, &$jwtCache = []) {
    if (!$vapidConfig) {
        $vapidConfig = $GLOBALS['vapid_config'] ?? [];
    }

    $endpoint = $subscription['endpoint'] ?? '';
    $p256dh = $subscription['p256dh'] ?? '';
    $auth = $subscription['auth'] ?? '';

    if (empty($endpoint) || empty($vapidConfig['public_key']) || empty($vapidConfig['private_key'])) {
        return ['success' => false, 'error' => 'Konfigurasi VAPID atau Endpoint tidak lengkap.'];
    }

    // 1. Validasi Keamanan Endpoint Push (Mitigasi SSRF)
    $parsedUrl = parse_url($endpoint);
    $scheme = strtolower($parsedUrl['scheme'] ?? '');
    $host = strtolower($parsedUrl['host'] ?? '');

    if ($scheme !== 'https' || empty($host)) {
        return ['success' => false, 'error' => 'Endpoint push tidak valid! Skema URL wajib menggunakan HTTPS.'];
    }

    $allowedPushDomains = [
        '*.googleapis.com',
        '*.push.services.mozilla.com',
        '*.notify.windows.com',
        '*.push.apple.com'
    ];

    $domainAllowed = false;
    foreach ($allowedPushDomains as $pattern) {
        if (fnmatch($pattern, $host) || $host === ltrim($pattern, '*.')) {
            $domainAllowed = true;
            break;
        }
    }

    if (!$domainAllowed) {
        return ['success' => false, 'error' => "Domain endpoint push ({$host}) ditolak! Hanya penyedia push resmi (*.googleapis.com, *.mozilla.com, *.apple.com, *.windows.com) yang diizinkan."];
    }

    // 2. Buat / Ambil VAPID JWT (RFC 8292) dengan Audience Caching
    $audience = $scheme . '://' . $host;
    if (!empty($parsedUrl['port'])) {
        $audience .= ':' . $parsedUrl['port'];
    }

    if (isset($jwtCache[$audience])) {
        $jwt = $jwtCache[$audience];
    } else {
        $jwtHeader = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $jwtClaims = base64url_encode(json_encode([
            'aud' => $audience,
            'exp' => time() + 86400,
            'sub' => $vapidConfig['subject'] ?? 'mailto:admin@example.com'
        ]));

        $jwtDataToSign = $jwtHeader . '.' . $jwtClaims;
        $privPem = phmx_pem_from_private_scalar($vapidConfig['private_key'], $vapidConfig['public_key']);

        $cnf = phmx_find_openssl_cnf();
        if ($cnf) putenv("OPENSSL_CONF=$cnf");

        $derSig = '';
        openssl_sign($jwtDataToSign, $derSig, $privPem, OPENSSL_ALGO_SHA256);
        $rawSig = phmx_der_to_raw_sig($derSig);

        $jwt = $jwtDataToSign . '.' . base64url_encode($rawSig);
        $jwtCache[$audience] = $jwt;
    }

    $vapidHeader = 'vapid t=' . $jwt . ', k=' . $vapidConfig['public_key'];

    // 3. Format & Enkripsi Payload
    $body = '';
    $headers = [
        'TTL: 86400',
        'Authorization: ' . $vapidHeader,
        'Urgency: high'
    ];

    if (!empty($payload) && !empty($p256dh) && !empty($auth)) {
        $payloadStr = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : (string)$payload;
        $body = encryptWebPushPayload($payloadStr, $p256dh, $auth);
        
        if ($body !== false) {
            $headers[] = 'Content-Type: application/octet-stream';
            $headers[] = 'Content-Encoding: aes128gcm';
            $headers[] = 'Content-Length: ' . strlen($body);
        } else {
            $body = '';
        }
    }

    return [
        'success'  => true,
        'endpoint' => $endpoint,
        'headers'  => $headers,
        'body'     => $body,
        'error'    => null
    ];
}

/**
 * Send Web Push Notification via VAPID (Single Dispatch)
 *
 * @param array $subscription ['endpoint' => '...', 'p256dh' => '...', 'auth' => '...']
 * @param array|string $payload Array data or JSON string
 * @param array|null $vapidConfig Optional custom VAPID keys
 * @return array ['success' => bool, 'status_code' => int, 'response' => string, 'error' => string]
 */
function sendWebPush($subscription, $payload = [], $vapidConfig = null) {
    $prep = phmx_prepare_web_push_request($subscription, $payload, $vapidConfig);
    if (!$prep['success']) {
        return [
            'success'     => false,
            'status_code' => 0,
            'response'    => '',
            'error'       => $prep['error']
        ];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $prep['endpoint'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $prep['body'],
        CURLOPT_HTTPHEADER     => $prep['headers'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $success = in_array($httpCode, [200, 201, 202]);

    return [
        'success'     => $success,
        'status_code' => $httpCode,
        'response'    => $response,
        'error'       => $curlErr ?: ($success ? '' : "HTTP Error {$httpCode}: {$response}")
    ];
}

/**
 * Kirim Web Push Notification secara Paralel / Concurrent menggunakan curl_multi_exec
 * Mengurangi waktu kirim dari puluhan detik menjadi hanya hitungan ratusan milidetik.
 *
 * @param array $subscriptions Array of subscription arrays
 * @param array|string $payload
 * @param array|null $vapidConfig
 * @return array ['total' => int, 'success_count' => int, 'fail_count' => int, 'results' => array]
 */
function sendWebPushBatch(array $subscriptions, $payload = [], $vapidConfig = null) {
    if (empty($subscriptions)) {
        return ['total' => 0, 'success_count' => 0, 'fail_count' => 0, 'results' => []];
    }

    $mh = curl_multi_init();
    $curlHandles = [];
    $results = [];
    $jwtCache = [];

    foreach ($subscriptions as $idx => $sub) {
        $prep = phmx_prepare_web_push_request($sub, $payload, $vapidConfig, $jwtCache);
        if (!$prep['success']) {
            $results[$idx] = [
                'endpoint'    => $sub['endpoint'] ?? '',
                'success'     => false,
                'status_code' => 0,
                'response'    => '',
                'error'       => $prep['error']
            ];
            continue;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $prep['endpoint'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $prep['body'],
            CURLOPT_HTTPHEADER     => $prep['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        curl_multi_add_handle($mh, $ch);
        $curlHandles[$idx] = $ch;
    }

    // Eksekusi paralel non-blocking
    if (!empty($curlHandles)) {
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($mh) === -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }

        foreach ($curlHandles as $idx => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_multi_getcontent($ch);
            $curlErr  = curl_error($ch);
            $success  = in_array($httpCode, [200, 201, 202]);

            $results[$idx] = [
                'endpoint'    => $subscriptions[$idx]['endpoint'] ?? '',
                'success'     => $success,
                'status_code' => $httpCode,
                'response'    => $response,
                'error'       => $curlErr ?: ($success ? '' : "HTTP Error {$httpCode}: {$response}")
            ];

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
    }

    curl_multi_close($mh);

    $successCount = 0;
    $failCount = 0;
    foreach ($results as $res) {
        if ($res['success']) {
            $successCount++;
        } else {
            $failCount++;
        }
    }

    return [
        'total'         => count($subscriptions),
        'success_count' => $successCount,
        'fail_count'    => $failCount,
        'results'       => $results
    ];
}

/**
 * =========================================================================
 * WEB PUSH QUEUE SYSTEM (ASYNCHRONOUS BACKGROUND WORKER)
 * =========================================================================
 */

/**
 * Memasukkan satu notifikasi ke dalam antrean database (push_notification_queue)
 *
 * @param mysqli $con
 * @param array $subscription
 * @param array|string $payload
 * @return bool|int
 */
function queueWebPush($con, array $subscription, $payload) {
    $endpoint = $subscription['endpoint'] ?? '';
    $p256dh = $subscription['p256dh'] ?? '';
    $auth = $subscription['auth'] ?? '';
    $payloadStr = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : (string)$payload;
    
    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        return false;
    }
    
    $id = generate_uuid();
    return executeSecure($con, 
        "INSERT INTO push_notification_queue (id, endpoint, p256dh, auth, payload, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
        [$id, $endpoint, $p256dh, $auth, $payloadStr],
        'sssss'
    );
}

/**
 * Memasukkan banyak notifikasi sekaligus ke antrean database secara efisien
 *
 * @param mysqli $con
 * @param array $subscriptions
 * @param array|string $payload
 * @return int Jumlah antrean yang berhasil dibuat
 */
function queueWebPushBatch($con, array $subscriptions, $payload) {
    if (empty($subscriptions)) return 0;
    
    $payloadStr = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : (string)$payload;
    $count = 0;
    
    foreach ($subscriptions as $sub) {
        $endpoint = $sub['endpoint'] ?? '';
        $p256dh = $sub['p256dh'] ?? '';
        $auth = $sub['auth'] ?? '';
        
        if (empty($endpoint) || empty($p256dh) || empty($auth)) continue;
        
        $id = generate_uuid();
        $ok = executeSecure($con, 
            "INSERT INTO push_notification_queue (id, endpoint, p256dh, auth, payload, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            [$id, $endpoint, $p256dh, $auth, $payloadStr],
            'sssss'
        );
        if ($ok) $count++;
    }
    
    return $count;
}

/**
 * Memproses antrean notifikasi dari database secara paralel di latar belakang (Background Worker)
 *
 * @param mysqli $con
 * @param int $batchSize Jumlah notifikasi yang diproses dalam 1 siklus
 * @param int $maxAttempts Batas maksimal percobaan pengiriman sebelum dinyatakan gagal permanen
 * @param array|null $vapidConfig
 * @return array ['processed' => int, 'success_count' => int, 'fail_count' => int]
 */
function processWebPushQueue($con, $batchSize = 50, $maxAttempts = 3, $vapidConfig = null) {
    // 1. Ambil antrean pending atau failed yang belum melebihi maxAttempts
    $sql = "SELECT id, endpoint, p256dh, auth, payload, attempts FROM push_notification_queue 
            WHERE status = 'pending' OR (status = 'failed' AND attempts < ?) 
            ORDER BY created_at ASC LIMIT ?";
    
    $res = querySecure($con, $sql, [$maxAttempts, $batchSize], 'ii');
    if (!$res || mysqli_num_rows($res) === 0) {
        return [
            'processed'     => 0,
            'success_count' => 0,
            'fail_count'    => 0
        ];
    }

    $queueItems = [];
    $ids = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $queueItems[$row['id']] = $row;
        $ids[] = $row['id'];
    }

    // 2. Tandai status menjadi 'processing'
    $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('s', count($ids));
    executeSecure($con, "UPDATE push_notification_queue SET status = 'processing' WHERE id IN ($inPlaceholders)", $ids, $types);

    // 3. Siapkan daftar subskripsi dan payload untuk batch execution
    $subscriptions = [];
    $payloads = [];
    $subIndexToId = [];

    $idx = 0;
    foreach ($queueItems as $id => $item) {
        $subscriptions[$idx] = [
            'endpoint' => $item['endpoint'],
            'p256dh'   => $item['p256dh'],
            'auth'     => $item['auth']
        ];
        $payloads[$idx] = json_decode($item['payload'], true) ?: $item['payload'];
        $subIndexToId[$idx] = $id;
        $idx++;
    }

    $mh = curl_multi_init();
    $curlHandles = [];
    $results = [];
    $jwtCache = [];

    foreach ($subscriptions as $i => $sub) {
        $prep = phmx_prepare_web_push_request($sub, $payloads[$i], $vapidConfig, $jwtCache);
        if (!$prep['success']) {
            $results[$i] = [
                'success'     => false,
                'status_code' => 0,
                'response'    => '',
                'error'       => $prep['error']
            ];
            continue;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $prep['endpoint'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $prep['body'],
            CURLOPT_HTTPHEADER     => $prep['headers'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        curl_multi_add_handle($mh, $ch);
        $curlHandles[$i] = $ch;
    }

    if (!empty($curlHandles)) {
        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc === CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($mh) === -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }

        foreach ($curlHandles as $i => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_multi_getcontent($ch);
            $curlErr  = curl_error($ch);
            $success  = in_array($httpCode, [200, 201, 202]);

            $results[$i] = [
                'success'     => $success,
                'status_code' => $httpCode,
                'response'    => $response,
                'error'       => $curlErr ?: ($success ? '' : "HTTP Error {$httpCode}: {$response}")
            ];

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
    }
    curl_multi_close($mh);

    // 4. Update status antrean di database
    $successCount = 0;
    $failCount = 0;

    foreach ($results as $i => $res) {
        $qId = $subIndexToId[$i];
        $isSuccess = $res['success'];
        $newStatus = $isSuccess ? 'completed' : 'failed';
        $errMsg = $isSuccess ? null : ($res['error'] ?? 'Unknown push error');

        if ($isSuccess) {
            $successCount++;
        } else {
            $failCount++;
        }

        executeSecure($con, 
            "UPDATE push_notification_queue SET status = ?, attempts = attempts + 1, error_message = ?, processed_at = NOW() WHERE id = ?",
            [$newStatus, $errMsg, $qId],
            'sss'
        );
    }

    return [
        'processed'     => count($queueItems),
        'success_count' => $successCount,
        'fail_count'    => $failCount
    ];
}
?>
