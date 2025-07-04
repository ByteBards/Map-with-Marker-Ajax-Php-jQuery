<?php 
function display_google_map() {
    ob_start();
    ?>
    <div class="mapWrapper">
        <span class="hotelResult"> <span class="hotelResult__num"></span> Results</span>
        <div class="map-container">
            <div class="jobs-list">
                <div id="jobs-container"></div>
                <div class="scroll-arrows">
                    <a class="scroll-up"><img src="/wp-content/uploads/2025/05/arrowup.svg" alt=""></a>
                    <a class="scroll-down"><img src="/wp-content/uploads/2025/05/arrowbottom.svg" alt=""></a>
                </div>
            </div>
            <div class="mapcontanermain">
                <div class="search-container">
                    <img class="search-button" src="/wp-content/uploads/2025/05/Search.svg" alt="">
                    <input type="text" class="search-bar" placeholder="Search" />
                </div>
                <div id="map-canvas"></div>
                <div id="country-flags-container">
                    <div class="flags-container"></div>
                </div>
            </div>
            <div id="loader" class="loader">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150">
                    <path fill="none" stroke="#FFFFFF" stroke-width="15" stroke-linecap="round" stroke-dasharray="300 385" stroke-dashoffset="0" d="M275 75c0 31-27 50-50 50-58 0-92-100-150-100-28 0-50 22-50 50s23 50 50 50c58 0 92-100 150-100 24 0 50 19 50 50Z">
                        <animate attributeName="stroke-dashoffset" calcMode="spline" dur="2" values="685;-685" keySplines="0 0 1 1" repeatCount="indefinite"></animate>
                    </path>
                </svg>
            </div>
        </div>
    </div>


    <?php
    return ob_get_clean();
}
add_shortcode('google_map', 'display_google_map');



add_action('wp_ajax_filter_markers', 'filter_portfolio_markers');
add_action('wp_ajax_nopriv_filter_markers', 'filter_portfolio_markers');

function filter_portfolio_markers() {
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

    // Include ISO country code mappings
    $country_iso_codes = array(
        'ireland' => 'ie',
        'usa' => 'us',
        'united-states' => 'us',
        'germany' => 'de',
        'united-kingdom' => 'gb',
        'france' => 'fr',
        'italy' => 'it',
        'spain' => 'es',
        'canada' => 'ca',
        'japan' => 'jp',
        'china' => 'cn',
        'india' => 'in',
        'australia' => 'au',
        'uae' => 'ae',
        'netherlands' => 'nl',
        'switzerland' => 'ch',
        'brazil' => 'br',
        'russia' => 'ru',
        'turkey' => 'tr',
        'mexico' => 'mx',
        'south-korea' => 'kr',
        'saudi-arabia' => 'sa',
        'egypt' => 'eg',
        'singapore' => 'sg',
        'sweden' => 'se',
    );

    $query_args = array(
        'post_type' => 'portfolio',
        'posts_per_page' => -1,
    );

    if (!empty($search)) {
        $query_args['s'] = $search;
    }

    $query = new WP_Query($query_args);
    $country_data = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $title = get_the_title();
            $link = get_permalink();
            $longitude = get_field('port_longitude');
            $latitude = get_field('port_latitude');
            $terms = get_the_terms(get_the_ID(), 'portfolio_countries');

            if (!empty($terms)) {
                $country = $terms[0]->name;
                $slug = strtolower($terms[0]->slug);
                $iso_code = isset($country_iso_codes[$slug]) ? $country_iso_codes[$slug] : 'xx';

                if ($longitude && $latitude && $country) {
                    if (!isset($country_data[$country])) {
                        $country_data[$country] = array(
                            'lat' => (float) $latitude,
                            'lng' => (float) $longitude,
                            'posts' => array(),
                            'count' => 0,
                            'code' => $iso_code
                        );
                    }
                    $country_data[$country]['posts'][] = array(
                        'title' => $title,
                        'link' => $link
                    );
                    $country_data[$country]['count']++;
                }
            }
        }
        wp_reset_postdata();
    }

    $markers = array();
    foreach ($country_data as $country => $data) {
        $markers[] = array(
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'country' => $country,
            'count' => $data['count'],
            'posts' => $data['posts'],
            'code' => $data['code'] // Include the code here
        );
    }

    wp_send_json($markers);
}

add_action('wp_ajax_filter_jobs', 'handle_ajax_filter_jobs');
add_action('wp_ajax_nopriv_filter_jobs', 'handle_ajax_filter_jobs');

function handle_ajax_filter_jobs() {
    $search = sanitize_text_field($_POST['search']);
    $pos_cta_book = get_field('pos_cta_book', 'options');

    $args = array(
        'post_type' => 'portfolio',
        'posts_per_page' => -1,
        's' => $search,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $hotel_index = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $thumb = get_the_post_thumbnail_url($post_id, 'full');
            $title = get_the_title($post_id);
            $link  = get_permalink($post_id);
            $post_add_location = get_field('post_add_location', $post_id);
            $sp_ov_od = get_field('sp_ov_od', $post_id);
            $sp_b_cta_link =  get_field('sp_b_cta_link', $post_id) ? : get_field('pos_cta_book', 'options')['url'];
        ?>

                <div class="portCard portCard--v1" data-index="<?php echo esc_attr($hotel_index); ?>">
                    <a href="<?php echo esc_url($link); ?>" class="img__zoom portCard__img">
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" class="img-fluid" />
                        <?php if ($sp_ov_od) : ?>
                        <span class="portCard__date"><?php echo $sp_ov_od; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="portCard__contentWrap">
                        <a href="<?php echo esc_url($link); ?>"  class="portCard__content">
                        <h5 class="h5 portCard__h"><?php echo esc_html($title); ?></h5>
                        <span class="portCard__loc">
                            <span class="portCard__locIcon">
                            <svg width="11" height="13" viewBox="0 0 11 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9.46613 1.79153C8.48121 0.806589 7.17165 0.26416 5.77874 0.26416C4.38584 0.26416 3.07626 0.806589 2.09134 1.79153C1.10639 2.7765 0.563965 4.08604 0.563965 5.47892C0.563965 8.2967 3.22826 10.6404 4.65962 11.8995C4.85853 12.0745 5.0303 12.2256 5.16715 12.3534C5.33861 12.5136 5.55869 12.5937 5.77872 12.5937C5.9988 12.5937 6.21883 12.5136 6.39031 12.3534C6.52716 12.2256 6.69893 12.0745 6.89785 11.8995C8.32921 10.6404 10.9935 8.2967 10.9935 5.47892C10.9935 4.08604 10.4511 2.7765 9.46613 1.79153ZM6.4208 11.3573C6.21753 11.5361 6.042 11.6905 5.89727 11.8257C5.83078 11.8877 5.72666 11.8877 5.66014 11.8257C5.51544 11.6904 5.33989 11.536 5.13662 11.3572C3.79096 10.1735 1.28616 7.97011 1.28616 5.47894C1.28616 3.00176 3.30149 0.986427 5.7787 0.986427C8.25588 0.986427 10.2712 3.00176 10.2712 5.47894C10.2712 7.97011 7.76645 10.1735 6.4208 11.3573Z" fill="currentColor"/>
                                <path d="M5.8479 2.98438C4.58072 2.98438 3.5498 4.01527 3.5498 5.28244C3.5498 6.54962 4.58072 7.58051 5.8479 7.58051C7.11507 7.58051 8.14596 6.54962 8.14596 5.28244C8.14596 4.01527 7.11507 2.98438 5.8479 2.98438ZM5.8479 6.85824C4.97897 6.85824 4.27205 6.15131 4.27205 5.28242C4.27205 4.41352 4.97897 3.70659 5.8479 3.70659C6.71682 3.70659 7.42372 4.41352 7.42372 5.28242C7.42372 6.15131 6.71682 6.85824 5.8479 6.85824Z" fill="currentColor"/>
                            </svg>
                            </span>  
                            <span class="portCard__locText"><?php echo $post_add_location; ?></span>
                        </span>
                        </a>
                    
                        <div class="ah__button__underlined ah__button portCard__button">
                        <a href="<?php echo $sp_b_cta_link; ?>" target="_blank">
                            <span><?php echo $pos_cta_book['title']; ?></span>
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.8956 0.60305C12.9338 0.694974 12.9536 0.792386 12.9536 0.889967L12.9536 6.54682C12.9536 6.7533 12.8701 6.94139 12.7344 7.07715C12.5986 7.21292 12.4112 7.2971 12.204 7.2964C11.7897 7.2964 11.4538 6.9605 11.4538 6.54613L11.4538 2.69951L1.41995 12.7333C1.12721 13.0261 0.652034 13.0261 0.359292 12.7333C0.0665493 12.4406 0.0665494 11.9654 0.359292 11.6727L10.3924 1.63954L6.54579 1.63954C6.13142 1.63954 5.79552 1.30364 5.79552 0.889277C5.79552 0.474912 6.13142 0.139012 6.54579 0.139012L12.2026 0.139011C12.3002 0.139011 12.3976 0.158833 12.4896 0.197016C12.6734 0.273384 12.8192 0.419202 12.8956 0.60305Z" fill="currentColor"/>
                            </svg>
                        </a>
                        </div> 
                    </div>
                </div> 
             
            <?php
            $hotel_index++;
        }
    } else {
        echo '<p class="notFounMsg"><img src="/wp-content/uploads/2025/06/icons8-not-found-50.png">No Hotel Found.</p>';
    }

    wp_die(); // required to end AJAX call
}


function enqueue_google_maps_scripts() {
    wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=Add API KEY &callback=initMap', [], null, true);
    wp_enqueue_script('markerclusterer', 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js', [], null, true);
    wp_enqueue_script('map-search', get_stylesheet_directory_uri() . '/assets/js/map-search-ajax.js', array('jquery'), null, true);
    wp_localize_script('map-search', 'ajax_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_google_maps_scripts');