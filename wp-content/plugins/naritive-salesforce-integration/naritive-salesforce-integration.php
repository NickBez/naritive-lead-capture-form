<?php
/**
 * Plugin Name: Naritive Salesforce Integration
 * Description: Handles Web Story lead capture and Salesforce integration
 * Version: 1.0
 * Author: Nick Bez
 */

add_action('rest_api_init', function () {

    register_rest_route('naritive-salesforce/v1', '/leads', [
        'methods'  => 'POST',
        'callback' => 'naritive_capture_lead',
        'permission_callback' => '__return_true'
    ]);

});


function naritive_capture_lead($request) {

    $first_name = sanitize_text_field($request['first_name']);
    $last_name  = sanitize_text_field($request['last_name']);
    $email      = sanitize_email($request['email']);
    $phone      = sanitize_text_field($request['phone']);

    // Backend validation

    if (!$first_name || !$last_name) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Name fields required'
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid email'
        ], 400);
    }

    if (!preg_match('/^\+?[0-9\s]{10,15}$/', $phone)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Invalid phone number'
        ], 400);
    }

    // TEMP logging so we can confirm it works
    error_log("Lead received: $first_name $last_name | $email | $phone");

    // Required header for AMP forms
    $response = new WP_REST_Response([
        'success' => true
    ]);

    $response->header(
        'AMP-Access-Control-Allow-Source-Origin',
        home_url()
    );

    return $response;
}