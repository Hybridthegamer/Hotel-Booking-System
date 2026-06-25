<?php
require_once __DIR__ . '/../includes/config.php';

class TrustScore {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function getScore(int $userId): float {
        $stmt = $this->db->prepare('SELECT trust_score FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? (float)$result['trust_score'] : 100.0;
    }

    public function recordEvent(int $userId, string $eventType, ?int $bookingId = null): void {
        $change = $this->getScoreChange($eventType);
        if ($change === 0.0) return;

        $currentScore = $this->getScore($userId);
        $newScore = max(0.0, min(100.0, $currentScore + $change));

        $stmt = $this->db->prepare(
            'UPDATE users SET trust_score = ?, ' . $this->getCounterColumn($eventType) . ' = ' . $this->getCounterColumn($eventType) . ' + 1 WHERE id = ?'
        );
        $stmt->bind_param('di', $newScore, $userId);
        $stmt->execute();
        $stmt->close();

        $note = $this->buildNote($eventType);
        $logStmt = $this->db->prepare(
            'INSERT INTO trust_score_log (user_id, event_type, score_change, score_after, booking_id, note) VALUES (?,?,?,?,?,?)'
        );
        $logStmt->bind_param('isddis', $userId, $eventType, $change, $newScore, $bookingId, $note);
        $logStmt->execute();
        $logStmt->close();
    }

    public function getTrustLabel(float $score): string {
        if ($score >= 90) return 'Excellent';
        if ($score >= 75) return 'Good';
        if ($score >= 50) return 'Fair';
        if ($score >= 25) return 'Poor';
        return 'Very Poor';
    }

    public function getTrustClass(float $score): string {
        if ($score >= 75) return 'success';
        if ($score >= 50) return 'warning';
        return 'danger';
    }

    public function getUserLog(int $userId, int $limit = 10): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM trust_score_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function getScoreChange(string $eventType): float {
        return match($eventType) {
            'booking_completed'    => TRUST_SCORE_BOOKING_COMPLETED,
            'stay_completed'       => TRUST_SCORE_STAY_COMPLETED,
            'long_stay_completed'  => TRUST_SCORE_LONG_STAY,
            'cancellation'         => TRUST_SCORE_CANCELLATION,
            'failed_payment'       => TRUST_SCORE_FAILED_PAYMENT,
            'no_show'              => TRUST_SCORE_NO_SHOW,
            default                => 0.0,
        };
    }

    private function getCounterColumn(string $eventType): string {
        return match($eventType) {
            'stay_completed', 'long_stay_completed' => 'completed_stays',
            'cancellation'                          => 'cancellations',
            'failed_payment', 'no_show'             => 'failed_bookings',
            default                                 => 'total_bookings',
        };
    }

    private function buildNote(string $eventType): string {
        return match($eventType) {
            'booking_completed'   => 'Booking successfully confirmed and paid.',
            'stay_completed'      => 'Guest completed their stay without issues.',
            'long_stay_completed' => 'Guest completed an extended stay (5+ nights).',
            'cancellation'        => 'Guest cancelled a confirmed reservation.',
            'failed_payment'      => 'Payment attempt failed after room was held.',
            'no_show'             => 'Guest did not show up for confirmed booking.',
            default               => 'Score adjusted.',
        };
    }
}
