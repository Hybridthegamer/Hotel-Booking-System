<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/TrustScore.php';

class QueueManager {
    private mysqli $db;
    private TrustScore $trustScore;

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->trustScore = new TrustScore($db);
    }

    /**
     * Add user to the reservation queue.
     * Priority score = (time_factor * WEIGHT_TIME) + (trust_score * WEIGHT_TRUST)
     * Lower priority_score = served sooner.
     */
    public function enqueue(int $userId, string $roomType, string $checkIn, string $checkOut): int {
        $this->expireOldEntries();

        // Check if already in queue for same room type and dates
        $checkStmt = $this->db->prepare(
            'SELECT id FROM reservation_queue
             WHERE user_id=? AND room_type=? AND check_in=? AND check_out=? AND status="waiting"'
        );
        $checkStmt->bind_param('isss', $userId, $roomType, $checkIn, $checkOut);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($existing) return (int)$existing['id'];

        $trustScore = $this->trustScore->getScore($userId);
        $priorityScore = $this->calculatePriorityScore($trustScore);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->db->prepare(
            'INSERT INTO reservation_queue (user_id, room_type, check_in, check_out, priority_score, expires_at)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param('isssds', $userId, $roomType, $checkIn, $checkOut, $priorityScore, $expiresAt);
        $stmt->execute();
        $queueId = (int)$this->db->insert_id;
        $stmt->close();

        $this->recalculatePositions($roomType);
        return $queueId;
    }

    public function getPosition(int $userId, string $roomType, string $checkIn, string $checkOut): int {
        $stmt = $this->db->prepare(
            'SELECT queue_position FROM reservation_queue
             WHERE user_id=? AND room_type=? AND check_in=? AND check_out=? AND status="waiting"'
        );
        $stmt->bind_param('isss', $userId, $roomType, $checkIn, $checkOut);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['queue_position'] : -1;
    }

    public function getNextInQueue(string $roomType, string $checkIn, string $checkOut): ?array {
        $this->expireOldEntries();
        $stmt = $this->db->prepare(
            'SELECT rq.*, u.full_name, u.email, u.trust_score
             FROM reservation_queue rq
             JOIN users u ON u.id = rq.user_id
             WHERE rq.room_type=? AND rq.check_in=? AND rq.check_out=? AND rq.status="waiting"
             ORDER BY rq.priority_score ASC, rq.created_at ASC
             LIMIT 1'
        );
        $stmt->bind_param('sss', $roomType, $checkIn, $checkOut);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function markAllocated(int $queueId): void {
        $stmt = $this->db->prepare(
            'UPDATE reservation_queue SET status="allocated", allocated_at=NOW() WHERE id=?'
        );
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $stmt->close();
    }

    public function cancelFromQueue(int $userId, string $roomType): void {
        $stmt = $this->db->prepare(
            'UPDATE reservation_queue SET status="cancelled" WHERE user_id=? AND room_type=? AND status="waiting"'
        );
        $stmt->bind_param('is', $userId, $roomType);
        $stmt->execute();
        $stmt->close();
    }

    public function getUserQueueEntries(int $userId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM reservation_queue WHERE user_id=? AND status="waiting" ORDER BY created_at ASC'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getQueueStats(): array {
        $result = $this->db->query(
            'SELECT room_type, COUNT(*) as waiting_count FROM reservation_queue WHERE status="waiting" GROUP BY room_type'
        );
        $stats = [];
        while ($row = $result->fetch_assoc()) {
            $stats[$row['room_type']] = (int)$row['waiting_count'];
        }
        return $stats;
    }

    private function calculatePriorityScore(float $trustScore): float {
        // Lower score = higher priority.
        // Time naturally provides FIFO; trust reduces the score, giving trusted users a small advantage.
        $trustFactor = (100.0 - $trustScore) * QUEUE_PRIORITY_TRUST_WEIGHT;
        return $trustFactor;
    }

    private function recalculatePositions(string $roomType): void {
        $stmt = $this->db->prepare(
            'SELECT id FROM reservation_queue
             WHERE room_type=? AND status="waiting"
             ORDER BY priority_score ASC, created_at ASC'
        );
        $stmt->bind_param('s', $roomType);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pos = 1;
        foreach ($rows as $row) {
            $upd = $this->db->prepare('UPDATE reservation_queue SET queue_position=? WHERE id=?');
            $upd->bind_param('ii', $pos, $row['id']);
            $upd->execute();
            $upd->close();
            $pos++;
        }
    }

    private function expireOldEntries(): void {
        $this->db->query(
            "UPDATE reservation_queue SET status='expired' WHERE status='waiting' AND expires_at <= NOW()"
        );
    }
}
