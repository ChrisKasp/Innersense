<?php

declare(strict_types=1);

final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $query = ''): array
    {
        if ($query === '') {
            $stmt = $this->pdo->query(
                'SELECT id, first_name, last_name, email, phone, company, created_at, updated_at
                 FROM customers
                 ORDER BY last_name ASC, first_name ASC'
            );

            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email, phone, company, created_at, updated_at
             FROM customers
             WHERE first_name LIKE :q
                OR last_name LIKE :q
                OR email LIKE :q
                OR company LIKE :q
             ORDER BY last_name ASC, first_name ASC'
        );
        $stmt->execute(['q' => '%' . $query . '%']);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email, phone, company, created_at, updated_at
             FROM customers
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, string> $data
     */
    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (first_name, last_name, email, phone, company)
             VALUES (:first_name, :last_name, :email, :phone, :company)'
        );

        $stmt->execute([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
        ]);
    }

    /**
     * @param array<string, string> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE customers
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 company = :company,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
