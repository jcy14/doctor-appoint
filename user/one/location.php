<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaflet Map - Location Display</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/location.css">
</head>
<body>
    <div class="container">
        <h1>Location Map</h1>
        
        <div class="map-controls">
            <div class="form-group">
                <span type="text" id="locationName" placeholder="Enter location name" value="New York City"></span>
           

        <div id="map"></div>
    </div>

    <script>
        // Initialize map with default location (New York City)
        let map = L.map('map').setView([40.7128, -74.0060], 13);
        let marker;
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add initial marker
        marker = L.marker([40.7128, -74.0060])
            .addTo(map)
            .bindPopup('New York City')
            .openPopup();
        
        // Function to update location
        function updateLocation() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            const name = document.getElementById('locationName').value;
            
            // Validate coordinates
            if (isNaN(lat) || isNaN(lng)) {
                alert('Please enter valid coordinates');
                return;
            }
            
            // Update map view
            map.setView([lat, lng], 13);
            
            // Remove previous marker if exists
            if (marker) {
                map.removeLayer(marker);
            }
            
            // Add new marker
            marker = L.marker([lat, lng])
                .addTo(map)
                .bindPopup(name)
                .openPopup();
            
            // Update displayed coordinates
            document.getElementById('lat').textContent = lat;
            document.getElementById('lng').textContent = lng;
        }
        
        // Optional: Get user's current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;
                        document.getElementById('locationName').value = 'My Location';
                        
                        updateLocation();
                    },
                    (error) => {
                        console.error('Geolocation error:', error);
                        alert('Unable to retrieve your location');
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }
        
        // Add click event to get location from map click
        map.on('click', function(e) {
            document.getElementById('latitude').value = e.latlng.lat;
            document.getElementById('longitude').value = e.latlng.lng;
            document.getElementById('locationName').value = 'Clicked Location';
            updateLocation();
        });
    </script>
</body>
</html>