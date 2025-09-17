if ( ! wp_next_scheduled( 'sync_external_posts_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'sync_external_posts_event' );
}

add_action( 'sync_external_posts_event', 'sync_external_posts_from_german_html' );

function sync_external_posts_from_german_html( $debug_output = false ) {
    $urls = [
        'https://campuls.hof-university.de/Schlagwort/inwa-de/',
        'https://campuls.hof-university.de/Schlagwort/wasseraufbereitung/',
        'https://campuls.hof-university.de/Schlagwort/wasserressourcen-de/',
        'https://campuls.hof-university.de/Schlagwort/wassersysteme/',
        'https://campuls.hof-university.de/Schlagwort/wassernutzung/',
        'https://campuls.hof-university.de/Schlagwort/wassermanagement-de/',
        'https://campuls.hof-university.de/Schlagwort/mueller-czygan/',
        'https://campuls.hof-university.de/Schlagwort/wimmer/',
        'https://campuls.hof-university.de/Schlagwort/harbach/',
        'https://campuls.hof-university.de/Schlagwort/nachhaltigkeit/',
        'https://campuls.hof-university.de/Schlagwort/sustainable-water-management-and-engineering/',
    ];

    $posts_data = [];

    foreach ( $urls as $url ) {
        $response = wp_remote_get( $url );
        if ( is_wp_error( $response ) ) continue;

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) continue;

        libxml_use_internal_errors( true );
        $dom = new DOMDocument();
        $dom->loadHTML( $html );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );
        $nodes = $xpath->query('//article');

        foreach ( $nodes as $node ) {
            // Title + Link
            $titleNode = $xpath->query('.//h2//a', $node);
            $title     = $titleNode->length ? trim($titleNode->item(0)->nodeValue) : '';
            $link      = $titleNode->length ? $titleNode->item(0)->getAttribute('href') : '';
            if ( ! $title || ! $link ) continue;

            // Image
            $imgNode   = $xpath->query('.//img', $node);
            $image_url = $imgNode->length ? $imgNode->item(0)->getAttribute('src') : '';

            // Date from <span class="datum">
            $dateNode  = $xpath->query('.//span[contains(@class,"datum")]', $node);
            if ( $dateNode->length ) {
                $date_text = str_replace('Veröffentlichung:', '', trim($dateNode->item(0)->nodeValue));
                $dt        = DateTime::createFromFormat('d.m.Y', trim($date_text));
                $date      = $dt ? $dt->format('Y-m-d 00:00:00') : current_time('mysql');
            } else {
                $date = current_time('mysql');
            }

            // Description
            $descNode  = $xpath->query('.//p', $node);
            $desc      = $descNode->length ? trim($descNode->item(0)->nodeValue) : '';

            $posts_data[] = [
                'title' => $title,
                'link'  => $link,
                'image' => $image_url,
                'date'  => $date,
                'desc'  => $desc,
            ];
        }
    }

    // Sort by date (newest first)
    usort( $posts_data, function( $a, $b ) {
        return strtotime($b['date']) <=> strtotime($a['date']);
    });

    foreach ( $posts_data as $post ) {
        $existing = new WP_Query([
            'post_type'  => 'post',
            'meta_query' => [
                [
                    'key'   => '_external_source_link',
                    'value' => $post['link'],
                ]
            ]
        ]);

        if ( $existing->have_posts() ) {
            $post_id = $existing->posts[0]->ID;
            wp_update_post([
                'ID'           => $post_id,
                'post_title'   => $post['title'],
                'post_date'    => $post['date'],
                'post_content' => $post['desc'],
            ]);
            update_post_meta( $post_id, '_external_source_link', esc_url_raw( $post['link'] ) );
            update_post_meta( $post_id, '_external_thumbnail_url', esc_url_raw( $post['image'] ) );

        } else {
            $post_id = wp_insert_post([
                'post_title'   => $post['title'],
                'post_content' => $post['desc'],
                'post_status'  => 'publish',
                'post_date'    => $post['date'],
                'post_type'    => 'post',
            ]);
            if ( $post_id && ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, '_external_source_link', esc_url_raw( $post['link'] ) );
                update_post_meta( $post_id, '_external_thumbnail_url', esc_url_raw( $post['image'] ) );
            }
        }
    }
}

// Manual sync trigger
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
