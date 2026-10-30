<?php
$pageTitle = "Manage Exam Locations - Smart Campus Connect";
$includeLeaflet = true;
include 'includes/config.php';
redirectIfNotLoggedIn();

$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$error = '';
$success = sanitizeInput($_GET['success'] ?? '');
$form = [
    'id' => '',
    'course' => '',
    'venue' => '',
    'date' => '',
    'time' => '',
    'latitude' => '',
    'longitude' => '',
    'description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form token. Please try again.';
    } elseif ($action === 'delete') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);

        if ($exam_id <= 0) {
            $error = 'Invalid exam selected.';
        } else {
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $exam_id);

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: manage_exams.php?success=' . urlencode('Exam deleted successfully.'));
                exit();
            }

            $error = 'Failed to delete exam.';
            $stmt->close();
        }
    } elseif ($action === 'save') {
        $form = [
            'id' => (int)($_POST['exam_id'] ?? 0),
            'course' => trim($_POST['course'] ?? ''),
            'venue' => trim($_POST['venue'] ?? ''),
            'date' => trim($_POST['date'] ?? ''),
            'time' => trim($_POST['time'] ?? ''),
            'latitude' => trim($_POST['latitude'] ?? ''),
            'longitude' => trim($_POST['longitude'] ?? ''),
            'description' => trim($_POST['description'] ?? '')
        ];

        $latitude = is_numeric($form['latitude']) ? (float)$form['latitude'] : null;
        $longitude = is_numeric($form['longitude']) ? (float)$form['longitude'] : null;

        if ($form['course'] === '' || $form['venue'] === '' || $form['date'] === '' || $form['time'] === '') {
            $error = 'Course, venue, date, and time are required.';
        } elseif ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $error = 'Set a valid map location for this exam.';
        } elseif (!strtotime($form['date']) || !strtotime($form['time'])) {
            $error = 'Enter a valid exam date and time.';
        } else {
            if ($form['id'] > 0) {
                $stmt = $conn->prepare("
                    UPDATE exams
                    SET course = ?, venue = ?, latitude = ?, longitude = ?, date = ?, time = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "ssddsssi",
                    $form['course'],
                    $form['venue'],
                    $latitude,
                    $longitude,
                    $form['date'],
                    $form['time'],
                    $form['description'],
                    $form['id']
                );
                $success_message = 'Exam updated successfully.';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO exams (course, venue, latitude, longitude, date, time, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssddsss",
                    $form['course'],
                    $form['venue'],
                    $latitude,
                    $longitude,
                    $form['date'],
                    $form['time'],
                    $form['description']
                );
                $success_message = 'Exam added successfully.';
            }

            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header('Location: manage_exams.php?success=' . urlencode($success_message));
                exit();
            }

            $error = 'Failed to save exam: ' . $stmt->error;
            $stmt->close();
        }
    }
}

$editing_id = (int)($_GET['edit'] ?? 0);
if ($editing_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
    $stmt->bind_param("i", $editing_id);
    $stmt->execute();
    $editing_exam = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($editing_exam) {
        $form = [
            'id' => $editing_exam['id'],
            'course' => $editing_exam['course'],
            'venue' => $editing_exam['venue'],
            'date' => $editing_exam['date'],
            'time' => $editing_exam['time'],
            'latitude' => $editing_exam['latitude'],
            'longitude' => $editing_exam['longitude'],
            'description' => $editing_exam['description'] ?? ''
        ];
    }
}

$result = $conn->query("SELECT * FROM exams ORDER BY date, time, course");
$exams = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

$mapExams = array_map(function($exam) {
    return [
        'id' => (int)$exam['id'],
        'course' => $exam['course'],
        'venue' => $exam['venue'],
        'date' => $exam['date'],
        'time' => $exam['time'],
        'latitude' => $exam['latitude'] !== null ? (float)$exam['latitude'] : null,
        'longitude' => $exam['longitude'] !== null ? (float)$exam['longitude'] : null
    ];
}, $exams);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2>Manage Exam Locations</h2>
            <p class="text-muted">Add exam schedules and set each venue point on the map.</p>
        </div>
        <a href="exam.php" class="btn btn-outline-primary">
            <i class="fas fa-map-location-dot"></i> View Locator
        </a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo e($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo e($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo (int)$form['id'] > 0 ? 'Edit Exam' : 'Add Exam'; ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" id="examLocationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="exam_id" value="<?php echo (int)$form['id']; ?>">

                    <div class="mb-3">
                        <label for="course" class="form-label">Course <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="course" name="course" required value="<?php echo e($form['course']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="venue" class="form-label">Venue <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="venue" name="venue" required value="<?php echo e($form['venue']); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" required value="<?php echo e($form['date']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="time" class="form-label">Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="time" name="time" required value="<?php echo e(substr((string)$form['time'], 0, 5)); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="latitude" class="form-label">Latitude <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="latitude" name="latitude" step="0.0000001" min="-90" max="90" required value="<?php echo e($form['latitude']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="longitude" class="form-label">Longitude <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="longitude" name="longitude" step="0.0000001" min="-180" max="180" required value="<?php echo e($form['longitude']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($form['description']); ?></textarea>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?php if ((int)$form['id'] > 0): ?>
                            <a href="manage_exams.php" class="btn btn-outline-secondary">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary" id="useCurrentLocationBtn">
                            <i class="fas fa-location-crosshairs"></i> Use My Location
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Exam
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Saved Exams</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($exams)): ?>
                    <div class="p-3 text-muted">No exams have been added yet.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush exam-admin-list">
                        <?php foreach ($exams as $exam): ?>
                            <?php $hasCoordinates = $exam['latitude'] !== null && $exam['longitude'] !== null && $exam['latitude'] !== '' && $exam['longitude'] !== ''; ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <h6 class="mb-1"><?php echo e($exam['course']); ?></h6>
                                        <p class="mb-1 small"><i class="fas fa-building"></i> <?php echo e($exam['venue']); ?></p>
                                        <p class="mb-0 small text-muted"><?php echo date('M d, Y', strtotime($exam['date'])); ?> at <?php echo e(substr((string)$exam['time'], 0, 5)); ?></p>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary focus-exam-btn" data-exam-id="<?php echo (int)$exam['id']; ?>" <?php echo $hasCoordinates ? '' : 'disabled'; ?>>
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        <a href="manage_exams.php?edit=<?php echo (int)$exam['id']; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this exam?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Location Picker</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearLocationBtn">
                    <i class="fas fa-xmark"></i> Clear
                </button>
            </div>
            <div class="card-body">
                <div id="examLocationMap" class="location-picker-map"></div>
            </div>
        </div>
    </div>
</div>

<script>
const managedExams = <?php echo json_encode($mapExams, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const selectedExam = {
    latitude: <?php echo is_numeric($form['latitude']) ? (float)$form['latitude'] : 'null'; ?>,
    longitude: <?php echo is_numeric($form['longitude']) ? (float)$form['longitude'] : 'null'; ?>
};
const campusCenter = [-17.3650, 30.2000];
const managedMarkers = {};
let locationMap;
let selectedMarker;

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

function coordinateIsValid(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

function setCoordinateInputs(lat, lng) {
    $('#latitude').val(Number(lat).toFixed(7));
    $('#longitude').val(Number(lng).toFixed(7));
}

function setSelectedLocation(lat, lng, moveMap = true) {
    lat = Number(lat);
    lng = Number(lng);

    if (!coordinateIsValid(lat, lng)) {
        return;
    }

    setCoordinateInputs(lat, lng);

    if (selectedMarker) {
        selectedMarker.setLatLng([lat, lng]);
    } else {
        selectedMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'exam-selected-marker',
                html: '<i class="fas fa-location-dot"></i>',
                iconSize: [34, 34],
                iconAnchor: [17, 33],
                popupAnchor: [0, -30]
            }),
            draggable: true
        }).addTo(locationMap);

        selectedMarker.on('dragend', function(event) {
            const position = event.target.getLatLng();
            setSelectedLocation(position.lat, position.lng, false);
        });
    }

    selectedMarker.bindPopup('Selected exam location');

    if (moveMap) {
        locationMap.setView([lat, lng], 17);
        selectedMarker.openPopup();
    }
}

function clearSelectedLocation() {
    $('#latitude').val('');
    $('#longitude').val('');

    if (selectedMarker) {
        locationMap.removeLayer(selectedMarker);
        selectedMarker = null;
    }
}

function initializeLocationPicker() {
    const mapElement = document.getElementById('examLocationMap');

    if (typeof L === 'undefined') {
        mapElement.innerHTML = '<div class="map-empty-state"><div><i class="fas fa-map-marked-alt"></i><p>Map library could not load. Check your internet connection and refresh.</p></div></div>';
        return;
    }

    locationMap = L.map('examLocationMap').setView(campusCenter, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(locationMap);

    const existingIcon = L.divIcon({
        className: 'exam-existing-marker',
        html: '<i class="fas fa-graduation-cap"></i>',
        iconSize: [30, 30],
        iconAnchor: [15, 29],
        popupAnchor: [0, -26]
    });

    const bounds = [];

    managedExams.forEach(function(exam) {
        const lat = Number(exam.latitude);
        const lng = Number(exam.longitude);

        if (!coordinateIsValid(lat, lng)) {
            return;
        }

        const marker = L.marker([lat, lng], {
            icon: existingIcon,
            title: exam.venue
        }).addTo(locationMap);

        marker.bindPopup(`
            <div class="map-info-window">
                <h6>${escapeHtml(exam.venue)}</h6>
                <p><strong>Course:</strong> ${escapeHtml(exam.course)}</p>
                <p><strong>Date:</strong> ${escapeHtml(exam.date)}</p>
                <p><strong>Time:</strong> ${escapeHtml(exam.time)}</p>
            </div>
        `);

        managedMarkers[exam.id] = marker;
        bounds.push([lat, lng]);
    });

    locationMap.on('click', function(event) {
        setSelectedLocation(event.latlng.lat, event.latlng.lng, false);
    });

    if (coordinateIsValid(Number(selectedExam.latitude), Number(selectedExam.longitude))) {
        setSelectedLocation(selectedExam.latitude, selectedExam.longitude, true);
    } else if (bounds.length > 1) {
        locationMap.fitBounds(bounds, {
            padding: [30, 30]
        });
    } else if (bounds.length === 1) {
        locationMap.setView(bounds[0], 17);
    }
}

$(document).ready(function() {
    initializeLocationPicker();

    $('.focus-exam-btn').on('click', function() {
        const marker = managedMarkers[$(this).data('exam-id')];

        if (marker) {
            locationMap.setView(marker.getLatLng(), 17);
            marker.openPopup();
        }
    });

    $('#latitude, #longitude').on('change', function() {
        setSelectedLocation($('#latitude').val(), $('#longitude').val());
    });

    $('#clearLocationBtn').on('click', clearSelectedLocation);

    $('#useCurrentLocationBtn').on('click', function() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by this browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(function(position) {
            setSelectedLocation(position.coords.latitude, position.coords.longitude);
        }, function() {
            alert('Could not access your current location.');
        }, {
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 60000
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
