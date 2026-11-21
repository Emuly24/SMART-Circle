<?php

declare(strict_types=1);

namespace SmartCircle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SmartCircle\Application\Lessons\LessonService;
use SmartCircle\Data\Database;
use SmartCircle\Data\LessonStorage;
use SmartCircle\Data\Repositories\LessonRepository;

final class LessonsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sc-lessons-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function testStorageSplitsIntoPartsUnderTheLimitAndKeepsTheBytes(): void
    {
        $storage = new LessonStorage($this->dir, 10); // 10-byte parts for the test
        $upload = str_repeat('a', 32);
        $data = 'SCL1' . implode('', array_map(static fn (int $i): string => chr(65 + $i % 26), range(0, 30)));

        // Arrive in uneven pieces, like a real upload.
        foreach (str_split($data, 7) as $piece) {
            $storage->append($upload, $piece);
        }

        self::assertSame(strlen($data), $storage->uploadSize($upload));
        self::assertSame(hash('sha256', $data), $storage->uploadHash($upload));
        self::assertSame('SCL1ABCD', $storage->readUploadStart($upload, 8));

        $parts = $storage->promote($upload, 42);
        self::assertSame((int) ceil(strlen($data) / 10), $parts);
        $files = $storage->lessonParts(42);
        foreach ($files as $file) {
            self::assertLessThanOrEqual(10, filesize($file));
        }
        self::assertSame($data, implode('', array_map('file_get_contents', $files)));

        $storage->deleteLesson(42);
        self::assertSame([], $storage->lessonParts(42));
    }

    public function testStorageRejectsPathTricksInUploadIds(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LessonStorage($this->dir))->append('../../etc', 'x');
    }

    public function testPublishingNeedsFormSubjectAndAllRealLifeSections(): void
    {
        $service = $this->service();
        $lesson = ['form' => null, 'subject' => null, 'why_it_matters' => 'x', 'where_in_malawi' => ' ', 'careers' => null, 'try_at_home' => 'y'];

        self::assertSame(['Form', 'Subject', 'Where you see it in Malawi', 'Careers that use it'], $service->missingForPublish($lesson));

        $complete = ['form' => 3, 'subject' => 'physics', 'why_it_matters' => 'a', 'where_in_malawi' => 'b', 'careers' => 'c', 'try_at_home' => 'd'];
        self::assertSame([], $service->missingForPublish($complete));
    }

    public function testOnlyTutorsSeeDraftsAndGuestsSeeNothing(): void
    {
        $service = $this->service();
        $draft = ['status' => 'draft'];
        $published = ['status' => 'published'];

        self::assertTrue($service->canWatch($draft, 'tutor'));
        self::assertFalse($service->canWatch($draft, 'student'));
        self::assertTrue($service->canWatch($published, 'student'));
        self::assertFalse($service->canWatch($published, 'partner'));
        self::assertFalse($service->canWatch($published, null));
    }

    private function service(): LessonService
    {
        // The database is never queried by these rules (PDO connects lazily).
        $db = new Database('127.0.0.1', 1, 'none', 'none', '');

        return new LessonService(new LessonRepository($db), new LessonStorage($this->dir));
    }
}
