<?php
/**
* NWS_Alerts functions and utility function.
*
* @since 1.0.0
*/

class NWS_Alerts {

    /**
    * The id of the NWS_Alerts XML file - a string URL pointing to the XML file.
    *
    * @var string
    */
    public $id = '';

    /**
    * The generator of the NWS_Alerts XML file - likely 'NWS CAP Server'.
    *
    * @var string
    */
    public $generator = '';

    /**
    * The date when the NWS_Alerts XML file was last updated.
    *
    * @var string
    */
    public $updated = '0000-00-00T00:00:00-00:00';

    /**
    * The title of the NWS_Alerts.
    *
    * @var string
    */
    public $title = '';

    /**
    * A URL pointing to the NWS_Alerts XML file.
    *
    * @var string
    */
    public $link = '';

    /**
    * An array of each of the NWS_Alerts_Entry objects.
    *
    * @var string
    */
    public $entries = array();

    /**
    * A string containing any error encountered while retrieving the NWS_Alerts data.
    *
    * @var boolean|string
    */
    public $error = false;

    /**
    * The average latitude of all NWS_Alerts_Entries. Intended to be used with Google Maps to center around each of the polygons.
    *
    * @var string
    */
    public $latitude;

    /**
    * The average longitude of all NWS_Alerts_Entries. Intended to be used with Google Maps to center around each of the polygons.
    *
    * @var string
    */
    public $longitude;

    /**
    * The zip code associated with the NWS_Alerts, or the exact zip code entered by the user.
    *
    * @var string
    */
    public $zip;

    /**
    * The nearest city associated with the NWS_Alerts, or the exact city (or town) entered by the user.
    *
    * @var string
    */
    public $city;

    /**
    * The state associated with the NWS_Alerts, or the exact state entered by the user.
    *
    * @var string
    */
    public $state;

    /**
    * The county, within the state, associated with the NWS_Alerts, or the exact county entered by the user.
    *
    * @var string
    */
    public $county;

    /**
    * The county code associated with the NWS_Alerts.
    *
    * @var string
    */
    public $county_code;

    /**
    * The scope of which to limit alert events to: county, state, national.
    *
    * @var string
    */
    public $scope = NWS_ALERTS_SCOPE_COUNTY;

    /**
    * Limit the number of entries.
    *
    * @var int
    */
    public $limit = 0;

    /**
    * The refresh_rate (in minutes), determined by the alert types. More severe alerts will be refreshed more often.
    *
    * @var int
    */
    public $refresh_rate = 15;



    /**
    * NWS_Alerts constructor $args, $nws_alerts_data
    */
    public function __construct($args = array()) {
        global $wpdb;
        $nws_alerts_xml;
        $nws_alerts_xml_url;
        $nws_alerts_data;
        $entry_cap_data;
        $locations_query = null;
        $county_code;
        $table_name_codes = NWS_ALERTS_TABLE_NAME_CODES;
        $table_name_locations = NWS_ALERTS_TABLE_NAME_LOCATIONS;

        $defaults = array('zip' => false,
                          'city' => false,
                          'state' => false,
                          'county' => false,
                          'scope' => NWS_ALERTS_SCOPE_COUNTY,
                          'limit' => 0);
        $args = wp_parse_args($args, $defaults);

        $zip = ($args['zip'] === false || empty($args['zip'])) ? false : sanitize_text_field($args['zip']);
        $city = ($args['city'] === false || empty($args['city'])) ? false : sanitize_text_field($args['city']);
        $state = ($args['state'] === false || empty($args['state'])) ? false : sanitize_text_field($args['state']);
        $county = ($args['county'] === false || empty($args['county'])) ? false : sanitize_text_field($args['county']);
        $scope = (string) sanitize_text_field($args['scope']);

        // Based on available attributes, search the nws_alerts_locations database table for a match
        if ($zip !== false && is_numeric($zip)) {
            $locations_query = $wpdb->get_row("SELECT * FROM $table_name_locations WHERE zip = $zip", ARRAY_A);
        } else if ($city !== false && $state !== false) {
            $city = strtolower($city);
            $state = strlen($state) > 2 ? NWS_Alerts_Utils::convert_state_format($state) : $state;
            $locations_query = $wpdb->get_row("SELECT * FROM $table_name_locations WHERE city LIKE '$city' AND state LIKE '$state'", ARRAY_A);
        } else if ($state !== false && $county !== false) {
            $state = strlen($state) > 2 ? NWS_Alerts_Utils::convert_state_format($state) : $state;
            $county = strtolower($county);
            $locations_query = $wpdb->get_row("SELECT * FROM $table_name_locations WHERE state LIKE '$state' AND county LIKE '$county'", ARRAY_A);
        }

        // Location could not be found or not enough information to determine the location and get an ANSI County code - set error status
        if ($locations_query === null && $scope !== 'national') $this->set_error(NWS_ALERTS_ERROR_NO_LOCATION);

        // Individual locations_query variables
        $latitude = $locations_query['latitude'];
        $longitude = $locations_query['longitude'];
        $zip = $locations_query['zip'];
        $city = $locations_query['city'];
        $state = $locations_query['state'];
        $county = $locations_query['county'];

        $county_code = $wpdb->get_var("SELECT countyansi FROM $table_name_codes WHERE state LIKE '{$state}' AND county LIKE '%{$county}%'");
        $county_code = str_pad($county_code, 3, '0', STR_PAD_LEFT);

        if (strlen($county_code) < 3) { $county_code = '0' . $county_code; }

        // Make the city and state more legible
        $city = ucwords($city);
        $state_abbrev = $state;
        $state = ucwords(NWS_Alerts_Utils::convert_state_format($state, 'abbrev'));

        // Set the XML (atom) feed URL to be loaded
        if ($scope === NWS_ALERTS_SCOPE_NATIONAL) {
            // National
            $nws_alerts_xml_url = 'https://alerts.weather.gov/cap/us.php?x=0';
        } else if ($scope === NWS_ALERTS_SCOPE_STATE) {
            // State
            $nws_alerts_xml_url = 'https://alerts.weather.gov/cap/' . $state_abbrev . '.php?x=0';
        } else {
            // Users requested location
            $nws_alerts_xml_url = 'https://alerts.weather.gov/cap/wwaatmget.php?x=' . strtoupper($state_abbrev) . 'C' . $county_code . '&y=0';
        }

        // Load XML and cache
        $nws_alerts_xml = false;
        if (isset($zip) && $zip !== null) {
            if (get_site_transient('nws_alerts_xml_' . $zip . $scope) === false) {
                $response = wp_remote_get($nws_alerts_xml_url);
                $response_headers = wp_remote_retrieve_headers($response);

                // Verify that the response code comes back as 'OK' and that the content type is rss+xml or atom+xml
                if (wp_remote_retrieve_response_code($response) == 200 && ($response_headers['content-type'] == 'application/rss+xml' || $response_headers['content-type'] == 'application/atom+xml')) {
                        $response_body = wp_remote_retrieve_body($response);

                        // Verify that the body is not a WP_Error
                        if (!is_wp_error($response_body)) {
                                $nws_alerts_xml = simplexml_load_string($response_body, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_ERR_NONE);
                                set_site_transient('nws_alerts_xml_' . $zip . $scope, $nws_alerts_xml->asXML(), 180);
                        }
                }
            } else {
                $nws_alerts_xml = simplexml_load_string(get_site_transient('nws_alerts_xml_' . $zip . $scope), 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_ERR_NONE);
            }
        }

        $nws_alerts_data = array();

        if ($nws_alerts_xml !== false) {
            $nws_alerts_data['id'] = isset($nws_alerts_xml->id) ? (string)$nws_alerts_xml->id : null;
            $nws_alerts_data['generator'] = isset($nws_alerts_xml->generator) ? (string)$nws_alerts_xml->generator : null;
            $nws_alerts_data['updated'] = isset($nws_alerts_xml->updated) ? (string)$nws_alerts_xml->updated : null;
            $nws_alerts_data['title'] = isset($nws_alerts_xml->title) ? (string)$nws_alerts_xml->title : null;
            $nws_alerts_data['link'] = isset($nws_alerts_xml->link['href']) ? (string)$nws_alerts_xml->link['href'] : null;
            $nws_alerts_data['entries'] = array();


            // Parse through and load into $nws_alerts_data array
            foreach($nws_alerts_xml->entry as $entry) {
                // load 'cap' namespaced data into $entry_cap_data
                $entry_cap_data = $entry->children('urn:oasis:names:tc:emergency:cap:1.1');

                $_entry = array(
                    'id' => isset($entry->id) ? (string)$entry->id : null,
                    'updated' => isset($entry->updated) ? NWS_Alerts_Utils::get_date_format(new DateTime((string)$entry->updated)) : null, // convert to date object '2013-08-30T21:31:26+00:00'
                    'published' => isset($entry->published) ? NWS_Alerts_Utils::get_date_format(new DateTime((string)$entry->published)) : null, // convert to date object '2013-08-30T11:33:00-05:00'
                    'title' => isset($entry->title) ? (string)$entry->title : null,
                    'link' => isset($entry->link['href']) ? (string)$entry->link['href'] : null,
                    'summary' => isset($entry->summary) ? (string)$entry->summary : null,
                    'cap_event' => isset($entry_cap_data->event) ? (string)$entry_cap_data->event : null, // list of cap:event above
                    'cap_effective' => isset($entry_cap_data->effective) ? NWS_Alerts_Utils::get_date_format(new DateTime((string)$entry_cap_data->effective)) : null, // convert to date object '2013-08-30T11:33:00-05:00'
                    'cap_expires' => isset($entry_cap_data->expires) ? NWS_Alerts_Utils::get_date_format(new DateTime((string)$entry_cap_data->expires)) : null, // convert to date object '2013-08-30T19:00:00-05:00'
                    'cap_status' => isset($entry_cap_data->status) ? (string)$entry_cap_data->status : null,
                    'cap_msg_type' => isset($entry_cap_data->msgType) ? (string)$entry_cap_data->msgType : null,
                    'cap_category' => isset($entry_cap_data->category) ? (string)$entry_cap_data->category : null,
                    'cap_urgency' => isset($entry_cap_data->urgency) ? (string)$entry_cap_data->urgency : null,
                    'cap_severity' => isset($entry_cap_data->severity) ? (string)$entry_cap_data->severity : null,
                    'cap_certainty' => isset($entry_cap_data->certainty) ? (string)$entry_cap_data->certainty : null,
                    'cap_area_desc' => isset($entry_cap_data->areaDesc) ? (string)$entry_cap_data->areaDesc : null,
                    'cap_polygon' => isset($entry_cap_data->polygon) ? (string)$entry_cap_data->polygon : null
                );

                $nws_alerts_data['entries'][] = $_entry;
            }
        } else {
            $this->set_error(NWS_ALERTS_ERROR_NO_XML);
        }

        /*
        * Possible CAP Event types that can be used when filtering $alert_types
        *
        * "Blizzard Warning"
        * "Dust Storm Warning"
        * "Flash Flood Watch"
        * "Flash Flood Warning"
        * "Flash Flood Statement"
        * "Flood Watch"
        * "Flood Warning"
        * "Flood Statement"
        * "High Wind Watch"
        * "High Wind Warning"
        * "Severe Thunderstorm Watch"
        * "Severe Thunderstorm Warning"
        * "Severe Weather Statement"
        * "Tornado Watch"
        * "Tornado Warning"
        * "Winter Storm Watch"
        * "Winter Storm Warning"
        * "Avalanche Watch"
        *
        * NON-WEATHER-RELATED-EVENTS
        * "Child Abduction Emergency"
        * "Civil Danger Warning"
        * "Civil Emergency Message"
        * "Evacuation Immediate"
        * "Fire Warning"
        * "Hazardous Materials Warning"
        * "Law Enforcement Warning"
        * "Local Area Emergency"
        * "911 Telephone Outage Emergency"
        * "Nuclear Power Plant Warning"
        * "Radiological Hazard Warning"
        * "Shelter in Place Warning"
        */
        $allowed_alert_types = apply_filters('nws_alerts_allowed_alert_types',
                                             array('Tornado Warning',
                                                   'Severe Thunderstorm Warning',
                                                   'Flash Flood Warning',
                                                   'Flood Warning',
                                                   'Blizzard Warning',
                                                   'Winter Storm Warning',
                                                   'Freeze Warning',
                                                   'Dust Storm Warning',
                                                   'High Wind Warning'
                                             ),
                                             $args);

        /*
        * msg types
        *
        * “Alert” - Initial information requiring attention by targeted recipients
        * “Update” - Updates and supercedes the earlier message(s) identified in <references>
        * “Cancel” - Cancels the earlier message(s) identified in <references>
        * “Ack” - Acknowledges receipt and acceptance of the message(s) identified in <references>
        * “Error” - Indicates rejection of the message(s) identified in <references>; explanation SHOULD appear in <note>
        */
        /* add_feature - add filter to allow msgTypes to be added or removed */
        $allowed_msg_types = apply_filters('nws_alerts_allowed_msg_types', array('Alert', 'Update'), $args);

        /*
        * Status types
        *
        * “Actual” - Actionable by all targeted recipients
        * “Exercise” - Actionable only by designated exercise participants; exercise identifier SHOULD appear in <note>
        * “System” - For messages that support alert network internal functions
        * “Test” - Technical testing only, all recipients disregard
        * “Draft” – A preliminary template or draft, not actionable in its current form
        */
        /* add_feature - add filter to allow msgTypes to be added or removed */
        $allowed_status_types = apply_filters('nws_alerts_allowed_status_types', array('Actual'), $args);

        // Store args in class attributes
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->zip = $zip;
        $this->city = $city;
        $this->state = $state;
        $this->county = $county;
        $this->county_code = $county_code;
        $this->scope = $scope;
        $this->limit = $args['limit'];

        if (!empty($nws_alerts_data) && !empty($nws_alerts_data['entries'])) {
            // Store first level $nws_alerts_data values in class attributes
            $this->id = $nws_alerts_data['id'];
            $this->generator = $nws_alerts_data['generator'];
            $this->updated = $nws_alerts_data['updated'];
            $this->title = $nws_alerts_data['title'];
            $this->link = $nws_alerts_data['link'];

            // Create NWS_Alerts_Entry objects for each $nws_alerts_data['entries'] and save in class attribute $entries, only if cap_event is a warning (flood, thunderstorm, or tornado)
            foreach ($nws_alerts_data['entries'] as $key => $entry) {
                // Only add entries of allowed alert types
                if (in_array($entry['cap_event'], $allowed_alert_types, true) !== false && in_array($entry['cap_msg_type'], $allowed_msg_types, true) !== false && in_array($entry['cap_status'], $allowed_status_types, true) !== false) {
                    $entry['ID'] = (int)$key + 1;
                    $this->entries[] = new NWS_Alerts_Entry($entry);
                }
            }

            // Sort by cap_event, urgency, severity, certainty
            $this->sort_entries();

            // Set class attributes $latitude and $longitude to average of all NWS_Alerts_Entry objects
            $this->set_latitude_and_longitude();
        }
    }




    /**
    * Sort Entries by alert type, urgency, and then severity
    *
    * @return array
    */
    public function sort_entries() {
        if (empty($this->entries)) return;

        $entries = array();

        $alert_types = apply_filters('nws_alerts_sort_alert_types',
                                     array('Tornado Warning',
                                           'Severe Thunderstorm Warning',
                                           'Flash Flood Warning',
                                           'Flood Warning',
                                           'Blizzard Warning',
                                           'Winter Storm Warning',
                                           'Freeze Warning',
                                           'Dust Storm Warning',
                                           'High Wind Warning',
                                           'Tornado Watch',
                                           'Severe Thunderstorm Watch',
                                           'Flash Flood Watch',
                                           'Flood Watch',
                                           'Winter Storm Watch',
                                           'Avalanche Watch',
                                           'High Wind Watch',
                                           'Fire Weather Watch',
                                           'Severe Weather Statement',
                                           'Flash Flood Statement',
                                           'Flood Statement',
                                           'Frost Advisory',
                                           'Heat Advisory'));
        /*
        * Urgency types
        *
        * Immediate - Responsive action SHOULD be taken immediately
        * Expected  - Responsive action SHOULD be taken soon (within next hour)
        * Future    - Responsive action SHOULD be taken in the near future
        * Past      - Responsive action is no longer required
        * Unknown   - Urgency not known
        */
        $urgency_types = array('Immediate', 'Expected', 'Future', 'Past', 'Unknown');

        /*
        * Severity types
        *
        * Extreme   - Extraordinary threat to life or property
        * Severe    - Significant threat to life or property
        * Moderate  - Possible threat to life or property
        * Minor     – Minimal to no known threat to life or property
        * Unknown   - Severity unknown
        */
        $severity_types = array('Extreme', 'Severe', 'Moderate', 'Minor', 'Unknown');

        /*
        * Certainty types
        *
        * Observed      – Determined to have occurred or to be ongoing
        * Very Likely   - Deprecated and should be treated the same as "Likely"
        * Likely        - Likely (p > ~50%)
        * Possible      - Possible but not likely (p <= ~50%)
        * Unlikely      - Not expected to occur (p ~ 0)
        * Unknown       - Certainty unknown
        */
        $certainty_types = array('Observed', 'Very Likely', 'Likely', 'Possible', 'Unlikely', 'Unknown');

        foreach ($alert_types as $alert_type) {
            $entries_by_alert_type = array();
            $entries_by_urgency = array();
            $entries_by_severity = array();
            $entries_by_certainty = array();

            // Sort by Alert Type
            foreach ($this->entries as $entry) {
                if ($entry->cap_event === $alert_type) {
                    $entries_by_alert_type[] = $entry;
                }
            }

            if (!empty($entries_by_alert_type)) {
                // Sort by Urgency
                foreach ($entries_by_alert_type as $entry) {
                    if (!isset($entries_by_urgency[$entry->cap_urgency])) $entries_by_urgency[$entry->cap_urgency] = array();
                    $entries_by_urgency[$entry->cap_urgency][] = $entry;
                }
                $entries_by_urgency = NWS_Alerts_Utils::array_merge_by_order($entries_by_urgency, $urgency_types);

                // Sort by Severity
                foreach ($entries_by_urgency as $entry) {
                    if (!isset($entries_by_severity[$entry->cap_severity])) $entries_by_severity[$entry->cap_severity] = array();
                    $entries_by_severity[$entry->cap_severity][] = $entry;
                }
                $entries_by_severity = NWS_Alerts_Utils::array_merge_by_order($entries_by_severity, $severity_types);

                // Sort by Certainty
                foreach ($entries_by_severity as $entry) {
                    if (!isset($entries_by_certainty[$entry->cap_certainty])) $entries_by_certainty[$entry->cap_certainty] = array();
                    $entries_by_certainty[$entry->cap_certainty][] = $entry;
                }
                $entries_by_certainty = NWS_Alerts_Utils::array_merge_by_order($entries_by_certainty, $certainty_types);

                // Merge into entries
                $entries = array_merge($entries, $entries_by_certainty);
            }
        }

        /*
        * Limit the number of events if necessary
        *
        * @since 1.3.0
        */
        if ($this->limit > 0) $entries = array_slice($entries, 0, $this->limit);

        $this->entries = $entries;

        // Set NWS Alerts refresh_rate - If top alerts are extreme or have potential to produce life threatening storms change the refresh_rate to 5 minutes
        if (!empty($this->entries) && ($this->entries[0]->cap_event === 'Tornado Warning' || $this->entries[0]->cap_event === 'Severe Thunderstorm Warning')) $this->refresh = 3;
    }




    /**
    * Builds the necessary JavaScript to output a Google map using the NWS_Alerts_Entry objects and their cap_polygon
    *
    * @return string|boolean
    */
    public function get_output_google_map() {
        $return_value = false;

        if (!empty($this->entries)) {
            $google_map_polys = '';

            foreach ($this->entries as $entry) {
                $google_map_polys .= $entry->get_output_google_map_polys();
            }

            if (!empty($google_map_polys)) {
                require(NWS_Alerts_Utils::get_template_path('template-map.php'));
            }
        }

        return $return_value;
    }




    /*
    * get_output_html
    *
    * Returns a string with html including full information about the alert(s).
    *
    * @param NWS_Alerts $nws_alerts a full populated NWS_Alerts object
    * @return string
    */
    public function get_output_html($display = NWS_ALERTS_DISPLAY_DEFAULT, $classes = array(), $args = array()) {
        $args_defaults = array(
            'location_title' => false,
            'default_classes' => array('nws-alerts-' . $display),
            'heading' => array(
                'alert' => '',
                'classes' => array('nws-alerts-heading'),
                'current_alert' => true,
                'graphic' => 2,
                'location' => false,
                'scope' => 'Local Weather Alerts'));
        $args = wp_parse_args($args, $args_defaults);
        $args['heading']['location'] = $args['location_title'];

        // CSS classes
        if (is_string($classes)) {
            $classes = explode(' ', trim($classes));
        } else if (!is_array($classes)) {
            $classes = array();
        }
        $classes = array_unique(array_merge($args['default_classes'], $classes));
        if (empty($this->entries)) $classes[] = 'nws-alerts-no-entries';

        // Heading settings
        if (in_array('nws-alerts-widget', $classes)) {
            $args['heading']['graphic'] = false;
        } else if ($display === NWS_ALERTS_DISPLAY_BAR) {
            $args['heading']['graphic'] = 1;
        }
        if ($args['heading']['graphic'] === false || empty($this->entries)) {
            $args['heading']['classes'][] = 'nws-alerts-heading-no-graphic';
        }

        // Heading alert
        if ($args['heading']['current_alert'] && !empty($this->entries)) {
            $args['heading']['alert'] .= $this->entries[0]->get_output_text(false);
        } else if ($this->error) {
            $args['heading']['alert'] .= NWS_ALERTS_ERROR_NO_XML_SHORT;
        }

        // Heading location and scope
        if ($args['location_title'] !== false) {

        } else if ($this->scope === NWS_ALERTS_SCOPE_NATIONAL) {
            $args['heading']['location'] = 'United States';
            $args['heading']['scope'] = 'National Weather Alerts';
        } else if ($this->scope === NWS_ALERTS_SCOPE_STATE) {
            $args['heading']['location'] = $this->state;
            $args['heading']['scope'] = 'State Weather Alerts';
        } else {
            $args['heading']['location'] = $this->city . ', ' . $this->state;
        }

        // Classes to space separated string
        $classes = trim(implode(' ', $classes));
        $args['heading']['classes'] = trim(implode(' ', $args['heading']['classes']));

        // Saved settings - to be used to auto update the NWS Alerts on the front end
        $settings = htmlspecialchars (json_encode(array('zip' => $this->zip,
                                                        'scope' => $this->scope,
                                                        'limit' => $this->limit,
                                                        'display' => $display,
                                                        'classes' => $classes,
                                                        'location_title' => $args['heading']['location'],
                                                        'refresh_rate' => $this->refresh_rate)), ENT_QUOTES, 'UTF-8');

        // Start output buffer
        ob_start();

        // Load the display template file
        require(NWS_Alerts_Utils::get_template_path('template-display-' . $display . '.php'));

        // Return output buffer
        return do_shortcode(ob_get_clean());
    }




    /**
    * Loops through all of the NWS_Alerts_Entry objects to find cap_polygon values and sets NWS_Alerts latitude and longitude to the average of them all
    *
    * @return boolean
    */
    private function set_latitude_and_longitude() {
        $return_value = false;

        if (!empty($this->entries)) {
            $latitudes = array();
            $longitudes = array();

            foreach ($this->entries as $entry) {
                if (empty($entry->cap_polygon)) continue;

                $polygon_points = explode(' ', $entry->cap_polygon);
                foreach($polygon_points as $polygon_point) {
                    $split = explode(',', $polygon_point);
                    $latitudes[] = $split[0];
                    $longitudes[] = $split[1];
                }
            }

            if (count($latitudes) > 0 && count($longitudes) > 0) {
                $this->latitude = array_sum($latitudes) / count($latitudes);
                $this->longitude = array_sum($longitudes) / count($longitudes);

                $return_value = true;
            }
        }

        return $return_value;
    }




    /**
    * Sets the error state of the NWS Alerts instance. Can only be set to one error, and new error states will not override if an existing error state is called.
    *
    * @return void
    */
    private function set_error($error) {
        if ($this->error === false) $this->error = $error;
    }
}

?>
