<?php
require_once '../config.php';

// Check if user is logged in and is a delivery person
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'delivery') {
    header('Location: ../login.php');
    exit();
}

$delivery_person_id = $_SESSION['user_id'];

// Fetch delivery person details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'delivery'");
    $stmt->execute([$delivery_person_id]);
    $delivery_person = $stmt->fetch();

    if (!$delivery_person) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

    // Fetch active deliveries with addresses
    $stmt = $pdo->prepare("
        SELECT 
            da.*,
            o.order_number,
            o.total_amount,
            u.first_name as customer_first_name,
            u.last_name as customer_last_name,
            u.phone as customer_phone,
            ua.address_line_1,
            ua.address_line_2,
            ua.city,
            ua.state,
            ua.postal_code
        FROM delivery_assignments da
        INNER JOIN orders o ON da.order_id = o.id
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN user_addresses ua ON o.shipping_address_id = ua.id
        WHERE da.delivery_person_id = ?
        AND da.status IN ('assigned', 'picked_up', 'in_transit')
        ORDER BY da.assigned_at ASC
    ");
    $stmt->execute([$delivery_person_id]);
    $active_deliveries = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Route Page Error: " . $e->getMessage());
    die("An error occurred while loading the route page.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route & Tracking - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");

        *, *::before, *::after {
          box-sizing: border-box;
          padding: 0;
          margin: 0;
        }

        nav {
          user-select: none;
          -webkit-user-select: none;
          -moz-user-select: none;
          -ms-user-select: none;
          -o-user-select: none;
        }

        nav ul, nav ul li {
          outline: 0;
        }

        nav ul li a {
          text-decoration: none;
        }

        body {
          font-family: "Nunito", sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          background-image: url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);
          background-repeat: no-repeat;
          background-size: cover;
        }

        main {
          display: grid;
          grid-template-columns: 13% 87%;
          width: 100%;
          margin: 40px;
          background: rgb(254, 254, 254);
          box-shadow: 0 0.5px 0 1px rgba(255, 255, 255, 0.23) inset,
            0 1px 0 0 rgba(255, 255, 255, 0.66) inset, 0 4px 16px rgba(0, 0, 0, 0.12);
          border-radius: 15px;
          z-index: 10;
        }

        .main-menu {
          overflow: hidden;
          background: rgb(73, 57, 113);
          padding-top: 10px;
          border-radius: 15px 0 0 15px;
          font-family: "Roboto", sans-serif;
        }

        .main-menu h1 {
          display: block;
          font-size: 1.5rem;
          font-weight: 500;
          text-align: center;
          margin: 0;
          color: #fff;
          font-family: "Nunito", sans-serif;
          padding-top: 15px;
        }

        .main-menu small {
          display: block;
          font-size: 1rem;
          font-weight: 300;
          text-align: center;
          margin: 10px 0;
          color: #fff;
          font-family: "Nunito", sans-serif;
        }

        .logo {
          display: none;
          width: 30px;
          margin: 20px auto;
        }

        .nav-item {
          position: relative;
          display: block;
        }

        .nav-item a {
          position: relative;
          display: flex;
          flex-direction: row;
          align-items: center;
          justify-content: center;
          color: #fff;
          font-size: 1rem;
          padding: 15px 0;
          margin-left: 10px;
          border-top-left-radius: 20px;
          border-bottom-left-radius: 20px;
        }

        .nav-item b:nth-child(1) {
          position: absolute;
          top: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(1)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-bottom-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item b:nth-child(2) {
          position: absolute;
          bottom: -15px;
          height: 15px;
          width: 100%;
          background: #fff;
          display: none;
        }

        .nav-item b:nth-child(2)::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          border-top-right-radius: 20px;
          background: rgb(73, 57, 113);
        }

        .nav-item.active b:nth-child(1),
        .nav-item.active b:nth-child(2) {
          display: block;
        }

        .nav-item.active a {
          text-decoration: none;
          color: #000;
          background: rgb(254, 254, 254);
        }

        .nav-icon {
          width: 60px;
          height: 20px;
          font-size: 20px;
          text-align: center;
        }

        .nav-text {
          display: block;
          width: 120px;
          height: 20px;
        }

        .content {
          background: #f6f7fb;
          margin: 15px;
          padding: 20px;
          border-radius: 15px;
          overflow-y: auto;
          max-height: calc(100vh - 80px);
        }

        .header-section {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 20px;
          flex-wrap: wrap;
          gap: 15px;
        }

        .header-title h1 {
          font-size: 1.5rem;
          font-weight: 700;
          color: #484d53;
          margin: 0;
        }

        .tracking-status {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-top: 5px;
          font-size: 0.9rem;
          color: #6b7280;
        }

        .status-indicator {
          width: 10px;
          height: 10px;
          border-radius: 50%;
          animation: pulse 2s infinite;
        }

        .status-indicator.tracking {
          background: #10b981;
        }

        .status-indicator.stopped {
          background: #ef4444;
          animation: none;
        }

        @keyframes pulse {
          0%, 100% { opacity: 1; transform: scale(1); }
          50% { opacity: 0.6; transform: scale(1.1); }
        }

        .header-actions {
          display: flex;
          gap: 10px;
        }

        .btn {
          padding: 10px 20px;
          font-size: 0.9rem;
          font-weight: 600;
          border: none;
          border-radius: 12px;
          cursor: pointer;
          text-decoration: none;
          transition: all 0.3s ease;
          display: inline-flex;
          align-items: center;
          gap: 8px;
        }

        .btn:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
          background: rgb(73, 57, 113);
          color: white;
        }

        .btn-outline {
          background: white;
          color: #484d53;
          border: 2px solid #e5e7eb;
        }

        /* Route Info Stats */
        .route-stats {
          background: linear-gradient(135deg, rgb(124, 136, 224) 0%, #c3f4fc 100%);
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 15px;
        }

        .stat-box {
          text-align: center;
          color: #484d53;
        }

        .stat-box h3 {
          font-size: 1.8rem;
          font-weight: 700;
          margin-bottom: 5px;
        }

        .stat-box p {
          font-size: 0.9rem;
          font-weight: 600;
          margin: 0;
        }

        /* Map Container */
        .map-container {
          display: grid;
          grid-template-columns: 65% 35%;
          gap: 20px;
        }

        .map-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        #map {
          height: 550px;
          width: 100%;
          border-radius: 12px;
          box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Delivery Stops */
        .stops-card {
          background: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
          max-height: 590px;
          overflow-y: auto;
        }

        .stops-card h3 {
          font-size: 1.2rem;
          font-weight: 700;
          color: #484d53;
          margin-bottom: 15px;
        }

        .delivery-stop {
          background: #f6f7fb;
          border-radius: 12px;
          padding: 15px;
          margin-bottom: 12px;
          border-left: 4px solid rgb(124, 136, 224);
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .delivery-stop:hover {
          transform: translateX(5px);
          box-shadow: rgba(0, 0, 0, 0.16) 0px 1px 3px;
        }

        .delivery-stop.active {
          background: linear-gradient(to right, rgba(124, 136, 224, 0.1), rgba(195, 244, 252, 0.1));
          border-left-color: #10b981;
        }

        .stop-header {
          display: flex;
          justify-content: space-between;
          align-items: start;
          margin-bottom: 10px;
        }

        .stop-title {
          font-weight: 700;
          color: #484d53;
          font-size: 0.95rem;
        }

        .stop-badge {
          padding: 4px 10px;
          border-radius: 12px;
          font-size: 0.75rem;
          font-weight: 600;
        }

        .stop-badge.assigned { background: rgba(108, 117, 125, 0.2); color: #6c757d; }
        .stop-badge.picked_up { background: rgba(151, 231, 209, 0.3); color: #0d9488; }
        .stop-badge.in_transit { background: rgba(124, 136, 224, 0.3); color: #4f46e5; }

        .stop-info {
          font-size: 0.85rem;
          color: #6b7280;
          margin-bottom: 6px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .stop-actions {
          display: flex;
          gap: 8px;
          margin-top: 10px;
        }

        .btn-sm {
          padding: 6px 12px;
          font-size: 0.8rem;
        }

        .btn-call {
          background: #10b981;
          color: white;
        }

        .btn-navigate {
          background: #3b82f6;
          color: white;
        }

        /* Location Button */
        .location-btn {
          position: fixed;
          bottom: 30px;
          right: 30px;
          width: 60px;
          height: 60px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc);
          border: none;
          box-shadow: 0 8px 20px rgba(124, 136, 224, 0.4);
          cursor: pointer;
          transition: all 0.3s ease;
          z-index: 1000;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .location-btn:hover {
          transform: scale(1.1);
        }

        .location-btn.active {
          background: linear-gradient(135deg, #10b981, #6ee7b7);
          box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .location-btn i {
          font-size: 24px;
          color: white;
        }

        .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #9ca3af;
        }

        .empty-state i {
          font-size: 64px;
          opacity: 0.3;
          display: block;
          margin-bottom: 20px;
        }

        .empty-state h3 {
          font-size: 1.3rem;
          margin-bottom: 10px;
        }

        /* Scrollbar styling */
        .stops-card::-webkit-scrollbar {
          width: 8px;
        }

        .stops-card::-webkit-scrollbar-track {
          background: #f1f1f1;
          border-radius: 10px;
        }

        .stops-card::-webkit-scrollbar-thumb {
          background: rgb(124, 136, 224);
          border-radius: 10px;
        }

        .stops-card::-webkit-scrollbar-thumb:hover {
          background: rgb(93, 106, 184);
        }

        @media (max-width: 1500px) {
          main { grid-template-columns: 6% 94%; }
          .main-menu h1, .main-menu small { display: none; }
          .logo { display: block; }
          .nav-text { display: none; }
        }

        @media (max-width: 1310px) {
          main { grid-template-columns: 8% 92%; margin: 30px; }
          .map-container { grid-template-columns: 60% 40%; }
          .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 910px) {
          main { grid-template-columns: 10% 90%; margin: 20px; }
          .map-container { grid-template-columns: 1fr; }
        }

        @media (max-width: 700px) {
          main { grid-template-columns: 15% 85%; }
          .content { margin: 15px; padding: 15px; }
          .stats-grid { grid-template-columns: 1fr; }
          .header-actions { flex-direction: column; width: 100%; }
          .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><?php echo APP_NAME; ?></h1>
            <small>Delivery Panel</small>
            <div class="logo">
                <i class="fa fa-truck" style="font-size: 24px; color: white;"></i>
            </div>
            <ul>
                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="dashboard.php">
                        <i class="fa fa-home nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="deliveries.php">
                        <i class="fa fa-box nav-icon"></i>
                        <span class="nav-text">My Deliveries</span>
                    </a>
                </li>

                <li class="nav-item active">
                    <b></b>
                    <b></b>
                    <a href="route.php">
                        <i class="fa fa-route nav-icon"></i>
                        <span class="nav-text">Route & Map</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="profile.php">
                        <i class="fa fa-user nav-icon"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="messages.php">
                        <i class="fa fa-envelope nav-icon"></i>
                        <span class="nav-text">Messages</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="contact.php">
                        <i class="fa fa-phone nav-icon"></i>
                        <span class="nav-text">Contact</span>
                    </a>
                </li>

                <li class="nav-item">
                    <b></b>
                    <b></b>
                    <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fa fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <section class="content">
            <div class="header-section">
                <div class="header-title">
                    <h1><i class="fas fa-route"></i> Route & Live Tracking</h1>
                    <div class="tracking-status">
                        <span class="status-indicator tracking"></span>
                        <span id="tracking-status">Location tracking active</span>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="optimizeRoute()">
                        <i class="fas fa-brain"></i> Optimize Route
                    </button>
                    <button class="btn btn-outline" onclick="centerMap()">
                        <i class="fas fa-crosshairs"></i> Center Map
                    </button>
                </div>
            </div>

            <!-- Route Stats -->
            <div class="route-stats">
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3 id="total-stops"><?php echo count($active_deliveries); ?></h3>
                        <p>Total Stops</p>
                    </div>
                    <div class="stat-box">
                        <h3 id="completed-stops">0</h3>
                        <p>Completed</p>
                    </div>
                    <div class="stat-box">
                        <h3 id="total-distance">...</h3>
                        <p>Total Distance</p>
                    </div>
                    <div class="stat-box">
                        <h3 id="eta">...</h3>
                        <p>Estimated Time</p>
                    </div>
                </div>
            </div>

            <!-- Map and Stops -->
            <div class="map-container">
                <div class="map-card">
                    <div id="map"></div>
                </div>

                <div class="stops-card">
                    <h3><i class="fas fa-list"></i> Delivery Stops</h3>
                    <div id="stops-list">
                        <?php if (empty($active_deliveries)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No active deliveries</h3>
                                <p>You don't have any deliveries to route</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_deliveries as $index => $delivery): ?>
                                <div class="delivery-stop" 
                                     data-id="<?php echo $delivery['id']; ?>"
                                     data-lat="6.9271"
                                     data-lng="79.8612"
                                     onclick="focusDelivery(<?php echo $delivery['id']; ?>)">
                                    <div class="stop-header">
                                        <div class="stop-title">
                                            Stop #<?php echo $index + 1; ?> - <?php echo htmlspecialchars($delivery['order_number']); ?>
                                        </div>
                                        <span class="stop-badge <?php echo $delivery['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="stop-info">
                                        <i class="fas fa-user" style="color: #7c88e0;"></i>
                                        <span><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></span>
                                    </div>
                                    <div class="stop-info">
                                        <i class="fas fa-map-marker-alt" style="color: #ef4444;"></i>
                                        <span><?php echo htmlspecialchars($delivery['address_line_1'] . ', ' . $delivery['city']); ?></span>
                                    </div>
                                    <div class="stop-actions">
                                        <a href="tel:<?php echo htmlspecialchars($delivery['customer_phone']); ?>" class="btn btn-call btn-sm" onclick="event.stopPropagation()">
                                            <i class="fas fa-phone"></i> Call
                                        </a>
                                        <button class="btn btn-navigate btn-sm" onclick="event.stopPropagation(); navigate(<?php echo $delivery['id']; ?>)">
                                            <i class="fas fa-directions"></i> Navigate
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Live Location Button -->
    <button class="location-btn" onclick="toggleTracking()" title="Toggle Live Location">
        <i class="fas fa-location-arrow"></i>
    </button>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const navItems = document.querySelectorAll(".nav-item");

        navItems.forEach((navItem) => {
            navItem.addEventListener("click", () => {
                navItems.forEach((item) => {
                    item.classList.remove("active");
                });
                navItem.classList.add("active");
            });
        });

        let map;
        let currentLocationMarker;
        let deliveryMarkers = [];
        let watchId;
        let currentPosition = null;
        let isTracking = false;

        // Initialize map
        function initMap() {
            // Default to Colombo, Sri Lanka
            map = L.map('map').setView([6.9271, 79.8612], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Try to get user's current location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        currentPosition = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        updateCurrentLocation(currentPosition);
                        map.setView([currentPosition.lat, currentPosition.lng], 14);
                        calculateDistanceAndTime();
                    },
                    error => {
                        console.error('Error getting location:', error);
                        showNotification('warning', 'Location access denied. Please enable location services.');
                    }
                );
            }

            // Add delivery markers
            addDeliveryMarkers();
        }

        // Add markers for all deliveries
        function addDeliveryMarkers() {
            const stops = document.querySelectorAll('.delivery-stop');
            stops.forEach((stop, index) => {
                const lat = parseFloat(stop.dataset.lat) + (Math.random() - 0.5) * 0.01;
                const lng = parseFloat(stop.dataset.lng) + (Math.random() - 0.5) * 0.01;
                
                // Update stop data with random coordinates
                stop.dataset.lat = lat;
                stop.dataset.lng = lng;
                
                const marker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        html: `<div style="background: linear-gradient(135deg, rgb(124, 136, 224), #c3f4fc); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 4px 10px rgba(124, 136, 224, 0.4); font-size: 14px;">${index + 1}</div>`,
                        className: '',
                        iconSize: [32, 32]
                    })
                }).addTo(map);

                const orderNum = stop.querySelector('.stop-title').textContent;
                marker.bindPopup(`
                    <strong>Stop ${index + 1}</strong><br>
                    ${orderNum}
                `);

                deliveryMarkers.push({ marker, element: stop, lat, lng });
            });
        }

        // Update current location marker
        function updateCurrentLocation(position) {
            if (currentLocationMarker) {
                map.removeLayer(currentLocationMarker);
            }

            currentLocationMarker = L.marker([position.lat, position.lng], {
                icon: L.divIcon({
                    html: '<div style="background: #10b981; width: 18px; height: 18px; border-radius: 50%; border: 4px solid white; box-shadow: 0 0 15px rgba(16,167,69,0.6); animation: pulse 2s infinite;"></div>',
                    className: '',
                    iconSize: [18, 18]
                })
            }).addTo(map);

            currentLocationMarker.bindPopup('<strong>Your Current Location</strong>');
        }

        // Toggle tracking
        function toggleTracking() {
            if (!navigator.geolocation) {
                showNotification('error', 'Geolocation is not supported by your browser');
                return;
            }

            if (isTracking) {
                // Stop tracking
                if (watchId) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }
                isTracking = false;
                document.querySelector('.location-btn').classList.remove('active');
                document.getElementById('tracking-status').textContent = 'Location tracking stopped';
                document.querySelector('.status-indicator').classList.remove('tracking');
                document.querySelector('.status-indicator').classList.add('stopped');
                showNotification('info', 'Live tracking stopped');
            } else {
                // Start tracking
                watchId = navigator.geolocation.watchPosition(
                    position => {
                        currentPosition = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        updateCurrentLocation(currentPosition);
                        calculateDistanceAndTime();
                    },
                    error => {
                        console.error('Tracking error:', error);
                        showNotification('error', 'Failed to track location');
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 5000,
                        timeout: 10000
                    }
                );
                isTracking = true;
                document.querySelector('.location-btn').classList.add('active');
                document.getElementById('tracking-status').textContent = 'Location tracking active';
                document.querySelector('.status-indicator').classList.add('tracking');
                document.querySelector('.status-indicator').classList.remove('stopped');
                showNotification('success', 'Live tracking started');
            }
        }

        // Calculate distance and estimated time
        function calculateDistanceAndTime() {
            if (!currentPosition || deliveryMarkers.length === 0) {
                document.getElementById('total-distance').textContent = 'N/A';
                document.getElementById('eta').textContent = 'N/A';
                return;
            }

            let totalDistance = 0;
            let prevLat = currentPosition.lat;
            let prevLng = currentPosition.lng;

            deliveryMarkers.forEach(({ lat, lng }) => {
                const distance = getDistanceFromLatLonInKm(prevLat, prevLng, lat, lng);
                totalDistance += distance;
                prevLat = lat;
                prevLng = lng;
            });

            document.getElementById('total-distance').textContent = totalDistance.toFixed(1) + ' km';
            
            // Estimate time (assuming average speed of 30 km/h in city)
            const estimatedMinutes = Math.round((totalDistance / 30) * 60);
            const hours = Math.floor(estimatedMinutes / 60);
            const minutes = estimatedMinutes % 60;
            
            if (hours > 0) {
                document.getElementById('eta').textContent = `${hours}h ${minutes}m`;
            } else {
                document.getElementById('eta').textContent = `${minutes}m`;
            }
        }

        // Calculate distance between two coordinates
        function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius of the earth in km
            const dLat = deg2rad(lat2 - lat1);
            const dLon = deg2rad(lon2 - lon1);
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
                Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const d = R * c; // Distance in km
            return d;
        }

        function deg2rad(deg) {
            return deg * (Math.PI/180);
        }

        // Focus on specific delivery
        function focusDelivery(deliveryId) {
            const stop = document.querySelector(`[data-id="${deliveryId}"]`);
            const lat = parseFloat(stop.dataset.lat);
            const lng = parseFloat(stop.dataset.lng);
            
            map.setView([lat, lng], 16);
            
            // Highlight marker and stop
            deliveryMarkers.forEach(({ marker, element }) => {
                if (element.dataset.id == deliveryId) {
                    marker.openPopup();
                    element.classList.add('active');
                } else {
                    element.classList.remove('active');
                }
            });
        }

        // Navigate to delivery
        function navigate(deliveryId) {
            const stop = document.querySelector(`[data-id="${deliveryId}"]`);
            const lat = parseFloat(stop.dataset.lat);
            const lng = parseFloat(stop.dataset.lng);
            
            // Open Google Maps for navigation
            if (currentPosition) {
                const url = `https://www.google.com/maps/dir/${currentPosition.lat},${currentPosition.lng}/${lat},${lng}`;
                window.open(url, '_blank');
            } else {
                const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
                window.open(url, '_blank');
            }
            
            showNotification('success', 'Opening navigation in Google Maps');
        }

        // Center map on current location
        function centerMap() {
            if (currentPosition) {
                map.setView([currentPosition.lat, currentPosition.lng], 14);
                showNotification('info', 'Map centered on your location');
            } else {
                showNotification('warning', 'Current location not available');
            }
        }

        // Optimize route (simplified version)
        function optimizeRoute() {
            if (deliveryMarkers.length === 0) {
                showNotification('warning', 'No deliveries to optimize');
                return;
            }

            showNotification('info', 'Optimizing route based on nearest neighbor...');
            
            // Simple nearest neighbor algorithm
            if (currentPosition) {
                const stops = Array.from(document.querySelectorAll('.delivery-stop'));
                const sortedStops = [];
                let currentLat = currentPosition.lat;
                let currentLng = currentPosition.lng;
                let remainingStops = [...stops];

                while (remainingStops.length > 0) {
                    let nearestIndex = 0;
                    let nearestDistance = Infinity;

                    remainingStops.forEach((stop, index) => {
                        const lat = parseFloat(stop.dataset.lat);
                        const lng = parseFloat(stop.dataset.lng);
                        const distance = getDistanceFromLatLonInKm(currentLat, currentLng, lat, lng);

                        if (distance < nearestDistance) {
                            nearestDistance = distance;
                            nearestIndex = index;
                        }
                    });

                    const nearestStop = remainingStops[nearestIndex];
                    sortedStops.push(nearestStop);
                    currentLat = parseFloat(nearestStop.dataset.lat);
                    currentLng = parseFloat(nearestStop.dataset.lng);
                    remainingStops.splice(nearestIndex, 1);
                }

                // Reorder the stops in the UI
                const container = document.getElementById('stops-list');
                sortedStops.forEach((stop, index) => {
                    container.appendChild(stop);
                    // Update stop number
                    const title = stop.querySelector('.stop-title');
                    const orderNum = title.textContent.split(' - ')[1];
                    title.textContent = `Stop #${index + 1} - ${orderNum}`;
                });

                calculateDistanceAndTime();
                showNotification('success', 'Route optimized successfully!');
            } else {
                showNotification('warning', 'Please enable location services first');
            }
        }

        // Show notification
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                background: ${type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : type === 'error' ? '#ef4444' : '#3b82f6'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                font-weight: 600;
                font-size: 1rem;
                animation: slideIn 0.3s ease;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            notification.innerHTML = `
                <i class="fas ${icons[type]}" style="font-size: 20px;"></i>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Auto-start tracking after a short delay
            setTimeout(() => {
                if (!isTracking) {
                    toggleTracking();
                }
            }, 1000);
        });
    </script>
</body>
</html>