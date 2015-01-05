<?php
/**
 * Plugin Name: WP Fast Search
 * Plugin URI: http://www.graemeboy.com/wp-fast-search
 * Description: A blazingly fast drop-down search widget
 * Version: 0.1
 * Author: Graeme Boy
 * Author URI: http://www.graemeboy.com
 * License: MIT
 */

// register WPFS_Fast_Search_Widget widget
function wpfs_register_fast_searcH_widget() {
    register_widget( 'WPFS_Fast_Search_Widget' );
}
add_action( 'widgets_init', 'wpfs_register_fast_searcH_widget' );

add_action( 'wp_ajax_wpfs_get_post_titles', 'wpfs_getPostTitles' );
add_action( 'wp_ajax_nopriv_wpfs_get_post_titles', 'wpfs_getPostTitles' );

class WPFS_Fast_Search_Widget extends WP_Widget {

  /**
   * Sets up the widgets name etc
   */
  public function __construct() {
    parent::__construct(
      'wpfs_fast_search_widget', // Base ID
      __( 'WP Fast Search', 'wpfs' ), // Name
      array( 'description' => __( 'A blazing fast search widget', 'wpfs' ), )
    );
  }

  /**
   * Outputs the content of the widget
   *
   * @param array $args
   * @param array $instance
   */
  public function widget( $args, $instance ) {
    // outputs the content of the widget
    
    if ( ! empty( $instance['placeholder'] ) ) {
      $placeholder = $instance['placeholder'];
    } else {
      $placeholder = 'Search Post Titles';
    }

    echo $args['before_widget'];
    if ( ! empty( $instance['title'] ) ) {
      echo $args['before_title'] . 
           apply_filters( 'widget_title', $instance['title'] ). 
           $args['after_title'];
    }

    wpfs_outputWidget($placeholder);

    echo $args['after_widget'];
  }

  /**
   * Outputs the options form on admin
   *
   * @param array $instance The widget options
   */
  public function form( $instance ) {
    // outputs the options form on admin
    $title = ! empty( $instance['title'] ) ? 
               $instance['title'] : __( '', 'wpfs' );
    $placeholder = ! empty( $instance['placeholder'] ) ? 
               $instance['placeholder'] : __( 'Search Post Titles', 'wpfs' );
    ?>
    <p>
    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php 
           _e( 'Title:' ); ?></label> 
    <input class="widefat" id="<?php 
           echo $this->get_field_id( 'title' ); ?>" name="<?php 
           echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php 
           echo esc_attr( $title ); ?>">
    </p>
    <p>
    <label for="<?php echo $this->get_field_id( 'placeholder' ); ?>"><?php 
           _e( 'Placeholder:' ); ?></label> 
    <input class="widefat" id="<?php 
           echo $this->get_field_id( 'placeholder' ); ?>" name="<?php 
           echo $this->get_field_name( 'placeholder' ); ?>" type="text" value="<?php 
           echo esc_attr( $placeholder ); ?>">
    </p>
    <?php 

  }

  /**
   * Processing widget options on save
   *
   * @param array $new_instance The new options
   * @param array $old_instance The previous options
   */
  public function update( $new_instance, $old_instance ) {
    // processes widget options to be saved
    $instance = array();
    $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
    $instance['placeholder'] = ( ! empty( $new_instance['placeholder'] ) ) ? strip_tags( $new_instance['placeholder'] ) : '';
    return $instance;
  }
}

function wpfs_getPostTitles () {
  query_posts();
  $output = array();
  while (have_posts()) : the_post();
    $output[get_the_title()] = get_permalink();
  endwhile;
  echo json_encode( $output );
  exit;
}

function wpfs_outputWidget ($placeholder) {
  ?>
  <style>
    ul.wpfs-results {
      display:none;
      position:absolute;
      left:0;
      z-index:3;
      padding:0;
      margin:0;
      top:0;
      width:100%;
      background-color: white;
      transition-property: display;
      transition-duration: 1s;
      transition-timing-function: ease-out;
    }
    .wpfs-open .wpfs-results {
      display: block;
    }
    ul.wpfs-results li {
      width:100%;
      padding:0;
      margin:0;
      display:block;
      background-color: white;
      list-style: none;
    }
    li.wpfs-result-item a, span.wpfs-no-results{
      text-align: left;
      padding:10px 10px 10px 15px;
      margin:0;
      display:block;
      width:100%;
      cursor:pointer;
    }
    li.wpfs-result-item a:hover {
      text-decoration: none;
    }
    li.wpfs-selected a {
      background-color: #f3f3f3;
    }
    .wpfs-results-wrapper {
      position:relative;
    }
  </style>
  <div class="wpfs-wrapper">
    <input type="text" class="wpfs-input" placeholder="<?php echo $placeholder ?>" >
    <div class="wpfs-results-wrapper">
      <ul class="wpfs-results"></ul>
    </div>
  </div>

  <script>
  jQuery(document).ready(function ($) {
    var postTitles = {},
      wpfsAjaxUrl = '<?php echo admin_url( 'admin-ajax.php' ) ?>',
      searchInput,
      resultsLimit = 10,
      results = [],
      resultsEasyIndex = [],
      numResults = 0,
      wpfsWrapper,
      resultsEl,
      resultItem,
      selectedIndex = -1;
    $.post(wpfsAjaxUrl, { action: 'wpfs_get_post_titles' },
       function (data) {
        postTitles = data;
       }, "json"
    );
    $('.wpfs-input').focus(function () {
      wpfsWrapper = $(this).parent();
    }).keyup(function (e) {
      var input = this;
      if (e.which === 13 || e.keyCode === 13) {
        if ($('.wpfs-selected').length > 0) {
          var clicked = $('.wpfs-selected a').first();
          $(input).val(clicked.text());
          window.location.href=clicked.attr('href');
        }
      } else if (e.which === 38 || e.keyCode === 38) { // up
        if (selectedIndex > 0) {
          selectedIndex -= 1;
          $('.wpfs-selected').removeClass('wpfs-selected');
          $('.wpfs-result-' + selectedIndex).addClass('wpfs-selected');
        }
      } else if (e.which === 40 || e.keyCode === 40) { // down arrow
        if (selectedIndex < numResults - 1) {
          selectedIndex += 1;
          $('.wpfs-selected').removeClass('wpfs-selected');
          $('.wpfs-result-' + selectedIndex).addClass('wpfs-selected');
        }
      } else {
        selectedIndex = -1;
        searchInput = $(this).val().toLowerCase();
        resultsEl = wpfsWrapper.find('.wpfs-results');
        resultsEl.html('');
        results = [];
        resultsEasyIndex = [];
        if (searchInput !== '') {
          Object.keys(postTitles).forEach(function (key) {
            if (key.toLowerCase().indexOf(searchInput) === 0 &&
                results.length <= resultsLimit) {
              results.push({key:postTitles[key],title:key});
              resultsEasyIndex.push(postTitles[key]);
            }
          });
          Object.keys(postTitles).forEach(function (key) {
            if (key.toLowerCase().indexOf(searchInput) > -1 &&
                results.length <= resultsLimit &&
                resultsEasyIndex.indexOf(postTitles[key]) === -1) {
                results.push({key:postTitles[key],title:key});
            }
          });
          if ((numResults = results.length) > 0) {
            wpfsWrapper.addClass("wpfs-open");
            for (var i = 0; i < numResults; i++) {
              resultItem = document.createElement('li');
              resultItem.className = 'wpfs-result-item wpfs-result-' + i;
              $(resultItem).attr('data-index', i)
                           .append('<a href="' + results[i].key + '">' + 
                                 results[i].title + '</a>')
              resultsEl.append(resultItem);
              $(resultItem).mouseover(function (e){
                selectedIndex = parseInt($(e.currentTarget).attr('data-index'));
                $('.wpfs-selected').removeClass('wpfs-selected');
                $(this).addClass('wpfs-selected');
              }).on('click', function (e) {
                var clicked = $(this).find('a').first();
                $(input).val(clicked.text());
                window.location.href=clicked.attr('href');
              });
            }
          } else {
            resultItem = document.createElement('li');
            $(resultItem).append('<span class="wpfs-no-results">No results</span>');
            resultsEl.append(resultItem);
          }
        }
      }
    }).blur(function (e) {
      results = [];
      resultsEasyIndex = [];
      var self = $(this);
      setTimeout(function () {
        self.parent().removeClass('wpfs-open');
      }, 150);
    });
  });
  </script>
  <?php
}

?>