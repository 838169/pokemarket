<?php
declare(strict_types=1);

if (getenv('POKEMARKET_LAN') !== '1') {
    return true;
}
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (in_array($remote, ['127.0.0.1', '::1'], true)) {
    return true;
}
if (!filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    return false;
}
foreach (explode(',', getenv('POKEMARKET_LAN_SUBNETS') ?: '') as $subnet) {
    [$address, $prefix] = array_pad(explode('/', $subnet, 2), 2, '');
    if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
        !ctype_digit($prefix) || (int) $prefix < 1 || (int) $prefix > 32) {
        continue;
    }
    $shift = 32 - (int) $prefix;
    if ((ip2long($address) >> $shift) === (ip2long($remote) >> $shift)) {
        return true;
    }
}
return false;
