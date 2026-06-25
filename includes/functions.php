<?php
require_once __DIR__ . '/config.php';

function getUserById(int $id): ?array {
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getRoomById(int $id): ?array {
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getAvailableRooms(string $checkIn, string $checkOut, string $roomType = ''): array {
    global $conn;

    $sql = "SELECT r.* FROM rooms r
            WHERE r.status = 'available'
            AND r.id NOT IN (
                SELECT b.room_id FROM bookings b
                WHERE b.status NOT IN ('cancelled','checked_out')
                AND NOT (b.check_out <= ? OR b.check_in >= ?)
            )";
    $params = [$checkIn, $checkOut];
    $types = 'ss';

    if ($roomType) {
        $sql .= ' AND r.room_type = ?';
        $types .= 's';
        $params[] = $roomType;
    }
    $sql .= ' ORDER BY r.room_type, r.rate ASC';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getBookingByPaymentId(string $paymentId): ?array {
    global $conn;
    $stmt = $conn->prepare(
        'SELECT b.*, r.room_number, r.room_type, r.rate, r.floor,
                u.full_name, u.email, u.phone
         FROM bookings b
         JOIN rooms r ON r.id = b.room_id
         JOIN users u ON u.id = b.user_id
         WHERE b.payment_id = ?'
    );
    $stmt->bind_param('s', $paymentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getUserBookings(int $userId): array {
    global $conn;
    $stmt = $conn->prepare(
        'SELECT b.*, r.room_number, r.room_type, r.rate, r.floor
         FROM bookings b
         JOIN rooms r ON r.id = b.room_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getRoomTypeInfo(): array {
    return [
        'Commercial' => ['icon' => 'bed', 'color' => '#6c757d', 'min_rate' => 7000],
        'Business'   => ['icon' => 'briefcase', 'color' => '#0d6efd', 'min_rate' => 12000],
        'Executive'  => ['icon' => 'star', 'color' => '#198754', 'min_rate' => 11000],
        'Double'     => ['icon' => 'people', 'color' => '#fd7e14', 'min_rate' => 56000],
        'Suite'      => ['icon' => 'gem', 'color' => '#6f42c1', 'min_rate' => 75000],
    ];
}

function getDashboardStats(): array {
    global $conn;
    $stats = [];

    $r = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status NOT IN ('cancelled')");
    $stats['total_bookings'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='confirmed' OR status='checked_in'");
    $stats['active_bookings'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) c FROM rooms WHERE status='available'");
    $stats['available_rooms'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) c FROM rooms");
    $stats['total_rooms'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT SUM(total_amount) s FROM bookings WHERE payment_status='paid'");
    $stats['total_revenue'] = (float)($r->fetch_assoc()['s'] ?? 0);

    $r = $conn->query("SELECT COUNT(*) c FROM users WHERE role='customer'");
    $stats['total_customers'] = (int)$r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) c FROM reservation_queue WHERE status='waiting'");
    $stats['queue_waiting'] = (int)$r->fetch_assoc()['c'];

    return $stats;
}

function getStatusBadge(string $status): string {
    $map = [
        'pending'     => 'warning',
        'confirmed'   => 'success',
        'checked_in'  => 'primary',
        'checked_out' => 'secondary',
        'cancelled'   => 'danger',
        'available'   => 'success',
        'occupied'    => 'danger',
        'reserved'    => 'warning',
        'maintenance' => 'secondary',
        'paid'        => 'success',
        'unpaid'      => 'danger',
        'refunded'    => 'info',
    ];
    $class = $map[$status] ?? 'secondary';
    return "<span class=\"badge bg-{$class}\">" . ucfirst(str_replace('_', ' ', $status)) . "</span>";
}
