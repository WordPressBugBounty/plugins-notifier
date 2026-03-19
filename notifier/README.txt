=== Notifications for Forms & WordPress Actions ===
Contributors: wanotifier
Donate link: https://wanotifier.com
Tags: whatsapp, whatsapp notification, whatsapp api, whatsapp chat, contact form 7
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 3.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Send WhatsApp notifications for form submissions from CF7, Gravity Forms, WPForms and more and WordPress actions using WhatsApp Business API

== Description ==
**WhatsApp API integration plugin for WordPress** to send WhatsApp notifications for...

* form submission confirmations
* new post or page published events
* new user registrations
* new comments
* and much more

...using the official [WhatsApp Business API](https://wanotifier.com/whatsapp-business-api/).

Not just that, **add WhatsApp chat button** to your website with integrated [WhatsApp chatbot](https://wanotifier.com/whatsapp-chatbot/) to automatically answer all your user queries instantly!

**NOTE: This plugin requires you to have a [WANotifier](https://wanotifier.com/) account to setup WhatsApp API and do the integration. [Click here](https://app.wanotifier.com/create-account/) to create your FREE account.**

**Looking for WooCommerce integration?** 

Install our [Order & Abandoned Cart Notifications for WooCommerce](https://wordpress.org/plugins/order-notifications-for-woocommerce/) plugin.

Here's everything that you can do with this plugin...

== Send WhatsApp Notifications from WordPress Plugins ==

Send WhatsApp notification for...

* Form submissions in [Contact Form 7](https://wordpress.org/plugins/contact-form-7/).
* Form submissions in [Gravity Forms](https://www.gravityforms.com/).
* Form submissions in [WPForms](https://wordpress.org/plugins/wpforms-lite/).
* Form submissions in [Ninja Forms](https://wordpress.org/plugins/ninja-forms/).
* Form submissions in [Formidable Forms](https://wordpress.org/plugins/formidable/).
* Form submissions in [Fluent Forms](https://wordpress.org/plugins/fluentform/).
* Form submissions in [SureForms](https://wordpress.org/plugins/sureforms/).
* Form submissions in [Forminator Forms](https://wordpress.org/plugins/forminator/).
* Form submissions in [WS Form](https://wordpress.org/plugins/ws-form/).

== Send WhatsApp Notifications for WordPress Events ==

* Send notification when a **new post or page is published**
* Send notification when a post of any **custom post type is published**
* Send notification when a **new comment is added**
* Send notification when a **new user is registered**

We keep adding more integrations with new plugin updates. If you want integration with your favorite plugins, you can request us [here](https://wanotifier.com/support/) or you can contact your developer to create custom triggers using the filter hooks we provide.

== Add WhatsApp Chat Button with Chatbot Integration ==

Add WhatsApp chat button to your website to allow your visitors to directly interact with you.

https://youtu.be/JHbgpnT2eX0

Boost your engagement with integrating the button with our **WhatsApp chatbot** or other automations to respond to user queries on autopilot 24/7.

👉 [Learn how to create a WhatsApp chatbot](https://wanotifier.com/create-whatsapp-chatbot/)

== About WANotifier ==

WANotifier is an all-in-one WhatsApp marketing and automation SaaS tool that let's businesses do end-to-end lifecycle marketing on WhatsApp using the official [WhatsApp Business API](https://developers.facebook.com/docs/whatsapp/cloud-api/overview).

We're one of the leading Meta Tech Partners. Our tool let's businesses: 

* Send targeted WhatsApp marketing campaigns to thousands of opted-in contacts on WhatsApp
* Trigger transactional messages from your website or apps in response to user actions like purchases, form submissions, or callback requests 
* Capture leads from Facebook and Instagram ads and add them to your contact lists (learn more about it here)
* Set up chatbots and auto replies to manage FAQs, qualify leads, and provide 24/7 customer support
* Set up drip sequences to onboard new users, nurture leads, and re-engage inactive customers
* Use WhatsApp Flows to collect user data directly within chat
* Integrate your CRM, e-commerce platform, and other third-party tools with WhatsApp
* Track message delivery and open rates, chatbot sessions, Flow responses, and ad-driven conversions
* Collaborate with your team on chats using a shared team inbox (invite team members, assign chats, and view entire conversation history)

And much more!

**Note: WhatsApp Business API is paid API.** 

They charge you a small fees per conversation as [shown here](https://developers.facebook.com/docs/whatsapp/pricing/) that you need to settle with them directly on their portal.

If you're looking for a **cost friendly and robust** solution for sending WhatsApp broadcasts or messages, this tool is for you!

**You can learn more about WANotifier using the following links:**

* [Visit website](https://wanotifier.com/)
* [Pricing](https://wanotifier.com/pricing/)
* [Create your FREE account](https://app.wanotifier.com/create-account/?utm_campaign=woo-plugin)

== Installation ==

1. Download the plugin zip, upload it to the `/wp-content/plugins/` directory and unzip. Or install the plugin via 'Plugins' page in your WordPress backend.
2. Activate the plugin through the 'Plugins' page.
3. Go to **WANotifier** page from your WordPress admin menu.
4. Follow the instructions on the screen to connect with WANotifier and complete your setup.

== Frequently Asked Questions ==

= Do I need a WANotifier account to use this plugin? =

Yes. This plugin connects your WordPress site to your [WANotifier](https://wanotifier.com/) account, which handles WhatsApp message delivery via the official WhatsApp Business API. You can [create a free account here](https://app.wanotifier.com/create-account/).

= Does this plugin use the official WhatsApp API? =

Yes. All messages are sent through the official WhatsApp Business API via WANotifier. No unofficial methods or browser extensions are used.

= Is WhatsApp API free? =

WhatsApp charges a small per-conversation fee billed directly by Meta. WANotifier does not add any markup on top of WhatsApp's pricing. See [WhatsApp's pricing page](https://developers.facebook.com/docs/whatsapp/pricing/) for details.

= Which form plugins are supported? =

Contact Form 7, Gravity Forms, WPForms, Ninja Forms, Formidable Forms, Fluent Forms, SureForms, Forminator Forms and WS Form.

= What about WooCommerce integration? =

WooCommerce integration has been moved to a standalone plugin: [Order & Abandoned Cart Notifications for WooCommerce](https://wordpress.org/plugins/order-notifications-for-woocommerce/).

== Screenshots ==

1. Grid of available integrations whose respective plugins are active
2. Connect your integration with WANotifier in one click
3. Configure / disconnect integrations and re-sync triggers
4. Integration specific configuration page
5. Configure the WhatsApp Chat Button with live preview
6. Manage plugin General settings
7. Activity log to monitor notification delivery

== Changelog ==
= 3.0.2 - 2026-03-19 =
* fix: WS Form integration query now uses single-quoted SQL string to ensure compatibility across all MySQL server configurations
* fix: exclude submit fields from WS Form merge tags

= 3.0.1 - 2026-03-19 =
* fix: prevent duplicate trigger actions from being scheduled for the same event

= 3.0.0 - 2026-03-02 =
* mod: complete UI overhaul with new React-based admin interface
* mod: migration system to migrate your old triggers to v3
* mod: WooCommerce integration moved to standalone plugin (Order & Abandoned Cart Notifications for WooCommerce)
* add: WordPress triggers config page with custom meta field selection
* add: WhatsApp Chat Button live preview

= 2.7.13 - 2026-02-06 =
fix: broken access control vulnerability in AJAX handlers

= 2.7.12 - 2025-11-05 =
fix: PHP warning for undefined array key in Fluent Forms field processing

= 2.7.11 - 2025-10-09 =
add: new slug field for post triggers

= 2.7.10 - 2025-07-04 =
fix: duplicate cart abandonment notifications
add: added session_key field to cart (new) abandonment trigger
add: improved API key validation to prevent errors when using invalid keys

= 2.7.9 - 2025-06-30 =
fix: resolved issue with incorrectly prefixed country codes in WooCommerce

= 2.7.8 - 2025-06-26 =
fix: 'isHidden' undefined error in Gravity Forms integration
mod: renamed Click-to-chat feature to WhatsApp Chat buttons
mod: renamed plugin name
mod: README.txt file

= 2.7.7 - 2025-06-04 =
mod: Tested upto version bump and README.txt file update
mod: minor UI updates

= 2.7.6 - 2025-05-26 =
add: added order item fields – name, price, total of the first product
mod: moved Woocommerce settings to Woocommerce tab
add: meta box on Woocommerce order page to toggle WhatsApp updates opt-in

= 2.7.5 - 2025-04-03 =
mod: renamed plugin from WANotifier to Notifier to comply with WordPress policies

= 2.7.4 - 2025-03-27 =
fix: FATAL error when Woocommerce is not installed

= 2.7.3 - 2025-02-12 =
fix: FATAL error related to WC order meta and checkout and cart related bugs

= 2.7.2 - 2025-02-10 =
fix: FATAL error related to WC session unset

= 2.7.1 - 2025-02-10 =
fix: phone number country code issue in abandoned cart trigger

= 2.7.0 - 2025-01-28 =
add: Inbuilt abandoned cart integration without need of any 3rd party plugin
fix: Bug caused on activating hidden meta fields setting

= 2.6.3 - 2024-09-30 =
* add: HPOS compatibility for getting WooCommerce order meta fields

= 2.6.2 - 2024-08-29 =
* fix: Display name

= 2.6.1 - 2024-07-04 =
* fix: vulnerability fixes
* wp supported version bump to v6.6

= 2.6 - 2024-07-01 =
* fix: vulnerability fixes

= 2.5.4 - 2024-05-14 =
* fix: Warning on installing plugin.

= 2.5.3 - 2024-04-08 =
* fix: Minor warnings.

= 2.5.2 - 2024-04-03 =
* add: Activity logs for debugging.

= 2.5.1 - 2024-03-08 =
* add: Option to add opt-in checkbox on WooCommerce checkout page.
* add: HPOS support for WooCommerce

= 2.5.0 - 2024-01-05 =
* add: Tools page with option to export WooCommerce customers to CSV to import in WANotifier.

= 2.4.6 - 2023-11-03 =
* fix: error log warnings

= 2.4.5 - 2023-11-01 =
* mod: some info text updates

= 2.4.4 - 2023-08-08 =
* fix: Product list not sending in New Order Trigger

= 2.4.3 - 2023-06-21 =
* fix: Woocommerce Cart Abandoned Recovery plugin not triggering notifications

= 2.4.2 - 2023-06-13 =
* add: option to select whether to trigger in real time or asynchonously using Action Scheduler
* fix: minor bug fixes

= 2.4.1 - 2023-06-01 =
* fix: Fluent Forms and Ninja Forms triggers getting triggered for all form subsmissions

= 2.4.0 - 2023-05-25 =
* add: Woocommerce Cart Abandoned Recovery plugin integration

= 2.3.0 - 2023-05-04 =
* add: default country code for recipient fields.
* add: option to enable hidden custom meta keys that start with underscore.
* mod: changed custom meta data fields type so it's available to map in both body and header.
* fix: country code not getting added to billing and shipping phone numbers for US numbers in WooCommerce.
* fix: woocommerce custom meta key not saving issue.

= 2.2.4 - 2023-05-03 =
* fix: Some minor typo fixes

= 2.2.3 - 2023-04-24 =
* add: WooCommerce order meta and customer user meta data fields and recipient fields
* add: new data field for Order payment URL
* add: few style fixes

= 2.2.2 - 2023-04-19 =
* add: new trigger - new order placed with COD payment method
* add: added trigger description to the Trigger dropdown
* add: custom fields support in Recipient Fields (experimental)

= 2.2.1 - 2023-04-17 =
* add: further speed boost - optimized code for fewer db queries

= 2.2.0 - 2023-04-13 =
* fix: made triggers more unique with site key
* add: UI and content updates
* add: replaced direct firing of actions with action scheduler to drastically improve performance
* add: custom meta fields for post types
* mod: changed WooCommerce new order hook from woocommerce_thankyou to woocommerce_new_order

= 2.1.3 - 2023-03-09 =
* fix: Contact Form 7 error in logs

= 2.1.2 - 2023-02-28 =
* fix: Contact Form 7 forms not visible
* fix: tel* fields not showing in Recipient Fields for Contact Form 7

= 2.1.1 - 2023-02-13 =
* fix: Order product items field was sending empty data

= 2.1.0 - 2023-02-03 =
* add: Click to chat feature
* add: support for custom post types
* add: product names field for WooCommerce

= 2.0.10 - 2023-01-29 =
* fix: error on saving triggers

= 2.0.9 - 2023-01-26 =
* Updated onboarding instructions & added testimonials

= 2.0.8 - 2023-01-25 =
* Added Fluent Forms integration

= 2.0.7 - 2023-01-18 =
* Added Formidable Forms integration

= 2.0.6 - 2023-01-13 =
* Added Ninja Forms integration

= 2.0.5 - 2023-01-12 =
* Fix: Recipient fields related bug in WPForms integration

= 2.0.4 - 2023-01-11 =
* Added WPForms integration

= 2.0.3 - 2023-01-06 =
* Fix: Added missing WordPress fields to Contact Form 7

= 2.0.2 - 2023-01-04 =
* Fix: Trigger sync message showing on deletiong of triggers
* Fix: Few typos

= 2.0.1 - 2022-12-30 =
* Fix: Woocommerce new order notification not sending

= 2.0.0 - 2022-12-30 =
* Major upgrade with new way to manage triggers
* Added ability to use custom Woocommerce order statuses.
* Send WhatsApp notifications on Gravity Forms form submission.
* Send WhatsApp notifications on Contact Form 7 form submission.

= 1.0.5 - 2022-12-26 =
* New: Improved on-boarding and How to? instructions

= 1.0.4 - 2022-11-10 =
* New: api enpoint upgrade

= 1.0.3 - 2022-11-08 =
* Fix: checkout not happening error

= 1.0.2 - 2022-10-28 =
* Fix: firing multiple notifications at the same time

= 1.0.1 - 2022-10-27 =
* Tested upto WP 6.1

= 1.0.0 - 2022-10-09 =
* Converted the plugin to provide integration with WANotifier.com

= 0.1.1 - 2022-08-04 =
* Fix - Minor bug fixes and code cleanup

= 0.1.0 - 2022-07-30 =
* Launch of the beta version of the plugin.
