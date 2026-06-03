<?php
namespace App\Utils;

class ProxyHelper
{
  public static function apply($ch, ?string $proxyUrl): void
  {
    if (!$proxyUrl) {
      return;
    }

    $parsed = parse_url($proxyUrl);
    if (!$parsed || !isset($parsed["host"])) {
      return;
    }

    $scheme = strtolower($parsed["scheme"] ?? "");
    $host = $parsed["host"];
    $port =
      $parsed["port"] ?? (in_array($scheme, ["http", "https"]) ? 80 : 1080);
    $user = $parsed["user"] ?? "";
    $pass = $parsed["pass"] ?? "";

    curl_setopt($ch, CURLOPT_PROXY, "$host:$port");

    if (in_array($scheme, ["socks5", "socks5h"])) {
      curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    } elseif ($scheme === "socks4") {
      curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
    } else {
      curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    }

    if ($user) {
      curl_setopt($ch, CURLOPT_PROXYUSERPWD, $pass ? "$user:$pass" : $user);
    }
  }
}
