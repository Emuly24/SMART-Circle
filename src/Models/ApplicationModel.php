<?php

declare(strict_types=1);

namespace SmartCircle\Models;

use PDO;
use PDOException;
use RuntimeException;
use SmartCircle\Data\Database;

/**
 * Application and university data access.
 */
final class ApplicationModel
{
    /** @var list<string> */
    public const ALL_SUBJECTS = [
        'Mathematics', 'English', 'Biology', 'Chichewa', 'Social Studies', 'History',
        'Bible Knowledge', 'Physics', 'Chemistry', 'Agriculture', 'Geography', 'Life Skills',
    ];

    /** @var list<string> */
    public const CORE_SUBJECTS = [
        'English', 'Mathematics', 'Biology', 'Physics', 'Chemistry',
    ];

    /** @var list<string> */
    private const DEFAULT_UNIVERSITIES = [
        'University of Malawi (UNIMA)',
        'Mzuzu University (MZUNI)',
        'Lilongwe University of Agriculture and Natural Resources (LUANAR)',
        'Malawi University of Business and Applied Sciences (MUBAS)',
        'Kamuzu University of Health Sciences (KUHeS)',
        'Malawi University of Science and Technology (MUST)',
        'DMI St. John the Baptist University',
        'Catholic University of Malawi',
    ];

    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findUserProfile(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT approved, class_level, gender, school, dob, subjects, route, fullname, phone, email
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function hasSubmittedApplication(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM applications WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);

        return $stmt->fetch() !== false;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM applications WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<string> */
    public function listUniversities(): array
    {
        $stmt = $this->db->query('SELECT name FROM universities ORDER BY name');
        $rows = $stmt ? $stmt->fetchAll() : [];

        $names = array_map(static fn (array $row): string => (string) $row['name'], $rows);

        if ($names === []) {
            return self::DEFAULT_UNIVERSITIES;
        }

        return $names;
    }

    public function ensureUniversityExists(string $name): void
    {
        $stmt = $this->db->prepare('SELECT id FROM universities WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);

        if ($stmt->fetch() !== false) {
            return;
        }

        $insert = $this->db->prepare('INSERT INTO universities (name) VALUES (?)');
        $insert->execute([$name]);
    }

    /**
     * @param array{class_level: string, gender: string, school: string, dob: string, subjects: string, route: string} $userData
     * @param array{ambition: string, career_reason: string, university: string, why_join: string, subject_assist: string, target_points: int} $applicationData
     */
    public function save(int $userId, array $userData, array $applicationData, bool $updateExisting): void
    {
        $this->db->beginTransaction();

        try {
            $userStmt = $this->db->prepare(
                'UPDATE users
                 SET class_level = ?, gender = ?, school = ?, dob = ?, subjects = ?, route = ?
                 WHERE id = ?'
            );
            $userStmt->execute([
                $userData['class_level'],
                $userData['gender'],
                $userData['school'],
                $userData['dob'],
                $userData['subjects'],
                $userData['route'],
                $userId,
            ]);

            $seriousness = json_encode(['agree' => true], JSON_THROW_ON_ERROR);

            if ($updateExisting) {
                $appStmt = $this->db->prepare(
                    'UPDATE applications
                     SET ambition = ?, career_reason = ?, university = ?, why_join = ?,
                         subject_assist = ?, target_points = ?, seriousness_answers = ?,
                         status = ?, submitted_at = NOW(), admin_notes = NULL
                     WHERE user_id = ?'
                );
                $appStmt->execute([
                    $applicationData['ambition'],
                    $applicationData['career_reason'],
                    $applicationData['university'],
                    $applicationData['why_join'],
                    $applicationData['subject_assist'],
                    $applicationData['target_points'],
                    $seriousness,
                    'pending',
                    $userId,
                ]);
            } else {
                $appStmt = $this->db->prepare(
                    'INSERT INTO applications
                     (user_id, ambition, career_reason, university, why_join, subject_assist, target_points, seriousness_answers, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $appStmt->execute([
                    $userId,
                    $applicationData['ambition'],
                    $applicationData['career_reason'],
                    $applicationData['university'],
                    $applicationData['why_join'],
                    $applicationData['subject_assist'],
                    $applicationData['target_points'],
                    $seriousness,
                    'pending',
                ]);
            }

            $this->db->commit();
        } catch (PDOException $exception) {
            $this->db->rollBack();

            throw new RuntimeException('Failed to save application.', 0, $exception);
        }
    }
}
