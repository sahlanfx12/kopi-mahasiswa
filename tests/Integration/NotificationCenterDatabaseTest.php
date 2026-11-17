<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/TestDatabase.php';

final class NotificationCenterDatabaseTest extends TestCase
{
    private mysqli $connection;
    private int $userOne;
    private int $userTwo;
    private int $notificationOne;

    protected function setUp(): void
    {
        $this->connection = TestDatabase::connectionOrSkip($this);
        $this->connection->query(
            'CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                type VARCHAR(32) NOT NULL DEFAULT "info",
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                link VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $this->connection->begin_transaction();

        $this->userOne = random_int(100000000, 199999999);
        $this->userTwo = random_int(200000000, 299999999);
        $stmt = $this->connection->prepare(
            'INSERT INTO notifications (user_id, title, body, is_read) VALUES (?, ?, ?, 0)'
        );
        $title = 'Synthetic notification';
        $body = 'Test fixture only';
        $stmt->bind_param('iss', $this->userOne, $title, $body);
        $stmt->execute();
        $this->notificationOne = (int)$stmt->insert_id;
        $stmt->bind_param('iss', $this->userTwo, $title, $body);
        $stmt->execute();
        $stmt->close();
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->rollback();
            $this->connection->close();
        }
    }

    public function testListAndUnreadCountAreScopedToTheRequestedUser(): void
    {
        $items = notification_center_list($this->connection, $this->userOne);

        self::assertIsArray($items);
        self::assertCount(1, $items);
        self::assertSame('Synthetic notification', $items[0]['title']);
        self::assertSame(1, notification_center_unread_count($this->connection, $this->userOne));
        self::assertSame(1, notification_center_unread_count($this->connection, $this->userTwo));
    }

    public function testMarkReadCannotChangeAnotherUsersNotification(): void
    {
        self::assertTrue(notification_center_mark_one_read($this->connection, $this->notificationOne, $this->userTwo));
        $stmt = $this->connection->prepare('SELECT is_read FROM notifications WHERE id = ?');
        $stmt->bind_param('i', $this->notificationOne);
        $stmt->execute();
        $isReadAfterCrossUserAttempt = (int)$stmt->get_result()->fetch_assoc()['is_read'];
        $stmt->close();
        self::assertSame(0, $isReadAfterCrossUserAttempt);

        self::assertTrue(notification_center_mark_one_read($this->connection, $this->notificationOne, $this->userOne));
        self::assertSame(0, notification_center_unread_count($this->connection, $this->userOne));
    }
}
