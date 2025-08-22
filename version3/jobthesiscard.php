// If you want to use this shortcode then use [jobs_thesis_cards]
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
            $terms     = get_the_terms(get_the_ID(), 'jobs');
            $term_slug = $terms && !is_wp_error($terms) ? $terms[0]->slug : '';

            if ($term_slug === 'abschlussarbeit') {
                // Thesis fields
                $title       = get_post_meta(get_the_ID(), 'Thema', true) ?: get_the_title();
                $short_desc  = get_post_meta(get_the_ID(), 'kurzbeschreibung_thema', true);
                $duration    = get_post_meta(get_the_ID(), 'Dauer', true);
                $link        = get_post_meta(get_the_ID(), 'link-bewerbung_abschlussarbeit', true);
                $supervisors = get_post_meta(get_the_ID(), 'betreuer_job', true);

                $badge_text  = 'Abschlussarbeit';
                $badge_class = 'badge-thesis';
            } else {
                // Job fields
                $title       = get_post_meta(get_the_ID(), 'bezeichnung', true) ?: get_the_title();
                $short_desc  = get_post_meta(get_the_ID(), 'beschreibung', true);
                $duration    = get_post_meta(get_the_ID(), 'beschaftigungsverhaltnis', true);
                $link        = get_post_meta(get_the_ID(), 'link_application', true);
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

                // Meta row (published date instead of start date)
                echo '<div class="card-footer">';
                    echo '<div class="card-meta">';

                        $published_date = get_the_date('d.m.Y', get_the_ID());
                        echo '<span class="card-date">
                            <svg fill="#98a2b3" width="18" height="18" viewBox="0 0 24 24" 
                                xmlns="http://www.w3.org/2000/svg">
                                <path d="M19,4H17V3a1,1,0,0,0-2,0V4H9V3A1,
                                    1,0,0,0,7,3V4H5A3,3,0,0,0,2,7V19a3,3,
                                    0,0,0,3,3H19a3,3,0,0,0,3-3V7A3,3,0,
                                    0,0,19,4Zm1,15a1,1,0,0,1-1,
                                    1H5a1,1,0,0,1-1-1V12H20Zm0-9H4V7A1,
                                    1,0,0,1,5,6H7V7A1,1,0,0,0,9,
                                    7V6h6V7a1,1,0,0,0,2,0V6h2a1,1,0,
                                    0,1,1,1Z">
                                </path>
                            </svg>
                            veröffentlicht am ' . esc_html($published_date) . '</span>';

                    echo '</div>'; // card-meta

                    // Supervisors section stays unchanged
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
                                                echo '<a href="mailto:' . esc_attr($email) . '" class="sup-icon email-icon">✉️</a>';
                                            }
                                            if ($phone) {
                                                echo '<a href="tel:' . esc_attr($phone) . '" class="sup-icon phone-icon">📞</a>';
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
                // More Info button
                if ($url) {
                    echo '<a href="' . esc_url($url) . '" class="more-info-btn" target="_blank">Weitere Informationen</a>';
                }

            echo '</div>'; // job-thesis-card

        endwhile;

        echo '</div>'; // wrapper
    else :
        echo '<p>Keine Jobs oder Abschlussarbeiten gefunden.</p>';
    endif;

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('jobs_thesis_cards', 'jobs_thesis_custom_shortcode');