<?php

// =============================================================================
// REFRESH COLLECTIONS SUMMARY SCHEMA
// =============================================================================

/**
 * Refresh collections summary schemas
 *
 * "There are only two hard things in Computer Science: cache invalidation and
 * naming things" -- Phil Karlton.
 *
 * @since 4.0.0
 *
 * @param int     $post_id  The ID of the saved post.
 * @param WP_Post $post     The saved post object.
 */

function fictioneer_refresh_collections_schema( $post_id, $post ) {
  // Prevent miss-fire
  if ( fictioneer_save_guard( $post_id ) ) {
    return;
  }

  // Delete schema if post is not published
  if ( $post->post_status !== 'publish' ) {
    delete_post_meta( $post_id, 'fictioneer_schema' );
  }

  // Check what was updated
  if (
    get_page_template_slug() != 'collections.php' &&
    ! in_array( $post->post_type, ['fcn_collection', 'fcn_story', 'fcn_chapter', 'fcn_recommendation', 'post'] )
  ) {
    return;
  }

  // Get all pages with the collections template
  $pages = get_posts(
    array(
      'post_type' => 'page',
      'numberposts' => -1,
      'meta_key' => '_wp_page_template',
      'meta_value' => 'collections.php',
      'update_post_meta_cache' => true,
      'update_post_term_cache' => false, // Improve performance
      'no_found_rows' => true // Improve performance
    )
  );

  // Rebuild schemas (empty array if nothing found)
  foreach ( $pages as $page ) {
    fictioneer_build_collections_schema( $page->ID );
  }
}
add_action( 'save_post', 'fictioneer_refresh_collections_schema', 20, 2 );

// =============================================================================
// BUILD COLLECTIONS SUMMARY SCHEMA
// =============================================================================

if ( ! function_exists( 'fictioneer_build_collections_schema' ) ) {
  /**
   * Refresh collections summary schema
   *
   * @since 4.0.0
   *
   * @param int $post_id  The ID of the page the schema is for.
   *
   * @return string The encoded JSON or an empty string.
   */

  function fictioneer_build_collections_schema( $post_id ) {
    // Abort if...
    if ( ! $post_id ) {
      return '';
    }

    // Prepare query arguments
    $query_args = array (
      'post_type' => 'fcn_collection',
      'post_status' => 'publish',
      'orderby' => 'modified',
      'order' => 'DESC',
      'posts_per_page' => 20,
      'update_post_meta_cache' => false, // Improve performance
      'update_post_term_cache' => false, // Improve performance
      'no_found_rows' => true // Improve performance
    );

    // Setup
    $list = get_posts( $query_args );
    $post = get_post( $post_id );
    $schema = fictioneer_get_schema_node_root();
    $image_data = fictioneer_get_schema_primary_image( $post_id );

    if ( ! $post ) {
      return '';
    }

    $page_description = fictioneer_get_seo_description( $post_id, array(
      'default' => sprintf(
        _x( 'All collections on %s.', 'SEO default description for Collections template.', 'fictioneer' ),
        get_bloginfo( 'name' )
      ),
      'skip_cache' => true
    ));

    $page_title = fictioneer_get_seo_title( $post_id, array(
      'default' => _x( 'Collections', 'SEO default title for Collections template.', 'fictioneer' ),
      'skip_cache' => true
    ));

    // Website node
    $schema['@graph'][] = fictioneer_get_schema_node_website();

    // Image node
    if ( $image_data ) {
      $schema['@graph'][] = fictioneer_get_schema_node_image( $image_data );
    }

    // Webpage node
    $schema['@graph'][] = fictioneer_get_schema_node_webpage(
      ['WebPage', 'CollectionPage'], $page_title, $page_description, $post_id, $image_data
    );

    // Article node
    $schema['@graph'][] = fictioneer_get_schema_node_article(
      'Article', $page_description, $post, $image_data
    );

    // List node
    $schema['@graph'][] = fictioneer_get_schema_node_list(
      $list,
      _x( 'Collections', 'SEO schema collection list node name.', 'fictioneer' ),
      sprintf(
        _x( 'List of collections on %s.', 'SEO schema collection list node description.', 'fictioneer' ),
        get_bloginfo( 'name' )
      ),
      '#article'
    );

    // Prepare and cache for next time
    $schema = json_encode( $schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES );
    update_post_meta( $post_id, 'fictioneer_schema', $schema );

    // Return JSON string
    return $schema;
  }
}
