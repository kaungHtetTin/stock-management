<?php
/**
 * User model
 */

require_once APP_PATH . '/helpers/Database.php';

class User
{
    public const ROLES = ['admin', 'staff'];
    public const STATUSES = ['active', 'inactive'];
    public const PRIMARY_ADMIN_ID = 1;
    public const MIN_PASSWORD_LENGTH = 8;

    public static function all(array $filters = []): array
    {
        $db = Database::connect();
        $sql = 'SELECT id, username, display_name, role, status, created_at, updated_at
                FROM users WHERE 1=1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (username LIKE :q1 OR display_name LIKE :q2)';
            $params['q1'] = $params['q2'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['role']) && in_array($filters['role'], self::ROLES, true)) {
            $sql .= ' AND role = :role';
            $params['role'] = $filters['role'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }

        $sql .= ' ORDER BY role ASC, username ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, username, password_hash, display_name, role, status, created_at, updated_at
             FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, username, display_name, role, status, created_at, updated_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO users (username, password_hash, display_name, role, status)
             VALUES (:username, :password_hash, :display_name, :role, :status)'
        );
        $stmt->execute([
            'username'      => $data['username'],
            'password_hash' => $data['password_hash'],
            'display_name'  => $data['display_name'],
            'role'          => $data['role'],
            'status'        => $data['status'],
        ]);

        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();

        if (!empty($data['password_hash'])) {
            $stmt = $db->prepare(
                'UPDATE users SET
                    display_name = :display_name,
                    role = :role,
                    status = :status,
                    password_hash = :password_hash
                 WHERE id = :id'
            );
            $params = [
                'id'            => $id,
                'display_name'  => $data['display_name'],
                'role'          => $data['role'],
                'status'        => $data['status'],
                'password_hash' => $data['password_hash'],
            ];
        } else {
            $stmt = $db->prepare(
                'UPDATE users SET
                    display_name = :display_name,
                    role = :role,
                    status = :status
                 WHERE id = :id'
            );
            $params = [
                'id'           => $id,
                'display_name' => $data['display_name'],
                'role'         => $data['role'],
                'status'       => $data['status'],
            ];
        }

        return $stmt->execute($params);
    }

    public static function deactivate(int $id): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'UPDATE users SET status = \'inactive\' WHERE id = :id AND status = \'active\''
        );

        return $stmt->execute(['id' => $id]);
    }

    public static function canDeactivate(int $id, int $currentUserId): bool
    {
        if ($id === self::PRIMARY_ADMIN_ID || $id === $currentUserId) {
            return false;
        }

        return self::findById($id) !== null;
    }

    public static function canModify(int $id): bool
    {
        return self::findById($id) !== null;
    }

    public static function validate(array $input, bool $isEdit = false, ?int $id = null): array
    {
        $errors = [];
        $username = trim($input['username'] ?? '');
        $displayName = trim($input['display_name'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? '';
        $status = $input['status'] ?? '';

        if ($displayName === '') {
            $errors[] = 'Display name is required.';
        }

        if (!$isEdit) {
            if ($username === '') {
                $errors[] = 'Username is required.';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
                $errors[] = 'Username must be 3–50 characters (letters, numbers, . _ -).';
            } elseif (self::findByUsername($username)) {
                $errors[] = 'Username is already taken.';
            }
        }

        if (!$isEdit || $password !== '') {
            if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $errors[] = 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
            }
        }

        if (!in_array($role, self::ROLES, true)) {
            $errors[] = 'Please select a valid role.';
        }

        if (!in_array($status, self::STATUSES, true)) {
            $errors[] = 'Please select a valid status.';
        }

        if ($isEdit && $id === self::PRIMARY_ADMIN_ID) {
            if ($role !== 'admin' || $status !== 'active') {
                $errors[] = 'The primary admin account must remain active with admin role.';
            }
        }

        if ($isEdit && $id === (int) (current_user()['id'] ?? 0)) {
            if ($status !== 'active') {
                $errors[] = 'You cannot deactivate your own account.';
            }
        }

        return $errors;
    }

    public static function normalize(array $input): array
    {
        return [
            'username'     => trim($input['username'] ?? ''),
            'display_name' => trim($input['display_name'] ?? ''),
            'password'     => $input['password'] ?? '',
            'role'         => $input['role'] ?? '',
            'status'       => $input['status'] ?? 'active',
        ];
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function toSessionUser(array $user): array
    {
        return [
            'id'           => (int) $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'role'         => $user['role'],
            'status'       => $user['status'],
        ];
    }
}
