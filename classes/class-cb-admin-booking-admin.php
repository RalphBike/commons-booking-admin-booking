<?php

class CB_Admin_Booking_Admin {

  /**
  * loads booking creation functionality on booking admin page of Commons Booking plugin
  */
  function load_bookings_creation() {

    //load translation
    $lang_path = 'commons-booking-admin-booking/languages/'; // CB_ADMIN_BOOKING_PATH . 'lang/';
    load_plugin_textdomain( 'commons-booking-admin-booking', false, $lang_path );

    //get all users
    $this->users = get_users();
    $this->valid_user_ids = array();

    foreach ($this->users as $user) {
      $this->valid_user_ids[] = $user->ID;
    }

    //get all items
    $item_posts_args = array(
      'numberposts' => -1,
      'post_type'   => 'cb_items',
      'orderby'    => 'post_date',
      'order' => 'ASC'
    );
    $this->cb_items = get_posts( $item_posts_args );

    $this->valid_cb_item_ids = array();
    foreach ($this->cb_items as $cb_item) {
      $this->valid_cb_item_ids[] = $cb_item->ID;
    }

    add_action( 'toplevel_page_cb_bookings', array($this, 'render_booking_creation'));

  }

  function validate_booking_form_input() {

    //validation
    $data = array();
    $errors = array();

    $data['date_start_valid'] = isset($_POST['date_start']) && strlen($_REQUEST['date_start']) > 0 ? new DateTime($_POST['date_start']) : null;
    $data['date_end_valid'] = isset($_POST['date_end']) && strlen($_REQUEST['date_end']) > 0 ? new DateTime($_POST['date_end']) : null;
    $data['item_id'] =  $_POST['item_id'];
    $data['user_id'] = $_POST['user_id'];
    $data['send_mail'] = isset($_POST['send_mail']) ? true : false;

    if(!in_array($data['item_id'], $this->valid_cb_item_ids)) {
      $errors[] = __('ARTICEL_INVALID', 'commons-booking-admin-booking');
    }

    if(!in_array($data['user_id'], $this->valid_user_ids)) {
      $errors[] = __('USER_INVALID', 'commons-booking-admin-booking');
    }

    if(!$data['date_start_valid']) {
      $errors[] = __('START_DATE_INVALID', 'commons-booking-admin-booking');
    }
    else {
      $data['date_start'] = $_REQUEST['date_start'];
    }

    if(!$data['date_end_valid']) {
      $errors[] = __('END_DATE_INVALID', 'commons-booking-admin-booking');
    }
    else {
      $data['date_end'] = $_REQUEST['date_end'];
    }

    return array(data => $data, errors => $errors);

  }

  /**
  * handle submit of booking creation form
  */
  function handle_booking_form_submit($data) {

    $date_start = $data['date_start'];
    $date_end = $data['date_end'];
    $item_id = $data['item_id'];
    $user_id = $data['user_id'];
    $send_mail = $data['send_mail'];

    if( strtotime($date_start) > strtotime($date_end)) {
      $message = __('START_DATE_AFTER_END_DATE', 'commons-booking-admin-booking');
      $class = 'notice notice-error';
      echo '<div class="' . $class .'"><p>' . $message . '</p></div>';
      return false;
    }

    $cb_booking = new CB_Booking();

    //check if location (timeframe) exists
    $location_id = $cb_booking->get_booking_location_id($date_start, $date_end, $item_id);
    if($location_id) {

      //check if no bookings exist in wanted period
      $conflict_bookings = $this->fetch_bookings_in_period($date_start, $date_end, $item_id);

      $closed_days = get_post_meta( $location_id, 'commons-booking_location_closeddays', TRUE  );
      $date_start_valid = $this->validate_day($date_start, $closed_days);
      $date_end_valid = $this->validate_day($date_end,$closed_days);

      if($date_start_valid && $date_end_valid) {
        if (count($conflict_bookings) == 0) {

          $booking_id = $this->create_booking($date_start, $date_end, $item_id, $user_id, 'confirmed', $location_id, $send_mail);

          if($booking_id) {
            $message = __('BOOKING_CREATED', 'commons-booking-admin-booking');
            $message .= $send_mail ? ' Eine Bestätigungsmail wurde versandt.' : '';
            $class = 'notice notice-success';
            echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
            return true;
          }
          else {
            $message = __('BOOKING_CREATION_ERROR', 'commons-booking-admin-booking');
            $class = 'notice notice-error';
            echo '<div class="' . $class .'"><p>' . $message . '</p></div>';
            return false;
          }

        }
        else {
          $message = __('ALREADY_BOOKING_IN_PERIOD', 'commons-booking-admin-booking');
          $class = 'notice notice-error';
          echo '<div class="' . $class .'"><p>' . $message . '</p></div>';
          return false;
        }
      }
      else {

        $dates .= !$date_start_valid ? date("d.m.Y", strtotime($date_start))  : '';
        if($date_start != $date_end) {
          $dates .= !$date_start_valid && !$date_end_valid ? ', ' : '';
          $dates .= !$date_end_valid ? date("d.m.Y", strtotime($date_end)) : '';
        }

        $message = sprintf(__('NO_BOOKING_FOR_CLOSED_DAYS', 'commons-booking-admin-booking'), $dates);
        $class = 'notice notice-error';
        echo '<div class="' . $class .'"><p>' . $message . '</p></div>';
        return false;
      }

    }
    else {

      $message = __('NO_TIMEFRAME_AVAILABLE', 'commons-booking-admin-booking');
      $class = 'notice notice-error';
      echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';
      return false;
    }
  }

  /**
  * handle submission and rendering of booking creation form
  */
  function render_booking_creation() {

    $booking_result = null;

    if(isset($_POST['action']))  {
      if($_POST['action'] == 'cb-booking-create') {

        $validation_result = $this->validate_booking_form_input();

        if(count($validation_result['errors']) > 0) {
          $error_list = str_replace(',', ', ', implode(",", $validation_result['errors']));
          $message = __('INPUT_ERRORS_OCCURED', 'commons-booking-admin-booking') . ': ' . $error_list;
          $class = 'notice notice-error';
          echo '<div id="message" class="' . $class .'"><p>' . $message . '</p></div>';

        }
        else {
          $booking_result = $this->handle_booking_form_submit($validation_result['data']);
        }

      }
    }

    //get a location
    $location_posts_args = array(
      'numberposts' => 1,
      'post_type'   => 'cb_locations'
    );

    $location = get_posts( $location_posts_args );

    //check if there are at least location and item created
    if(count($location) > 0 && count($this->cb_items) > 0) {
      $data = $validation_result['data'];

      $date_min = new DateTime();
      $date_start = !$booking_result && $data['date_start_valid'] ? $data['date_start_valid'] : new DateTime();
      $date_end = !$booking_result && $data['date_end_valid'] ? $data['date_end_valid'] : new DateTime();

      $user_id = !$booking_result ? $data['user_id'] : null;
      $item_id = !$booking_result ? $data['item_id'] : null;

      $cb_items = $this->cb_items;
      $users = $this->users;

      $send_mail = !$booking_result && $data['send_mail'] ? $data['send_mail'] : null;

      include_once( CB_ADMIN_BOOKING_PATH . 'templates/bookings-template.php' );
    }
    else {
      echo '<h3>' . __('CREATE_BOOKING', 'commons-booking-admin-booking') . '</h3>';
      echo '<p>' . __('NO_LOCATIONS_AND_ARTICLES', 'commons-booking-admin-booking') . '</p>';
    }

  }

  /**
  * validate date by checking if it falls on a closing day
  */
  function validate_day($date, $closed_days) {

    $weekday = date( "N", strtotime( $date ));

    if ( is_array ( $closed_days ) && in_array( $weekday, $closed_days ) ) {
      return false;
    }
    else {
      return true;
    }
  }

  /**
  * create a booking with given properties
  */
  function create_booking($date_start, $date_end, $item_id, $user_id, $status, $location_id, $send_mail) {

    $cb_booking = new CB_Booking();

    //set wanted user
    $cb_booking->user_id = $user_id;

    //create booking (pending)
    $cb_booking->hash = $cb_booking->create_hash();
    $booking_id = $cb_booking->create_booking( $date_start, $date_end, $item_id);

      if($booking_id) {

        //set status - (default is pending - it will be deleted by Commons Booking cronjob, if it's not confirmed)
        //set_booking_status is a private method, it has to be made accessible first
        $method = new ReflectionMethod('CB_Booking', 'set_booking_status');
        $method->setAccessible(true);
        $method->invoke($cb_booking, $booking_id, $status);

        if($send_mail) {

          //prepare  CB_Booking instance to send email
          $cb_booking->item_id = $item_id;
          $cb_booking->location_id = $location_id;
          $cb_booking->date_start = $date_start;
          $cb_booking->date_end = $date_end;

          $cb_booking->booking = $cb_booking->get_booking($booking_id);

          $cb_booking->email_messages = $cb_booking->settings->get_settings( 'mail' );

          $this->set_booking_vars($cb_booking);

          $cb_booking->send_mail($cb_booking->user['email']);
      }

    }

    return $booking_id;

  }

  /**
  * set booking vars on given CB_Booking instance to prepare sending an email
  */
  private function set_booking_vars( $cb_booking ) {

    $cb_booking->item = $cb_booking->data->get_item( $cb_booking->item_id );
    $cb_booking->location = $cb_booking->data->get_location( $cb_booking->location_id );
    $cb_booking->user = $cb_booking->data->get_user( $cb_booking->user_id );

    $b_vars['date_start'] = date_i18n( get_option( 'date_format' ), strtotime($cb_booking->date_start) );
    $b_vars['date_end'] = date_i18n( get_option( 'date_format' ), strtotime($cb_booking->date_end) );
    $b_vars['date_end_timestamp'] = strtotime($cb_booking->date_end);
    $b_vars['item_name'] = get_the_title ($cb_booking->item_id );
    $b_vars['item_thumb'] = get_thumb( $cb_booking->item_id );
    $b_vars['item_content'] =  get_post_meta( $cb_booking->item_id, 'commons-booking_item_descr', TRUE  );
    $b_vars['location_name'] = get_the_title ($cb_booking->location_id );
    $b_vars['location_content'] = '';
    $b_vars['location_address'] = $cb_booking->data->format_adress($cb_booking->location['address']);
    $b_vars['location_thumb'] = get_thumb( $cb_booking->location_id );
    $b_vars['location_contact'] = $cb_booking->location['contact'];
    $b_vars['location_openinghours'] = $cb_booking->location['openinghours'];

    $b_vars['page_confirmation'] = $cb_booking->settings->get_settings('pages', 'booking_confirmed_page_select');

    $b_vars['hash'] = $cb_booking->hash;

    $b_vars['site_email'] = '';

    $b_vars['user_name'] = $cb_booking->user['name'];
    $b_vars['user_email'] = $cb_booking->user['email'];

    $b_vars['first_name'] = $cb_booking->user['first_name'];
    $b_vars['last_name'] = $cb_booking->user['last_name'];

    $b_vars['user_address'] = $cb_booking->user['address'];
    $b_vars['user_phone'] = $cb_booking->user['phone'];

    $b_vars['code'] = $cb_booking->get_code( $cb_booking->booking['code_id'] );
    $b_vars['url'] = add_query_arg( 'booking', $cb_booking->hash, get_permalink($b_vars['page_confirmation']) );

    $cb_booking->b_vars = $b_vars;

}

  /**
  * fetches bookings in period determined by start and end date from db for given item
  */
  function fetch_bookings_in_period($date_start, $date_end, $item_id) {
    global $wpdb;

    //get bookings data
    $table_name = $wpdb->prefix . 'cb_bookings';
    $select_statement = "SELECT * FROM $table_name WHERE item_id = %d ".
                        "AND ((date_start BETWEEN '".$date_start."' ".
                        "AND '".$date_end."') ".
                        "OR (date_end BETWEEN '".$date_start."' ".
                        "AND '".$date_end."') ".
                        "OR (date_start < '".$date_start."' ".
                        "AND date_end > '".$date_end."'))";

    $prepared_statement = $wpdb->prepare($select_statement, $item_id);

    $bookings_result = $wpdb->get_results($prepared_statement);

    return $bookings_result;
  }
}
