<?php
// Path: wp-content/plugins/wp2/REST/Network/Sites/Controller.php

namespace WP2\REST\Network\Sites;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Site_Query;

class Controller
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register the routes for this controller.
     */
    public function register_routes()
    {
        $namespace = 'wp2/v1';

        // Register the '/sites' route
        register_rest_route($namespace, '/sites', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'permissions_check'],
            'callback'            => [$this, 'handle_get'],
            'args'                => $this->get_collection_params(),
        ]);

        // Register the '/sites/{id}' route for GET requests
        register_rest_route($namespace, '/sites/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'permission_callback' => [$this, 'permissions_check'],
            'callback'            => [$this, 'get_single_site'],
        ]);

        // Register the '/sites/{id}' route for UPDATE requests
        register_rest_route($namespace, '/sites/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'permission_callback' => [$this, 'permissions_check'],
            'callback'            => [$this, 'update_site'],
            'args'                => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
        ]);
    }

    /**
     * Check if the current user has permission to access this endpoint.
     *
     * @return bool|\WP_Error
     */
    public function permissions_check($request)
    {
        // Allow network admins or site admins for specific sites
        $site_id = $request->get_param('id');

        if (is_multisite() && $site_id) {
            return current_user_can('manage_options') || current_user_can_for_blog($site_id, 'manage_options');
        }

        return current_user_can('manage_options');
    }

    public function update_site($request)
    {
        $site_id = (int) $request['id'];

        $params = $request->get_params();

        // Validate the site ID
        if ($site_id <= 0) {
            return new WP_Error('invalid_site_id', __('Invalid site ID.'), ['status' => 400]);
        }

        // Get the site object
        $site = get_site($site_id);

        if (!$site) {
            return new WP_Error('site_not_found', __('Site not found.'), ['status' => 404]);
        }

        // Prepare data for updating
        $data = [];

        $options = [];

        // Define fields and their sanitization callbacks
        $fields = [
            'domain'        => 'sanitize_text_field',
            'path'          => 'sanitize_text_field',
            'public'        => 'absint',
            'archived'      => 'absint',
            'mature'        => 'absint',
            'spam'          => 'absint',
            'deleted'       => 'absint',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($params[$field])) {
                $value = $params[$field];
                if ($sanitize_callback) {
                    $value = call_user_func($sanitize_callback, $value);
                }
                $data[$field] = $value;
            }
        }

        // Handle options from the 'options' object in the request
        if (isset($params['options']) && is_array($params['options'])) {
            $allowed_options = [
                'blogname',
                'blogdescription',
                'admin_email',
                'home',
                'siteurl',
            ];
            foreach ($params['options'] as $key => $value) {
                if (in_array($key, $allowed_options)) {
                    $options[$key] = sanitize_text_field($value);
                }
            }
        }

        // Update the site details
        if (!empty($data)) {
            $result = update_blog_details($site_id, $data);
            if (!$result) {
                return new WP_Error('site_update_failed', __('Failed to update site.'), ['status' => 500]);
            }
        }

        // Update the site options without switching context
        if (!empty($options)) {
            foreach ($options as $key => $value) {
                update_blog_option($site_id, $key, $value);
            }
        }

        // Get the updated site data with settings
        $updated_site = get_site($site_id);
        
        $site_data    = $this->get_site_with_settings($updated_site);

        return rest_ensure_response($site_data);
    }
    /**
     * Handles the GET request to retrieve a single site by ID.
     */
    public function get_single_site($request)
    {
        $site_id = (int) $request['id'];

        // Validate the site ID
        if ($site_id <= 0) {
            return new WP_Error('invalid_site_id', __('Invalid site ID.'), ['status' => 400]);
        }

        // Get the site object
        $site = get_site($site_id);
        if (!$site) {
            return new WP_Error('site_not_found', __('Site not found.'), ['status' => 404]);
        }

        // Get the site data with settings
        $site_data = $this->get_site_with_settings($site);

        return rest_ensure_response($site_data);
    }

    /**
     * Handles the GET request to retrieve a list of sites with their settings.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|WP_Error
     */
    public function handle_get($request)
    {
        try {
            // Validate and sanitize input parameters
            $number = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 100;

            $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;

            $offset = ($page - 1) * $number;

            // Arguments for the site query
            $args = [
                'number'                 => $number,
                'offset'                 => $offset,
                'no_found_rows'          => false,
                'orderby'                => 'id',
                'order'                  => 'ASC',
                'update_site_cache'      => true,
                'update_site_meta_cache' => true,
            ];

            // Perform the site query
            $sites_query = new WP_Site_Query($args);

            $sites = $sites_query->get_sites();

            // Check if sites were found
            if (empty($sites)) {
                return new WP_Error('no_sites', 'No sites found', ['status' => 404]);
            }

            // Initialize an array to hold enhanced site data
            $enhanced_sites = [];

            // Loop through each site and fetch additional data
            foreach ($sites as $site) {
                $site_data = $this->get_site_with_settings($site);
                $enhanced_sites[] = $site_data;
            }

            // Prepare the response with pagination headers
            $total_sites = $sites_query->found_sites;

            $response = new WP_REST_Response($enhanced_sites);

            $response->header('x-wp-total', $total_sites);

            $response->header('x-wp-totalpages', ceil($total_sites / $number));

            return $response;
        } catch (\Exception $e) {

            error_log('Error in handle_get: ' . $e->getMessage());

            return new WP_Error('server_error', 'An unexpected error occurred', ['status' => 500]);
        }
    }

    /**
     * Retrieves site data along with its settings from the settings API endpoint.
     *
     * @param WP_Site $site
     * @return array
     */
    private function get_site_with_settings($site)
    {
        // Prepare the site data
        $site_data = [
            'id'           => $site->id,
            'domain'       => $site->domain,
            'path'         => $site->path,
            'registered'   => $site->registered,
            'last_updated' => $site->last_updated,
            'public'       => (int) $site->public,
            'archived'     => (int) $site->archived,
            'mature'       => (int) $site->mature,
            'spam'         => (int) $site->spam,
            'deleted'      => (int) $site->deleted,
        ];

        // Fetch the settings data for the site without switching context
        $settings_data = $this->get_site_settings($site->blog_id);

        // Add the settings data to the site data
        $site_data['settings'] = $settings_data;

        return $site_data;
    }

    /**
     * Retrieves the settings for a given site using the settings API endpoint.
     *
     * @param int $blog_id
     * @return array|null
     */
    private function get_site_settings($blog_id)
    {
        // Fetch options directly without switching context
        $settings = [
            'blogname'        => get_blog_option($blog_id, 'blogname'),
            'blogdescription' => get_blog_option($blog_id, 'blogdescription'),
            'admin_email'     => get_blog_option($blog_id, 'admin_email'),
            'timezone_string' => get_blog_option($blog_id, 'timezone_string'),
            'date_format'     => get_blog_option($blog_id, 'date_format'),
            'time_format'     => get_blog_option($blog_id, 'time_format'),
            'start_of_week'   => get_blog_option($blog_id, 'start_of_week'),
        ];

        return $settings;
    }

    /**
     * Define the arguments for the endpoint.
     */
    public function get_endpoint_args_for_item_schema($method = WP_REST_Server::CREATABLE)
    {
        $args = [];

        if ($method === WP_REST_Server::EDITABLE) {
            $args = [
                'domain' => [
                    'description' => __('The domain of the site.'),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'path' => [
                    'description' => __('The path of the site.'),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'public' => [
                    'description' => __('Whether the site is public.'),
                    'type'        => 'boolean',
                    'required'    => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
                'blogname' => [
                    'description' => __('The name of the site.'),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'blogdescription' => [
                    'description' => __('The description of the site.'),
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ];
        }

        return $args;
    }

    /**
     * Get the query parameters for collections.
     */
    public function get_collection_params()
    {
        return [
            'per_page' => [
                'description'       => __('Maximum number of items to be returned in result set.'),
                'type'              => 'integer',
                'default'           => 100,
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'description'       => __('Current page of the collection.'),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
}
