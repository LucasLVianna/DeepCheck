<?php
// Headers de segurança enviados por todas as respostas PHP.
// Incluído automaticamente por config/auth.php.

function requisicao_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) == 443;
}

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
// O filtro XSS legado dos navegadores foi descontinuado e podia ser explorado; "0" o desliga.
header('X-XSS-Protection: 0');

// HSTS só faz sentido (e só é respeitado) quando a requisição chega via HTTPS.
if (requisicao_https()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
