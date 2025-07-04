jQuery(document).ready(function ($) {
    let map;
    let allMarkers = [];
    let currentInfoWindow = null;
    let countryFlags = {};

    const $searchBar = $('.search-bar');
    const $flagsContainer = $('.flags-container');
    const $loader = $('#loader');

    initMap();
    setupEventListeners();

    function setupEventListeners() {
        $('.search-button, .search-bar').on('click keypress', function (e) {
            if (e.type === 'click' || (e.type === 'keypress' && e.which === 13)) {
                e.preventDefault();
                triggerSearch();
            }
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#map-canvas').length && currentInfoWindow) {
                currentInfoWindow.close();
                currentInfoWindow = null;
            }
        });
    }

    function initMap() {
        map = new google.maps.Map(document.getElementById('map-canvas'), {
            center: { lat: 30.0, lng: 50.0 },
            zoom: 4,
        });
    }

    function triggerSearch() {
        const searchTerm = $searchBar.val().trim();
        $loader.show();

        $.post(ajax_params.ajax_url, {
            action: 'filter_markers',
            search: searchTerm
        }).done(function (response) {
            $loader.hide();
            clearMarkers();
            $flagsContainer.empty();
            countryFlags = {};

            response.forEach(createMarker);
            renderCountryFlags();
            fitMapToMarkers();
        }).fail(function (xhr, status, error) {
            $loader.hide();
            console.error("AJAX Error:", status, error);
        });
    }

    function clearMarkers() {
        allMarkers.forEach(({ marker }) => marker.setMap(null));
        allMarkers = [];
    }

    function createMarker(data) {
        const marker = new google.maps.Marker({
            position: { lat: data.lat, lng: data.lng },
            map,
            title: data.country,
            icon: {
                url: "/wp-content/uploads/2025/05/map-pin.svg",
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div style="padding:10px;min-width:200px;" class="maptooltip">
                    <h3>${data.country} - (${data.count} <span>Hotel</span>${data.count === 1 ? '' : 's'})</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <ul style="margin-top: 10px; padding-left: 16px;">
                            ${data.posts.map(post =>
                `<li style="list-style: disc; color:#fff;"><a href="${post.link}" target="_blank" style="color: #fff; text-decoration: none;">${post.title}</a></li>`
            ).join('')}
                        </ul>
                    </div>
                </div>
            `
        });

        marker.addListener('click', () => {
            map.panTo(marker.getPosition());
            if (currentInfoWindow) currentInfoWindow.close();
            infoWindow.open(map, marker);
            currentInfoWindow = infoWindow;
        });

        if (!countryFlags[data.country]) {
            countryFlags[data.country] = { code: data.code, count: data.count };
        }

        allMarkers.push({ marker, infoWindow, country: data.country, code: data.code });
        updateJobCount();
    }

    const countryCodeMap = {};

    /**
     * Fetch country names and ISO alpha-2 codes from REST Countries API,
     * normalize them, and merge into the existing countryCodeMap.
     */
    function populateCountryCodeMap() {
        const apiUrl = 'https://restcountries.com/v3.1/all?fields=name,cca2';

        fetch(apiUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to fetch: HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(countryDataList => {
                countryDataList.forEach(country => {
                    const rawName = country.name.common.toLowerCase();
                    const isoCode = country.cca2.toLowerCase();

                    // Normalize common naming differences
                    let normalizedKey = rawName;
                    if (rawName === 'united states') normalizedKey = 'usa';
                    if (rawName === 'united arab emirates') normalizedKey = 'uae';

                    // Add to map only if it doesn't already exist
                    if (!countryCodeMap[normalizedKey]) {
                        countryCodeMap[normalizedKey] = isoCode;
                    }
                });

                console.log('✅ Country code map populated:', countryCodeMap);
            })
            .catch(error => {
                console.error('❌ Error fetching country data:', error);
            });
    }

    // Call the function to populate the map
    populateCountryCodeMap();

    function renderCountryFlags() {

        allMarkers.forEach(({ country, code, marker, infoWindow }) => {
            code = countryCodeMap[country.toLowerCase()];
            // if (!code) return; // Skip if no code found
            const $flag = $('<div class="country-flag">').append(
                $('<img>').attr('src', `https://flagcdn.com/48x36/${code}.png`)
            );

            $flag.on('click', async function () {
                $('.country-flag').removeClass('active');
                $(this).addClass('active');

                if (map.getZoom() > 5) {
                    map.setZoom(4);
                    await delay(300);
                }

                map.panTo(marker.getPosition());
                setTimeout(() => {
                    map.setZoom(6);
                    if (currentInfoWindow) currentInfoWindow.close();
                    infoWindow.open(map, marker);
                    currentInfoWindow = infoWindow;
                }, 600);
            });

            $flagsContainer.append($flag);
        });
    }

    function fitMapToMarkers() {
        if (allMarkers.length === 1) {
            map.setCenter(allMarkers[0].marker.getPosition());
            map.setZoom(6);
        } else if (allMarkers.length > 1) {
            const bounds = new google.maps.LatLngBounds();
            allMarkers.forEach(item => bounds.extend(item.marker.getPosition()));
            map.fitBounds(bounds);
        }
    }

    function delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function updateJobCount() {
        const container = document.querySelector('#jobs-container');
        const output = document.querySelector('.hotelResult__num');

        if (container && output) {
            const count = container.querySelectorAll('.portCard').length;
            output.textContent = count < 10 ? `0${count}` : `${count}`;
        }
    }
});

// JOB SEARCH
jQuery(function ($) {
    const $searchBar = $('.search-bar');
    const $loader = $('#loader');

    function performSearch() {
        const searchQuery = $searchBar.val().trim();
        $loader.show();

        $.post(ajax_params.ajax_url, {
            action: 'filter_jobs',
            search: searchQuery
        }).done(function (response) {
            $('#jobs-container').html(response);
            if (response.indexOf('<p class="notFounMsg"><img src="/wp-content/uploads/2025/06/icons8-not-found-50.png">No Hotel Found.</p>') !== -1) {
                $('#jobs-container').addClass('notFoundCon');
            } else {
                $('#jobs-container').removeClass('notFoundCon');
            }
            $loader.hide();
            updateJobCount();
        }).fail(function () {
            $loader.hide();
            alert('Something went wrong. Please try again.');
        });
    }

    $('.search-button, .search-bar').on('click keypress', function (e) {
        if (e.type === 'click' || (e.type === 'keypress' && e.which === 13)) {
            e.preventDefault();
            performSearch();
        }
    });

    function updateJobCount() {
        const container = document.querySelector('#jobs-container');
        const output = document.querySelector('.hotelResult__num');

        if (container && output) {
            const count = container.querySelectorAll('.portCard').length;
            output.textContent = count < 10 ? `0${count}` : `${count}`;
        }
    }

    function initSingleMap() {
        const map = new google.maps.Map(document.getElementById('single-map-canvas'), {
            center: new google.maps.LatLng(20.0, 10.0),
            zoom: 3,
        });

        const markers = careers_map_vars.markers || [];
        const infoWindows = []; // Store all info windows

        // Create markers for all locations
        markers.forEach(markerData => {
            const marker = new google.maps.Marker({
                position: new google.maps.LatLng(markerData.lat, markerData.lng),
                map: map,
                title: markerData.title,
                icon: {
                    url: "/wp-content/uploads/2025/05/map-pin.svg",
                    scaledSize: new google.maps.Size(30, 40) // Adjust size as needed
                },
                zIndex: markerData.isCurrent ? 2000 : 1000,
            });

            // Create info window content
            const infoContent = `
                <div class="map-info-window">
                    <h3>${markerData.title}</h3>
                    <p>${markerData.country}</p>
                    <a href="${markerData.isCurrent ? '#' : get_permalink(markerData.post_id)}">
                        View ${markerData.isCurrent ? 'current' : 'details'}
                    </a>
                </div>
            `;

            const infoWindow = new google.maps.InfoWindow({
                content: infoContent
            });

            // Add click event to show info window
            marker.addListener('click', () => {
                // Close all other info windows first
                infoWindows.forEach(window => window.close());
                infoWindow.open(map, marker);
            });

            infoWindows.push(infoWindow);
        });

        // Center map on current post's first marker if available
        const currentPostMarkers = markers.filter(marker => marker.isCurrent);
        if (currentPostMarkers.length > 0) {
            map.setCenter(new google.maps.LatLng(
                currentPostMarkers[0].lat,
                currentPostMarkers[0].lng
            ));
            map.setZoom(4);
        }
    }

    initSingleMap();
});


const scrollContainer = document.querySelector('#jobs-container');
const scrollUp = document.querySelector('.scroll-up');
const scrollDown = document.querySelector('.scroll-down');

if (scrollUp) {
    scrollUp.addEventListener('click', () => {
        scrollContainer.scrollBy({ top: -50, behavior: 'smooth' });
    });
}


if (scrollDown) {
    scrollDown.addEventListener('click', () => {
        scrollContainer.scrollBy({ top: 50, behavior: 'smooth' });
    });
}

window.addEventListener('load', function () {
    const searchBtn = document.querySelector('.search-button');
    if (searchBtn) {
        searchBtn.click();
    }
});
