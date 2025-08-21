//If you want to use this shortcode then use [jobs_thesis_cards]
function jobs_thesis_custom_shortcode($atts) {
    ob_start();

    $args = array(
        'post_type'      => array('jobs', 'thesis'),
        'post_status'    => 'publish', 
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC'
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) :
        echo '<div class="jobs-thesis-wrapper">';
        while ($query->have_posts()) : $query->the_post();

// ✅ Get taxonomy term (job vs abschlussarbeit)
            $terms = get_the_terms(get_the_ID(), 'jobs');
            $term_slug = $terms && !is_wp_error($terms) ? $terms[0]->slug : '';

            if ($term_slug === 'abschlussarbeit') {
                // Thesis fields
                $title       = get_post_meta(get_the_ID(), 'Thema', true) ?: get_the_title();
                $short_desc  = get_post_meta(get_the_ID(), 'kurzbeschreibung_thema', true);
                $start       = get_post_meta(get_the_ID(), 'Beginn', true);
                $duration    = get_post_meta(get_the_ID(), 'Dauer', true);
                $link        = get_post_meta(get_the_ID(), 'link-bewerbung_abschlussarbeit', true);
                $supervisors = get_post_meta(get_the_ID(), 'betreuer_job', true);

                $badge_text  = 'Abschlussarbeit';
                $badge_class = 'badge-thesis';

            } else {
                // Job fields
                $title       = get_post_meta(get_the_ID(), 'bezeichnung', true) ?: get_the_title();
                $short_desc  = get_post_meta(get_the_ID(), 'beschreibung', true);
                $start       = get_post_meta(get_the_ID(), 'antrittsmonat', true);
                $duration    = get_post_meta(get_the_ID(), 'beschaftigungsverhaltnis', true);
                $link        = get_post_meta(get_the_ID(), 'link-bewerbung', true);
                $supervisors = get_post_meta(get_the_ID(), 'betreuer_job', true);

                $badge_text  = 'Jobs';
                $badge_class = 'badge-job';
            }
            echo '<div class="job-thesis-card">';
                
                // Header row: title + badge
                echo '<div class="card-header">';
                    echo '<h3 class="card-title">' . esc_html($title) . '</h3>';
                    echo '<span class="status-badge ' . esc_attr($badge_class) . '">' . esc_html($badge_text) . '</span>';
                echo '</div>';

                // Short description
                if ($short_desc) {
                    echo '<p class="card-desc">' . esc_html($short_desc) . '</p>';
                }
	// Meta row (start + duration)
// Meta row (start + duration)
echo '<div class="card-footer">';
    echo '<div class="card-meta">';
    if ($start) {
    // Format into "Mon. YYYY"
    $formatted_date = '';
    if (preg_match('/^\d{8}$/', $start)) { 
        // YYYYMMDD
        $date_obj = DateTime::createFromFormat('Ymd', $start);
        if ($date_obj) {
            $formatted_date = $date_obj->format('M. Y');
        }
    } elseif (preg_match('/^\d{6}$/', $start)) { 
        // YYYYMM
        $date_obj = DateTime::createFromFormat('Ym', $start);
        if ($date_obj) {
            $formatted_date = $date_obj->format('M. Y');
        }
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) { 
        // YYYY-MM-DD
        $date_obj = DateTime::createFromFormat('Y-m-d', $start);
        if ($date_obj) {
            $formatted_date = $date_obj->format('M. Y');
        }
    }

    if (!$formatted_date) {
        $formatted_date = esc_html($start); // fallback
    }

    echo '<span class="card-date">
       <svg fill="#98a2b3" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
         <path d="M19,4H17V3a1,1,0,0,0-2,0V4H9V3A1,1,0,0,0,7,3V4H5A3,3,0,0,0,2,7V19a3,3,0,0,0,3,3H19a3,3,0,0,0,3-3V7A3,3,0,0,0,19,4Zm1,15a1,1,0,0,1-1,1H5a1,1,0,0,1-1-1V12H20Zm0-9H4V7A1,1,0,0,1,5,6H7V7A1,1,0,0,0,9,7V6h6V7a1,1,0,0,0,2,0V6h2a1,1,0,0,1,1,1Z"></path>
       </svg>
       ab ' . $formatted_date . '</span>';
}

        if ($duration) {
            echo '<span class="card-duration">
               <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke=""><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12 7V12L14.5 13.5M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="#98a2b3" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                ' . esc_html($duration) . '</span>';
        }
    echo '</div>'; // card-meta

    if ($supervisors) {
        $supervisors = maybe_unserialize($supervisors);
        if (is_array($supervisors)) {
            foreach ($supervisors as $sup_id) {
                $name   = get_post_meta($sup_id, 'titel-vorname-nachname', true);
                $email  = get_post_meta($sup_id, 'e-mail', true);
                $phone  = get_post_meta($sup_id, 'telefon', true);
                $pos    = get_post_meta($sup_id, 'position', true);
                $bild   = get_post_meta($sup_id, 'bild', true); 

                echo '<div class="supervisor-box">';
                    if ($bild) {
                        echo wp_get_attachment_image($bild, 'thumbnail', false, ['class' => 'avatar']);
                    } elseif ($email) {
                        echo get_avatar($email, 50);
                    }

                    echo '<div class="sup-info">';
                        if ($name) {
                            echo '<p class="sup-name"><strong>' . esc_html($name) . '</strong></p>';
                        }
                        if ($pos) {
                            echo '<p class="sup-position">' . esc_html($pos) . '</p>';
                        }
                        echo '<p class="sup-role">Betreuer/in</p>';

                        echo '<div class="sup-icons">';
                            if ($email) {
                                echo '<a href="mailto:' . esc_attr($email) . '" class="sup-icon email-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#98a2b3"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M4 18L9 12M20 18L15 12M3 8L10.225 12.8166C10.8665 13.2443 11.1872 13.4582 11.5339 13.5412C11.8403 13.6147 12.1597 13.6147 12.4661 13.5412C12.8128 13.4582 13.1335 13.2443 13.775 12.8166L21 8M6.2 19H17.8C18.9201 19 19.4802 19 19.908 18.782C20.2843 18.5903 20.5903 18.2843 20.782 17.908C21 17.4802 21 16.9201 21 15.8V8.2C21 7.0799 21 6.51984 20.782 6.09202C20.5903 5.71569 20.2843 5.40973 19.908 5.21799C19.4802 5 18.9201 5 17.8 5H6.2C5.0799 5 4.51984 5 4.09202 5.21799C3.71569 5.40973 3.40973 5.71569 3.21799 6.09202C3 6.51984 3 7.07989 3 8.2V15.8C3 16.9201 3 17.4802 3.21799 17.908C3.40973 18.2843 3.71569 18.5903 4.09202 18.782C4.51984 19 5.07989 19 6.2 19Z" stroke="#98a2b3" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                                </a>';
                            }
                            if ($phone) {
                                echo '<a href="tel:' . esc_attr($phone) . '" class="sup-icon phone-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#98a2b3"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M21 7V3M21 3H17M21 3L15 9M18.5 21C9.93959 21 3 14.0604 3 5.5C3 5.11378 3.01413 4.73086 3.04189 4.35173C3.07375 3.91662 3.08968 3.69907 3.2037 3.50103C3.29814 3.33701 3.4655 3.18146 3.63598 3.09925C3.84181 3 4.08188 3 4.56201 3H7.37932C7.78308 3 7.98496 3 8.15802 3.06645C8.31089 3.12515 8.44701 3.22049 8.55442 3.3441C8.67601 3.48403 8.745 3.67376 8.88299 4.05321L10.0491 7.26005C10.2096 7.70153 10.2899 7.92227 10.2763 8.1317C10.2643 8.31637 10.2012 8.49408 10.0942 8.64506C9.97286 8.81628 9.77145 8.93713 9.36863 9.17882L8 10C9.2019 12.6489 11.3501 14.7999 14 16L14.8212 14.6314C15.0629 14.2285 15.1837 14.0271 15.3549 13.9058C15.5059 13.7988 15.6836 13.7357 15.8683 13.7237C16.0777 13.7101 16.2985 13.7904 16.74 13.9509L19.9468 15.117C20.3262 15.255 20.516 15.324 20.6559 15.4456C20.7795 15.553 20.8749 15.6891 20.9335 15.842C21 16.015 21 16.2169 21 16.6207V19.438C21 19.9181 21 20.1582 20.9007 20.364C20.8185 20.5345 20.663 20.7019 20.499 20.7963C20.3009 20.9103 20.0834 20.9262 19.6483 20.9581C19.2691 20.9859 18.8862 21 18.5 21Z" stroke="#98a2b3" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                                </a>';
                            }
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
            }
        }
    }
echo '</div>'; // card-footer

	if ($link) {
    $url = $link;
} else {
    $url = get_permalink(get_the_ID());
}
echo '<a href="' . esc_url($url) . '" class="more-info-btn" target="_blank">Weitere Informationen</a>';

//                 // More Info button
//                 if ($link) {
//                     echo '<a href="' . esc_url($link) . '" class="more-info-btn" target="_blank">Weitere Informationen</a>';
//                 }

            echo '</div>'; // card

        endwhile;
        echo '</div>'; // wrapper
    else :
        echo '<p>Keine Jobs oder Abschlussarbeiten gefunden.</p>';
    endif;

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('jobs_thesis_cards', 'jobs_thesis_custom_shortcode');
