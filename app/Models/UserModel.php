<?php
declare(strict_types=1);
namespace App\Models;

class UserModel extends BaseModel
{
    /**
     * Les jetons (confirmation, reset) sont stockés sous forme de hash SHA-256 :
     * en cas de fuite de la base, les jetons bruts envoyés par email restent
     * inutilisables (même principe que pour les mots de passe, en plus léger
     * car ce sont des jetons aléatoires à usage unique et non des secrets choisis).
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function findByEmail(string $email): ?array
    {
        // Colonnes explicites — évite d'exposer des champs futurs non prévus
        $stmt = $this->db()->prepare(
            "SELECT id, nomUtilisateur, email, motDePasse, is_confirmed
             FROM users WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT id, nomUtilisateur, motDePasse FROM users
             WHERE reset_token = :token AND reset_token_expiration > NOW()"
        );
        $stmt->execute([':token' => $this->hashToken($token)]);
        return $stmt->fetch() ?: null;
    }

    public function findByConfirmationToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT id FROM users
             WHERE confirmation_token = :token AND is_confirmed = 0
               AND confirmation_token_expiration > NOW()"
        );
        $stmt->execute([':token' => $this->hashToken($token)]);
        return $stmt->fetch() ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db()->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetch();
    }

    public function create(
        string $nomUtilisateur,
        string $email,
        string $hashedPassword,
        string $token
    ): int {
        $stmt = $this->db()->prepare(
            "INSERT INTO users
                (nomUtilisateur, email, motDePasse, confirmation_token, confirmation_token_expiration, is_confirmed)
             VALUES (:nomUtilisateur, :email, :motDePasse, :token, :expiration, 0)"
        );
        $stmt->execute([
            ':nomUtilisateur' => $nomUtilisateur,
            ':email'          => $email,
            ':motDePasse'     => $hashedPassword,
            ':token'          => $this->hashToken($token),
            ':expiration'     => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function confirmAccount(string $token): bool
    {
        $stmt = $this->db()->prepare(
            "UPDATE users SET is_confirmed = 1, confirmation_token = NULL, confirmation_token_expiration = NULL
             WHERE confirmation_token = :token AND confirmation_token_expiration > NOW()"
        );
        $stmt->execute([':token' => $this->hashToken($token)]);
        return $stmt->rowCount() > 0;
    }

    public function setResetToken(string $email, string $token, string $expiration): void
    {
        $stmt = $this->db()->prepare(
            "UPDATE users SET reset_token = :token, reset_token_expiration = :expiration
             WHERE email = :email"
        );
        $stmt->execute([
            ':token'      => $this->hashToken($token),
            ':expiration' => $expiration,
            ':email'      => $email,
        ]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $stmt = $this->db()->prepare(
            "UPDATE users SET motDePasse = :hash, reset_token = NULL, reset_token_expiration = NULL
             WHERE id = :id"
        );
        $stmt->execute([':hash' => $hash, ':id' => $id]);
    }
}
