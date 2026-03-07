<?php
/**
 * Plugin Name: Naritive Salesforce Integration
 * Description: Handles Web Story lead capture and Salesforce integration
 * Version: 1.0
 * Author: Nick Bez
 */

// Load Salesforce credentials
require_once plugin_dir_path( __FILE__ ) . 'config.php';

// Register REST route
add_action( 'rest_api_init', function () {
    register_rest_route( 'naritive-salesforce/v1', '/leads', [
        'methods'             => 'POST',
        'callback'            => 'naritive_capture_lead',
        'permission_callback' => '__return_true',
    ] );
} );


/**
 * Handle incoming lead from Web Story form.
 */
function naritive_capture_lead( $request ) {

    // Sanitise inputs
    $first_name = sanitize_text_field( $request['first_name'] );
    $last_name  = sanitize_text_field( $request['last_name'] );
    $email      = sanitize_email( $request['email'] );
    $phone      = sanitize_text_field( $request['phone'] );

    // Backend validation
    if ( ! $first_name || ! $last_name ) {
        return naritive_amp_response( [ 'success' => false, 'message' => 'Name fields required' ], 400 );
    }

    if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        return naritive_amp_response( [ 'success' => false, 'message' => 'Invalid email' ], 400 );
    }

    if ( ! preg_match( '/^\+?[0-9\s]{10,15}$/', $phone ) ) {
        return naritive_amp_response( [ 'success' => false, 'message' => 'Invalid phone number' ], 400 );
    }

    // Get Salesforce access token
    $token = naritive_get_sf_token();

    if ( is_wp_error( $token ) ) {
        error_log( 'Salesforce auth error: ' . $token->get_error_message() );
        return naritive_amp_response( [ 'success' => false, 'message' => 'Authentication failed' ], 500 );
    }

    // Push lead to Salesforce
    $result = naritive_create_sf_lead( $token, [
        'FirstName' => $first_name,
        'LastName'  => $last_name,
        'Email'     => $email,
        'Phone'     => $phone,
        'Company'   => 'Web Story Lead',
        'LeadSource' => 'Web',
    ] );

    if ( is_wp_error( $result ) ) {
        error_log( 'Salesforce lead error: ' . $result->get_error_message() );
        return naritive_amp_response( [ 'success' => false, 'message' => 'Failed to save lead' ], 500 );
    }

    error_log( "Lead pushed to Salesforce: $first_name $last_name | $email | $phone" );

    return naritive_amp_response( [ 'success' => true ] );
}


/**
 * Get a Salesforce OAuth access token using client credentials flow.
 * Token is cached in a WordPress transient for 1 hour.
 *
 * @return string|WP_Error Access token string or WP_Error on failure.
 */
function naritive_get_sf_token() {

    $cached = get_transient( 'naritive_sf_token' );
    if ( $cached ) {
        return $cached;
    }

    $response = wp_remote_post( NARITIVE_SF_INSTANCE_URL . '/services/oauth2/token', [
        'body' => [
            'grant_type'    => 'client_credentials',
            'client_id'     => NARITIVE_SF_CLIENT_ID,
            'client_secret' => NARITIVE_SF_CLIENT_SECRET,
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['access_token'] ) ) {
        return new WP_Error(
            'sf_auth_failed',
            isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown auth error'
        );
    }

    // Cache token for 55 minutes (tokens last 1 hour)
    set_transient( 'naritive_sf_token', $body['access_token'], 55 * MINUTE_IN_SECONDS );

    return $body['access_token'];
}


/**
 * Create a Lead record in Salesforce.
 *
 * @param string $token  Salesforce access token.
 * @param array  $data   Lead field data.
 * @return array|WP_Error Response body or WP_Error on failure.
 */
function naritive_create_sf_lead( $token, $data ) {

    $response = wp_remote_post(
        NARITIVE_SF_INSTANCE_URL . '/services/data/v60.0/sobjects/Lead/',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode( $data ),
        ]
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    // Salesforce returns 201 on successful record creation
    if ( $code !== 201 ) {
        $message = isset( $body[0]['message'] ) ? $body[0]['message'] : 'Unknown Salesforce error';
        return new WP_Error( 'sf_lead_failed', $message );
    }

    return $body;
}


/**
 * Return a WP_REST_Response with the required AMP CORS header.
 *
 * @param array $data   Response data.
 * @param int   $status HTTP status code.
 * @return WP_REST_Response
 */
function naritive_amp_response( $data, $status = 200 ) {
    $response = new WP_REST_Response( $data, $status );
    $response->header( 'AMP-Access-Control-Allow-Source-Origin', home_url() );
    return $response;
}