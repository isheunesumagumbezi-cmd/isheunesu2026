<?php
$pageTitle = "Exam Venue Locator - Smart Campus Connect";
$includeLeaflet = true;
include 'includes/config.php';
include 'includes/header.php';

$conn = getDBConnection();

// Get filter parameters
$course = isset($_GET['course']) ? sanitizeInput($_GET['course']) : '';
$date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';

// Build query
$query = "SELECT * FROM exams WHERE 1=1";

$params = [];
$types = '';

if (!empty($course)) {
    $query .= " AND course LIKE ?";
    $params[] = "%$course%";
    $types .= 's';
}

if (!empty($date)) {
    $query .= " AND date = ?";
    $params[] = $date;
    $types .= 's';
}

$query .= " ORDER BY date, time";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$exams = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Prepare data for JavaScript
$examVenues = array_map(function($exam) {
    return [
        'venue' => $exam['venue'],
        'course' => $exam['course'],
        'date' => $exam['date'],
        'time' => $exam['time'],
        'latitude' => $exam['latitude'] !== null ? (float)$exam['latitude'] : null,
        'longitude' => $exam['longitude'] !== null ? (float)$exam['longitude'] : null
    ];
}, $exams);
?>

<div class="page-hero mb-4 exam-hero">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="page-kicker"><i class="fas fa-map-location-dot me-2"></i> campus navigation</span>
            <h1 class="display-6 fw-bold mb-3">Find your exam venue and get there confidently.</h1>
            <p class="text-white-50 mb-0">Use your live location, view nearby venues, and navigate faster on the day of your exam.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <div class="hero-badges">
                <span><i class="fas fa-location-crosshairs me-2"></i> GPS enabled</span>
                <span><i class="fas fa-route me-2"></i> Directions</span>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card search-shell">
            <div class="card-body">
                <form method="GET" action="" class="search-form">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="course" class="form-label">Course</label>
                            <div class="input-wrap">
                                <i class="fas fa-book"></i>
                                <input type="text" class="form-control" id="course" name="course" value="<?php echo e($course); ?>" placeholder="Enter course name">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label for="date" class="form-label">Exam Date</label>
                            <div class="input-wrap">
                                <i class="fas fa-calendar-alt"></i>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo e($date); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100 search-button">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Map and Exam List -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Exam Venues Map</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="locateMeBtn">
                    <i class="fas fa-location-crosshairs"></i> Locate Me
                </button>
            </div>
            <div class="card-body">
                <div id="map" class="map-container"></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Exam Schedule</h5>
            </div>
            <div class="card-body">
                <?php if (empty($exams)): ?>
                    <p class="text-muted">No exams found matching your criteria.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($exams as $index => $exam): ?>
                            <?php
                                $hasCoordinates = $exam['latitude'] !== null && $exam['longitude'] !== null && $exam['latitude'] !== '' && $exam['longitude'] !== '';
                            ?>
                            <div class="list-group-item exam-card mb-2">
                                <h6><?php echo htmlspecialchars($exam['course']); ?></h6>
                                <p class="mb-1"><strong>Venue:</strong> <?php echo htmlspecialchars($exam['venue']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y', strtotime($exam['date'])); ?></p>
                                <p class="mb-1"><strong>Time:</strong> <?php echo date('H:i', strtotime($exam['time'])); ?></p>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button class="btn btn-sm btn-outline-primary show-on-map-btn"
                                            data-index="<?php echo $index; ?>"
                                            <?php echo $hasCoordinates ? '' : 'disabled'; ?>>
                                        <i class="fas fa-map-marker-alt"></i> Show on Map
                                    </button>
                                    <button class="btn btn-sm btn-primary get-directions-btn"
                                            data-lat="<?php echo e($exam['latitude']); ?>"
                                            data-lng="<?php echo e($exam['longitude']); ?>"
                                            data-venue="<?php echo e($exam['venue']); ?>"
                                            <?php echo $hasCoordinates ? '' : 'disabled'; ?>>
                                        <i class="fas fa-route"></i> Directions
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.examLocatorUsesLeaflet = true;

const examVenues = <?php echo json_encode($examVenues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const campusCenter = [-17.3650, 30.2000];
const venueMarkers = {};
let examMap;
let userMarker;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(character) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character];
    });
}

function hasVenueCoordinates(venue) {
    return Number.isFinite(Number(venue.latitude)) && Number.isFinite(Number(venue.longitude));
}

function directionsUrl(lat, lng, origin) {
    const destination = `${lat},${lng}`;
    const originQuery = origin ? `&origin=${origin.lat},${origin.lng}` : '';
    return `https://www.google.com/maps/dir/?api=1${originQuery}&destination=${destination}`;
}

function openDirections(lat, lng) {
    const destination = {
        lat: Number(lat),
        lng: Number(lng)
    };

    if (!Number.isFinite(destination.lat) || !Number.isFinite(destination.lng)) {
        alert('This venue does not have map coordinates yet.');
        return;
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            window.open(directionsUrl(destination.lat, destination.lng, {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            }), '_blank', 'noopener');
        }, function() {
            window.open(directionsUrl(destination.lat, destination.lng), '_blank', 'noopener');
        }, {
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 60000
        });
    } else {
        window.open(directionsUrl(destination.lat, destination.lng), '_blank', 'noopener');
    }
}

function initializeExamMap() {
    const mapElement = document.getElementById('map');

    if (typeof L === 'undefined') {
        mapElement.innerHTML = '<div class="map-empty-state"><div><i class="fas fa-map-marked-alt"></i><p>Map library could not load. Check your internet connection and refresh.</p></div></div>';
        return;
    }

    examMap = L.map('map', {
        scrollWheelZoom: true
    }).setView(campusCenter, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(examMap);

    const examIcon = L.divIcon({
        className: 'exam-map-marker',
        html: '<i class="fas fa-location-dot"></i>',
        iconSize: [32, 32],
        iconAnchor: [16, 31],
        popupAnchor: [0, -28]
    });

    const bounds = [];

    examVenues.forEach(function(venue, index) {
        if (!hasVenueCoordinates(venue)) {
            return;
        }

        const lat = Number(venue.latitude);
        const lng = Number(venue.longitude);
        const marker = L.marker([lat, lng], {
            icon: examIcon,
            title: venue.venue
        }).addTo(examMap);

        marker.bindPopup(`
            <div class="map-info-window">
                <h6>${escapeHtml(venue.venue)}</h6>
                <p><strong>Course:</strong> ${escapeHtml(venue.course)}</p>
                <p><strong>Date:</strong> ${escapeHtml(venue.date)}</p>
                <p><strong>Time:</strong> ${escapeHtml(venue.time)}</p>
                <button type="button" class="btn btn-sm btn-primary mt-2" onclick="openDirections(${lat}, ${lng})">
                    <i class="fas fa-route"></i> Directions
                </button>
            </div>
        `);

        venueMarkers[index] = marker;
        bounds.push([lat, lng]);
    });

    if (bounds.length > 1) {
        examMap.fitBounds(bounds, {
            padding: [30, 30]
        });
    } else if (bounds.length === 1) {
        examMap.setView(bounds[0], 17);
        venueMarkers[Object.keys(venueMarkers)[0]].openPopup();
    }

    if (bounds.length === 0) {
        L.popup()
            .setLatLng(campusCenter)
            .setContent('<div class="map-info-window"><h6>No venue coordinates</h6><p>Add latitude and longitude to exam records to place them on the map.</p></div>')
            .openOn(examMap);
    }
}

function locateUser() {
    if (!examMap) {
        return;
    }

    if (!navigator.geolocation) {
        alert('Geolocation is not supported by this browser.');
        return;
    }

    const locateBtn = document.getElementById('locateMeBtn');
    const originalHtml = locateBtn ? locateBtn.innerHTML : '';
    if (locateBtn) {
        locateBtn.disabled = true;
        locateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';
    }

    navigator.geolocation.getCurrentPosition(function(position) {
        const location = [position.coords.latitude, position.coords.longitude];

        if (userMarker) {
            userMarker.setLatLng(location);
        } else {
            userMarker = L.circleMarker(location, {
                radius: 8,
                color: '#4bb0ff',
                fillColor: '#73d7ff',
                fillOpacity: 0.9,
                weight: 3
            }).addTo(examMap).bindPopup('You are here');
        }

        examMap.setView(location, 16);
        userMarker.openPopup();

        if (locateBtn) {
            locateBtn.disabled = false;
            locateBtn.innerHTML = originalHtml;
        }
    }, function(error) {
        if (locateBtn) {
            locateBtn.disabled = false;
            locateBtn.innerHTML = originalHtml;
        }

        const messages = {
            1: 'Location permission was denied. Please enable location access to use this feature.',
            2: 'Your location is currently unavailable. Please try again in a moment.',
            3: 'Location lookup timed out. Please try again.'
        };

        alert(messages[error.code] || 'Could not access your current location.');
    }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 30000
    });
}

$(document).ready(function() {
    initializeExamMap();

    $('.show-on-map-btn').on('click', function() {
        const index = $(this).data('index');
        const marker = venueMarkers[index];

        if (marker) {
            examMap.setView(marker.getLatLng(), 17);
            marker.openPopup();
        }
    });

    $('.get-directions-btn').on('click', function() {
        openDirections($(this).data('lat'), $(this).data('lng'));
    });

    $('#locateMeBtn').on('click', locateUser);
});
</script>

<?php include 'includes/footer.php'; ?>
