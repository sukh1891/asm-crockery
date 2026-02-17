<?php

function isInWishlist($product_id) {
    global $conn;
    $user_id = getLoggedInUserId();
    if (!$user_id) return false;

    $pid = intval($product_id);
    $uid = intval($user_id);

    $q = mysqli_query($conn,
        "SELECT id FROM wishlist 
         WHERE user_id='$uid' AND product_id='$pid' LIMIT 1"
    );
    return mysqli_num_rows($q) > 0;
}

function getWishlistItems() {
    global $conn;
    $user_id = getLoggedInUserId();
    if (!$user_id) return [];

    $uid = intval($user_id);

    $q = mysqli_query($conn,
        "SELECT w.*, p.title, p.images, p.price_inr, p.product_type 
         FROM wishlist w 
         LEFT JOIN products p ON p.id = w.product_id
         WHERE w.user_id='$uid'
         ORDER BY w.created_at DESC"
    );

    $items = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $items[] = $row;
    }
    return $items;
}

function getWishlistCount() {
    global $conn;
    $user_id = getLoggedInUserId();
    if (!$user_id) return 0;

    $uid = intval($user_id);
    $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM wishlist WHERE user_id='$uid'");
    $r = mysqli_fetch_assoc($q);
    return intval($r['c'] ?? 0);
}

// get user country (reuse your existing geolocation)
function getUserCountry() {
    if(isset($_SERVER['HTTP_CF_IPCOUNTRY'])) return $_SERVER['HTTP_CF_IPCOUNTRY'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $json = @file_get_contents("https://ipapi.co/{$ip}/json/");
    if($json) {
        $data = json_decode($json, true);
        return $data['country'] ?? 'IN';
    }
    return 'IN';
}

function convertToUSD($inr) {
    global $conn;
    $q = mysqli_query($conn, "SELECT rate FROM currency_rates WHERE currency='INR_TO_USD' LIMIT 1");
    $row = mysqli_fetch_assoc($q);
    $rate = $row['rate'] ?? 0.012;
    return round($inr * $rate, 2);
}

// Merge session cart into user cart on login
function mergeGuestCartToUser($user_id) {
    global $conn;
    $sess = getSessionId();

    // get guest cart items
    $res = mysqli_query($conn, "SELECT * FROM cart WHERE session_id='".mysqli_real_escape_string($conn,$sess)."'");
    while($item = mysqli_fetch_assoc($res)) {
        // try to find if same product+variation exists for user
        $pq = "SELECT * FROM cart WHERE user_id='".intval($user_id)."' AND product_id='".intval($item['product_id'])."' AND ";
        $pq .= is_null($item['variation_id']) ? "variation_id IS NULL" : "variation_id='".intval($item['variation_id'])."'";
        $pq_res = mysqli_query($conn, $pq);

        if (mysqli_num_rows($pq_res) > 0) {
            // update qty (sum)
            $existing = mysqli_fetch_assoc($pq_res);
            $newQty = $existing['qty'] + $item['qty'];
            mysqli_query($conn, "UPDATE cart SET qty='".intval($newQty)."' WHERE id='".intval($existing['id'])."'");
            // remove guest row
            mysqli_query($conn, "DELETE FROM cart WHERE id='".intval($item['id'])."'");
        } else {
            // move row to user
            mysqli_query($conn, "UPDATE cart SET user_id='".intval($user_id)."', session_id=NULL WHERE id='".intval($item['id'])."'");
        }
    }
}
// NO session_start() here

function getSessionId() {
    if (!isset($_SESSION['cart_session'])) {
        $_SESSION['cart_session'] = session_id();
    }
    return $_SESSION['cart_session'];
}

function getLoggedInUserId() {
    return $_SESSION['user_id'] ?? null;
}


function setUserRememberLogin($user_id, $days = 30) {
    global $conn;

    $uid = intval($user_id);
    if ($uid <= 0) return;

    $selector = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $token_plain = $selector . ':' . $validator;
    $token_hash = hash('sha256', $token_plain);

    $expires_ts = time() + ($days * 86400);
    $expires_sql = date('Y-m-d H:i:s', $expires_ts);

    mysqli_query($conn,
        "UPDATE users SET remember_token='".mysqli_real_escape_string($conn, $token_hash)."', remember_expires='".mysqli_real_escape_string($conn, $expires_sql)."' WHERE id='$uid'"
    );

    $is_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('user_remember', $token_plain, [
        'expires' => $expires_ts,
        'path' => '/',
        'secure' => $is_secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function tryRememberUserLogin() {
    global $conn;

    if (!empty($_SESSION['user_id'])) return intval($_SESSION['user_id']);

    $raw = $_COOKIE['user_remember'] ?? '';
    if (!$raw || strpos($raw, ':') === false) return null;

    [$selector, $validator] = explode(':', $raw, 2);
    if (!$selector || !$validator) return null;

    $token_hash = hash('sha256', $selector . ':' . $validator);
    $token_hash_esc = mysqli_real_escape_string($conn, $token_hash);
    $now = date('Y-m-d H:i:s');

    $q = mysqli_query($conn,
        "SELECT id FROM users WHERE remember_token='$token_hash_esc' AND (remember_expires IS NULL OR remember_expires >= '$now') LIMIT 1"
    );

    if (!$q || mysqli_num_rows($q) === 0) {
        setcookie('user_remember', '', time() - 3600, '/');
        return null;
    }

    $user = mysqli_fetch_assoc($q);
    $uid = intval($user['id']);
    if ($uid <= 0) return null;

    $_SESSION['user_id'] = $uid;

    // Rotate token on each successful remember-login
    setUserRememberLogin($uid);

    return $uid;
}


/* =====================
   GET CART ITEMS (DB)
===================== */
function getCartItems() {
    global $conn;
    $userId = getLoggedInUserId();

    if ($userId) {
        $q = mysqli_query($conn,"
            SELECT c.*, p.title, p.product_type
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id='".intval($userId)."'
            ORDER BY c.added_at DESC
        ");
    } else {
        $sid = mysqli_real_escape_string($conn, getSessionId());
        $q = mysqli_query($conn,"
            SELECT c.*, p.title, p.product_type
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.session_id='$sid'
            ORDER BY c.added_at DESC
        ");
    }

    $items = [];
    while ($r = mysqli_fetch_assoc($q)) $items[] = $r;
    return $items;
}

/* =====================
   CART SUMMARY (DB)
===================== */
function getCartSummary($conn) {
    global $conn;
    $items = getCartItems();
    $subtotal = 0;

    foreach ($items as $item) {
        $qty = max(1, intval($item['qty']));
        $price = 0;

        $price = floatval($item['price_inr']);
        $subtotal += $price * $qty;
    }

    $shipping = ($subtotal > 0 && $subtotal < 999) ? 99 : 0;

    return [
        'items'    => $items,
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'total'    => round($subtotal + $shipping, 2)
    ];
}
