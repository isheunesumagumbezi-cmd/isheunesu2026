// Custom JavaScript for Smart Campus Connect

$(document).ready(function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Confirm delete actions
    $('.delete-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });

    // Image preview for file uploads
    $('.image-upload').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#image-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Search functionality
    $('#search-input').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('.searchable-item').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Load more functionality
    $('.load-more-btn').on('click', function() {
        const btn = $(this);
        const container = btn.data('container');
        const offset = parseInt(btn.data('offset')) || 0;
        const limit = parseInt(btn.data('limit')) || 10;

        $.ajax({
            url: 'api/load_more.php',
            method: 'GET',
            data: {
                container: container,
                offset: offset,
                limit: limit
            },
            success: function(response) {
                $(`#${container}`).append(response);
                btn.data('offset', offset + limit);
            },
            error: function() {
                alert('Error loading more items. Please try again.');
            }
        });
    });

    // Real-time notifications
    function checkNotifications() {
        $.ajax({
            url: 'api/check_notifications.php',
            method: 'GET',
            success: function(response) {
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.unread > 0) {
                        $('#notification-badge').text(data.unread).show();
                    } else {
                        $('#notification-badge').hide();
                    }
                } catch (error) {
                    $('#notification-badge').hide();
                }
            },
            error: function() {
                $('#notification-badge').hide();
            }
        });
    }

    if (window.currentUserId) {
        checkNotifications();
        setInterval(checkNotifications, 30000); // Check every 30 seconds
    }

    // Chat functionality
    $('.send-message-btn').on('click', function() {
        const message = $('#message-input').val().trim();
        const receiverId = $(this).data('receiver-id');

        if (message) {
            $.ajax({
                url: 'api/send_message.php',
                method: 'POST',
                data: {
                    message: message,
                    receiver_id: receiverId,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        $('#message-input').val('');
                        loadMessages(receiverId);
                    } else {
                        alert('Error sending message: ' + data.message);
                    }
                },
                error: function() {
                    alert('Error sending message. Please try again.');
                }
            });
        }
    });

    function loadMessages(receiverId) {
        $.ajax({
            url: 'api/get_messages.php',
            method: 'GET',
            data: { receiver_id: receiverId },
            success: function(response) {
                $('#chat-messages').html(response);
                $('#chat-messages').scrollTop($('#chat-messages')[0].scrollHeight);
            }
        });
    }

    // Initialize maps for exam locator
    if (!window.examLocatorUsesLeaflet && typeof google !== 'undefined' && $('#map').length) {
        initMap();
    }
});

function initMap() {
    const mapOptions = {
        center: { lat: -17.3650, lng: 30.2000 }, // Chinhoyi coordinates
        zoom: 15
    };
    const map = new google.maps.Map(document.getElementById('map'), mapOptions);

    // Add markers for exam venues
    if (typeof examVenues !== 'undefined') {
        examVenues.forEach(function(venue) {
            const marker = new google.maps.Marker({
                position: { lat: parseFloat(venue.latitude), lng: parseFloat(venue.longitude) },
                map: map,
                title: venue.venue
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `<h5>${venue.venue}</h5><p>${venue.course}</p><p>${venue.date} at ${venue.time}</p>`
            });

            marker.addListener('click', function() {
                infoWindow.open(map, marker);
            });
        });
    }

    // Get user's location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            const userLocation = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            const userMarker = new google.maps.Marker({
                position: userLocation,
                map: map,
                title: 'Your Location',
                icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png'
            });

            map.setCenter(userLocation);
        });
    }
}

// Utility functions
function isLoggedIn() {
    return typeof userId !== 'undefined' && userId !== null;
}

function showAlert(message, type = 'info') {
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('.container').prepend(alertHtml);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Accommodation booking with price negotiation
function negotiatePrice(accommodationId, proposedPrice) {
    $.ajax({
        url: 'api/negotiate_price.php',
        method: 'POST',
        data: {
            accommodation_id: accommodationId,
            proposed_price: proposedPrice,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                showAlert('Price negotiation request sent!', 'success');
            } else {
                showAlert('Error: ' + data.message, 'danger');
            }
        },
        error: function() {
            showAlert('Error negotiating price. Please try again.', 'danger');
        }
    });
}
