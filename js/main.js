/* Grand Royale Hotel — Main JS */

document.addEventListener('DOMContentLoaded', () => {

    // Auto-dismiss flash alerts after 5 s
    document.querySelectorAll('.alert.fade.show').forEach(el => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            bsAlert.close();
        }, 5000);
    });

    // Keep check-out min date in sync with check-in
    const checkIn  = document.getElementById('check_in');
    const checkOut = document.getElementById('check_out');
    if (checkIn && checkOut) {
        const today = new Date().toISOString().split('T')[0];
        checkIn.min = today;
        checkIn.addEventListener('change', () => {
            const next = new Date(checkIn.value);
            next.setDate(next.getDate() + 1);
            checkOut.min = next.toISOString().split('T')[0];
            if (checkOut.value && checkOut.value <= checkIn.value) {
                checkOut.value = next.toISOString().split('T')[0];
            }
            updateNightCount();
        });
        checkOut.addEventListener('change', updateNightCount);
        updateNightCount();
    }

    function updateNightCount() {
        if (!checkIn || !checkOut || !checkIn.value || !checkOut.value) return;
        const nights = Math.round((new Date(checkOut.value) - new Date(checkIn.value)) / 86400000);
        const el = document.getElementById('night_count');
        const totalEl = document.getElementById('total_price');
        const rate = parseFloat(document.getElementById('room_rate')?.value || 0);
        if (el && nights > 0) {
            el.textContent = nights + (nights === 1 ? ' night' : ' nights');
        }
        if (totalEl && rate > 0 && nights > 0) {
            totalEl.textContent = '₦' + (rate * nights).toLocaleString('en-NG', {minimumFractionDigits: 2});
        }
    }

    // Countdown timer for temporary reservations
    const timerEl = document.getElementById('reservation-timer');
    if (timerEl) {
        let seconds = parseInt(timerEl.dataset.seconds, 10);
        const countdownEl = document.getElementById('countdown-display');
        const warningEl   = document.getElementById('timer-warning');

        function tick() {
            if (seconds <= 0) {
                clearInterval(interval);
                if (countdownEl) countdownEl.textContent = '00:00';
                showExpiredMessage();
                return;
            }
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            if (countdownEl) {
                countdownEl.textContent = m + ':' + s;
                if (seconds <= 120) countdownEl.classList.add('urgent');
            }
            if (warningEl) warningEl.style.display = seconds <= 120 ? 'block' : 'none';
            seconds--;
        }
        tick();
        const interval = setInterval(tick, 1000);
    }

    function showExpiredMessage() {
        const body = document.getElementById('payment-body');
        if (body) {
            body.innerHTML = `
            <div class="text-center py-5">
                <div class="mb-3 text-danger" style="font-size:4rem"><i class="bi bi-clock-history"></i></div>
                <h4 class="text-danger">Reservation Expired</h4>
                <p class="text-muted">Your temporary room hold has expired. The room is now available for others.</p>
                <a href="/rooms.php" class="btn btn-primary mt-2">Browse Rooms Again</a>
            </div>`;
        }
    }

    // Confirm dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    // Room type filter buttons
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.room-card-wrap').forEach(card => {
                card.style.display = (type === 'all' || card.dataset.type === type) ? '' : 'none';
            });
        });
    });

    // Live search in admin tables
    const tableSearch = document.getElementById('tableSearch');
    if (tableSearch) {
        tableSearch.addEventListener('input', () => {
            const q = tableSearch.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
});
