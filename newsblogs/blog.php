
// Schedule hourly sync
if ( ! wp_next_scheduled( 'sync_external_posts_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'sync_external_posts_event' );
}

add_action( 'sync_external_posts_event', 'sync_external_posts_from_api' );

/**
 * Main sync function
 */
function sync_external_posts_from_api( $debug_output = false ) {
    $tag_id   = 5816;
    $per_page = 20;
    $base_url = 'https://campuls.hof-university.com/wp-json/wp/v2/posts?tags=' . $tag_id . '&_embed=1&per_page=' . $per_page;

    $response = wp_remote_get( $base_url . '&page=1' );
    if ( is_wp_error( $response ) ) return;

    $total_pages = (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
    if ( $total_pages < 1 ) $total_pages = 1;

    for ( $page = 1; $page <= $total_pages; $page++ ) {
        $response = wp_remote_get( $base_url . '&page=' . $page );
        if ( is_wp_error( $response ) ) continue;

        $posts = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $posts ) ) continue;

        foreach ( $posts as $post ) {
            $source_id   = $post['id'];
            $title       = wp_strip_all_tags( $post['title']['rendered'] );
            $content     = $post['content']['rendered'];
            $date        = $post['date'];
            $slug        = $post['slug'];

            $existing = new WP_Query([
                'post_type'  => 'post',
                'meta_query' => [
                    [
                        'key'   => '_source_post_id',
                        'value' => $source_id,
                    ]
                ]
            ]);

            // --- Featured Image (Hotlink) ---
            $image_url = '';
            if ( isset( $post['_embedded']['wp:featuredmedia'][0]['source_url'] ) ) {
                $image_url = $post['_embedded']['wp:featuredmedia'][0]['source_url'];
            }

            if ( $existing->have_posts() ) {
                $existing_post_id = $existing->posts[0]->ID;

                wp_update_post([
                    'ID'           => $existing_post_id,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_date'    => $date,
                    'post_name'    => $slug,
                ]);

                if ( $image_url ) {
                    update_post_meta( $existing_post_id, '_external_thumbnail_url', esc_url_raw( $image_url ) );
                }

                if ( $debug_output ) {
                    echo "<p>Updated: <strong>{$title}</strong> (ID: {$existing_post_id})<br>Image URL: " 
                        . ( $image_url ? "<img src='" . esc_url( $image_url ) . "' style='max-width:150px;'><br><code>{$image_url}</code>" : "<span style='color:red;'>None</span>" ) . "</p>";
                }

            } else {
                $new_post_id = wp_insert_post([
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_date'    => $date,
                    'post_name'    => $slug,
                    'post_type'    => 'post',
                ]);

                if ( $new_post_id && ! is_wp_error( $new_post_id ) ) {
                    update_post_meta( $new_post_id, '_source_post_id', $source_id );

                    if ( $image_url ) {
                        update_post_meta( $new_post_id, '_external_thumbnail_url', esc_url_raw( $image_url ) );
                    }

                    if ( $debug_output ) {
                        echo "<p>Inserted: <strong>{$title}</strong> (ID: {$new_post_id})<br>Image URL: " 
                            . ( $image_url ? "<img src='" . esc_url( $image_url ) . "' style='max-width:150px;'><br><code>{$image_url}</code>" : "<span style='color:red;'>None</span>" ) . "</p>";
                    }
                }
            }
        }
    }
}

/**
 * Manual sync with debug report
 * URL: http://your-site.com/?run_sync_now=1
 */
add_action( 'init', function () {
    if ( isset($_GET['run_sync_now']) && $_GET['run_sync_now'] == 1 ) {
        echo "<h2>Manual Sync Report</h2>";

        $response = wp_remote_get( 'https://campuls.hof-university.com/wp-json/wp/v2/posts?tags=5816&_embed=1&per_page=1' );
        if ( is_wp_error( $response ) ) {
            echo "<p style='color:red;'>API request failed: " . $response->get_error_message() . "</p>";
            exit;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        echo "<p>API HTTP Status: {$status_code}</p>";

        $total_pages = (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' );
        if ( $total_pages < 1 ) $total_pages = 1;
        echo "<p>Total pages reported by API: {$total_pages}</p>";

        sync_external_posts_from_api( true );
        exit;
    }
});

/**
 * Add admin columns: Source ID + Featured Image
 */
add_filter( 'manage_post_posts_columns', function( $columns ) {
    $columns['source_post_id'] = 'Source ID';
    $columns['featured_image'] = 'Featured Image';
    return $columns;
});

add_action( 'manage_post_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'source_post_id' ) {
        echo esc_html( get_post_meta( $post_id, '_source_post_id', true ) ?: '-' );
    }

    if ( $column === 'featured_image' ) {
        $external = get_post_meta( $post_id, '_external_thumbnail_url', true );
        if ( $external ) {
            echo '<img src="' . esc_url( $external ) . '" style="max-width:60px; height:auto;" />';
        } elseif ( has_post_thumbnail( $post_id ) ) {
            echo get_the_post_thumbnail( $post_id, [60, 60] );
        } else {
            echo '<span style="color:red;">No image</span>';
        }
    }
}, 10, 2);

/**
 * Filters to make external image behave like a real Featured Image
 */
add_filter( 'has_post_thumbnail', function( $has_thumbnail, $post ) {
    $external = get_post_meta( $post->ID, '_external_thumbnail_url', true );
    if ( $external ) return true;
    return $has_thumbnail;
}, 10, 2);

add_filter( 'post_thumbnail_html', function( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    $external = get_post_meta( $post_id, '_external_thumbnail_url', true );
    if ( $external ) {
        return '<img src="' . esc_url( $external ) . '" class="wp-post-image" />';
    }
    return $html;
}, 10, 5);

add_filter( 'get_post_metadata', function( $value, $object_id, $meta_key, $single ) {
    if ( $meta_key === '_thumbnail_id' ) {
        $external = get_post_meta( $object_id, '_external_thumbnail_url', true );
        if ( $external ) {
            // Fake an ID so editor/front-end believes a featured image exists
            return 999999;
        }
    }
    return $value;
}, 10, 4);
