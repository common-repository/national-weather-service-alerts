=== Plugin Name ===

Contributors: laubsterboy
Tags: National Weather Service, NWS, Storm Prediction Center, SPC, Alert, Weather, Storm, Severe, Tornado, Thunder, Flood
Requires at least: 3.1
Tested up to: 4.9
Stable tag: 1.3.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily add official National Weather Service alerts to your website.




== Description ==

The National Weather Service Alerts plugin allows you to easily display weather alerts on your website. The
alerts are pulled directly from the National Weather Service (http://alerts.weather.gov) based on the location
that you specify and are then parsed, sorted, and output to your website. The alerts are then automatically updated using
AJAX, based on the severity of the alerts for the specified location. The location can be set by using zipcode,
city and state, or state and county. There is also the option to choose the scope of what alerts to include,
such as alerts only for your county, alerts only for your state, or alerts for the entire United States.

If applicable, a Google Map will be included with polygon overlays to show the affected regions of certain alert
types, such as tornado warnings or flash flood warnings.

*Currently the National Weather Service Alerts plugin only works for areas within United States. However, the
plugin expects Atom feeds that use the Common Alerting Protocol (CAP) format so in theory any CAP feed could be
used.*

**Features**

* Shortcode
* Widget
* NWS Alerts settings page for adding the Alerts Bar
* Clean html5 markup
* CSS classes that make it easy to override default styles
* Developer API (filters)

**Weather Alerts**

* Tornado Warning
* Severe Thunderstorm Warning
* Flash Flood Warning
* Flood Warning
* Blizzard Warning
* Winter Storm Warning
* Freeze Warning
* Dust Storm Warning
* High Wind Warning

*The default weather alert types can be modified using the 'nws_alerts_allowed_alert_types'
and 'nws_alerts_sort_alert_types' filter hooks.





== Installation ==

1. Go to Plugins > Add New in the admin area, and search for National Weather Service Alerts.
1. Click install.
1. Once installed, activate the plugin.
1. Lastly, go to the NWS Alerts settings page and click **Build Database Tables**.

**Note that building the database tables used for location searching can take up to a minute to complete, so please be patient.
The process is monitored via AJAX and a status bar will update you on the progress of the build process.
These tables are deleted from the database when the plugin is deactivated, and then deleted, in the WordPress admin Plugins area.**

Once the plugin is installed and activated you can easily add weather alerts to your website by using the included
NWS Alerts widget or by using the [nws_alert] shortcode. The plugin adds a "National Weather Service Alerts" button
to the WordPress editor that can be used to build properly formatted nws_alert shortcodes.

For further documentation and developer reference check out the GitHub repository: https://github.com/laubsterboy/national-weather-service-alerts




== Frequently Asked Questions ==

= I'm only seeing the following message: The specified location could not be found. Try specifying a county and state instead. =
The plugin is letting your know that there was an error when attempting to retrieve additional location information
about the specified location. Check for spelling errors in the city or county name. On rare occasion the locations
database table may not include the specified city and is thus unable to retrieve additional information necessary
for the plugin to function properly and the only workaround is to instead use the zipcode. If you continue to seeing
this error, despite trying the above fixes, please try deactivating and deleting the plugin and re-installing.

= I'm seeing the following message: Data Error =
The plugin will show this message when it is unable to retrieve the Atom feed from the National Weather Service.
It is rare for this to happen and when it does it's generally because the Atom feed is temporarily unavailable.
Simply refreshing the page should fix the problem.




== Screenshots ==

1. *Full display example - with no Google map*
1. *Full display example - with Google map*
1. *Shortcode builder in the page/post editor*
1. *Widget*
1. *Alerts Bar example - with Google map*




== Changelog ==

= 1.3.5 =
* Fixed: The error retrieving weather alert data. This was caused by the feed mime type changing from rss+xml to atom+xml.

= 1.3.4 =
* Fixed: The 1.3.3 updates included some code incompatible with PHP <5.5 and this update fixes that.

= 1.3.3 =
* Fixed: The alerts map now requires a Google Maps JavaScript API key, so a new setting was added to the NWS Alerts settings page.

= 1.3.2 =
* Improvement: The wp_remote_get function is now used to request National Weather Service CAP data. This will use WordPress core HTTP functions and classes and improves server compatibility.

= 1.3.1 =
* Fixed: The National Weather Service Public Alerts require the use of https, so the feed urls needed to be updated.
* Fixed: A bug that caused the NWS Alerts Bar to display even when there are no alerts. It will now automatically hide if there are no alerts and display when there are active alerts.

= 1.3.0 =
* Added Feature: Support for WordPress Multisite.
* Added Feature: 'nws_alerts_template_path' filter, which can be used to specify alternative template paths.
* Added Feature: 'nws-alerts-page-builder-index-fix' CSS class that is unused, but intended to be added via page builder (Visual Composer, Beaver Builder, etc) to ensure that NWS Alerts display on top of surrounding content.
* Added Feature: New display templates can be created and added by calling NWS_Alerts_Utils::register_display_template.
* Added Feature: Alert entries can now be limited to a specified amount.
* Improvement: The outputting of html has been moved to templates. Default plugin templates can be overridden by copying the template files into child theme and parent theme directories and then modified.
* Improvement: Each Alert entry now includes the Target Area.
* Removed: NWS_Alerts->get_output_headings object method.
* Fixed: Alert effective and expiration times. They're no longer adjusted to the WordPress gmt_offset for the site.
* Fixed: Auto updating alerts via AJAX. This was broken in update 1.2.0.

= 1.2.0 =
* Added Feature: The location title can now be overridden with a custom name.
* Added Feature: The alert entry details now include a link to the official NWS notice. Also, the effective and expiration date and times are included as part of each entries details.
* Added Feature: NWS Alerts XML is now cached, when possible, to speed up page loads.
* Improvement: NWS Alerts XML is now fetched using CURL if server support is available.

= 1.1.1 =
* Fixed: Bug fix that prevented the alerts from auto-updating using AJAX.
* Improvement: The plugin activation process is simplified and should work on all web hosts.
* Improvement: The process to build database tables used for location look-up has been removed from the activation process and broken up into multiple automated parts to be compatible with all web hosts.

= 1.1.0 =
* Added: NWS Alerts settings page to add the NWS Alerts Bar.
* Added: Bar display option, which displays in a horizontal layout and only displays when there are active alerts.
* Improvement: Style and layout compatibility with themes. Also added additional classes to nws alerts markup to allow for more specific adjustments.

= 1.0.1 =
* Improvement: Typos
* Change: Updated readme

= 1.0.0 =
* Initial release of the National Weather Service Alerts plugin.




== Upgrade Notice ==

= 1.3.5 =
* Urgent: This update fixes the 'There was an error retrieving the National Weather Service alert data.' error.

= 1.3.4 =
* Urgent: This update fixes the PHP parse error on web servers running PHP 5.4 or less. Those with PHP 5.5 or greater will see no difference.

= 1.3.3 =
* Fixed: The maps are working again as long as a Google Maps JavaScript API key is provided.

= 1.3.2 =
* Standardized the method used to request National Weather Service alert data.

= 1.3.1 =
* Urgent: The National Weather Service Alerts plugin will no longer work until this update is installed, which updates compliance with NWS Public Alert feed urls.

= 1.3.0 =
* Added support for WordPress Multisite, fixed bugs, display settings now use templates, and the number of alert entries shown can be limited.

= 1.2.0 =
* Improved compatibility with web hosts for fetching NWS Alerts, and alerts are now cached when possible. Added new display features.

= 1.1.1 =
* Bug fixes with alerts auto-updating and revamped the activation process.

= 1.1.0 =
* Added features, including an alerts bar, and improved layout compatibility across across themes.

= 1.0.1 =
* Updated reference.

= 1.0.0 =
* Initial release of the National Weather Service Alerts plugin.
