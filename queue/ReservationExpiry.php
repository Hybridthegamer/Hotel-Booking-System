<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/TrustScore.php';

class ReservationExpiry {
    private mysqli $db;
    private TrustScore $trustScore;

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->trustScore = new TrustScore($db);
    }

    public function createTempReservation(int $userId, int $roomId, string $checkIn, string $checkOut): string {
        $this->releaseExpiredReservations();

        $token = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TEMP_RESERVATION_MINUTES . ' minutes'));

        $stmt = $this->db->prepare(
            'INSERT INTO temp_reservations (user_id, room_id, session_token, check_in, check_out, expires_at)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param('iissss', $userId, $roomId, $token, $checkIn, $checkOut, $expiresAt);
        $stmt->execute();
        $stmt->close();

        $updateStmt = $this->db->prepare('UPDATE rooms SET status = "reserved" WHERE id = ?');
        $updateStmt->bind_param('i', $roomId);
        $updateStmt->execute();
        $updateStmt->close();

        return $token;
    }

    public function validateToken(string $token): ?array {
        $this->releaseExpiredReservations();

        $stmt = $this->db->prepare(
            'SELECT tr.*, r.room_number, r.room_type, r.rate, r.floor
             FROM temp_reservations tr
             JOIN rooms r ON r.id = tr.room_id
             WHERE tr.session_token = ? AND tr.status = "active" AND tr.expires_at > NOW()'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function convertToBooking(string $token, int $bookingId): bool {
        $stmt = $this->db->prepare(
            'UPDATE temp_reservations SET status = "converted" WHERE session_token = ? AND status = "active"'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected > 0;
    }

    public function releaseExpiredReservations(): void {
        $expiredStmt = $this->db->prepare(
            'SELECT user_id, room_id FROM temp_reservations WHERE status = "active" AND expires_at <= NOW()'
        );
        $expiredStmt->execute();
        $expired = $expiredStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $expiredStmt->close();

        foreach ($expired as $row) {
            $this->trustScore->recordEvent((int)$row['user_id'], 'failed_payment');
        }

        $this->db->query(
            'UPDATE rooms r
             JOIN temp_reservations tr ON tr.room_id = r.id
             SET r.status = "available"
             WHERE tr.status = "active" AND tr.expires_at <= NOW()'
        );

        $this->db->query(
            'UPDATE temp_reservations SET status = "expired" WHERE status = "active" AND expires_at <= NOW()'
        );
    }

    public function getSecondsRemaining(string $token): int {
        $stmt = $this->db->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS secs
             FROM temp_reservations WHERE session_token = ? AND status = "active"'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? max(0, (int)$row['secs']) : 0;
    }

    public function cancelTempReservation(string $token, int $userId): bool {
        $reservation = $this->validateToken($token);
        if (!$reservation || (int)$reservation['user_id'] !== $userId) {
            return false;
        }

        $this->db->query("UPDATE rooms SET status = 'available' WHERE id = {$reservation['room_id']}");

        $stmt = $this->db->prepare(
            'UPDATE temp_reservations SET status = "expired" WHERE session_token = ?'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();

        return true;
    }
}
