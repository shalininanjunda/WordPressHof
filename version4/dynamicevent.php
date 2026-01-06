// Shortcode: [ereignisse_cards]
function ereignisse_cards_shortcode() {
    ob_start();

    $args = array(
        'post_type'      => 'ereignisse',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        echo '<div class="events-wrapper">';

        while ($query->have_posts()) : $query->the_post();

            $event_date  = get_post_meta(get_the_ID(), 'event_date', true);
            $event_link  = get_post_meta(get_the_ID(), 'event_link', true);
            $event_notes = get_post_meta(get_the_ID(), 'event_notes', true);

            $url = $event_link ? $event_link : get_permalink(get_the_ID());

            echo '<div class="event-card">';

                // Header
                echo '<div class="event-header">';
                    echo '<h3 class="event-title">' . esc_html(get_the_title()) . '</h3>';
                    echo '<span class="event-badge">Ereignis</span>';
                echo '</div>';

               // Date
if ($event_date) {
    echo '<div class="event-meta">';
        echo '<span class="card-date" style="display: inline-flex; align-items: center; gap: 6px;">
            <svg class="event-calendar-icon" width="18" height="18" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path d="M19,4H17V3a1,1,0,0,0-2,0V4H9V3a1,
                    1,0,0,0-2,0V4H5A3,3,0,0,0,2,7V19a3,3,
                    0,0,0,3,3H19a3,3,0,0,0,3-3V7A3,3,
                    0,0,0,19,4Zm1,15a1,1,0,0,1-1,
                    1H5a1,1,0,0,1-1-1V12H20Zm0-9H4V7a1,
                    1,0,0,1,1-1H7V7a1,1,0,0,0,2,
                    0V6h6V7a1,1,0,0,0,2,0V6h2a1,
                    1,0,0,1,1,1Z"></path>
            </svg>' . esc_html($event_date) . '
        </span>';
    echo '</div>';
}



                // Notes
                if ($event_notes) {
                    echo '<p class="event-notes">' . esc_html($event_notes) . '</p>';
                }

                // Action
                echo '<div class="event-action">';
                    echo '<a href="' . esc_url($url) . '" class="event-more-btn" target="_blank">
                        Weitere Informationen
                    </a>';
                echo '</div>';

            echo '</div>';

        endwhile;

        echo '</div>';
    } else {
        echo '<p>Keine Ereignisse vorhanden.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('ereignisse_cards', 'ereignisse_cards_shortcode');
