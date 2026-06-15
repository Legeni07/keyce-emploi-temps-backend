<?php
/**
 * utils/Response.php
 * Helper centralisé pour toutes les réponses JSON de l'API.
 */

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Succès', int $status = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $message = 'Ressource créée avec succès'): void
    {
        self::success($data, $message, 201);
    }

    public static function error(string $message = 'Erreur', int $status = 400, mixed $errors = null): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    public static function notFound(string $message = 'Ressource introuvable'): void
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Non authentifié'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Accès refusé'): void
    {
        self::error($message, 403);
    }

    public static function validationError(array $errors, string $message = 'Données invalides'): void
    {
        self::error($message, 422, $errors);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        self::json([
            'success'     => true,
            'data'        => $items,
            'pagination'  => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
                'from'         => ($page - 1) * $perPage + 1,
                'to'           => min($page * $perPage, $total),
            ],
        ]);
    }
}
