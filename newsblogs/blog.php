

if ( ! wp_next_scheduled( 'sync_external_posts_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'sync_external_posts_event' );
}

add_action( 'sync_external_posts_event', 'sync_external_posts_from_german_html' );

/**
 * Sync from German frontend page (title, link, image, date, description)
 */
function sync_external_posts_from_german_html( $debug_output = false ) {
    $url = 'https://campuls.hof-university.de/Schlagwort/inwa-de/';
    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) return;

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) return;

    // Parse HTML
    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    $dom->loadHTML( $html );
    libxml_clear_errors();

    $xpath = new DOMXPath( $dom );
    $nodes = $xpath->query('//article'); // each post is in <article>

    foreach ( $nodes as $node ) {
        // Title
        $titleNode = $xpath->query('.//h2//a', $node);
        $title     = $titleNode->length ? trim($titleNode->item(0)->nodeValue) : '';

        // Link
        $link      = $titleNode->length ? $titleNode->item(0)->getAttribute('href') : '';

        // Image
        $imgNode   = $xpath->query('.//img', $node);
        $image_url = $imgNode->length ? $imgNode->item(0)->getAttribute('src') : '';

        // Date
        $dateNode  = $xpath->query('.//time', $node);
        $date      = $dateNode->length ? $dateNode->item(0)->getAttribute('datetime') : current_time('mysql');

        // Description (first <p>)
        $descNode  = $xpath->query('.//p', $node);
        $desc      = $descNode->length ? trim($descNode->item(0)->nodeValue) : '';

        if ( ! $link || ! $title ) continue;

        // Check if already imported (by external link)
        $existing = new WP_Query([
            'post_type'  => 'post',
            'meta_query' => [
                [
                    'key'   => '_external_source_link',
                    'value' => $link,
                ]
            ]
        ]);

        if ( $existing->have_posts() ) {
            $post_id = $existing->posts[0]->ID;

            wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $title,
                'post_date'    => $date,
                'post_content' => $desc, // update description
            ]);

            if ( $image_url ) {
                update_post_meta( $post_id, '_external_thumbnail_url', esc_url_raw( $image_url ) );
            }

            if ( $debug_output ) {
                echo "<p>Updated: <strong>{$title}</strong></p>";
            }

        } else {
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_content' => $desc, // insert description
                'post_status'  => 'publish',
                'post_date'    => $date,
                'post_type'    => 'post',
            ]);

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_external_source_link', esc_url_raw( $link ) );
                if ( $image_url ) {
                    update_post_meta( $post_id, '_external_thumbnail_url', esc_url_raw( $image_url ) );
                }

                if ( $debug_output ) {
                    echo "<p>Inserted: <strong>{$title}</strong></p>";
                }
            }
        }
    }
}

/**
 * Manual sync trigger
 * URL: http://your-site.com/?run_sync_now=1
 */
add_action( 'init', function () {
    if ( isset($_GET['run_sync_now']) && $_GET['run_sync_now'] == 1 ) {
        echo "<h2>Manual German Sync Report</h2>";
        sync_external_posts_from_german_html( true );
        exit;
    }
});

/**
 * Admin columns for Source Link + Featured Image
 */
add_filter( 'manage_post_posts_columns', function( $columns ) {
    $columns['external_source_link'] = 'Source Link';
    $columns['featured_image'] = 'Featured Image';
    return $columns;
});

add_action( 'manage_post_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'external_source_link' ) {
        $link = get_post_meta( $post_id, '_external_source_link', true );
        echo $link ? '<a href="' . esc_url( $link ) . '" target="_blank">View Source</a>' : '-';
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
 * Featured image hotlink filters
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
            return 999999; // Fake attachment ID
        }
    }
    return $value;
}, 10, 4);

add_filter( 'wp_get_attachment_image_src', function( $image, $attachment_id, $size, $icon ) {
    global $post;
    if ( $attachment_id === 999999 && $post ) {
        $external = get_post_meta( $post->ID, '_external_thumbnail_url', true );
        if ( $external ) {
            return [ $external, 600, 400, true ];
        }
    }
    return $image;
}, 10, 4);

/**
 * Force permalinks to go to Campuls original post
 */
add_filter( 'post_type_link', function( $url, $post, $leavename, $sample ) {
    $external = get_post_meta( $post->ID, '_external_source_link', true );
    return $external ? esc_url( $external ) : $url;
}, 10, 4);

add_filter( 'post_link', function( $permalink, $post, $leavename ) {
    $external = get_post_meta( $post->ID, '_external_source_link', true );
    return $external ? esc_url( $external ) : $permalink;
}, 10, 3);

add_filter( 'page_link', function( $permalink, $post_id, $leavename ) {
    $external = get_post_meta( $post_id, '_external_source_link', true );
    return $external ? esc_url( $external ) : $permalink;
}, 10, 3);
