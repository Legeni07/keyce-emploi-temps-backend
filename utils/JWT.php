<?php
/**
 * utils/JWT.php
 * Implémentation minimale de JWT HS256 — sans dépendance externe.
 * Pour production, utiliser firebase/php-jwt via Composer.
 */

class JWT
{
    private static function getSecret(): string
    {
        $secret = getenv('JWT_SECRET');
        if (!$secret) {
            // Clé par défaut pour les tests étudiants — CHANGER EN PRODUCTION
            $secret = 'keyce_jwt_secret_2025_change_me_in_production';
        }
        return $secret;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Génère un token JWT.
     *
     * @param array $payload Données à encoder (user_id, role, etc.)
     * @param int   $expiry  Durée de vie en secondes (défaut : 24h)
     */
    public static function encode(array $payload, int $expiry = 86400): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['iss'] = 'keyce-api';

        $encodedPayload = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$encodedPayload}", self::getSecret(), true)
        );

        return "{$header}.{$encodedPayload}.{$signature}";
    }

    /**
     * Décode et valide un token JWT.
     * Retourne le payload ou null si invalide / expiré.
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        // Comparaison résistante aux attaques timing
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $data = json_decode(self::base64UrlDecode($payload), true);

        if (!$data || !isset($data['exp']) || $data['exp'] < time()) {
            return null; // Expiré
        }

        return $data;
    }

    /**
     * Extrait le token depuis l'en-tête Authorization: Bearer <token>
     */
    public static function fromHeader(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? apache_request_headers()['Authorization']
            ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
