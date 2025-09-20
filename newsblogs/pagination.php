// Add this in Code Snippets plugin
function german_posts_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'per_page' => 6,    // posts per page
    ], $atts, 'german_posts' );

    ob_start();

    // Use ?aktuelles_page=2 instead of default ?paged=2
    $paged = ( isset($_GET['aktuelles_page']) && intval($_GET['aktuelles_page']) > 0 ) 
        ? intval($_GET['aktuelles_page']) 
        : 1;

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => intval($atts['per_page']),
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [
            [
                'key'     => '_external_source_link',
                'compare' => 'EXISTS',
            ],
        ],
    ];

    $query = new WP_Query($args);

    if ($query->have_posts()) :
        echo '<div class="aktuelles-grid">';

        while ($query->have_posts()) : $query->the_post();

            $image = get_post_meta(get_the_ID(), '_external_thumbnail_url', true);
            $link  = get_post_meta(get_the_ID(), '_external_source_link', true);
            $categories = get_the_category();
            $cat_name   = $categories ? esc_html($categories[0]->name) : '';

            echo '<article class="aktuelles-item" style="margin-bottom:40px;">';

            if ($image) {
                echo '<a href="'. esc_url($link) .'" target="_blank">
                        <img src="'. esc_url($image) .'" alt="'. esc_attr(get_the_title()) .'" style="max-width:100%; height:auto; border-radius:8px;">
                      </a>';
            }

            if ( $cat_name ) {
                echo '<div class="news-category" style="margin-top:10px; font-size:14px; color:#555;">'. $cat_name .'</div>';
            }

            echo '<h3 style="margin:5px 0;"><a href="'. esc_url($link) .'" target="_blank">'. get_the_title() .'</a></h3>';
            echo '<p>'. wp_trim_words(get_the_content(), 25, '...') .'</p>';
            echo '<span class="date" style="font-size:13px; color:#777;">'. get_the_date() .'</span>';

            echo '</article>';

        endwhile;

        echo '</div>';

        echo '<div class="pagination">';

        echo '<div class="prev-link">';
        previous_posts_link('« Zurück', $query->max_num_pages);
        echo '</div>';

        echo '<div class="page-numbers-wrap">';
        echo paginate_links([
            'base'      => add_query_arg('aktuelles_page', '%#%'),
            'format'    => '?aktuelles_page=%#%',
            'current'   => max(1, $paged),
            'total'     => $query->max_num_pages,
            'prev_next' => false
        ]);
        echo '</div>';

        echo '<div class="next-link">';
        next_posts_link('Weiter »', $query->max_num_pages);
        echo '</div>';

        echo '</div>';

        wp_reset_postdata();
    else :
        echo '<p>No posts found.</p>';
    endif;

    return ob_get_clean();
}
add_shortcode( 'german_posts', 'german_posts_shortcode' );
